<?php

declare(strict_types=1);

namespace Rgalstyan\SymfonyAggregatedQueries\Hydrator;

use Rgalstyan\SymfonyAggregatedQueries\Query\RelationConfig;

/**
 * @phpstan-type DbScalar bool|float|int|string|null
 * @phpstan-type JsonObject array<string, DbScalar>
 * @phpstan-type JsonArray list<JsonObject>
 * @phpstan-type HydratedValue DbScalar|JsonObject|JsonArray
 * @phpstan-type RawRow array<string, DbScalar>
 * @phpstan-type HydratedRow array<string, HydratedValue>
 */
interface HydratorInterface
{
    /**
     * Hydrate raw DBAL results.
     *
     * @param list<RawRow> $rows
     * @param array<string, RelationConfig> $relations
     *
     * @return list<HydratedRow|object>
     */
    public function hydrate(array $rows, string $entityClass, array $relations): array;
}
