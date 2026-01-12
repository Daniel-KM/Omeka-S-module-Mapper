<?php declare(strict_types=1);

/**
 * MapperConfig - Parses and normalizes mapping configurations.
 *
 * Supports ini-style text, xml, and array formats for mapping definitions.
 *
 * ## Mapping Structure
 *
 * A mapping configuration has two levels of "sections":
 *
 * ### Level 1: Mapping Sections (top-level structure)
 *
 * ```
 * [info]      → Metadata: label, from, to, querier, mapper, example
 * [params]    → Configuration parameters (key-value pairs)
 * [default]   → Default maps applied to all resources
 * [maps]      → Actual mapping rules (array of maps)
 * [tables]    → Lookup tables for value conversions
 * ```
 *
 * ### Level 2: Map Parts (structure of each map in 'default' or 'maps')
 *
 * ```
 * [from]  → Source: where data comes from (path, querier, index)
 * [to]    → Target: where data goes (field, property_id, datatype, language, is_public)
 * [mod]   → Modifiers: how to transform data (raw, val, pattern, prepend, append)
 * ```
 *
 * ### Example INI format:
 *
 * ```ini
 * [info]
 * label = "My Mapping"
 * querier = xpath
 *
 * [maps]
 * //title = dcterms:title ^^literal @fra
 * //creator = dcterms:creator ~ {{ value|trim }}
 * ```
 *
 * @copyright Daniel Berthereau, 2017-2026
 * @license http://www.cecill.info/licences/Licence_CeCILL_V2.1-en.txt
 */

namespace Mapper\Stdlib;

use Common\Stdlib\EasyMeta;
use Laminas\Log\Logger;
use Omeka\Api\Manager as ApiManager;

class MapperConfig
{
    // =========================================================================
    // Mapping Section Constants (Level 1: top-level sections of a mapping)
    // =========================================================================

    /**
     * Metadata section: label, from, to, querier, mapper, example.
     * Stores key-value pairs describing the mapping.
     */
    public const SECTION_INFO = 'info';

    /**
     * Parameters section: configuration values for the mapping.
     * Stores key-value pairs for custom settings.
     */
    public const SECTION_PARAMS = 'params';

    /**
     * Default maps section: maps applied when creating any resource.
     * Contains an array of map definitions (without source paths).
     *
     * @deprecated Use maps section instead. Default maps are now detected
     *             automatically by the absence of a source path (from.path).
     *             This section is kept for backward compatibility and will
     *             be merged into maps during normalization.
     */
    public const SECTION_DEFAULT = 'default';

    /**
     * Maps section: the actual mapping rules.
     * Contains an array of map definitions. Maps without a source path
     * (from.path) are treated as "default" maps and always applied.
     */
    public const SECTION_MAPS = 'maps';

    /**
     * Tables section: lookup tables for value conversions.
     * Contains nested associative arrays indexed by table name.
     */
    public const SECTION_TABLES = 'tables';

    /**
     * All valid mapping section names.
     */
    public const MAPPING_SECTIONS = [
        self::SECTION_INFO,
        self::SECTION_PARAMS,
        self::SECTION_DEFAULT,
        self::SECTION_MAPS,
        self::SECTION_TABLES,
    ];

    /**
     * Sections that contain arrays of maps.
     */
    public const MAP_SECTIONS = [
        self::SECTION_DEFAULT,
        self::SECTION_MAPS,
    ];

    /**
     * Sections that contain key-value pairs.
     */
    public const KEYVALUE_SECTIONS = [
        self::SECTION_INFO,
        self::SECTION_PARAMS,
    ];

    // =========================================================================
    // Map Part Constants (Level 2: parts of each individual map)
    // =========================================================================

    /**
     * Source part: defines where data comes from.
     * Keys: path, querier, index.
     */
    public const MAP_FROM = 'from';

