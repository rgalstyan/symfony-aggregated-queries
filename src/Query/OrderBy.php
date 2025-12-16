<?php

declare(strict_types=1);

namespace Rgalstyan\SymfonyAggregatedQueries\Query;

final readonly class OrderBy
{
    public function __construct(
        public string $column,
        public string $direction,
    ) {}
}

