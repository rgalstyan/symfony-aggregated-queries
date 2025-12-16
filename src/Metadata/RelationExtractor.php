<?php

declare(strict_types=1);

namespace Rgalstyan\SymfonyAggregatedQueries\Metadata;

use ArrayAccess;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\Mapping\MappingException as PersistenceMappingException;
use Rgalstyan\SymfonyAggregatedQueries\Exception\InvalidEntityException;
use Rgalstyan\SymfonyAggregatedQueries\Exception\UnsupportedRelationException;

/**
 * @phpstan-type JoinColumn array{
 *     name: string|null,
 *     referencedColumnName: string|null,
 *     unique?: bool,
 *     quoted?: bool,
 *     fieldName?: string,
 *     onDelete?: string,
 *     columnDefinition?: string,
 *     nullable?: bool,
 *     ...
 * }
 * @phpstan-type AssociationMapping array{
 *     type: int,
 *     targetEntity: class-string,
 *     joinColumns: list<JoinColumn>,
 *     mappedBy?: string,
 *     ...
 * }
 */
final class RelationExtractor
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {}

    /**
     * Extract relation metadata from Doctrine mapping.
     *
     * @throws InvalidEntityException
     * @throws UnsupportedRelationException
     */
    public function extract(string $entityClass, string $relationName): RelationMetadata
    {
        if ($entityClass === '') {
            throw new InvalidEntityException('Entity class cannot be empty.');
        }

        if ($relationName === '') {
            throw new InvalidEntityException('Relation name cannot be empty.');
        }

        $this->assertValidIdentifier($relationName, 'Relation');

        try {
            $metadata = $this->entityManager->getClassMetadata($entityClass);
        } catch (PersistenceMappingException $exception) {
            throw new InvalidEntityException(sprintf('Entity "%s" is not a valid Doctrine-mapped class.', $entityClass), 0, $exception);
        }

        if (!$metadata->hasAssociation($relationName)) {
            throw new InvalidEntityException(sprintf('Relation "%s" not found on entity "%s".', $relationName, $entityClass));
        }

        $associationMapping = $this->normalizeAssociationMapping($metadata->getAssociationMapping($relationName));

        $type = $this->resolveRelationType($associationMapping['type']);
        if ($type === 'ManyToMany') {
            throw new UnsupportedRelationException(sprintf(
                'Relation "%s" on "%s" is ManyToMany which is not supported in v1.0.',
                $relationName,
                $entityClass
            ));
        }

        $targetEntity = $associationMapping['targetEntity'];

        $join = $this->extractJoinColumns($type, $associationMapping);

        return new RelationMetadata(
            type: $type,
            targetEntity: $targetEntity,
            baseColumn: $join['baseColumn'],
            relatedColumn: $join['relatedColumn'],
        );
    }

    /**
     * @param array{
     *     type?: int,
     *     targetEntity?: string,
     *     joinColumns?: list<array<string, bool|int|string|null>|object>,
     *     mappedBy?: string,
     * }|object $mapping
     *
     * @return AssociationMapping
     */
    private function normalizeAssociationMapping(array|object $mapping): array
    {
        if (is_array($mapping)) {
            $type = $mapping['type'] ?? null;
            $targetEntity = $mapping['targetEntity'] ?? null;
            $joinColumnsRaw = $mapping['joinColumns'] ?? [];
            $mappedBy = $mapping['mappedBy'] ?? null;
        } elseif ($mapping instanceof ArrayAccess) {
            $type = $mapping['type'] ?? null;
            $targetEntity = $mapping['targetEntity'] ?? null;
            $joinColumnsRaw = $mapping['joinColumns'] ?? [];
            $mappedBy = $mapping['mappedBy'] ?? null;
        } else {
            throw new InvalidEntityException('Association mapping must be an array or ArrayAccess.');
        }

        if (!is_int($type)) {
            throw new InvalidEntityException('Association mapping "type" must be an integer.');
        }

        if (!is_string($targetEntity) || $targetEntity === '') {
            throw new InvalidEntityException('Association mapping "targetEntity" must be a non-empty string.');
        }

        if (!is_array($joinColumnsRaw)) {
            throw new InvalidEntityException('Association mapping "joinColumns" must be an array.');
        }

        $joinColumns = [];
        foreach ($joinColumnsRaw as $joinColumn) {
            if (!is_array($joinColumn) && !($joinColumn instanceof ArrayAccess)) {
                throw new InvalidEntityException('Join column mapping must be an array or ArrayAccess.');
            }

            $name = $joinColumn['name'] ?? null;
            if ($name !== null && !is_string($name)) {
                throw new InvalidEntityException('Join column "name" must be a string or null.');
            }

            $referenced = $joinColumn['referencedColumnName'] ?? null;
            if ($referenced !== null && !is_string($referenced)) {
                throw new InvalidEntityException('Join column "referencedColumnName" must be a string or null.');
            }

            $joinColumns[] = [
                'name' => $name,
                'referencedColumnName' => $referenced,
            ];
        }

        $normalized = [
            'type' => $type,
            'targetEntity' => $targetEntity,
            'joinColumns' => $joinColumns,
        ];

        if ($mappedBy !== null) {
            if (!is_string($mappedBy)) {
                throw new InvalidEntityException('Association mapping "mappedBy" must be a string.');
            }

            $normalized['mappedBy'] = $mappedBy;
        }

        /** @var AssociationMapping $normalized */
        return $normalized;
    }

    private function resolveRelationType(int $type): string
    {
        return match ($type) {
            ClassMetadata::MANY_TO_ONE => 'ManyToOne',
            ClassMetadata::ONE_TO_ONE => 'OneToOne',
            ClassMetadata::ONE_TO_MANY => 'OneToMany',
            ClassMetadata::MANY_TO_MANY => 'ManyToMany',
            default => throw new UnsupportedRelationException(sprintf('Unsupported relation type: %d', $type)),
        };
    }

    /**
     * @param AssociationMapping $mapping
     * @return array{baseColumn: string, relatedColumn: string}
     */
    private function extractJoinColumns(string $type, array $mapping): array
    {
        if ($type !== 'OneToMany' && $mapping['joinColumns'] !== []) {
            $joinColumn = $mapping['joinColumns'][0];
            $baseColumn = $joinColumn['name'] ?? '';
            $relatedColumn = $joinColumn['referencedColumnName'] ?? '';

            if ($baseColumn !== '' && $relatedColumn !== '') {
                $this->assertValidIdentifier($baseColumn, 'Join column');
                $this->assertValidIdentifier($relatedColumn, 'Referenced join column');

                return [
                    'baseColumn' => $baseColumn,
                    'relatedColumn' => $relatedColumn,
                ];
            }
        }

        $mappedBy = $mapping['mappedBy'] ?? '';
        if ($mappedBy === '') {
            throw new UnsupportedRelationException(sprintf('Relation has no joinColumns/mappedBy (type: %s).', $type));
        }

        $this->assertValidIdentifier($mappedBy, 'MappedBy');

        $targetEntity = $mapping['targetEntity'];
        $targetMetadata = $this->entityManager->getClassMetadata($targetEntity);

        $inverseMapping = $this->normalizeAssociationMapping($targetMetadata->getAssociationMapping($mappedBy));

        if ($inverseMapping['joinColumns'] === []) {
            throw new UnsupportedRelationException(sprintf('Unable to resolve join column from mappedBy="%s" on "%s".', $mappedBy, $targetEntity));
        }

        $joinColumn = $inverseMapping['joinColumns'][0];
        $relatedColumn = $joinColumn['name'] ?? '';
        $baseColumn = $joinColumn['referencedColumnName'] ?? '';

        $this->assertValidIdentifier($baseColumn, 'Referenced join column');
        $this->assertValidIdentifier($relatedColumn, 'Join column');

        return [
            'baseColumn' => $baseColumn,
            'relatedColumn' => $relatedColumn,
        ];
    }

    private function assertValidIdentifier(string $identifier, string $label): void
    {
        if ($identifier === '') {
            throw new InvalidEntityException(sprintf('%s cannot be empty.', $label));
        }

        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $identifier)) {
            throw new InvalidEntityException(sprintf('%s has invalid format: "%s".', $label, $identifier));
        }
    }
}
