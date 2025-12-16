<?php

declare(strict_types=1);

namespace Rgalstyan\SymfonyAggregatedQueries\Tests\Unit\Generator;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use PHPUnit\Framework\TestCase;
use Rgalstyan\SymfonyAggregatedQueries\Generator\MySqlGenerator;
use Rgalstyan\SymfonyAggregatedQueries\Query\OrderBy;
use Rgalstyan\SymfonyAggregatedQueries\Query\RelationConfig;
use Rgalstyan\SymfonyAggregatedQueries\Query\WhereClause;
use Rgalstyan\SymfonyAggregatedQueries\Tests\Fixtures\Entity\Partner;
use Rgalstyan\SymfonyAggregatedQueries\Tests\Fixtures\Entity\Profile;

final class MySqlGeneratorTest extends TestCase
{
    public function testGeneratesSqlForJsonObjectAndWhere(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $partnerMetadata = $this->createMock(ClassMetadata::class);
        $profileMetadata = $this->createMock(ClassMetadata::class);

        $partnerMetadata->method('getTableName')->willReturn('partners');
        $partnerMetadata->method('getIdentifierColumnNames')->willReturn(['id']);

        $profileMetadata->method('getTableName')->willReturn('profiles');
        $profileMetadata->method('getIdentifierColumnNames')->willReturn(['id']);

        $entityManager->method('getClassMetadata')->willReturnCallback(
            fn (string $class): ClassMetadata => match ($class) {
                Partner::class => $partnerMetadata,
                Profile::class => $profileMetadata,
                default => throw new \RuntimeException('Unexpected class'),
            }
        );

        $generator = new MySqlGenerator($entityManager);

        $relations = [
            'profile' => new RelationConfig(
                relationName: 'profile',
                type: 'ManyToOne',
                targetEntity: Profile::class,
                method: 'json_object',
                columns: ['id', 'name'],
                baseColumn: 'profile_id',
                relatedColumn: 'id',
            ),
        ];

        $sqlQuery = $generator->generate(
            Partner::class,
            $relations,
            [],
            [new WhereClause('status', '=', 'active')],
            [new OrderBy('created_at', 'DESC')],
            50,
            10
        );

        $expectedSql = "SELECT e.*, (CASE WHEN rel_profile.id IS NULL THEN NULL ELSE JSON_OBJECT('id', rel_profile.id, 'name', rel_profile.name) END) AS profile FROM partners e LEFT JOIN profiles rel_profile ON rel_profile.id = e.profile_id WHERE e.status = ? ORDER BY e.created_at DESC LIMIT 50 OFFSET 10";

        $this->assertSame($expectedSql, $sqlQuery->sql);
        $this->assertSame(['active'], $sqlQuery->params);
    }
}

