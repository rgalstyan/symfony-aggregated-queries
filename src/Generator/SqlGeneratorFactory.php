<?php

declare(strict_types=1);

namespace Rgalstyan\SymfonyAggregatedQueries\Generator;

use Doctrine\ORM\EntityManagerInterface;
use Rgalstyan\SymfonyAggregatedQueries\Exception\DatabaseNotSupportedException;

final class SqlGeneratorFactory
{
    /** @var array<string, SqlGeneratorInterface> */
    private array $generatorsByPlatform = [];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {}

    /**
     * Register a generator for a Doctrine DBAL platform name (e.g. mysql, postgresql).
     */
    public function registerGenerator(string $platform, SqlGeneratorInterface $generator): void
    {
        $platform = strtolower(trim($platform));
        if ($platform === '') {
            throw new DatabaseNotSupportedException('Generator platform cannot be empty.');
        }

        $this->generatorsByPlatform[$platform] = $generator;
    }

    /**
     * Create the generator matching the current database platform.
     *
     * @throws DatabaseNotSupportedException
     */
    public function create(): SqlGeneratorInterface
    {
        $platform = strtolower($this->entityManager->getConnection()->getDatabasePlatform()->getName());
        $platform = $platform === 'mariadb' ? 'mysql' : $platform;

        $generator = $this->generatorsByPlatform[$platform] ?? null;
        if ($generator === null) {
            throw new DatabaseNotSupportedException(sprintf(
                'Database platform "%s" is not supported. Supported: %s',
                $platform,
                implode(', ', array_keys($this->generatorsByPlatform))
            ));
        }

        return $generator;
    }
}

