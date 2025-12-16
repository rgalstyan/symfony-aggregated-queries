<?php

declare(strict_types=1);

namespace Rgalstyan\SymfonyAggregatedQueries\Bundle;

use Rgalstyan\SymfonyAggregatedQueries\DependencyInjection\SymfonyAggregatedQueriesExtension;
use Rgalstyan\SymfonyAggregatedQueries\DependencyInjection\Compiler\RegisterGeneratorsPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

final class SymfonyAggregatedQueriesBundle extends Bundle
{
    private ?SymfonyAggregatedQueriesExtension $containerExtension = null;

    public function getContainerExtension(): ExtensionInterface
    {
        if ($this->containerExtension === null) {
            $this->containerExtension = new SymfonyAggregatedQueriesExtension();
        }

        return $this->containerExtension;
    }

    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new RegisterGeneratorsPass());
    }
}
