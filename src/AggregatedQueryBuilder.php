<?php

declare(strict_types=1);

namespace Rgalstyan\SymfonyAggregatedQueries;

use Doctrine\ORM\EntityManagerInterface;
use Rgalstyan\SymfonyAggregatedQueries\Exception\HydrationException;
use Rgalstyan\SymfonyAggregatedQueries\Exception\InvalidEntityException;
use Rgalstyan\SymfonyAggregatedQueries\Exception\UnsupportedRelationException;
use Rgalstyan\SymfonyAggregatedQueries\Generator\SqlGeneratorFactory;
use Rgalstyan\SymfonyAggregatedQueries\Hydrator\HydratorFactory;
use Rgalstyan\SymfonyAggregatedQueries\Metadata\ColumnResolver;
use Rgalstyan\SymfonyAggregatedQueries\Metadata\RelationExtractor;
use Rgalstyan\SymfonyAggregatedQueries\Query\OrderBy;
use Rgalstyan\SymfonyAggregatedQueries\Query\RelationConfig;
use Rgalstyan\SymfonyAggregatedQueries\Query\WhereClause;

/**
 * @phpstan-import-type DbScalar from \Rgalstyan\SymfonyAggregatedQueries\Hydrator\HydratorInterface
 * @phpstan-import-type HydratedRow from \Rgalstyan\SymfonyAggregatedQueries\Hydrator\HydratorInterface
 * @phpstan-import-type RawRow from \Rgalstyan\SymfonyAggregatedQueries\Hydrator\HydratorInterface
 */
final class AggregatedQueryBuilder
{
    private string $entityClass = '';

    /** @var array<string, RelationConfig> */
    private array $relations = [];

    /** @var array<string, RelationConfig> */
    private array $counts = [];

    /** @var array<int, WhereClause> */
    private array $wheres = [];

    /** @var array<int, OrderBy> */
    private array $orders = [];

    private ?int $limit = null;

    private ?int $offset = null;

