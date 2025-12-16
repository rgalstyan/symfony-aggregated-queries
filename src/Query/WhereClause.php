<?php

declare(strict_types=1);

namespace Rgalstyan\SymfonyAggregatedQueries\Query;

final readonly class WhereClause
{
    /**
     * @param list<bool|float|int|string|null>|bool|float|int|string|null $value
     */
    public function __construct(
        public string $column,
        public string $operator,
        public array|int|float|string|bool|null $value,
    ) {}
}
