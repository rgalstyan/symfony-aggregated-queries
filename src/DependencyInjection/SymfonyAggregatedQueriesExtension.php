<?php

declare(strict_types=1);

namespace Rgalstyan\SymfonyAggregatedQueries\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

final class SymfonyAggregatedQueriesExtension extends Extension
{
    /**
     * @param array<int, array{
     *     default_hydrator?: string,
     *     enabled?: bool,
     *     debug?: bool,
     *     max_relations?: int,
     *     fallback_enabled?: bool,
     * }> $configs
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $container->setParameter('aggregated_queries.default_hydrator', $config['default_hydrator']);
        $container->setParameter('aggregated_queries.enabled', (bool) $config['enabled']);
        $container->setParameter('aggregated_queries.debug', (bool) $config['debug']);
        $container->setParameter('aggregated_queries.max_relations', (int) $config['max_relations']);
        $container->setParameter('aggregated_queries.fallback_enabled', (bool) $config['fallback_enabled']);

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../../config'));
        $loader->load('services.yaml');
    }

    public function getAlias(): string
    {
        return 'aggregated_queries';
    }
}
