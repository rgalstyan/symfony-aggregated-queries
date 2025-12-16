<?php

declare(strict_types=1);

namespace Rgalstyan\SymfonyAggregatedQueries\DependencyInjection\Compiler;

use Rgalstyan\SymfonyAggregatedQueries\Generator\SqlGeneratorFactory;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Reference;

final class RegisterGeneratorsPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(SqlGeneratorFactory::class)) {
            return;
        }

        $factoryDefinition = $container->getDefinition(SqlGeneratorFactory::class);

        foreach ($container->findTaggedServiceIds('aggregated_queries.generator') as $serviceId => $tags) {
            foreach ($tags as $tag) {
                $platform = $tag['platform'] ?? null;
                if (!is_string($platform) || $platform === '') {
                    throw new InvalidArgumentException(sprintf(
                        'Service "%s" must define a non-empty "platform" attribute on tag "%s".',
                        $serviceId,
                        'aggregated_queries.generator'
                    ));
                }

                $factoryDefinition->addMethodCall('registerGenerator', [$platform, new Reference($serviceId)]);
            }
        }
    }
}

