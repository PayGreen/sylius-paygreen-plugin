<?php

declare(strict_types=1);

namespace Paygreen\SyliusPaygreenPlugin\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('paygreen_sylius_paygreen_plugin');
        $rootNode = $treeBuilder->getRootNode();

        return $treeBuilder;
    }
}
