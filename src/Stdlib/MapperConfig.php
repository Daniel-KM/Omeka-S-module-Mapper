<?php declare(strict_types=1);

/**
 * MapperConfig - Parses and normalizes mapping configurations.
 *
 * Supports ini-style text, xml, and array formats for mapping definitions.
 *
 * @copyright Daniel Berthereau, 2017-2025
 * @license http://www.cecill.info/licences/Licence_CeCILL_V2.1-en.txt
 */

namespace Mapper\Stdlib;

use Common\Stdlib\EasyMeta;
use Laminas\Log\Logger;
use Omeka\Api\Manager as ApiManager;

class MapperConfig
{
    /**
     * @var \Omeka\Api\Manager
     */
    protected $api;

    /**
     * @var \Common\Stdlib\EasyMeta
     */
    protected $easyMeta;

    /**
     * @var \Laminas\Log\Logger
     */
    protected $logger;

    /**
     * Base path for user files.
     *
     * @var string
     */
    protected $basePath;

    /**
     * Cache for parsed mappings.
     *
     * @var array
     */
    protected $mappings = [];

    /**
     * Current mapping name.
     *
     * @var string|null
     */
    protected $currentName;

    /**
     * Empty mapping template.
     */
    protected const EMPTY_MAPPING = [
        'info' => [
            // Label is the only required data.
            'label' => null,
            'from' => null,
            'to' => null,
            // xpath, jsonpath, jsdot, jmespath or index.
            // Index is used for simple array, like a spreadsheet header.
            'querier' => null,
            // Used by ini for json. In xml, include can be used.
            'mapper' => null,
            'example' => null,
        ],
        'params' => [],
        // TODO Merge default and maps by a setting in the maps.
        'default' => [],
        'maps' => [],
        // List of tables (associative arrays) indexed by their name.
        'tables' => [],
        'has_error' => false,
    ];

    public function __construct(
        ApiManager $api,
        EasyMeta $easyMeta,
        Logger $logger,
        string $basePath
    ) {
        $this->api = $api;
        $this->easyMeta = $easyMeta;
        $this->logger = $logger;
        $this->basePath = $basePath;
    }