    private bool $debugEnabled;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly RelationExtractor $relationExtractor,
        private readonly ColumnResolver $columnResolver,
        private readonly SqlGeneratorFactory $generatorFactory,
        private readonly HydratorFactory $hydratorFactory,
        bool $debug = false,
        private readonly bool $enabled = true,
        private readonly int $maxRelations = 15,
    ) {
        $this->debugEnabled = $debug;
    }

    /**
     * Set the entity class to query.
     *
     * Returns a fresh builder instance, safe to reuse from a shared service.
     *
     * @throws InvalidEntityException
     */
    public function from(string $entityClass): self
    {
        if ($entityClass === '') {
            throw new InvalidEntityException('Entity class cannot be empty.');
        }

        $clone = clone $this;
        $clone->entityClass = $entityClass;
        $clone->relations = [];
        $clone->counts = [];
        $clone->wheres = [];
        $clone->orders = [];
        $clone->limit = null;
        $clone->offset = null;

        return $clone;
    }

    /**
     * Eager load a relation as JSON object (ManyToOne, OneToOne).
     *
     * @param array<int, string> $columns Entity field names (or column names). Empty = default columns.
     *
     * @throws InvalidEntityException
     * @throws UnsupportedRelationException
     */
    public function withJsonRelation(string $relation, array $columns = []): self
    {
        $this->assertReady();
        $this->assertMaxRelations(1);

        $metadata = $this->relationExtractor->extract($this->entityClass, $relation);

        if (!in_array($metadata->type, ['ManyToOne', 'OneToOne'], true)) {
            throw new UnsupportedRelationException(sprintf(
                'Relation "%s" on "%s" is %s; only ManyToOne/OneToOne are supported for JSON object loading.',
                $relation,
                $this->entityClass,
                $metadata->type
            ));
        }

        $resolvedColumns = $this->resolveRelationColumns($metadata->targetEntity, $columns);

        $this->relations[$relation] = new RelationConfig(
            relationName: $relation,
            type: $metadata->type,
            targetEntity: $metadata->targetEntity,
            method: 'json_object',
            columns: $resolvedColumns,
            baseColumn: $metadata->baseColumn,
            relatedColumn: $metadata->relatedColumn,
        );

        return $this;
    }

    /**
     * Eager load a collection as JSON array (OneToMany).
     *
     * @param array<int, string> $columns Entity field names (or column names). Empty = default columns.
     *
     * @throws InvalidEntityException
     * @throws UnsupportedRelationException
     */
    public function withJsonCollection(string $relation, array $columns = []): self
    {
        $this->assertReady();
        $this->assertMaxRelations(1);

        $metadata = $this->relationExtractor->extract($this->entityClass, $relation);

        if ($metadata->type !== 'OneToMany') {
            throw new UnsupportedRelationException(sprintf(
                'Relation "%s" on "%s" is %s; only OneToMany is supported for JSON collections in v1.0.',
                $relation,
                $this->entityClass,
                $metadata->type
            ));
        }

        $resolvedColumns = $this->resolveRelationColumns($metadata->targetEntity, $columns);

        $this->relations[$relation] = new RelationConfig(
            relationName: $relation,
            type: $metadata->type,
            targetEntity: $metadata->targetEntity,
            method: 'json_array',
            columns: $resolvedColumns,
            baseColumn: $metadata->baseColumn,
            relatedColumn: $metadata->relatedColumn,
        );

        return $this;
    }

    /**
     * Add a COUNT aggregate for a relation (OneToMany).
     *
     * @throws InvalidEntityException
     * @throws UnsupportedRelationException
     */
    public function withCount(string $relation): self
    {
        $this->assertReady();
        $this->assertMaxRelations(1);

        $metadata = $this->relationExtractor->extract($this->entityClass, $relation);

        if ($metadata->type !== 'OneToMany') {
            throw new UnsupportedRelationException(sprintf(
                'Relation "%s" on "%s" is %s; only OneToMany is supported for counts in v1.0.',
                $relation,
                $this->entityClass,
                $metadata->type
            ));
        }

        $this->counts[$relation] = new RelationConfig(
            relationName: $relation,
            type: $metadata->type,
            targetEntity: $metadata->targetEntity,
            method: 'count',
            columns: [],
            baseColumn: $metadata->baseColumn,
            relatedColumn: $metadata->relatedColumn,
        );

        return $this;
    }

    /**
     * Add a WHERE clause on the root entity.
     *
     * @throws InvalidEntityException
     */
    public function where(
        string $field,
        int|float|string|bool|\DateTimeInterface|\Stringable|null $value,
        string $operator = '='
    ): self {
        $this->assertReady();

        $operator = strtoupper(trim($operator));
        $this->assertValidOperator($operator);
        if ($operator === 'IN') {
            throw new InvalidEntityException('Use whereIn() for IN queries.');
        }

        $this->wheres[] = new WhereClause(
            column: $this->columnResolver->resolveColumnName($this->entityClass, $field),
            operator: $operator,
            value: $this->normalizeParamValue($value),
        );

        return $this;
    }

    /**
     * Add a WHERE IN clause on the root entity.
     *
     * @param array<int, int|float|string|bool|\DateTimeInterface|\Stringable> $values
     *
     * @throws InvalidEntityException
     */
    public function whereIn(string $field, array $values): self
    {
        $this->assertReady();

        if ($values === []) {
            throw new InvalidEntityException('whereIn() values cannot be empty.');
        }

        $normalized = [];
        foreach ($values as $value) {
            $normalized[] = $this->normalizeParamValue($value);
        }

        $this->wheres[] = new WhereClause(
            column: $this->columnResolver->resolveColumnName($this->entityClass, $field),
            operator: 'IN',
            value: $normalized,
        );

        return $this;
    }

    /**
     * Add an ORDER BY clause on the root entity.
     *
     * @throws InvalidEntityException
     */
    public function orderBy(string $field, string $direction = 'ASC'): self
    {
        $this->assertReady();

        $direction = strtoupper(trim($direction));
        if (!in_array($direction, ['ASC', 'DESC'], true)) {
            throw new InvalidEntityException(sprintf('Invalid order direction "%s". Use ASC or DESC.', $direction));
        }

        $this->orders[] = new OrderBy(
            column: $this->columnResolver->resolveColumnName($this->entityClass, $field),
            direction: $direction,
        );

        return $this;
    }

    /**
     * Set LIMIT.
     *
     * @throws InvalidEntityException
     */
    public function limit(int $limit): self
    {
        $this->assertReady();

        if ($limit < 0) {
            throw new InvalidEntityException('Limit must be >= 0.');
        }

        $this->limit = $limit;

        return $this;
    }

    /**
     * Set OFFSET.
     *
     * @throws InvalidEntityException
     */
    public function offset(int $offset): self
    {
        $this->assertReady();

        if ($offset < 0) {
            throw new InvalidEntityException('Offset must be >= 0.');
        }

        $this->offset = $offset;

        return $this;
    }

    /**
     * Execute query and return results.
     *
     * @param string $hydrator 'array'|'entity'|'dto'|FQCN
     *
     * @return list<HydratedRow|object>
     *
     * @throws HydrationException
     */
    public function getResult(string $hydrator = 'array'): array
    {
        $this->assertReady();

        $generator = $this->generatorFactory->create();
        $sqlQuery = $generator->generate(
            $this->entityClass,
            $this->relations,
            $this->counts,
            $this->wheres,
            $this->orders,
            $this->limit,
            $this->offset
        );

        $connection = $this->entityManager->getConnection();
        $sql = $sqlQuery->sql;
        if ($this->debugEnabled) {
            $sql = '/* symfony-aggregated-queries */ ' . $sql;
        }

        $rawRows = $connection->executeQuery($sql, $sqlQuery->params)->fetchAllAssociative();

        /** @var list<RawRow> $rows */
        $rows = [];
        foreach ($rawRows as $rawRow) {
            $normalizedRow = [];
            foreach ($rawRow as $column => $value) {
                if (is_bool($value) || is_int($value) || is_float($value) || is_string($value) || $value === null) {
                    $normalizedRow[(string) $column] = $value;
                    continue;
                }

                throw new HydrationException(sprintf('Unsupported value type returned for column "%s".', (string) $column));
            }

            /** @var RawRow $normalizedRow */
            $rows[] = $normalizedRow;
        }

        $hydratorInstance = $this->hydratorFactory->create($hydrator);

        return $hydratorInstance->hydrate($rows, $this->entityClass, $this->relations);
    }

    /**
     * Get first result or null.
     *
     * @param string $hydrator 'array'|'entity'|'dto'|FQCN
     *
     * @return HydratedRow|object|null
     */
    public function getOneOrNullResult(string $hydrator = 'array'): array|object|null
    {
        $results = $this->limit(1)->getResult($hydrator);

        return $results[0] ?? null;
    }

    /**
     * Get the generated SQL (for debugging).
     */
    public function toSql(): string
    {
        $this->assertReady();

        return $this->generatorFactory->create()->generate(
            $this->entityClass,
            $this->relations,
            $this->counts,
            $this->wheres,
            $this->orders,
            $this->limit,
            $this->offset
        )->sql;
    }

    /**
     * Get query parameters (for debugging).
     *
     * @return list<DbScalar>
     */
    public function getParameters(): array
    {
        $this->assertReady();

        return $this->generatorFactory->create()->generate(
            $this->entityClass,
            $this->relations,
            $this->counts,
            $this->wheres,
            $this->orders,
            $this->limit,
            $this->offset
        )->params;
    }

    /**
     * Enable/disable debug mode (affects generator behavior).
     */
    public function debug(bool $enabled = true): self
    {
        $this->debugEnabled = $enabled;

        return $this;
    }

    private function assertReady(): void
    {
        if (!$this->enabled) {
            throw new InvalidEntityException('SymfonyAggregatedQueries is disabled by configuration.');
        }

        if ($this->entityClass === '') {
            throw new InvalidEntityException('Call from(Entity::class) before building the query.');
        }
    }

    private function assertMaxRelations(int $additional): void
    {
        if ($this->maxRelations === 0) {
            return;
        }

        $current = count($this->relations) + count($this->counts);
        if ($current + $additional > $this->maxRelations) {
            throw new InvalidEntityException(sprintf(
                'Too many relations requested (%d). Max allowed is %d.',
                $current + $additional,
                $this->maxRelations
            ));
        }
    }

    /**
     * @param array<int, string> $columns
     * @return array<int, string>
     */
    private function resolveRelationColumns(string $targetEntity, array $columns): array
    {
        $identifierColumns = $this->columnResolver->resolveIdentifierColumnNames($targetEntity);

        if ($columns === []) {
            $metadata = $this->entityManager->getClassMetadata($targetEntity);
            $fieldNames = $metadata->getFieldNames();
            $columns = array_values(array_unique(array_merge($metadata->getIdentifierFieldNames(), $fieldNames)));
        }

        $resolved = $this->columnResolver->resolveColumnNames($targetEntity, $columns);

        foreach ($identifierColumns as $identifierColumn) {
            if (!in_array($identifierColumn, $resolved, true)) {
                $resolved[] = $identifierColumn;
            }
        }

        return array_values(array_unique($resolved));
    }

    private function normalizeParamValue(int|float|string|bool|\DateTimeInterface|\Stringable|null $value): int|float|string|bool|null
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if ($value instanceof \Stringable) {
            return (string) $value;
        }

        return $value;
    }

    private function assertValidOperator(string $operator): void
    {
        if (!in_array($operator, ['=', '!=', '<>', '<', '>', '<=', '>=', 'LIKE', 'NOT LIKE'], true)) {
            throw new InvalidEntityException(sprintf('Unsupported operator "%s".', $operator));
        }
    }
}
