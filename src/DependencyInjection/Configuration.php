<?php

declare(strict_types=1);

namespace Rgalstyan\SymfonyAggregatedQueries\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('aggregated_queries');

        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->scalarNode('default_hydrator')
                    ->defaultValue('array')
                    ->cannotBeEmpty()
                ->end()
                ->booleanNode('enabled')
                    ->defaultTrue()
                ->end()
                ->booleanNode('debug')
                    ->defaultFalse()
                ->end()
                ->integerNode('max_relations')
                    ->min(0)
                    ->defaultValue(15)
                ->end()
                ->booleanNode('fallback_enabled')
                    ->defaultFalse()
                ->end()
            ->end();

        return $treeBuilder;
    }
}