    /**
     * Load and parse a mapping configuration.
     *
     * It can be used as headers of a spreadsheet, or in an import mapping, or
     * to extract metadata from files json, or xml files, or for any file.
     * It contains a list of mappings between source data and destination data.
     *
     * A mapping contains four sections:
     * - info: label, base mapper if any, querier to use, example of source;
     * - params: what to import (metadata or files) and constants;
     * - default: default maps when creating resources, for example the owner;
     * - maps: the maps to use for the import.
     * Some other sections are available (passed options, tables, has_error).
     *
     * Each map is based on a source and a destination, eventually modified.
     * So the internal representation of the maps is:
     *
     * ```php
     * [
     *     [
     *          'from' => [
     *              'querier' => 'xpath',
     *              'path' => '/record/datafield[@tag='200'][@ind1='1']/subfield[@code='a']',
     *          ],
     *          'to' => [
     *              'field' => 'dcterms:title',
     *              'property_id' => 1,
     *              'datatype' => [
     *                  'literal',
     *              ],
     *              'language' => 'fra',
     *              'is_public' => false,
     *          ],
     *          'mod' => [
     *              'raw' => null,
     *              'val' => null,
     *              'prepend' => 'Title is: ',
     *              'pattern' => 'pattern for {{ value|trim }} with {{/source/record/data}}',
     *              'append' => null,
     *              'replace' => [
     *                  '{{/source/record/data}}',
     *              ],
     *              'twig' => [
     *                  '{{ value|trim }}',
     *              ],
     *          ],
     *      ],
     * ]
     * ```
     *
     * Such a mapping can be created from an array, a list of fields (for
     * example headers of a spreadsheet), an ini-like or xml file, or stored in
     * database as ini-like or xml. It can be based on another mapping.
     *
     * For example, the ini map for the map above is (except prepend/append,
     * that should be included in pattern):
     *
     * ```
     * /record/datafield[@tag='200'][@ind1='1']/subfield[@code='a'] = dcterms:title @fra ^^literal §public ~ pattern for {{ value|trim }} with {{/source/record/data}}
     * ```
     *
     * The same mapping for the xml is:
     *
     * ```xml
     * <mapping>
     *     <map>
     *         <from xpath="/record/datafield[@tag='200']/subfield[@code='a']"/>
     *         <to field="dcterms:title" language="fra" datatype="literal" visibility="public"/>
     *         <mod prepend="Title is: " pattern="pattern for {{ value|trim }} with {{/source/record/data}}"/>
     *     </map>
     * </mapping>
     * ```
     *
     * The default querier is to take the value provided by the reader.
     *
     * "mod/raw" is the raw value set in all cases, even without source value.
     * "mod/val" is the raw value set only when "from" is a value, that may be
     * extracted with a path.
     * "mod/prepend" and "mod/append" are used only when the pattern returns a
     * value with at least one replacement. So a pattern without replacements
     * (simple or twig) should be a "val".
     *
     * Note that a ini mapping has a static querier (the same for all maps), but
     * a xml mapping has a dynamic querier (set as attribute of element "from").
     *
     * For more information and formats: see {@link https://gitlab.com/Daniel-KM/Omeka-S-module-BulkImport}.
     *
     * The mapping should contain some infos about the mapping itself in section
     * "info", some params if needed, and tables or reference to tables.
     *
     * @todo Merge default and maps by a setting in the maps.
     *
     * The mapping is not overridable once built, even if options are different.
     * If needed, another name should be used.
     *
     * @todo Remove options and use mapping only, that may contain info, params and tables.
     *
     * @param string $mappingName The name of the mapping to get or to set.
     * @param array|string|null $mappingOrMappingReference Full mapping or
     *   reference file or name or array.
     * @param array $options Parsing options.
     * // To be removed: part of the main mapping.
     * - section_types (array): way to manage the sections of the mapping.
     * - tables (array): tables to use for some maps with conversion.
     * // To be removed: Only used by spreadsheet.
     * - infos
     * - label
     * - params
     * - default
     * // To be removed: For automap a single string.
     * - check_field (bool)
     * - output_full_matches (bool)
     * - output_property_id (bool)
     * // To be removed?
     * - resource_name (string) : same as info[to]
     * - field_types (array) : Field types of a resource from the processor.
     * // Tempo fix to be removed with new manual form.
     * - is_single_manual (bool): should prepare mapping with full data from keys.
     * @return self|array Returns self for chaining, or the parsed mapping if $name is set.
     */
    public function __invoke(?string $name = null, $mappingOrRef = null, array $options = [])
    {
        if ($name === null && $mappingOrRef === null) {
            return $this;
        }

        // Generate name from content if not provided.
        if ($name === null && $mappingOrRef !== null) {
            $name = $this->generateNameFromReference($mappingOrRef);
        }

        $this->currentName = $name;

        if ($mappingOrRef === null) {
            return $this->getMapping($name);
        }

        // Only parse if not already cached.
        if (!isset($this->mappings[$name])) {
            $this->parseAndStore($mappingOrRef, $options);
        }

        return $this->getMapping($name);
    }

    /**
     * Get a parsed mapping by name.
     *
     * @todo Recusively merge mappings.
     */
    public function getMapping(?string $name = null): ?array
    {
        return $this->mappings[$name ?? $this->currentName] ?? null;
    }

    /**
     * Check if a mapping has errors.
     */
    public function hasError(?string $name = null): bool
    {
        $mapping = $this->getMapping($name);
        return $mapping === null || !empty($mapping['has_error']);
    }

    /**
     * Get a section from the current mapping.
     */
    public function getSection(string $section): array
    {
        $mapping = $this->getMapping();
        return $mapping[$section] ?? [];
    }

