<?php declare(strict_types=1);

/**
 * AutomapFields - Automap field specifications to normalized property terms.
 *
 * This class provides intelligent field resolution, converting various input
 * formats (labels, local names, terms) into canonical property terms with
 * full qualifiers (datatype, language, visibility, pattern).
 *
 * Features:
 * - Property term resolution (e.g., "dcterms:title")
 * - Local name matching (e.g., "title" → "dcterms:title")
 * - Label matching (e.g., "Dublin Core : Title" → "dcterms:title")
 * - Datatype normalization (e.g., "item" → "resource:item")
 * - Custom vocab label resolution
 *   (e.g., ^^customvocab:"My List" → ^^customvocab:123)
 * - Old pattern detection with warnings
 * - Multiple targets with | separator
 *
 * Migrated from BulkImport\Mvc\Controller\Plugin\AutomapFields.
 *
 * @copyright Daniel Berthereau, 2017-2026
 * @license http://www.cecill.info/licences/Licence_CeCILL_V2.1-en.txt
 */

namespace Mapper\Stdlib;

use Common\Stdlib\EasyMeta;
use Laminas\Log\Logger;
use Omeka\Api\Manager as ApiManager;

class AutomapFields
{
    /**
     * Pattern to parse field specifications.
     *
     * Components (in order):
     * - Field (term/keyword/label): required, at beginning
     * - Datatype(s): ^^resource:item or ^^customvocab:"My List"
     * - Language: @fra or @en-GB
     * - Visibility: §private or §public
     * - Pattern: ~ {{ value|trim }}
     */
    public const PATTERN = '#'
        // Requires a term/keyword/label at beginning.
        . '^\s*+(?<field>[^@§^~|\n\r]+)'
        // Arguments in any order:
        . '(?<args>(?:'
        // Datatypes (^^resource:item or ^^customvocab:"Label").
        . '(?:\s*\^\^(?<datatype>(?:customvocab:(?:"[^\n\r"]+"|\'[^\n\r\']+\')|[a-zA-Z_][\w:-]*)))'
        // Language (@fra or @en-GB).
        . '|(?:\s*@(?<language>(?:(?:[a-zA-Z0-9]+-)*[a-zA-Z]+|)))'
        // Visibility (§private).
        . '|(?:\s*§(?<visibility>private|public|))'
        . ')*)?'
        // Pattern (~ {{ value|trim }}).
        . '(?:\s*~\s*(?<pattern>.*))?'
        . '\s*$'
        . '#';

    /**
     * Pattern to extract each datatype from args.
     */
    public const PATTERN_DATATYPES = '#\^\^(?<datatype>(?:customvocab:(?:"[^\n\r"]+"|\'[^\n\r\']+\')|[a-zA-Z_][\w:-]*))#';

    /**
     * Pattern to detect old format (deprecated).
     */
    public const OLD_PATTERN_CHECK = '#'
        . '(?<prefix_with_space>(?:\^\^|@|§)\s)'
        . '|(?<datatypes_semicolon>\^\^\s*[a-zA-Z][^\^@§~\n\r;]*;)'
        . '|(?<unwrapped_customvocab_label>(?:\^\^|;)\s*customvocab:[^\d"\';\^\n]+)'
        . '#u';

    /**
     * @var ApiManager
     */
    protected $api;

    /**
     * @var EasyMeta
     */
    protected $easyMeta;

    /**
     * @var Logger|null
     */
    protected $logger;

    /**
     * @var array Cached property lists for term resolution.
     */
    protected $propertyLists;

    /**
     * @var array Cached custom vocab labels to ids.
     */
    protected $customVocabLabels;

    /**
     * @var array User-defined mapping overrides.
     */
    protected $map = [];

    public function __construct(
        ApiManager $api,
        EasyMeta $easyMeta,
        ?Logger $logger = null,
        array $map = []
    ) {
        $this->api = $api;
        $this->easyMeta = $easyMeta;
        $this->logger = $logger;
        $this->map = $map;
    }

