<?php

declare(strict_types=1);

namespace Rgalstyan\SymfonyAggregatedQueries\Metadata;

final readonly class RelationMetadata
{
    public function __construct(
        public string $type,
        public string $targetEntity,
        public string $baseColumn,
        public string $relatedColumn,
    ) {}
}

