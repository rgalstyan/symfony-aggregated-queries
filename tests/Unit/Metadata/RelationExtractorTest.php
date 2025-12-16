<?php

declare(strict_types=1);

namespace Rgalstyan\SymfonyAggregatedQueries\Tests\Unit\Metadata;

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
        $partnerMetadata->method('getAssociationMapping')->with('profile')->willReturn([
            'type' => ClassMetadata::MANY_TO_ONE,
            'targetEntity' => Profile::class,
            'joinColumns' => [
                ['name' => 'profile_id', 'referencedColumnName' => 'id'],
            ],
        ]);

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
        $partnerMetadata->method('getAssociationMapping')->with('promocodes')->willReturn([
            'type' => ClassMetadata::ONE_TO_MANY,
            'targetEntity' => Promocode::class,
            'mappedBy' => 'partner',
        ]);

        $promocodeMetadata->method('getAssociationMapping')->with('partner')->willReturn([
            'joinColumns' => [
                ['name' => 'partner_id', 'referencedColumnName' => 'id'],
            ],
        ]);

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
        $partnerMetadata->method('getAssociationMapping')->with('tags')->willReturn([
            'type' => ClassMetadata::MANY_TO_MANY,
            'targetEntity' => Profile::class,
        ]);

        $entityManager->method('getClassMetadata')->with(Partner::class)->willReturn($partnerMetadata);

        $extractor = new RelationExtractor($entityManager);

        $this->expectException(UnsupportedRelationException::class);
        $extractor->extract(Partner::class, 'tags');
    }
}

