<?php

declare(strict_types=1);

namespace Rgalstyan\SymfonyAggregatedQueries\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Rgalstyan\SymfonyAggregatedQueries\Tests\Fixtures\Entity\Country;
use Rgalstyan\SymfonyAggregatedQueries\Tests\Fixtures\Entity\Partner;
use Rgalstyan\SymfonyAggregatedQueries\Tests\Fixtures\Entity\Profile;
use Rgalstyan\SymfonyAggregatedQueries\Tests\Fixtures\Entity\Promocode;
use Rgalstyan\SymfonyAggregatedQueries\Tests\Fixtures\Repository\PartnerRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class AggregatedQueryBuilderTest extends KernelTestCase
{
    private PartnerRepository $repository;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();

        $container = self::getContainer();

        $this->repository = $container->get(PartnerRepository::class);
        $this->entityManager = $container->get(EntityManagerInterface::class);

        $platform = $this->entityManager->getConnection()->getDatabasePlatform()->getName();
        if (!in_array($platform, ['mysql', 'mariadb', 'postgresql'], true)) {
            self::markTestSkipped(sprintf('DB platform "%s" is not supported for functional tests.', $platform));
        }

        $this->resetSchema();
        $this->seedData();
    }

    public function testLoadsJsonRelationsAndCounts(): void
    {
        $result = $this->repository->aggregatedQuery()
            ->withJsonRelation('profile', ['id', 'name'])
            ->withJsonRelation('country', ['id', 'name', 'code'])
            ->withJsonCollection('promocodes', ['id', 'code'])
            ->withCount('promocodes')
            ->where('status', 'active')
            ->orderBy('createdAt', 'DESC')
            ->limit(10)
            ->getResult();

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);

        $first = $result[0];
        $this->assertArrayHasKey('profile', $first);
        $this->assertIsArray($first['profile']);
        $this->assertArrayHasKey('country', $first);
        $this->assertIsArray($first['country']);
        $this->assertArrayHasKey('promocodes', $first);
        $this->assertIsArray($first['promocodes']);
        $this->assertArrayHasKey('promocodes_count', $first);
        $this->assertIsInt($first['promocodes_count']);
    }

    private function resetSchema(): void
    {
        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool = new SchemaTool($this->entityManager);
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
    }

    private function seedData(): void
    {
        $country = new Country();
        $country->setName('Armenia');
        $country->setCode('AM');
        $this->entityManager->persist($country);

        for ($i = 0; $i < 3; $i++) {
            $profile = new Profile();
            $profile->setName("Profile $i");
            $this->entityManager->persist($profile);

            $partner = new Partner();
            $partner->setName("Partner $i");
            $partner->setStatus('active');
            $partner->setProfile($profile);
            $partner->setCountry($country);

            for ($j = 0; $j < 2; $j++) {
                $promocode = new Promocode();
                $promocode->setCode("SAVE{$i}{$j}");
                $promocode->setDiscount(10 + $j);
                $partner->addPromocode($promocode);
            }

            $this->entityManager->persist($partner);
        }

        $this->entityManager->flush();
        $this->entityManager->clear();
    }
}

