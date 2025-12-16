<?php

declare(strict_types=1);

namespace Rgalstyan\SymfonyAggregatedQueries\Tests;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Rgalstyan\SymfonyAggregatedQueries\Bundle\SymfonyAggregatedQueriesBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;

final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    /**
     * @return iterable<int, object>
     */
    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new DoctrineBundle();
        yield new SymfonyAggregatedQueriesBundle();
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'test' => true,
            'secret' => 'test',
        ]);

        $container->extension('doctrine', [
            'dbal' => [
                'url' => '%env(resolve:DATABASE_URL)%',
            ],
            'orm' => [
                'auto_generate_proxy_classes' => true,
                'auto_mapping' => false,
	                'mappings' => [
	                    'Fixtures' => [
	                        'is_bundle' => false,
	                        'type' => 'attribute',
	                        'dir' => '%kernel.project_dir%/tests/Fixtures/Entity',
	                        'prefix' => 'Rgalstyan\\SymfonyAggregatedQueries\\Tests\\Fixtures\\Entity',
	                    ],
	                ],
	            ],
	        ]);

        $container->extension('aggregated_queries', [
            'enabled' => true,
            'debug' => false,
            'max_relations' => 15,
            'default_hydrator' => 'array',
        ]);

        $services = $container->services();
        $services->defaults()
            ->autowire()
            ->autoconfigure();

	        $services->load(
	            'Rgalstyan\\SymfonyAggregatedQueries\\Tests\\Fixtures\\Repository\\',
	            '%kernel.project_dir%/tests/Fixtures/Repository/*'
	        )->tag('doctrine.repository_service');
	    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir() . '/symfony_aggregated_queries/cache/' . $this->environment;
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir() . '/symfony_aggregated_queries/log';
    }
}