    /**
     * Target part: defines where data goes.
     * Keys: field, property_id, datatype, language, is_public.
     */
    public const MAP_TO = 'to';

    /**
     * Modifier part: defines how to transform data.
     *
     * Input keys: raw, pattern, prepend, append.
     * Computed keys (from PatternParser): replace, twig, twig_has_replace.
     *
     * Note: 'val' is accepted as input but normalized to 'raw'.
     */
    public const MAP_MOD = 'mod';

    /**
     * All map parts.
     */
    public const MAP_PARTS = [
        self::MAP_FROM,
        self::MAP_TO,
        self::MAP_MOD,
    ];

    // =========================================================================
    // Dependencies
    // =========================================================================

    /**
     * @var ApiManager
     */
    protected $api;

    /**
     * @var EasyMeta
     */
    protected $easyMeta;

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * Base path for user files.
     */
    protected string $basePath;

    /**
     * @var MapNormalizer
     */
    protected $mapNormalizer;

    /**
     * @var PatternParser
     */
    protected $patternParser;

    /**
     * Cache for parsed mappings.
     */
    protected array $mappings = [];

    /**
     * Current mapping name.
     */
    protected ?string $currentName = null;

    /**
     * Empty mapping template.
     *
     * Structure:
     * - info: Metadata about the mapping
     * - params: Configuration parameters
     * - default: Default maps (no source path)
     * - maps: Mapping rules (with source paths)
     * - tables: Lookup tables
     * - has_error: Error flag
     */
    protected const EMPTY_MAPPING = [
        self::SECTION_INFO => [
            'label' => null,
            'from' => null,
            'to' => null,
            'querier' => null,
            'mapper' => null,
            'example' => null,
        ],
        self::SECTION_PARAMS => [],
        self::SECTION_DEFAULT => [],
        self::SECTION_MAPS => [],
        self::SECTION_TABLES => [],
        'has_error' => false,
    ];

    public function __construct(
        ApiManager $api,
        EasyMeta $easyMeta,
        Logger $logger,
        string $basePath,
        MapNormalizer $mapNormalizer,
        PatternParser $patternParser
    ) {
        $this->api = $api;
        $this->easyMeta = $easyMeta;
        $this->logger = $logger;
        $this->basePath = $basePath;
        $this->mapNormalizer = $mapNormalizer;
        $this->patternParser = $patternParser;
    }

    /**
     * Load and parse a mapping configuration.
     *
     * @param string|null $name The name of the mapping.
     * @param array|string|null $mappingOrRef Full mapping or reference.
     * @param array $options Parsing options.
     * @return self|array Returns self for chaining, or the parsed mapping.
     */
    public function __invoke(?string $name = null, $mappingOrRef = null, array $options = [])
    {
        if ($name === null && $mappingOrRef === null) {
            return $this;
        }

        if ($name === null && $mappingOrRef !== null) {
            $name = $this->generateNameFromReference($mappingOrRef);
        }

        $this->currentName = $name;

        if ($mappingOrRef === null) {
            return $this->getMapping($name);
        }

        if (!isset($this->mappings[$name])) {
            $this->parseAndStore($mappingOrRef, $options);
        }

        return $this->getMapping($name);
    }

