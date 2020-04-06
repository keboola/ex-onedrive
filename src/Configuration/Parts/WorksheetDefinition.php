<?php

declare(strict_types=1);

namespace Keboola\OneDriveExtractor\Configuration\Parts;

use Keboola\OneDriveExtractor\Exception\InvalidConfigException;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;

class WorksheetDefinition
{
    public static function getDefinition(): ArrayNodeDefinition
    {
        $treeBuilder = new TreeBuilder('worksheet');

        /** @var ArrayNodeDefinition $root */
        $root = $treeBuilder->getRootNode();

        // @formatter:off
        $root
            ->isRequired()
            ->children()
                // Name of the output CSV file (the file/sheet name can be quite exotic and we cannot rely on it)
                ->scalarNode('name')->isRequired()->cannotBeEmpty()->end()
                // Worksheet is specified by id
                ->scalarNode('id')->cannotBeEmpty()->end()
                // ... OR by position, first is 0, hidden sheets are included
                ->scalarNode('position')->cannotBeEmpty()->end()
            ->end()
            // Only one of id/position allowed
            ->validate()
                ->ifTrue(function (array $worksheet): bool {
                    $hasId = isset($worksheet['id']);
                    $hasPosition = array_key_exists('position', $worksheet); // position can be 0
                    return $hasId && $hasPosition;
                })
                ->thenInvalid('In config must be ONLY ONE OF "worksheet.id" OR "worksheet.position". Both given.')
            ->end()
            // One of id/position must be set
            ->validate()
                ->ifTrue(function (array $worksheet): bool {
                    $hasId = isset($worksheet['id']);
                    $hasPosition = array_key_exists('position', $worksheet); // position can be 0
                    return !$hasId && !$hasPosition;
                })
                ->thenInvalid('In config must be ONE OF "worksheet.id" OR "worksheet.position".')
            ->end();
        // @formatter:on

        return $root;
    }
}
