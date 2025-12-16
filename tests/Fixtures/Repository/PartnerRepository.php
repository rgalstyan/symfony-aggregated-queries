<?php

declare(strict_types=1);

namespace Rgalstyan\SymfonyAggregatedQueries\Tests\Fixtures\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Rgalstyan\SymfonyAggregatedQueries\Repository\AggregatedRepositoryTrait;
use Rgalstyan\SymfonyAggregatedQueries\Tests\Fixtures\Entity\Partner;

final class PartnerRepository extends ServiceEntityRepository
{
    use AggregatedRepositoryTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Partner::class);
    }
}

