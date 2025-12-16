<?php

declare(strict_types=1);

namespace Rgalstyan\SymfonyAggregatedQueries\Generator;

use Rgalstyan\SymfonyAggregatedQueries\Query\OrderBy;
use Rgalstyan\SymfonyAggregatedQueries\Query\RelationConfig;
use Rgalstyan\SymfonyAggregatedQueries\Query\SqlQuery;
use Rgalstyan\SymfonyAggregatedQueries\Query\WhereClause;

final class MySqlGenerator extends AbstractSqlGenerator
{
    /**
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
    ): SqlQuery {
        $baseAlias = 'e';
        $baseTable = $this->resolveEntityTable($entityClass);

        $selects = [sprintf('%s.*', $baseAlias)];
        $joins = [];

        foreach ($relations as $relation) {
            $targetTable = $this->resolveEntityTable($relation->targetEntity);
            $relationAlias = 'rel_' . $relation->relationName;
            $targetIdColumn = $this->resolvePrimaryIdentifierColumn($relation->targetEntity);

            if ($relation->method === 'json_object') {
                $json = sprintf('JSON_OBJECT(%s)', $this->buildJsonObjectPairs($relationAlias, $relation->columns));
                $selects[] = sprintf(
                    '(CASE WHEN %s.%s IS NULL THEN NULL ELSE %s END) AS %s',
                    $relationAlias,
                    $targetIdColumn,
                    $json,
                    $relation->relationName
                );

                $joins[] = sprintf(
                    'LEFT JOIN %s %s ON %s.%s = %s.%s',
                    $targetTable,
                    $relationAlias,
                    $relationAlias,
                    $relation->relatedColumn,
                    $baseAlias,
                    $relation->baseColumn
                );

                continue;
            }

            if ($relation->method === 'json_array') {
                $json = sprintf('JSON_OBJECT(%s)', $this->buildJsonObjectPairs('sub', $relation->columns));
                $subquery = sprintf(
                    '(SELECT COALESCE(JSON_ARRAYAGG(%s), JSON_ARRAY()) FROM %s sub WHERE sub.%s = %s.%s) AS %s',
                    $json,
                    $targetTable,
                    $relation->relatedColumn,
                    $baseAlias,
                    $relation->baseColumn,
                    $relation->relationName
                );
                $selects[] = $subquery;
            }
        }

        foreach ($counts as $relation) {
            $targetTable = $this->resolveEntityTable($relation->targetEntity);
            $selects[] = sprintf(
                '(SELECT COUNT(*) FROM %s cnt WHERE cnt.%s = %s.%s) AS %s_count',
                $targetTable,
                $relation->relatedColumn,
                $baseAlias,
                $relation->baseColumn,
                $relation->relationName
            );
        }

        $whereData = $this->buildWhereClause($baseAlias, $wheres);
        $orderBySql = $this->buildOrderByClause($baseAlias, $orders);

        $sql = sprintf('SELECT %s FROM %s %s', implode(', ', $selects), $baseTable, $baseAlias);
        if ($joins !== []) {
            $sql .= ' ' . implode(' ', $joins);
        }
        if ($whereData['sql'] !== '') {
            $sql .= ' ' . $whereData['sql'];
        }
        if ($orderBySql !== '') {
            $sql .= ' ' . $orderBySql;
        }
        if ($limit !== null) {
            $sql .= sprintf(' LIMIT %d', $limit);
        }
        if ($offset !== null) {
            $sql .= sprintf(' OFFSET %d', $offset);
        }

        return new SqlQuery($sql, $whereData['params']);
    }
}