    /**
     * Automap a list of field specifications to normalized property data.
     *
     * @param array $fields List of field specifications.
     * @param array $options Options:
     *   - map: Additional mapping overrides.
     *   - check_field: Validate that field exists (default: true).
     *   - check_names_alone: Match local names without prefix (default: true).
     *   - single_target: Disable | separator (default: false).
     *   - output_full_matches: Return full data (default: false).
     *   - output_property_id: Include property_id in results (default: false).
     * @return array Mapped fields. With output_full_matches: field, datatype,
     *   language, is_public, pattern, property_id.
     */
    public function __invoke(array $fields, array $options = []): array
    {
        $options += [
            'map' => [],
            'check_field' => true,
            'check_names_alone' => true,
            'single_target' => false,
            'output_full_matches' => false,
            'output_property_id' => false,
        ];

        if (!$options['check_field']) {
            return $this->automapNoCheckField($fields, $options);
        }

        // Return all values, preserving keys.
        $automaps = array_fill_keys(array_keys($fields), null);

        $fields = $this->cleanStrings($fields);

        $checkNamesAlone = (bool) $options['check_names_alone'];
        $singleTarget = (bool) $options['single_target'];
        $outputFullMatches = (bool) $options['output_full_matches'];
        $outputPropertyId = $outputFullMatches && $options['output_property_id'];

        $map = array_merge($this->map, $options['map']);

        // Prepare lookup lists.
        $lists = $this->preparePropertyLists($checkNamesAlone);
        $automapLists = $this->prepareAutomapLists($map);

        foreach ($fields as $index => $fieldsMulti) {
            // Split by | unless single_target or pattern present.
            $fieldsMulti = $singleTarget || strpos($fieldsMulti, '~') !== false
                ? [trim($fieldsMulti)]
                : array_filter(array_map('trim', explode('|', $fieldsMulti)));

            foreach ($fieldsMulti as $field) {
                $this->checkOldPattern($field);

                if (!preg_match(self::PATTERN, $field, $matches)) {
                    continue;
                }

                $datatypes = $outputFullMatches
                    ? $this->extractDatatypes($matches)
                    : [];

                $fieldName = trim($matches['field']);
                $lowerField = mb_strtolower($fieldName);

                // Check custom automap list first.
                $found = $this->findInLists($fieldName, $lowerField, $automapLists);
                if ($found !== null) {
                    $resolvedField = $map[$found] ?? $found;
                    $automaps[$index][] = $outputFullMatches
                        ? $this->buildResult($resolvedField, $matches, $datatypes, $outputPropertyId)
                        : $resolvedField;
                    continue;
                }

                // Check property lists (terms and labels).
                $found = $this->findInLists($fieldName, $lowerField, $lists);
                if ($found !== null) {
                    $resolvedField = $this->propertyLists['names'][$found] ?? $found;
                    $automaps[$index][] = $outputFullMatches
                        ? $this->buildResult($resolvedField, $matches, $datatypes, $outputPropertyId)
                        : $resolvedField;
                }
            }
        }

        return $automaps;
    }

    /**
     * Automap without validating that fields exist.
     */
    protected function automapNoCheckField(array $fields, array $options): array
    {
        $automaps = array_fill_keys(array_keys($fields), null);
        $fields = $this->cleanStrings($fields);

        $singleTarget = (bool) $options['single_target'];
        $outputFullMatches = (bool) $options['output_full_matches'];
        $outputPropertyId = $outputFullMatches && $options['output_property_id'];

        // Make field optional in pattern.
        $pattern = substr_replace(self::PATTERN, '#^\s*+(?<field>[^@§^~|\n\r]+)?', 0, 30);

        foreach ($fields as $index => $fieldsMulti) {
            $fieldsMulti = $singleTarget || strpos($fieldsMulti, '~') !== false
                ? [$fieldsMulti]
                : array_filter(array_map('trim', explode('|', $fieldsMulti)));

            foreach ($fieldsMulti as $field) {
                $this->checkOldPattern($field);

                if (!preg_match($pattern, $field, $matches)) {
                    continue;
                }

                $fieldName = trim($matches['field'] ?? '');

                if ($outputFullMatches) {
                    $datatypes = $this->extractDatatypes($matches);
                    $automaps[$index][] = $this->buildResult($fieldName, $matches, $datatypes, $outputPropertyId);
                } else {
                    $automaps[$index][] = $fieldName;
                }
            }
        }

        return $automaps;
    }

    /**
     * Build a full result array with all qualifiers.
     */
    protected function buildResult(string $field, array $matches, array $datatypes, bool $includePropertyId): array
    {
        $result = [
            'field' => $field ?: null,
            'datatype' => $this->normalizeDatatypes($datatypes),
            'language' => empty($matches['language']) ? null : trim($matches['language']),
            'is_public' => empty($matches['visibility']) ? null : trim($matches['visibility']),
            'pattern' => empty($matches['pattern']) ? null : trim($matches['pattern']),
        ];

        if ($includePropertyId) {
            $result['property_id'] = $field ? $this->easyMeta->propertyId($field) : null;
        }

        return $this->processPattern($result);
    }