    /**
     * Get a setting from a section.
     */
    public function getSectionSetting(string $section, string $name, $default = null)
    {
        $mapping = $this->getMapping();
        if (!$mapping || !isset($mapping[$section])) {
            return $default;
        }

        // For maps sections, search by 'from' path.
        if (in_array($section, ['default', 'maps'])) {
            foreach ($mapping[$section] as $map) {
                if ($name === ($map['from']['path'] ?? null)) {
                    return $map;
                }
            }
            return $default;
        }

        return $mapping[$section][$name] ?? $default;
    }

    /**
     * Get a sub-setting from a section.
     */
    public function getSectionSettingSub(string $section, string $name, string $subName, $default = null)
    {
        $mapping = $this->getMapping();
        return $mapping[$section][$name][$subName] ?? $default;
    }

    /**
     * Get the current mapping name.
     */
    public function getCurrentName(): ?string
    {
        return $this->currentName;
    }

    /**
     * Normalize a list of maps.
     */
    public function normalizeMaps(array $maps, array $options = []): array
    {
        if (empty($maps)) {
            return [];
        }

        $result = [];
        foreach ($maps as $index => $map) {
            $options['index'] = $index;

            if (empty($map)) {
                $result[] = ['from' => [], 'to' => [], 'mod' => []];
                continue;
            }

            $normalized = $this->normalizeMap($map, $options);

            // A single map can expand to multiple maps.
            if (!empty($normalized) && is_numeric(key($normalized))) {
                foreach ($normalized as $item) {
                    $result[] = $item;
                }
            } else {
                $result[] = $normalized;
            }
        }

        return $result;
    }

    /**
     * Normalize a single map from various input formats.
     */
    public function normalizeMap($map, array $options = []): array
    {
        if (empty($map)) {
            return ['from' => [], 'to' => [], 'mod' => []];
        }

        if (is_string($map)) {
            return $this->normalizeMapFromString($map, $options);
        }

        if (is_array($map)) {
            // Handle array of maps recursively.
            if (is_numeric(key($map))) {
                return array_map(fn($m) => $this->normalizeMap($m, $options), $map);
            }
            return $this->normalizeMapFromArray($map, $options);
        }

        return ['from' => [], 'to' => [], 'mod' => [], 'has_error' => true];
    }

    /**
     * Parse and store a mapping.
     */
    protected function parseAndStore($mappingOrRef, array $options): void
    {
        $mapping = null;

        if (empty($mappingOrRef)) {
            $mapping = self::EMPTY_MAPPING;
        } elseif (is_array($mappingOrRef)) {
            $mapping = isset($mappingOrRef['info'])
                ? $this->parseNormalizedArray($mappingOrRef, $options)
                : $this->parseMapList($mappingOrRef, $options);
        } else {
            $content = $this->loadMappingContent((string) $mappingOrRef);
            if ($content) {
                $mapping = $this->parseContent($content, $options);
            }
        }

        if (!$mapping) {
            $mapping = self::EMPTY_MAPPING;
            $mapping['has_error'] = true;
            $this->logger->err('Mapping "{name}" could not be loaded.', ['name' => $this->currentName]);
        }

        $this->mappings[$this->currentName] = $mapping;
    }

    /**
     * Generate the default unique name from a mapping reference.
     *
     * Use the filename for file-based references or md5 hash for array content.
     */
    protected function generateNameFromReference($mappingOrRef): string
    {
        // For string references, use the reference itself as name.
        // This covers file references like "module:xml/idref_personne.xml"
        // and database references like "mapping:5".
        if (is_string($mappingOrRef)) {
            return $mappingOrRef;
        }

        // For array content, fall back to md5 hash.
        return md5(serialize($mappingOrRef));
    }

    /**
     * Load mapping content from a reference (file path, database ID, etc.).
     */
    protected function loadMappingContent(string $reference): ?string
    {
        // Database reference: "mapping:5"
        if (mb_substr($reference, 0, 8) === 'mapping:') {
            $id = (int) mb_substr($reference, 8);
            try {
                $mapper = $this->api->read('mappers', ['id' => $id])->getContent();
                return $mapper->mapping();
            } catch (\Exception $e) {
                return null;
            }
        }

        // File reference with prefix.
        $prefixes = [
            'user' => $this->basePath . '/mapping/',
            'module' => dirname(__DIR__, 2) . '/data/mapping/',
        ];

        if (strpos($reference, ':') !== false) {
            $prefix = strtok($reference, ':');
            $file = mb_substr($reference, strlen($prefix) + 1);
        } else {
            // Default to module prefix.
            $prefix = 'module';
            $file = $reference;
        }

        if (!isset($prefixes[$prefix])) {
            return null;
        }

        $filepath = $prefixes[$prefix] . $file;
        if (file_exists($filepath) && is_readable($filepath)) {
            return trim((string) file_get_contents($filepath));
        }

        return null;
    }

