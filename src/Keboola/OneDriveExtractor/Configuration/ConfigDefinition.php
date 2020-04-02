<?php

declare(strict_types=1);

namespace Keboola\OneDriveExtractor\Configuration;

use Keboola\Component\Config\BaseConfigDefinition;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;

class ConfigDefinition extends BaseConfigDefinition
{
    protected function getParametersDefinition(): ArrayNodeDefinition
    {
        $builder = new TreeBuilder('parameters');
        /** @var ArrayNodeDefinition $parametersNode */
        $parametersNode = $builder->getRootNode();

        // @formatter:off
        $parametersNode
            ->children()
                ->arrayNode('output')
                    ->isRequired()
                    ->children()
                        // Default bucked is used when no bucket name is specified
                        ->scalarNode('bucket')->cannotBeEmpty()->end()
                        // Output table is required
                        ->scalarNode('table')->isRequired()->cannotBeEmpty()->end()
                    ->end()
                ->end()
                // Workbook is one XLSX file
                ->arrayNode('workbook')
                    ->isRequired()
                    ->children()
                        // Workbook is specified by driveId, fileId
                        ->scalarNode('driveId')->cannotBeEmpty()->end()
                        ->scalarNode('fileId')->cannotBeEmpty()->end()
                        // ... OR by search (path, download url, ...)
                        ->scalarNode('search')->cannotBeEmpty()->end()
                    ->end()
                ->end()
                // In one workbook are multiple worksheets, specify one
                ->arrayNode('worksheet')
                    ->isRequired()
                    ->children()
                        // Worksheet is specified by id
                        ->scalarNode('id')->cannotBeEmpty()->end()
                        // ... OR by position, first is 0, hidden sheets are included
                        ->scalarNode('position')->cannotBeEmpty()->end()
                    ->end()
                ->end()
            ->end();
        // @formatter:on

        return $parametersNode;
    }
}