    /**
     * Process pattern to extract raw, replace, and twig components.
     */
    protected function processPattern(array $result): array
    {
        if (empty($result['pattern'])) {
            return $result;
        }

        $pattern = $result['pattern'];

        // Check for quoted raw value.
        $isQuoted = (mb_substr($pattern, 0, 1) === '"' && mb_substr($pattern, -1) === '"')
            || (mb_substr($pattern, 0, 1) === "'" && mb_substr($pattern, -1) === "'");

        if ($isQuoted) {
            $result['raw'] = trim(mb_substr($pattern, 1, -1));
            $result['pattern'] = null;
            return $result;
        }

        // Special patterns.
        $exceptions = ['{{ value }}', '{{ label }}', '{{ list }}'];
        if (in_array($pattern, $exceptions)) {
            $result['replace'][] = $pattern;
            return $result;
        }

        // Extract replacements ({{path}}) and twig filters.
        if (preg_match_all('~\{\{( value | label | list |\S+?|\S.*?\S)\}\}~', $pattern, $matches) !== false) {
            $result['replace'] = empty($matches[0]) ? [] : array_values(array_unique($matches[0]));
        }

        // Extract twig patterns ({{ ... }}).
        if (preg_match_all('~\{\{ ([^{}]+) \}\}~', $pattern, $matches) !== false) {
            $result['twig'] = empty($matches[0]) ? [] : array_unique($matches[0]);
            $result['twig'] = array_values(array_diff($result['twig'], $exceptions));
        }

        return $result;
    }

    /**
     * Extract datatypes from regex matches.
     */
    protected function extractDatatypes(array $matches): array
    {
        if (empty($matches['args']) || empty($matches['datatype'])) {
            return [];
        }

        $datatypeMatches = [];
        preg_match_all(self::PATTERN_DATATYPES, $matches['args'], $datatypeMatches, PREG_SET_ORDER);

        return array_column($datatypeMatches, 'datatype');
    }

    /**
     * Normalize datatype names and resolve custom vocab labels.
     */
    protected function normalizeDatatypes(array $datatypes): array
    {
        if (empty($datatypes)) {
            return [];
        }

        $result = [];
        foreach ($datatypes as $datatype) {
            // Resolve custom vocab labels.
            if (strpos($datatype, 'customvocab:') === 0) {
                $datatype = $this->resolveCustomVocabDatatype($datatype);
            }

            // Normalize via EasyMeta.
            $normalized = $this->easyMeta->dataTypeName($datatype);
            if ($normalized !== null) {
                $result[] = $normalized;
            }
        }

        return array_values(array_unique(array_filter($result)));
    }

    /**
     * Resolve custom vocab datatype with label to id.
     *
     * Converts "customvocab:'My List'" to "customvocab:123".
     */
    protected function resolveCustomVocabDatatype(string $datatype): string
    {
        $suffix = substr($datatype, 12);

        // Already an id.
        if (is_numeric($suffix)) {
            return $datatype;
        }

        // Extract label from quotes.
        if ((mb_substr($suffix, 0, 1) === '"' && mb_substr($suffix, -1) === '"')
            || (mb_substr($suffix, 0, 1) === "'" && mb_substr($suffix, -1) === "'")
        ) {
            $label = mb_substr($suffix, 1, -1);
        } else {
            $label = $suffix;
        }

        // Lookup custom vocab id by label.
        if ($this->customVocabLabels === null) {
            $this->loadCustomVocabLabels();
        }

        $id = $this->customVocabLabels[$label] ?? null;
        if ($id !== null) {
            return 'customvocab:' . $id;
        }

        // Return original if not found.
        return $datatype;
    }

    /**
     * Load custom vocab labels to ids mapping.
     */
    protected function loadCustomVocabLabels(): void
    {
        $this->customVocabLabels = [];

        try {
            $customVocabs = $this->api->search('custom_vocabs', [], ['responseContent' => 'resource'])->getContent();
            foreach ($customVocabs as $customVocab) {
                $this->customVocabLabels[$customVocab->getLabel()] = $customVocab->getId();
            }
        } catch (\Exception $e) {
            // Custom vocab module not installed.
        }
    }

