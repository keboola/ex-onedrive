<?php

declare(strict_types=1);

namespace Keboola\OneDriveExtractor\Configuration\Actions;

use Keboola\Component\Config\BaseConfigDefinition;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;

class SearchConfigDefinition extends BaseConfigDefinition
{
    protected function getParametersDefinition(): ArrayNodeDefinition
    {
        $builder = new TreeBuilder('parameters');
        /** @var ArrayNodeDefinition $parametersNode */
        $parametersNode = $builder->getRootNode();

        // @formatter:off
        $parametersNode
            ->children()
                // Workbook is one XLSX file
                ->arrayNode('workbook')
                    ->isRequired()
                    ->children()
                        ->scalarNode('search')->isRequired()->cannotBeEmpty()->end()
                ->end()
            ->end();
        // @formatter:on

        return $parametersNode;
    }
}
