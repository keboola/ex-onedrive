<?php

declare(strict_types=1);

namespace Keboola\OneDriveExtractor\Configuration;

use Keboola\Component\Config\BaseConfigDefinition;
use Keboola\OneDriveExtractor\Configuration\Parts\WorkbookDefinition;
use Keboola\OneDriveExtractor\Configuration\Parts\WorksheetDefinition;
use Keboola\OneDriveExtractor\Exception\InvalidConfigException;
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
                        // Output table is required, XLSX file name cannot always be used
                        ->scalarNode('table')->isRequired()->cannotBeEmpty()->end()
                    ->end()
                ->end()
                // Workbook is one XLSX file
                ->append(WorkbookDefinition::getDefinition())
                // In one workbook are multiple worksheets, specify one
                ->append(WorksheetDefinition::getDefinition())
            ->end();
        // @formatter:on

        return $parametersNode;
    }
}
