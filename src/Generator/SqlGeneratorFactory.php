<?php

declare(strict_types=1);

namespace Rgalstyan\SymfonyAggregatedQueries\Generator;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
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
        $dbalPlatform = $this->entityManager->getConnection()->getDatabasePlatform();
        $platform = $this->resolvePlatformKey($dbalPlatform);

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

    private function resolvePlatformKey(object $platform): string
    {
        if ($platform instanceof AbstractMySQLPlatform) {
            return 'mysql';
        }

        if ($platform instanceof PostgreSQLPlatform) {
            return 'postgresql';
        }

        return strtolower($platform::class);
    }
}