    /**
     * Parse content string (ini or xml).
     */
    protected function parseContent(string $content, array $options): array
    {
        $content = trim($content);
        if (!strlen($content)) {
            return self::EMPTY_MAPPING;
        }

        return mb_substr($content, 0, 1) === '<'
            ? $this->parseXml($content, $options)
            : $this->parseIni($content, $options);
    }

    /**
     * Parse ini-style mapping content.
     */
    protected function parseIni(string $content, array $options): array
    {
        $mapping = self::EMPTY_MAPPING;
        $mapping['info']['label'] = $this->currentName;

        $lines = array_filter(array_map('trim', explode("\n", $content)));
        $section = null;

        foreach ($lines as $line) {
            // Skip comments.
            if (mb_substr($line, 0, 1) === ';') {
                continue;
            }

            // Section header.
            if (mb_substr($line, 0, 1) === '[' && mb_substr($line, -1) === ']') {
                $section = trim(mb_substr($line, 1, -1));
                if (!in_array($section, ['info', 'params', 'default', 'maps', 'tables'])) {
                    $section = null;
                }
                continue;
            }

            if (!$section) {
                continue;
            }

            // Parse key = value.
            $map = $this->parseIniLine($line, $section, $options);
            if ($map === null) {
                continue;
            }

            // For info/params/tables, store as key-value.
            if (in_array($section, ['info', 'params'])) {
                if (isset($map['from']) && is_scalar($map['from'])) {
                    $mapping[$section][$map['from']] = $map['to'];
                }
            } elseif ($section === 'tables') {
                // Tables have nested structure.
                if (isset($map['from']) && isset($map['to'])) {
                    $mapping['tables'][$map['from']][$map['key'] ?? ''] = $map['to'];
                }
            } else {
                // For default/maps, store as array of maps.
                $mapping[$section][] = $map;
            }
        }

        return $mapping;
    }

    /**
     * Parse a single ini line.
     */
    protected function parseIniLine(string $line, string $section, array $options): ?array
    {
        // Find the = separator (handle patterns with = after ~).
        $tildePos = mb_strpos($line, '~');
        $equalsPos = $tildePos !== false
            ? mb_strpos(mb_substr($line, 0, $tildePos), '=')
            : mb_strrpos($line, '=');

        if ($equalsPos === false) {
            return null;
        }

        $from = trim(mb_substr($line, 0, $equalsPos));
        $to = trim(mb_substr($line, $equalsPos + 1));

        if (!strlen($from) && !strlen($to)) {
            return null;
        }

        // Simple key-value for info/params.
        if (in_array($section, ['info', 'params'])) {
            // Remove quotes if present.
            if ((mb_substr($to, 0, 1) === '"' && mb_substr($to, -1) === '"')
                || (mb_substr($to, 0, 1) === "'" && mb_substr($to, -1) === "'")
            ) {
                $to = mb_substr($to, 1, -1);
            }
            return ['from' => $from, 'to' => $to];
        }

        // For default/maps, normalize the full map.
        $options['section'] = $section;
        return $this->normalizeMapFromIniParts($from, $to, $options);
    }

