<?php

declare(strict_types=1);

namespace Rgalstyan\SymfonyAggregatedQueries\Query;

final readonly class SqlQuery
{
    /**
     * @param list<bool|float|int|string|null> $params
     */
    public function __construct(
        public string $sql,
        public array $params,
    ) {}
}
