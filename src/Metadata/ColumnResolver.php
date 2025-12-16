<?php

declare(strict_types=1);

namespace Rgalstyan\SymfonyAggregatedQueries\Metadata;

use ArrayAccess;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\Mapping\MappingException as PersistenceMappingException;
use Rgalstyan\SymfonyAggregatedQueries\Exception\InvalidEntityException;

final class ColumnResolver
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {}

    /**
     * Resolve an entity field name or join column name to a database column name.
     *
     * @throws InvalidEntityException
     */
    public function resolveColumnName(string $entityClass, string $fieldOrColumn): string
    {
        if ($entityClass === '') {
            throw new InvalidEntityException('Entity class cannot be empty.');
        }

        if ($fieldOrColumn === '') {
            throw new InvalidEntityException('Column name cannot be empty.');
        }

        $this->assertValidIdentifier($fieldOrColumn);

        try {
            $metadata = $this->entityManager->getClassMetadata($entityClass);
        } catch (PersistenceMappingException $exception) {
            throw new InvalidEntityException(sprintf('Entity "%s" is not a valid Doctrine-mapped class.', $entityClass), 0, $exception);
        }

        if ($metadata->hasField($fieldOrColumn)) {
            $columnName = (string) $metadata->getColumnName($fieldOrColumn);
            $this->assertValidIdentifier($columnName);

            return $columnName;
        }

        foreach ($metadata->getFieldNames() as $fieldName) {
            $columnName = (string) $metadata->getColumnName($fieldName);
            if ($columnName === $fieldOrColumn) {
                return $columnName;
            }
        }

        foreach ($metadata->getAssociationMappings() as $associationMapping) {
            if (!isset($associationMapping['joinColumns'])) {
                continue;
            }

            $joinColumns = $associationMapping['joinColumns'];
            if (!is_array($joinColumns)) {
                continue;
            }

            foreach ($joinColumns as $joinColumn) {
                if (!is_array($joinColumn) && !($joinColumn instanceof ArrayAccess)) {
                    continue;
                }

                $name = $joinColumn['name'] ?? null;
                if (is_string($name) && $name === $fieldOrColumn) {
                    return $name;
                }
            }
        }

        throw new InvalidEntityException(sprintf('Column "%s" not found on entity "%s".', $fieldOrColumn, $entityClass));
    }

    /**
     * Resolve a list of entity field names to database column names.
     *
     * @param array<int, string> $fieldsOrColumns
     * @return array<int, string>
     *
     * @throws InvalidEntityException
     */
    public function resolveColumnNames(string $entityClass, array $fieldsOrColumns): array
    {
        $resolved = [];
        foreach ($fieldsOrColumns as $fieldOrColumn) {
            if ($fieldOrColumn === '') {
                throw new InvalidEntityException('Column names must be non-empty strings.');
            }

            $resolved[] = $this->resolveColumnName($entityClass, $fieldOrColumn);
        }

        return $resolved;
    }

    /**
     * @return array<int, string>
     *
     * @throws InvalidEntityException
     */
    public function resolveIdentifierColumnNames(string $entityClass): array
    {
        try {
            $metadata = $this->entityManager->getClassMetadata($entityClass);
        } catch (PersistenceMappingException $exception) {
            throw new InvalidEntityException(sprintf('Entity "%s" is not a valid Doctrine-mapped class.', $entityClass), 0, $exception);
        }

        $columns = $metadata->getIdentifierColumnNames();
        if ($columns === []) {
            throw new InvalidEntityException(sprintf('Entity "%s" has no identifier columns.', $entityClass));
        }

        foreach ($columns as $column) {
            $this->assertValidIdentifier((string) $column);
        }

        /** @var array<int, string> $columns */
        return $columns;
    }

    private function assertValidIdentifier(string $identifier): void
    {
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $identifier)) {
            throw new InvalidEntityException(sprintf('Invalid identifier format: "%s".', $identifier));
        }
    }
}
