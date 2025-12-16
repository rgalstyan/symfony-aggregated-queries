<?php

declare(strict_types=1);

namespace Rgalstyan\SymfonyAggregatedQueries\Hydrator;

use Rgalstyan\SymfonyAggregatedQueries\Exception\HydrationException;
use Rgalstyan\SymfonyAggregatedQueries\Query\RelationConfig;

/**
 * @phpstan-import-type DbScalar from HydratorInterface
 * @phpstan-import-type HydratedRow from HydratorInterface
 * @phpstan-import-type JsonArray from HydratorInterface
 * @phpstan-import-type JsonObject from HydratorInterface
 * @phpstan-import-type RawRow from HydratorInterface
 */
final class DtoHydrator implements HydratorInterface
{
    /**
     * @param class-string $dtoClass
     */
    public function __construct(
        private readonly string $dtoClass,
    ) {}

    /**
     * @param list<RawRow> $rows
     * @param array<string, RelationConfig> $relations
     *
     * @return list<object>
     */
    public function hydrate(array $rows, string $entityClass, array $relations): array
    {
        $reflection = new \ReflectionClass($this->dtoClass);
        $constructor = $reflection->getConstructor();
        if ($constructor === null) {
            throw new HydrationException(sprintf('DTO "%s" must have a constructor.', $this->dtoClass));
        }

        $results = [];

        foreach ($rows as $row) {
            $decoded = $this->decodeJsonRelations($row, $relations);

            $args = [];
            foreach ($constructor->getParameters() as $parameter) {
                $name = $parameter->getName();
                if (array_key_exists($name, $decoded)) {
                    $args[] = $decoded[$name];
                    continue;
                }

                if ($parameter->isDefaultValueAvailable()) {
                    $args[] = $parameter->getDefaultValue();
                    continue;
                }

                throw new HydrationException(sprintf('Missing DTO constructor argument "%s" for "%s".', $name, $this->dtoClass));
            }

            $results[] = $reflection->newInstanceArgs($args);
        }

        return $results;
    }

    /**
     * @param RawRow $row
     * @param array<string, RelationConfig> $relations
     *
     * @return HydratedRow
     */
    private function decodeJsonRelations(array $row, array $relations): array
    {
        $result = $row;

        foreach ($relations as $relationName => $config) {
            if (!array_key_exists($relationName, $row)) {
                continue;
            }

            $raw = $row[$relationName];

            if ($config->method === 'json_object') {
                $result[$relationName] = $this->decodeJsonObject($relationName, $raw);
                continue;
            }

            if ($config->method === 'json_array') {
                $result[$relationName] = $this->decodeJsonArray($relationName, $raw);
            }
        }

        /** @var HydratedRow $result */
        return $result;
    }

    /**
     * @param DbScalar $raw
     *
     * @return JsonObject|null
     */
    private function decodeJsonObject(string $relationName, bool|float|int|string|null $raw): array|null
    {
        if ($raw === null) {
            return null;
        }

        if (!is_string($raw)) {
            throw new HydrationException(sprintf('Expected JSON string for relation "%s".', $relationName));
        }

        $decoded = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new HydrationException(sprintf('Invalid JSON for relation "%s": %s', $relationName, json_last_error_msg()));
        }

        if ($decoded === null) {
            return null;
        }

        if (!is_array($decoded) || array_is_list($decoded)) {
            throw new HydrationException(sprintf('Decoded JSON for relation "%s" must be an object or null.', $relationName));
        }

        $result = [];
        foreach ($decoded as $key => $value) {
            if (is_array($value)) {
                throw new HydrationException(sprintf('Decoded JSON object for relation "%s" must be flat.', $relationName));
            }

            $result[(string) $key] = $value;
        }

        /** @var JsonObject $result */
        return $result;
    }

    /**
     * @param DbScalar $raw
     *
     * @return JsonArray
     */
    private function decodeJsonArray(string $relationName, bool|float|int|string|null $raw): array
    {
        if ($raw === null) {
            return [];
        }

        if (!is_string($raw)) {
            throw new HydrationException(sprintf('Expected JSON string for relation "%s".', $relationName));
        }

        $decoded = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new HydrationException(sprintf('Invalid JSON for relation "%s": %s', $relationName, json_last_error_msg()));
        }

        if ($decoded === null) {
            return [];
        }

        if (!is_array($decoded) || !array_is_list($decoded)) {
            throw new HydrationException(sprintf('Decoded JSON for relation "%s" must be a list or null.', $relationName));
        }

        $result = [];
        foreach ($decoded as $item) {
            if (!is_array($item) || array_is_list($item)) {
                throw new HydrationException(sprintf('Decoded JSON array for relation "%s" must contain objects.', $relationName));
            }

            $row = [];
            foreach ($item as $key => $value) {
                if (is_array($value)) {
                    throw new HydrationException(sprintf('Decoded JSON object in relation "%s" must be flat.', $relationName));
                }

                $row[(string) $key] = $value;
            }

            /** @var JsonObject $row */
            $result[] = $row;
        }

        /** @var JsonArray $result */
        return $result;
    }
}
