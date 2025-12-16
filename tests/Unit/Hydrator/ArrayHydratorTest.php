<?php

declare(strict_types=1);

namespace Rgalstyan\SymfonyAggregatedQueries\Tests\Unit\Hydrator;

use PHPUnit\Framework\TestCase;
use Rgalstyan\SymfonyAggregatedQueries\Hydrator\ArrayHydrator;
use Rgalstyan\SymfonyAggregatedQueries\Query\RelationConfig;
use Rgalstyan\SymfonyAggregatedQueries\Tests\Fixtures\Entity\Partner;
use Rgalstyan\SymfonyAggregatedQueries\Tests\Fixtures\Entity\Profile;
use Rgalstyan\SymfonyAggregatedQueries\Tests\Fixtures\Entity\Promocode;

final class ArrayHydratorTest extends TestCase
{
    public function testDecodesJsonAndCastsCounts(): void
    {
        $hydrator = new ArrayHydrator();

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
            'promocodes' => new RelationConfig(
                relationName: 'promocodes',
                type: 'OneToMany',
                targetEntity: Promocode::class,
                method: 'json_array',
                columns: ['id', 'code'],
                baseColumn: 'id',
                relatedColumn: 'partner_id',
            ),
        ];

        $rows = [
            [
                'id' => 1,
                'profile' => '{"id":10,"name":"John"}',
                'promocodes' => '[{"id":1,"code":"A"},{"id":2,"code":"B"}]',
                'promocodes_count' => '2',
            ],
        ];

        $result = $hydrator->hydrate($rows, Partner::class, $relations);

        $this->assertSame(10, $result[0]['profile']['id']);
        $this->assertCount(2, $result[0]['promocodes']);
        $this->assertSame(2, $result[0]['promocodes_count']);
    }

    public function testNullJsonCollectionBecomesEmptyArray(): void
    {
        $hydrator = new ArrayHydrator();

        $relations = [
            'promocodes' => new RelationConfig(
                relationName: 'promocodes',
                type: 'OneToMany',
                targetEntity: Promocode::class,
                method: 'json_array',
                columns: ['id'],
                baseColumn: 'id',
                relatedColumn: 'partner_id',
            ),
        ];

        $rows = [
            [
                'id' => 1,
                'promocodes' => null,
            ],
        ];

        $result = $hydrator->hydrate($rows, Partner::class, $relations);

        $this->assertSame([], $result[0]['promocodes']);
    }
}

