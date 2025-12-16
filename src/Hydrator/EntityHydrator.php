<?php

declare(strict_types=1);

namespace Rgalstyan\SymfonyAggregatedQueries\Hydrator;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\Mapping\MappingException as PersistenceMappingException;
use Rgalstyan\SymfonyAggregatedQueries\Exception\HydrationException;
use Rgalstyan\SymfonyAggregatedQueries\Exception\InvalidEntityException;
use Rgalstyan\SymfonyAggregatedQueries\Query\RelationConfig;

/**
 * @phpstan-import-type DbScalar from HydratorInterface
 * @phpstan-import-type JsonArray from HydratorInterface
 * @phpstan-import-type JsonObject from HydratorInterface
 * @phpstan-import-type RawRow from HydratorInterface
 *
 * @phpstan-type JsonScalar bool|float|int|string|null
 * @phpstan-type JsonNested array<int|string, JsonScalar|array<int|string, JsonScalar>>
 * @phpstan-type JsonDocument array<int|string, JsonScalar|JsonNested>
 */
final class EntityHydrator implements HydratorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {}

    /**
     * @param list<RawRow> $rows
     * @param array<string, RelationConfig> $relations
     *
     * @return list<object>
     */
    public function hydrate(array $rows, string $entityClass, array $relations): array
    {
        try {
            /** @var ClassMetadata<object> $rootMetadata */
            $rootMetadata = $this->entityManager->getClassMetadata($entityClass);
        } catch (PersistenceMappingException $exception) {
            throw new InvalidEntityException(sprintf('Entity "%s" is not a valid Doctrine-mapped class.', $entityClass), 0, $exception);
        }

        $results = [];

        foreach ($rows as $row) {
            $entity = $rootMetadata->newInstance();
            $this->hydrateEntityFields($rootMetadata, $entity, $row);

            foreach ($relations as $relationName => $config) {
                if (!array_key_exists($relationName, $row)) {
                    continue;
                }

                if ($config->method === 'json_object') {
                    $decoded = $this->decodeJsonObject($relationName, $row[$relationName]);
                    $related = $this->hydrateRelatedEntity($config->targetEntity, $decoded);
                    $rootMetadata->setFieldValue($entity, $relationName, $related);
                    continue;
                }

                if ($config->method === 'json_array') {
                    $decoded = $this->decodeJsonArray($relationName, $row[$relationName]);
                    $collection = $this->hydrateRelatedCollection($config->targetEntity, $decoded);
                    $rootMetadata->setFieldValue($entity, $relationName, $collection);
                }
            }

            $results[] = $entity;
        }

        return $results;
    }

    /**
     * @param ClassMetadata<object> $metadata
     * @param RawRow $row
     */
    private function hydrateEntityFields(ClassMetadata $metadata, object $entity, array $row): void
    {
        foreach ($metadata->getFieldNames() as $fieldName) {
            $columnName = (string) $metadata->getColumnName($fieldName);
            if (!array_key_exists($columnName, $row)) {
                continue;
            }

            $value = $row[$columnName];
            $metadata->setFieldValue($entity, $fieldName, $this->convertFieldValue((string) $metadata->getTypeOfField($fieldName), $value));
        }
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

    /**
     * @param JsonObject|null $data
     */
    private function hydrateRelatedEntity(string $entityClass, array|null $data): object|null
    {
        if ($data === null || $data === []) {
            return null;
        }

        /** @var ClassMetadata<object> $metadata */
        $metadata = $this->entityManager->getClassMetadata($entityClass);
        $entity = $metadata->newInstance();

        foreach ($metadata->getFieldNames() as $fieldName) {
            $columnName = (string) $metadata->getColumnName($fieldName);
            if (!array_key_exists($columnName, $data)) {
                continue;
            }

            $metadata->setFieldValue(
                $entity,
                $fieldName,
                $this->convertFieldValue(
                    (string) $metadata->getTypeOfField($fieldName),
                    $data[$columnName]
                )
            );
        }

        return $entity;
    }

    /**
     * @param JsonArray $data
     *
     * @return ArrayCollection<int, object>
     */
    private function hydrateRelatedCollection(string $entityClass, array $data): ArrayCollection
    {
        if ($data === []) {
            return new ArrayCollection();
        }

        $entities = [];
        foreach ($data as $item) {
            $entity = $this->hydrateRelatedEntity($entityClass, $item);
            if ($entity !== null) {
                $entities[] = $entity;
            }
        }

        return new ArrayCollection($entities);
    }

    /**
     * @phpstan-return DbScalar|\DateTimeInterface|JsonDocument|list<string>
     */
    private function convertFieldValue(string $doctrineType, bool|float|int|string|null $value): bool|float|int|string|\DateTimeInterface|array|null
    {
        if ($value === null) {
            return null;
        }

        if ($doctrineType === 'boolean') {
            return $this->convertBoolean($value);
        }

        return match ($doctrineType) {
            'integer', 'smallint', 'bigint' => (int) $value,
            'float' => (float) $value,
            'json', 'json_array' => $this->decodeJsonValue((string) $value),
            'simple_array' => $this->decodeSimpleArrayValue((string) $value),
            'datetime', 'datetime_immutable', 'datetimetz', 'datetimetz_immutable', 'date', 'date_immutable', 'time', 'time_immutable'
                => $this->parseDateTime((string) $value, str_contains($doctrineType, 'immutable')),
            default => is_bool($value) ? ($value ? '1' : '0') : (string) $value,
        };
    }

    private function convertBoolean(bool|float|int|string $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value !== 0;
        }

        if (is_float($value)) {
            return $value !== 0.0;
        }

        $normalized = strtolower(trim((string) $value));

        return in_array($normalized, ['1', 'true', 't', 'yes', 'y', 'on'], true);
    }

    /**
     * @phpstan-return JsonDocument
     */
    private function decodeJsonValue(string $value): array
    {
        if ($value === '') {
            return [];
        }

        $decoded = json_decode($value, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new HydrationException(sprintf('Invalid JSON value: %s', json_last_error_msg()));
        }

        if (!is_array($decoded)) {
            return [];
        }

        /** @var JsonDocument $decoded */
        return $decoded;
    }

    /**
     * @return list<string>
     */
    private function decodeSimpleArrayValue(string $value): array
    {
        if (trim($value) === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $value)), fn (string $v): bool => $v !== ''));
    }

    private function parseDateTime(string $value, bool $immutable): \DateTimeInterface
    {
        try {
            return $immutable ? new \DateTimeImmutable($value) : new \DateTime($value);
        } catch (\Exception $exception) {
            throw new HydrationException(sprintf('Invalid datetime value "%s".', $value), 0, $exception);
        }
    }
}