    /**
     * Normalize a map from ini from/to parts.
     */
    protected function normalizeMapFromIniParts(string $from, string $to, array $options): array
    {
        $map = ['from' => [], 'to' => [], 'mod' => []];

        // Check if "to" is a raw value (quoted).
        $isRaw = (mb_substr($to, 0, 1) === '"' && mb_substr($to, -1) === '"')
            || (mb_substr($to, 0, 1) === "'" && mb_substr($to, -1) === "'");

        if ($isRaw) {
            $map['mod']['raw'] = mb_substr($to, 1, -1);
            $map['to'] = $this->parseFieldSpec($from);
            return $map;
        }

        // Set source path if not empty or tilde.
        if ($from !== '~' && $from !== '') {
            $map['from']['path'] = $from;
        }

        // Parse destination with optional pattern.
        $tildePos = mb_strpos($to, '~');
        if ($tildePos !== false) {
            $fieldPart = trim(mb_substr($to, 0, $tildePos));
            $patternPart = trim(mb_substr($to, $tildePos + 1));

            $map['to'] = $this->parseFieldSpec($fieldPart);
            $map['mod'] = $this->parsePattern($patternPart);
        } else {
            $map['to'] = $this->parseFieldSpec($to);
        }

        return $map;
    }

    /**
     * Parse a field specification string.
     *
     * Format: "dcterms:title ^^datatype @language §visibility"
     */
    protected function parseFieldSpec(string $spec): array
    {
        $result = [
            'field' => null,
            'datatype' => [],
            'language' => null,
            'is_public' => null,
        ];

        $spec = trim($spec);
        if (!$spec) {
            return $result;
        }

        // Extract pattern (~ ...) if present at the end.
        $tildePos = mb_strpos($spec, '~');
        if ($tildePos !== false) {
            $spec = trim(mb_substr($spec, 0, $tildePos));
        }

        // Parse parts separated by spaces.
        $parts = preg_split('/\s+/', $spec);
        foreach ($parts as $part) {
            if (mb_substr($part, 0, 2) === '^^') {
                // Datatype.
                $result['datatype'][] = mb_substr($part, 2);
            } elseif (mb_substr($part, 0, 1) === '@') {
                // Language.
                $result['language'] = mb_substr($part, 1);
            } elseif (mb_substr($part, 0, 1) === '§') {
                // Visibility.
                $visibility = mb_strtolower(mb_substr($part, 1));
                $result['is_public'] = $visibility !== 'private';
            } elseif ($result['field'] === null) {
                // First non-prefixed part is the field.
                $result['field'] = $part;
            }
        }

        // Add property_id if field is a property term.
        if ($result['field']) {
            $propertyId = $this->easyMeta->propertyId($result['field']);
            if ($propertyId) {
                $result['property_id'] = $propertyId;
            }
        }

        return $result;
    }

    /**
     * Parse a pattern string for replacements and twig filters.
     */
    protected function parsePattern(string $pattern): array
    {
        $result = [
            'pattern' => $pattern,
            'replace' => [],
            'twig' => [],
            'twig_has_replace' => [],
        ];

        if (!strlen($pattern)) {
            return $result;
        }

        $matches = [];

        // Find all {{ ... }} expressions.
        preg_match_all('/\{\{[^}]+\}\}/', $pattern, $matches);
        foreach ($matches[0] as $match) {
            // Check if it's a twig filter (contains |).
            if (mb_strpos($match, '|') !== false) {
                $result['twig'][] = $match;
                // Check if twig expression has replacements inside.
                $result['twig_has_replace'][] = preg_match('/\{\{[^|]+\}\}/', $match);
            } else {
                $result['replace'][] = $match;
            }
        }

        $simpleMatches = [];

        // Also find simple {path} replacements.
        preg_match_all('/\{[^{}]+\}/', $pattern, $simpleMatches);
        foreach ($simpleMatches[0] as $match) {
            if (!in_array($match, $result['replace'])) {
                $result['replace'][] = $match;
            }
        }

        return $result;
    }

