<?php

declare(strict_types=1);

namespace Rgalstyan\SymfonyAggregatedQueries\Hydrator;

use Rgalstyan\SymfonyAggregatedQueries\Exception\HydrationException;

final class HydratorFactory
{
    public function __construct(
        private readonly ArrayHydrator $arrayHydrator,
        private readonly EntityHydrator $entityHydrator,
        private readonly string $defaultHydrator = 'array',
    ) {}

    /**
     * Create hydrator instance.
     *
     * @param string $hydrator 'array'|'entity'|'dto'|FQCN
     *
     * @throws HydrationException
     */
    public function create(string $hydrator): HydratorInterface
    {
        $hydrator = trim($hydrator);
        if ($hydrator === '') {
            $hydrator = $this->defaultHydrator;
        }

        $lower = strtolower($hydrator);
        if ($lower === 'array') {
            return $this->arrayHydrator;
        }

        if ($lower === 'entity') {
            return $this->entityHydrator;
        }

        if ($lower === 'dto') {
            throw new HydrationException('DTO hydrator requires passing the DTO FQCN to getResult().');
        }

        if (!class_exists($hydrator)) {
            throw new HydrationException(sprintf('Unknown hydrator "%s".', $hydrator));
        }

        /** @var class-string $hydrator */
        return new DtoHydrator($hydrator);
    }
}
