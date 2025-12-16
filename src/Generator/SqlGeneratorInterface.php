<?php

declare(strict_types=1);

namespace Rgalstyan\SymfonyAggregatedQueries\Generator;

use Rgalstyan\SymfonyAggregatedQueries\Query\OrderBy;
use Rgalstyan\SymfonyAggregatedQueries\Query\RelationConfig;
use Rgalstyan\SymfonyAggregatedQueries\Query\SqlQuery;
use Rgalstyan\SymfonyAggregatedQueries\Query\WhereClause;

interface SqlGeneratorInterface
{
    /**
     * Generate SQL query and parameters for aggregated loading.
     *
     * @param array<string, RelationConfig> $relations
     * @param array<string, RelationConfig> $counts
     * @param array<int, WhereClause> $wheres
     * @param array<int, OrderBy> $orders
     */
    public function generate(
        string $entityClass,
        array $relations,
        array $counts,
        array $wheres,
        array $orders,
        ?int $limit,
        ?int $offset,
    ): SqlQuery;
}