    /**
     * Parse xml mapping content.
     */
    protected function parseXml(string $content, array $options): array
    {
        $mapping = self::EMPTY_MAPPING;
        $mapping['info']['label'] = $this->currentName;

        // The mapping is always a small file (less than some megabytes), so it
        // can be managed directly with SimpleXml.

        try {
            $xml = new \SimpleXMLElement($content);
        } catch (\Exception $e) {
            $mapping['has_error'] = true;
            $this->logger->err('Invalid xml mapping: ' . $e->getMessage());
            return $mapping;
        }

        // Parse info section.
        if (isset($xml->info)) {
            foreach ($xml->info->children() as $element) {
                $mapping['info'][$element->getName()] = (string) $element;
            }
        }

        // Parse params section.
        if (isset($xml->params)) {
            foreach ($xml->params->children() as $element) {
                $mapping['params'][$element->getName()] = (string) $element;
            }
        }

        // Parse maps.
        foreach ($xml->map as $mapElement) {
            $hasXpath = !empty($mapElement->from['xpath']);
            $section = $hasXpath ? 'maps' : 'default';
            $mapping[$section][] = $this->parseXmlMap($mapElement);
        }

        // Parse tables.
        foreach ($xml->table as $table) {
            $code = (string) $table['code'];
            if (!$code || !isset($table->list)) {
                continue;
            }
            foreach ($table->list->term as $term) {
                $termCode = (string) $term['code'];
                if (strlen($termCode)) {
                    $mapping['tables'][$code][$termCode] = (string) $term;
                }
            }
        }

        return $mapping;
    }

    /**
     * Parse a single xml map element.
     */
    protected function parseXmlMap(\SimpleXMLElement $element): array
    {
        $map = ['from' => [], 'to' => [], 'mod' => []];

        // Parse from element.
        if (isset($element->from)) {
            $from = $element->from;
            if (!empty($from['xpath'])) {
                $map['from']['querier'] = 'xpath';
                $map['from']['path'] = (string) $from['xpath'];
            } elseif (!empty($from['jsdot'])) {
                $map['from']['querier'] = 'jsdot';
                $map['from']['path'] = (string) $from['jsdot'];
            } elseif (!empty($from['jsonpath'])) {
                $map['from']['querier'] = 'jsonpath';
                $map['from']['path'] = (string) $from['jsonpath'];
            } elseif (!empty($from['jmespath'])) {
                $map['from']['querier'] = 'jmespath';
                $map['from']['path'] = (string) $from['jmespath'];
            }
        }

        // Parse to element.
        if (isset($element->to)) {
            $to = $element->to;
            $map['to']['field'] = (string) ($to['field'] ?? '');

            if (!empty($to['datatype'])) {
                $map['to']['datatype'] = explode(' ', (string) $to['datatype']);
            }
            if (!empty($to['language'])) {
                $map['to']['language'] = (string) $to['language'];
            }
            if (isset($to['visibility'])) {
                $map['to']['is_public'] = (string) $to['visibility'] !== 'private';
            }

            // Add property_id.
            if ($map['to']['field']) {
                $propertyId = $this->easyMeta->propertyId($map['to']['field']);
                if ($propertyId) {
                    $map['to']['property_id'] = $propertyId;
                }
            }
        }

        // Parse mod element.
        if (isset($element->mod)) {
            $mod = $element->mod;
            if (!empty($mod['raw'])) {
                $map['mod']['raw'] = (string) $mod['raw'];
            }
            if (!empty($mod['val'])) {
                $map['mod']['val'] = (string) $mod['val'];
            }
            if (!empty($mod['prepend'])) {
                $map['mod']['prepend'] = (string) $mod['prepend'];
            }
            if (!empty($mod['pattern'])) {
                $patternMod = $this->parsePattern((string) $mod['pattern']);
                $map['mod'] = array_merge($map['mod'], $patternMod);
            }
            if (!empty($mod['append'])) {
                $map['mod']['append'] = (string) $mod['append'];
            }
        }

        return $map;
    }

