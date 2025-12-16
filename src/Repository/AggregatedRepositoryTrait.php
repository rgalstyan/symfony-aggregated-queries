<?php

declare(strict_types=1);

namespace Rgalstyan\SymfonyAggregatedQueries\Repository;

use Rgalstyan\SymfonyAggregatedQueries\AggregatedQueryBuilder;
use Symfony\Contracts\Service\Attribute\Required;

trait AggregatedRepositoryTrait
{
    private ?AggregatedQueryBuilder $aggregatedQueryBuilder = null;

    /**
     * Begin an aggregated query for this repository's entity.
     *
     * @throws \RuntimeException When the builder is not injected.
     */
    public function aggregatedQuery(): AggregatedQueryBuilder
    {
        if ($this->aggregatedQueryBuilder === null) {
            throw new \RuntimeException(
                'AggregatedQueryBuilder not injected. Ensure your repository is defined as a service and autowired.'
            );
        }

        return $this->aggregatedQueryBuilder->from($this->getClassName());
    }

    /**
     * @required
     */
    #[Required]
    public function setAggregatedQueryBuilder(AggregatedQueryBuilder $builder): void
    {
        $this->aggregatedQueryBuilder = $builder;
    }
}
