<?php

declare(strict_types=1);

namespace Rgalstyan\SymfonyAggregatedQueries\Tests\Unit\Metadata;

use ArrayAccess;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use PHPUnit\Framework\TestCase;
use Rgalstyan\SymfonyAggregatedQueries\Exception\UnsupportedRelationException;
use Rgalstyan\SymfonyAggregatedQueries\Metadata\RelationExtractor;
use Rgalstyan\SymfonyAggregatedQueries\Tests\Fixtures\Entity\Partner;
use Rgalstyan\SymfonyAggregatedQueries\Tests\Fixtures\Entity\Profile;
use Rgalstyan\SymfonyAggregatedQueries\Tests\Fixtures\Entity\Promocode;

final class RelationExtractorTest extends TestCase
{
    public function testExtractsManyToOneJoinColumns(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $partnerMetadata = $this->createMock(ClassMetadata::class);

        $partnerMetadata->method('hasAssociation')->with('profile')->willReturn(true);
        $partnerMetadata->method('getAssociationMapping')->with('profile')->willReturn(
            $this->createAssociationMapping(
                ClassMetadata::MANY_TO_ONE,
                fieldName: 'profile',
                sourceEntity: Partner::class,
                targetEntity: Profile::class,
                joinColumns: [
                    ['name' => 'profile_id', 'referencedColumnName' => 'id'],
                ],
            )
        );

        $entityManager->method('getClassMetadata')->with(Partner::class)->willReturn($partnerMetadata);

        $extractor = new RelationExtractor($entityManager);
        $metadata = $extractor->extract(Partner::class, 'profile');

        $this->assertSame('ManyToOne', $metadata->type);
        $this->assertSame(Profile::class, $metadata->targetEntity);
        $this->assertSame('profile_id', $metadata->baseColumn);
        $this->assertSame('id', $metadata->relatedColumn);
    }

    public function testExtractsOneToManyJoinColumnsViaMappedBy(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $partnerMetadata = $this->createMock(ClassMetadata::class);
        $promocodeMetadata = $this->createMock(ClassMetadata::class);

        $partnerMetadata->method('hasAssociation')->with('promocodes')->willReturn(true);
        $partnerMetadata->method('getAssociationMapping')->with('promocodes')->willReturn(
            $this->createAssociationMapping(
                ClassMetadata::ONE_TO_MANY,
                fieldName: 'promocodes',
                sourceEntity: Partner::class,
                targetEntity: Promocode::class,
                joinColumns: [],
                mappedBy: 'partner',
            )
        );

        $promocodeMetadata->method('getAssociationMapping')->with('partner')->willReturn(
            $this->createAssociationMapping(
                ClassMetadata::MANY_TO_ONE,
                fieldName: 'partner',
                sourceEntity: Promocode::class,
                targetEntity: Partner::class,
                joinColumns: [
                    ['name' => 'partner_id', 'referencedColumnName' => 'id'],
                ],
            )
        );

        $entityManager->method('getClassMetadata')->willReturnCallback(
            fn (string $class): ClassMetadata => match ($class) {
                Partner::class => $partnerMetadata,
                Promocode::class => $promocodeMetadata,
                default => throw new \RuntimeException('Unexpected class'),
            }
        );

        $extractor = new RelationExtractor($entityManager);
        $metadata = $extractor->extract(Partner::class, 'promocodes');

        $this->assertSame('OneToMany', $metadata->type);
        $this->assertSame(Promocode::class, $metadata->targetEntity);
        $this->assertSame('id', $metadata->baseColumn);
        $this->assertSame('partner_id', $metadata->relatedColumn);
    }

    public function testThrowsForManyToMany(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $partnerMetadata = $this->createMock(ClassMetadata::class);

        $partnerMetadata->method('hasAssociation')->with('tags')->willReturn(true);
        $partnerMetadata->method('getAssociationMapping')->with('tags')->willReturn(
            $this->createAssociationMapping(
                ClassMetadata::MANY_TO_MANY,
                fieldName: 'tags',
                sourceEntity: Partner::class,
                targetEntity: Profile::class,
            )
        );

        $entityManager->method('getClassMetadata')->with(Partner::class)->willReturn($partnerMetadata);

        $extractor = new RelationExtractor($entityManager);

        $this->expectException(UnsupportedRelationException::class);
        $extractor->extract(Partner::class, 'tags');
    }

    /**
     * @param list<array{name: string, referencedColumnName: string}> $joinColumns
     *
     * @return array<string, bool|int|string|array>|ArrayAccess
     */
    private function createAssociationMapping(
        int $type,
        string $fieldName,
        string $sourceEntity,
        string $targetEntity,
        array $joinColumns = [],
        string $mappedBy = '',
    ): array|ArrayAccess {
        if (class_exists(\Doctrine\ORM\Mapping\ManyToOneAssociationMapping::class)) {
            if ($type === ClassMetadata::MANY_TO_ONE) {
                return \Doctrine\ORM\Mapping\ManyToOneAssociationMapping::fromMappingArray([
                    'fieldName' => $fieldName,
                    'sourceEntity' => $sourceEntity,
                    'targetEntity' => $targetEntity,
                    'isOwningSide' => true,
                    'joinColumns' => $joinColumns,
                ]);
            }

            if ($type === ClassMetadata::ONE_TO_MANY) {
                if ($mappedBy === '') {
                    throw new \InvalidArgumentException('mappedBy is required for OneToMany association mapping.');
                }

                return \Doctrine\ORM\Mapping\OneToManyAssociationMapping::fromMappingArray([
                    'fieldName' => $fieldName,
                    'sourceEntity' => $sourceEntity,
                    'targetEntity' => $targetEntity,
                    'isOwningSide' => false,
                    'mappedBy' => $mappedBy,
                ]);
            }

            if ($type === ClassMetadata::MANY_TO_MANY) {
                return new \Doctrine\ORM\Mapping\ManyToManyOwningSideMapping($fieldName, $sourceEntity, $targetEntity);
            }
        }

        $mapping = [
            'type' => $type,
            'targetEntity' => $targetEntity,
            'joinColumns' => $joinColumns,
        ];

        if ($mappedBy !== '') {
            $mapping['mappedBy'] = $mappedBy;
        }

        return $mapping;
    }
}