    /**
     * Parse a pre-normalized array mapping.
     */
    protected function parseNormalizedArray(array $input, array $options): array
    {
        $mapping = self::EMPTY_MAPPING;

        if (!isset($input['info']) || !is_array($input['info'])) {
            $mapping['has_error'] = true;
            return $mapping;
        }

        // Copy info with validation.
        foreach (['label', 'from', 'to', 'querier', 'mapper', 'example'] as $key) {
            if (!empty($input['info'][$key]) && is_string($input['info'][$key])) {
                $mapping['info'][$key] = $input['info'][$key];
            }
        }
        $mapping['info']['label'] = $mapping['info']['label'] ?? $this->currentName;

        // Copy params.
        if (isset($input['params']) && is_array($input['params'])) {
            $mapping['params'] = $input['params'];
        }

        // Copy tables.
        if (isset($input['tables']) && is_array($input['tables'])) {
            $mapping['tables'] = $input['tables'];
        }

        // Normalize maps.
        foreach (['default', 'maps'] as $section) {
            if (isset($input[$section]) && is_array($input[$section])) {
                $options['section'] = $section;
                $mapping[$section] = $this->normalizeMaps($input[$section], $options);
            }
        }

        return $mapping;
    }

    /**
     * Parse a simple list of maps (e.g., spreadsheet headers).
     */
    protected function parseMapList(array $maps, array $options): array
    {
        $mapping = self::EMPTY_MAPPING;
        $mapping['info']['label'] = $options['label'] ?? $this->currentName;
        $mapping['info']['querier'] = 'index';

        $options['section'] = 'maps';
        $mapping['maps'] = $this->normalizeMaps($maps, $options);

        return $mapping;
    }

    /**
     * Convert a string into a map.
     *
     * The string may be:
     * - a simple field: a spreadsheet header like `dcterms:title`
     * - a default value: `dcterms:license = "Public domain"`
     * - a mapping: `License = dcterms:license ^^literal ~ "Public domain"`
     * - a complex mapping: `~ = dcterms:spatial ^^geography:coordinates ~ {lat}/{lng}`
     */
    protected function normalizeMapFromString(string $map, array $options): array
    {
        $map = trim($map);
        if (!$map) {
            return ['from' => [], 'to' => [], 'mod' => []];
        }

        // Xml string.
        if (mb_substr($map, 0, 1) === '<') {
            try {
                $xml = new \SimpleXMLElement($map);
                return $this->parseXmlMap($xml);
            } catch (\Exception $e) {
                return ['from' => [], 'to' => [], 'mod' => [], 'has_error' => true];
            }
        }

        // Ini-style string.
        $equalsPos = mb_strpos($map, '=');
        if ($equalsPos === false) {
            // Simple field specification (no source path).
            return [
                'from' => isset($options['index']) ? ['index' => $options['index']] : [],
                'to' => $this->parseFieldSpec($map),
                'mod' => [],
            ];
        }

        $from = trim(mb_substr($map, 0, $equalsPos));
        $to = trim(mb_substr($map, $equalsPos + 1));

        return $this->normalizeMapFromIniParts($from, $to, $options);
    }

    /**
     * Normalize a map from an array.
     */
    protected function normalizeMapFromArray(array $map, array $options): array
    {
        $result = ['from' => [], 'to' => [], 'mod' => []];

        // Handle 'from' section.
        if (isset($map['from'])) {
            if (is_string($map['from'])) {
                $result['from']['path'] = $map['from'];
            } elseif (is_array($map['from'])) {
                $result['from'] = $map['from'];
            }
        }

        // Handle 'to' section.
        if (isset($map['to'])) {
            if (is_string($map['to'])) {
                $result['to'] = $this->parseFieldSpec($map['to']);
            } elseif (is_array($map['to'])) {
                $result['to'] = $map['to'];
                // Ensure property_id is set.
                if (!empty($result['to']['field']) && empty($result['to']['property_id'])) {
                    $propertyId = $this->easyMeta->propertyId($result['to']['field']);
                    if ($propertyId) {
                        $result['to']['property_id'] = $propertyId;
                    }
                }
            }
        }

        // Handle 'mod' section.
        if (isset($map['mod'])) {
            if (is_string($map['mod'])) {
                $result['mod'] = $this->parsePattern($map['mod']);
            } elseif (is_array($map['mod'])) {
                $result['mod'] = $map['mod'];
            }
        }

        // Handle index for spreadsheet-style maps.
        if (isset($options['index']) && empty($result['from']['path'])) {
            $result['from']['index'] = $options['index'];
        }

        return $result;
    }
}
