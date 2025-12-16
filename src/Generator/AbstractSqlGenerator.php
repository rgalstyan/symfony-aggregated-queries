<?php

declare(strict_types=1);

namespace Rgalstyan\SymfonyAggregatedQueries\Generator;

use Doctrine\ORM\EntityManagerInterface;
use Rgalstyan\SymfonyAggregatedQueries\Exception\InvalidEntityException;
use Rgalstyan\SymfonyAggregatedQueries\Query\OrderBy;
use Rgalstyan\SymfonyAggregatedQueries\Query\RelationConfig;
use Rgalstyan\SymfonyAggregatedQueries\Query\WhereClause;

abstract class AbstractSqlGenerator implements SqlGeneratorInterface
{
    public function __construct(
        protected readonly EntityManagerInterface $entityManager,
    ) {}

    /**
     * @param array<int, string> $columns
     */
    protected function buildJsonObjectPairs(string $alias, array $columns): string
    {
        $pairs = [];
        foreach ($columns as $column) {
            $pairs[] = sprintf("'%s', %s.%s", $column, $alias, $column);
        }

        return implode(', ', $pairs);
    }

    /**
     * @param array<int, WhereClause> $wheres
     * @return array{sql: string, params: list<bool|float|int|string|null>}
     */
    protected function buildWhereClause(string $baseAlias, array $wheres): array
    {
        $clauses = [];
        $params = [];

        foreach ($wheres as $where) {
            if ($where->operator === 'IN') {
                if (!is_array($where->value) || $where->value === []) {
                    throw new InvalidEntityException('IN operator requires a non-empty array of values.');
                }

                $values = $where->value;
                $placeholders = implode(', ', array_fill(0, count($values), '?'));
                $clauses[] = sprintf('%s.%s IN (%s)', $baseAlias, $where->column, $placeholders);
                foreach ($values as $value) {
                    $params[] = $value;
                }

                continue;
            }

            if (is_array($where->value)) {
                throw new InvalidEntityException(sprintf('Operator "%s" does not support array value.', $where->operator));
            }

            if ($where->value === null) {
                if ($where->operator === '=') {
                    $clauses[] = sprintf('%s.%s IS NULL', $baseAlias, $where->column);
                    continue;
                }

                if (in_array($where->operator, ['!=', '<>'], true)) {
                    $clauses[] = sprintf('%s.%s IS NOT NULL', $baseAlias, $where->column);
                    continue;
                }

                throw new InvalidEntityException(sprintf('Operator "%s" does not support NULL value.', $where->operator));
            }

            $clauses[] = sprintf('%s.%s %s ?', $baseAlias, $where->column, $where->operator);
            $params[] = $where->value;
        }

        return [
            'sql' => $clauses === [] ? '' : ('WHERE ' . implode(' AND ', $clauses)),
            'params' => $params,
        ];
    }

    /**
     * @param array<int, OrderBy> $orders
     */
    protected function buildOrderByClause(string $baseAlias, array $orders): string
    {
        if ($orders === []) {
            return '';
        }

        $clauses = [];
        foreach ($orders as $order) {
            $clauses[] = sprintf('%s.%s %s', $baseAlias, $order->column, $order->direction);
        }

        return 'ORDER BY ' . implode(', ', $clauses);
    }

    protected function resolveEntityTable(string $entityClass): string
    {
        return $this->entityManager->getClassMetadata($entityClass)->getTableName();
    }

    /**
     * @return array<int, string>
     */
    protected function resolveIdentifierColumns(string $entityClass): array
    {
        $columns = $this->entityManager->getClassMetadata($entityClass)->getIdentifierColumnNames();
        if ($columns === []) {
            throw new InvalidEntityException(sprintf('Entity "%s" has no identifier columns.', $entityClass));
        }

        /** @var array<int, string> $columns */
        return $columns;
    }

    protected function resolvePrimaryIdentifierColumn(string $entityClass): string
    {
        $columns = $this->resolveIdentifierColumns($entityClass);

        return $columns[0];
    }
}
