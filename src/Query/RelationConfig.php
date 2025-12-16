<?php

declare(strict_types=1);

namespace Rgalstyan\SymfonyAggregatedQueries\Query;

final readonly class RelationConfig
{
    /**
     * @param array<int, string> $columns Database column names to include in JSON.
     */
    public function __construct(
        public string $relationName,
        public string $type,
        public string $targetEntity,
        public string $method,
        public array $columns,
        public string $baseColumn,
        public string $relatedColumn,
    ) {}
}