    /**
     * Prepare property lookup lists.
     */
    protected function preparePropertyLists(bool $checkNamesAlone): array
    {
        if ($this->propertyLists === null) {
            $this->loadPropertyLists();
        }

        $lists = [];

        // Term names (dcterms:title).
        $lists['names'] = array_combine(
            array_keys($this->propertyLists['names']),
            array_keys($this->propertyLists['names'])
        );
        $lists['lower_names'] = array_map('mb_strtolower', $lists['names']);

        // Labels (Dublin Core : Title).
        $labelNames = array_keys($this->propertyLists['names']);
        $labelLabels = \SplFixedArray::fromArray(array_keys($this->propertyLists['labels']));
        $labelLabels->setSize(count($labelNames));
        $lists['labels'] = array_combine($labelNames, array_map('strval', $labelLabels->toArray()));
        $lists['lower_labels'] = array_filter(array_map('mb_strtolower', $lists['labels']));

        // Local names (title without prefix).
        if ($checkNamesAlone) {
            $lists['local_names'] = array_map(function ($v) {
                $w = explode(':', (string) $v);
                return end($w);
            }, $lists['names']);
            $lists['lower_local_names'] = array_map('mb_strtolower', $lists['local_names']);

            $lists['local_labels'] = array_map(function ($v) {
                $w = explode(':', (string) $v);
                return end($w);
            }, $lists['labels']);
            $lists['lower_local_labels'] = array_map('mb_strtolower', $lists['local_labels']);
        }

        return $lists;
    }

    /**
     * Load property term names and labels.
     */
    protected function loadPropertyLists(): void
    {
        $this->propertyLists = ['names' => [], 'labels' => []];

        $vocabularies = $this->api->search('vocabularies')->getContent();

        foreach ($vocabularies as $vocabulary) {
            $properties = $vocabulary->properties();
            if (empty($properties)) {
                continue;
            }

            foreach ($properties as $property) {
                $term = $property->term();
                $this->propertyLists['names'][$term] = $term;

                $label = $vocabulary->label() . ':' . $property->label();
                if (isset($this->propertyLists['labels'][$label])) {
                    $label .= ' (#' . $property->id() . ')';
                }
                $this->propertyLists['labels'][$label] = $term;
            }
        }

        // Add "dc:" prefix for "dcterms:" (common shorthand).
        if (isset($vocabularies[0])) {
            $dcVocab = $vocabularies[0];
            foreach ($dcVocab->properties() as $property) {
                $term = $property->term();
                $dcTerm = 'dc:' . substr($term, 8);
                $this->propertyLists['names'][$dcTerm] = $term;
            }
        }
    }

    /**
     * Prepare automap lists from user-defined map.
     */
    protected function prepareAutomapLists(array $map): array
    {
        if (empty($map)) {
            return [];
        }

        // Add mapped values as keys too.
        $map += array_combine($map, $map);

        $lists = [];
        $lists['base'] = array_combine(array_keys($map), array_keys($map));
        $lists['lower_base'] = array_map('mb_strtolower', $lists['base']);

        if ($lists['base'] === $lists['lower_base']) {
            unset($lists['base']);
        }

        return $lists;
    }

    /**
     * Find a field in lookup lists.
     */
    protected function findInLists(string $field, string $lowerField, array $lists): ?string
    {
        foreach ($lists as $listName => $list) {
            $toSearch = strpos($listName, 'lower_') === 0 ? $lowerField : $field;
            $found = array_search($toSearch, $list, true);
            if ($found !== false) {
                return $found;
            }
        }

        return null;
    }

    /**
     * Check for and warn about old pattern format.
     */
    protected function checkOldPattern(?string $field): bool
    {
        if (!$field || !preg_match(self::OLD_PATTERN_CHECK, $field)) {
            return false;
        }

        if ($this->logger) {
            $this->logger->warn(
                'The field pattern "{field}" uses old format. Update by replacing ";" with "^^", removing spaces after "^^", "@", "§", and wrapping custom vocab labels with quotes.',
                ['field' => $field]
            );
        }

        return true;
    }

    /**
     * Clean whitespace and normalize colons.
     */
    protected function cleanStrings(array $strings): array
    {
        return array_map(function ($string) {
            $string = preg_replace('/[\s\h\v[:blank:][:space:]]+/u', ' ', (string) $string);
            return preg_replace('~\s*:\s*~', ':', trim($string));
        }, $strings);
    }
}
