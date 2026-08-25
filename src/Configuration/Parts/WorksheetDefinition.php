<?php

declare(strict_types=1);

namespace Keboola\OneDriveExtractor\Configuration\Parts;

use Closure;
use InvalidArgumentException;
use Keboola\OneDriveExtractor\Api\Model\TableRange;
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
                // optional A1-notation range (eg. "B5:Z1000"), first row of the range = header
                // An empty value means "whole sheet", so the UI can always send the key.
                // The value is normalized (uppercase, trimmed) or rejected with a user error.
                ->scalarNode('range')
                    ->defaultNull()
                    ->validate()
                        ->always(Closure::fromCallable([self::class, 'normalizeRange']))
                    ->end()
                ->end()
                // optional metadata can be always present, it is not used in code
                ->arrayNode('metadata')->ignoreExtraKeys(true)->end()
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

    /**
     * Validates and normalizes the user-supplied "worksheet.range" value.
     *
     * An empty value means "whole sheet", so the UI can always send the key.
     *
     * Public because it is referenced as a callable, see getDefinition().
     *
     * @param mixed $range
     */
    public static function normalizeRange($range): ?string
    {
        if ($range === null) {
            return null;
        }

        if (!is_string($range)) {
            throw new InvalidArgumentException(sprintf(
                'Invalid range, expected a string, given "%s".',
                gettype($range)
            ));
        }

        if (trim($range) === '') {
            return null;
        }

        return TableRange::fromUserInput($range)->getAddress();
    }
}