    /**
     * Get a parsed mapping by name.
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

        if (in_array($section, self::MAP_SECTIONS)) {
            foreach ($mapping[$section] as $map) {
                if ($name === ($map[self::MAP_FROM]['path'] ?? null)) {
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
     *
     * Delegates to MapNormalizer when available.
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
                $result[] = [self::MAP_FROM => [], self::MAP_TO => [], self::MAP_MOD => []];
                continue;
            }

            $normalized = $this->normalizeMap($map, $options);

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
     *
     * Uses MapNormalizer for the heavy lifting, then converts to legacy format.
     */
    public function normalizeMap($map, array $options = []): array
    {
        if (empty($map)) {
            return [self::MAP_FROM => [], self::MAP_TO => [], self::MAP_MOD => []];
        }

        if (is_string($map)) {
            return $this->normalizeMapFromString($map, $options);
        }

        if (is_array($map)) {
            if (is_numeric(key($map))) {
                return array_map(fn($m) => $this->normalizeMap($m, $options), $map);
            }
            return $this->normalizeMapFromArray($map, $options);
        }

        return [self::MAP_FROM => [], self::MAP_TO => [], self::MAP_MOD => [], 'has_error' => true];
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
     * Generate a name from a mapping reference.
     */
    protected function generateNameFromReference($mappingOrRef): string
    {
        if (is_string($mappingOrRef)) {
            return $mappingOrRef;
        }
        return md5(serialize($mappingOrRef));
    }

    /**
     * Load mapping content from a reference.
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

        $isFileReference = false;
        if (strpos($reference, ':') !== false) {
            $prefix = strtok($reference, ':');
            if (isset($prefixes[$prefix])) {
                $isFileReference = true;
                $file = mb_substr($reference, strlen($prefix) + 1);
                $filepath = $prefixes[$prefix] . $file;
                if (file_exists($filepath) && is_readable($filepath)) {
                    return trim((string) file_get_contents($filepath));
                }
                return null;
            }
        }

        // Check for raw content (INI or XML).
        $trimmed = trim($reference);
        if (strlen($trimmed) > 10 && (
            mb_substr($trimmed, 0, 1) === '<' ||
            mb_substr($trimmed, 0, 1) === '[' ||
            strpos($trimmed, ' = ') !== false ||
            strpos($trimmed, "\n") !== false
        )) {
            return $trimmed;
        }

        // Try as module file without prefix.
        $filepath = $prefixes['module'] . $reference;
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

        $mapping = mb_substr($content, 0, 1) === '<'
            ? $this->parseXml($content, $options)
            : $this->parseIni($content, $options);

        return $this->finalizeMapping($mapping);
    }

    /**
     * Finalize a mapping by merging deprecated sections and normalizing structure.
     *
     * This method:
     * - Merges 'default' section into 'maps' (default is deprecated)
     * - Logs a deprecation warning if 'default' section was used
     */
    protected function finalizeMapping(array $mapping): array
    {
        // Merge 'default' section into 'maps'.
        if (!empty($mapping[self::SECTION_DEFAULT])) {
            // Log deprecation warning for user awareness.
            $this->logger->notice(
                'Mapping "{name}": The [default] section is deprecated. Move its content to [maps] section. Maps without a source path are automatically treated as default maps.', // @translate
                ['name' => $this->currentName ?? 'unknown']
            );

            // Prepend default maps to maps section (default maps should be processed first).
            $mapping[self::SECTION_MAPS] = array_merge(
                $mapping[self::SECTION_DEFAULT],
                $mapping[self::SECTION_MAPS] ?? []
            );
            // Clear the default section (keep empty array for structure).
            $mapping[self::SECTION_DEFAULT] = [];
        }

        return $mapping;
    }

    /**
     * Parse ini-style mapping content.
     */
    protected function parseIni(string $content, array $options): array
    {
        $mapping = self::EMPTY_MAPPING;
        $mapping[self::SECTION_INFO]['label'] = $this->currentName;

        $lines = array_filter(array_map('trim', explode("\n", $content)));
        $section = null;

        // Set default querier for MapNormalizer.
        $defaultQuerier = null;

        foreach ($lines as $line) {
            if (mb_substr($line, 0, 1) === ';') {
                continue;
            }

            if (mb_substr($line, 0, 1) === '[' && mb_substr($line, -1) === ']') {
                $section = trim(mb_substr($line, 1, -1));
                if (!in_array($section, self::MAPPING_SECTIONS)) {
                    $section = null;
                }
                continue;
            }

            if (!$section) {
                continue;
            }

            $map = $this->parseIniLine($line, $section, $options);
            if ($map === null) {
                continue;
            }

            if (in_array($section, self::KEYVALUE_SECTIONS)) {
                if (isset($map[self::MAP_FROM]) && is_scalar($map[self::MAP_FROM])) {
                    $mapping[$section][$map[self::MAP_FROM]] = $map[self::MAP_TO];
                    // Capture querier for later use.
                    if ($section === self::SECTION_INFO && $map[self::MAP_FROM] === 'querier') {
                        $defaultQuerier = $map[self::MAP_TO];
                    }
                }
            } elseif ($section === self::SECTION_TABLES) {
                if (isset($map[self::MAP_FROM]) && isset($map[self::MAP_TO])) {
                    $mapping[self::SECTION_TABLES][$map[self::MAP_FROM]][$map['key'] ?? ''] = $map[self::MAP_TO];
                }
            } else {
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

        if (in_array($section, self::KEYVALUE_SECTIONS)) {
            if ((mb_substr($to, 0, 1) === '"' && mb_substr($to, -1) === '"')
                || (mb_substr($to, 0, 1) === "'" && mb_substr($to, -1) === "'")
            ) {
                $to = mb_substr($to, 1, -1);
            }
            return [self::MAP_FROM => $from, self::MAP_TO => $to];
        }

        $options['section'] = $section;
        return $this->normalizeMapFromIniParts($from, $to, $options);
    }

    /**
     * Normalize a map from ini from/to parts.
     */
    protected function normalizeMapFromIniParts(string $from, string $to, array $options): array
    {
        $map = [self::MAP_FROM => [], self::MAP_TO => [], self::MAP_MOD => []];

        // Check if "to" is a raw value (quoted).
        $isRaw = (mb_substr($to, 0, 1) === '"' && mb_substr($to, -1) === '"')
            || (mb_substr($to, 0, 1) === "'" && mb_substr($to, -1) === "'");

        if ($isRaw) {
            $map[self::MAP_MOD]['raw'] = mb_substr($to, 1, -1);
            $map[self::MAP_TO] = $this->parseFieldSpec($from);
            return $map;
        }

        // Set source path if not empty or tilde.
        if ($from !== '~' && $from !== '') {
            $map[self::MAP_FROM]['path'] = $from;
        }

        // Parse destination with optional pattern.
        $tildePos = mb_strpos($to, '~');
        if ($tildePos !== false) {
            $fieldPart = trim(mb_substr($to, 0, $tildePos));
            $patternPart = trim(mb_substr($to, $tildePos + 1));

            $map[self::MAP_TO] = $this->parseFieldSpec($fieldPart);
            $map[self::MAP_MOD] = $this->parsePattern($patternPart);
        } else {
            $map[self::MAP_TO] = $this->parseFieldSpec($to);
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

        $tildePos = mb_strpos($spec, '~');
        if ($tildePos !== false) {
            $spec = trim(mb_substr($spec, 0, $tildePos));
        }

        $parts = preg_split('/\s+/', $spec);
        foreach ($parts as $part) {
            if (mb_substr($part, 0, 2) === '^^') {
                $result['datatype'][] = mb_substr($part, 2);
            } elseif (mb_substr($part, 0, 1) === '@') {
                $result['language'] = mb_substr($part, 1);
            } elseif (mb_substr($part, 0, 1) === '§') {
                $visibility = mb_strtolower(mb_substr($part, 1));
                $result['is_public'] = $visibility !== 'private';
            } elseif ($result['field'] === null) {
                $result['field'] = $part;
            }
        }

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
        $parsed = $this->patternParser->parse($pattern);
        return [
            'pattern' => $parsed['pattern'],
            'replace' => $parsed['replace'],
            'twig' => $parsed['twig'],
            'twig_has_replace' => $parsed['twig_has_replace'],
        ];
    }

    /**
     * Parse xml mapping content.
     */
    protected function parseXml(string $content, array $options): array
    {
        $mapping = self::EMPTY_MAPPING;
        $mapping[self::SECTION_INFO]['label'] = $this->currentName;

        try {
            $xml = new \SimpleXMLElement($content);
        } catch (\Exception $e) {
            $mapping['has_error'] = true;
            $this->logger->err('Invalid xml mapping: ' . $e->getMessage());
            return $mapping;
        }

        if (isset($xml->info)) {
            foreach ($xml->info->children() as $element) {
                $mapping['info'][$element->getName()] = (string) $element;
            }
        }

        if (isset($xml->params)) {
            foreach ($xml->params->children() as $element) {
                $mapping['params'][$element->getName()] = (string) $element;
            }
        }

        foreach ($xml->map as $mapElement) {
            $hasXpath = !empty($mapElement->from['xpath']);
            $section = $hasXpath ? self::SECTION_MAPS : self::SECTION_DEFAULT;
            $mapping[$section][] = $this->parseXmlMap($mapElement);
        }

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
        $map = [self::MAP_FROM => [], self::MAP_TO => [], self::MAP_MOD => []];

        // Parse from element.
        if (isset($element->from)) {
            $from = $element->from;
            if (!empty($from['xpath'])) {
                $map[self::MAP_FROM]['querier'] = 'xpath';
                $map[self::MAP_FROM]['path'] = (string) $from['xpath'];
            } elseif (!empty($from['jsdot'])) {
                $map[self::MAP_FROM]['querier'] = 'jsdot';
                $map[self::MAP_FROM]['path'] = (string) $from['jsdot'];
            } elseif (!empty($from['jsonpath'])) {
                $map[self::MAP_FROM]['querier'] = 'jsonpath';
                $map[self::MAP_FROM]['path'] = (string) $from['jsonpath'];
            } elseif (!empty($from['jmespath'])) {
                $map[self::MAP_FROM]['querier'] = 'jmespath';
                $map[self::MAP_FROM]['path'] = (string) $from['jmespath'];
            }
        }

        // Parse to element.
        if (isset($element->to)) {
            $to = $element->to;
            $map[self::MAP_TO]['field'] = (string) ($to['field'] ?? '');

            if (!empty($to['datatype'])) {
                $map[self::MAP_TO]['datatype'] = explode(' ', (string) $to['datatype']);
            }
            if (!empty($to['language'])) {
                $map[self::MAP_TO]['language'] = (string) $to['language'];
            }
            if (isset($to['visibility'])) {
                $map[self::MAP_TO]['is_public'] = (string) $to['visibility'] !== 'private';
            }

            if ($map[self::MAP_TO]['field']) {
                $propertyId = $this->easyMeta->propertyId($map[self::MAP_TO]['field']);
                if ($propertyId) {
                    $map[self::MAP_TO]['property_id'] = $propertyId;
                }
            }
        }

        // Parse mod element.
        if (isset($element->mod)) {
            $mod = $element->mod;
            if (!empty($mod['raw'])) {
                $map[self::MAP_MOD]['raw'] = (string) $mod['raw'];
            }
            if (!empty($mod['val'])) {
                $map[self::MAP_MOD]['val'] = (string) $mod['val'];
            }
            if (!empty($mod['prepend'])) {
                $map[self::MAP_MOD]['prepend'] = (string) $mod['prepend'];
            }
            if (!empty($mod['pattern'])) {
                $patternMod = $this->parsePattern((string) $mod['pattern']);
                $map[self::MAP_MOD] = array_merge($map[self::MAP_MOD], $patternMod);
            }
            if (!empty($mod['append'])) {
                $map[self::MAP_MOD]['append'] = (string) $mod['append'];
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

        foreach (['label', 'from', 'to', 'querier', 'mapper', 'example'] as $key) {
            if (!empty($input['info'][$key]) && is_string($input['info'][$key])) {
                $mapping['info'][$key] = $input['info'][$key];
            }
        }
        $mapping['info']['label'] = $mapping['info']['label'] ?? $this->currentName;

        if (isset($input['params']) && is_array($input['params'])) {
            $mapping['params'] = $input['params'];
        }

        if (isset($input['tables']) && is_array($input['tables'])) {
            $mapping['tables'] = $input['tables'];
        }

        foreach (self::MAP_SECTIONS as $section) {
            if (isset($input[$section]) && is_array($input[$section])) {
                $options['section'] = $section;
                $mapping[$section] = $this->normalizeMaps($input[$section], $options);
            }
        }

        return $this->finalizeMapping($mapping);
    }

    /**
     * Parse a simple list of maps (e.g., spreadsheet headers).
     */
    protected function parseMapList(array $maps, array $options): array
    {
        $mapping = self::EMPTY_MAPPING;
        $mapping[self::SECTION_INFO]['label'] = $options['label'] ?? $this->currentName;
        $mapping[self::SECTION_INFO]['querier'] = 'index';

        $options['section'] = self::SECTION_MAPS;
        $mapping[self::SECTION_MAPS] = $this->normalizeMaps($maps, $options);

        // No finalize needed here - parseMapList only creates maps section.
        return $mapping;
    }

    /**
     * Normalize a map from a string.
     */
    protected function normalizeMapFromString(string $map, array $options): array
    {
        $map = trim($map);
        if (!$map) {
            return [self::MAP_FROM => [], self::MAP_TO => [], self::MAP_MOD => []];
        }

        // Xml string.
        if (mb_substr($map, 0, 1) === '<') {
            try {
                $xml = new \SimpleXMLElement($map);
                return $this->parseXmlMap($xml);
            } catch (\Exception $e) {
                return [self::MAP_FROM => [], self::MAP_TO => [], self::MAP_MOD => [], 'has_error' => true];
            }
        }

        // Ini-style string.
        $equalsPos = mb_strpos($map, '=');
        if ($equalsPos === false) {
            return [
                self::MAP_FROM => isset($options['index']) ? ['index' => $options['index']] : [],
                self::MAP_TO => $this->parseFieldSpec($map),
                self::MAP_MOD => [],
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
        $result = [self::MAP_FROM => [], self::MAP_TO => [], self::MAP_MOD => []];

        if (isset($map[self::MAP_FROM])) {
            if (is_string($map[self::MAP_FROM])) {
                $result[self::MAP_FROM]['path'] = $map[self::MAP_FROM];
            } elseif (is_array($map[self::MAP_FROM])) {
                $result[self::MAP_FROM] = $map[self::MAP_FROM];
            }
        }

        if (isset($map[self::MAP_TO])) {
            if (is_string($map[self::MAP_TO])) {
                $result[self::MAP_TO] = $this->parseFieldSpec($map[self::MAP_TO]);
            } elseif (is_array($map[self::MAP_TO])) {
                $result[self::MAP_TO] = $map[self::MAP_TO];
                if (!empty($result[self::MAP_TO]['field']) && empty($result[self::MAP_TO]['property_id'])) {
                    $propertyId = $this->easyMeta->propertyId($result[self::MAP_TO]['field']);
                    if ($propertyId) {
                        $result[self::MAP_TO]['property_id'] = $propertyId;
                    }
                }
            }
        }

        if (isset($map[self::MAP_MOD])) {
            if (is_string($map[self::MAP_MOD])) {
                $result[self::MAP_MOD] = $this->parsePattern($map[self::MAP_MOD]);
            } elseif (is_array($map[self::MAP_MOD])) {
                $result[self::MAP_MOD] = $map[self::MAP_MOD];
            }
        }

        if (isset($options['index']) && empty($result[self::MAP_FROM]['path'])) {
            $result[self::MAP_FROM]['index'] = $options['index'];
        }

        return $result;
    }
}
