<?php declare(strict_types=1);

/**
 * MapNormalizer - Normalizes map definitions to a canonical format.
 *
 * This class handles the conversion of various input formats (INI syntax,
 * XML attributes, arrays) into a consistent internal representation.
 *
 * ## Canonical Map Format
 *
 * Each map has three parts:
 *
 * ```php
 * [
 *     'from' => [              // Source definition
 *         'type' => 'xpath',   // SOURCE_* constant
 *         'path' => '//title', // Query path (null for index/none)
 *         'index' => null,     // Column index (for spreadsheets)
 *     ],
 *     'to' => [                // Target definition with qualifiers
 *         'field' => 'dcterms:title',
 *         'property_id' => 1,
 *         'datatype' => ['literal'],
 *         'language' => 'fra',
 *         'is_public' => true,
 *     ],
 *     'mod' => [               // Modifiers
 *         'type' => 'pattern', // TRANSFORM_* constant
 *         'raw' => null,       // Fixed value
 *         'pattern' => '{{ value|upper }}',
 *         'prepend' => null,
 *         'append' => null,
 *     ],
 * ]
 * ```
 *
 * This format matches both INI and XML mapping files where qualifiers
 * are specified alongside the destination field.
 *
 * @copyright Daniel Berthereau, 2017-2026
 * @license http://www.cecill.info/licences/Licence_CeCILL_V2.1-en.txt
 */

namespace Mapper\Stdlib;

use Common\Stdlib\EasyMeta;
use Omeka\Api\Manager as ApiManager;

class MapNormalizer
{
    // =========================================================================
    // Map Part Constants (same as MapperConfig for compatibility)
    // =========================================================================

    /**
     * Source part: where data comes from.
     */
    public const MAP_FROM = 'from';

    /**
     * Target part: where data goes (includes qualifiers).
     */
    public const MAP_TO = 'to';

    /**
     * Modifier part: how to transform data.
     */
    public const MAP_MOD = 'mod';

    // =========================================================================
    // Source Type Constants (from.type)
    // =========================================================================

    /**
     * XPath query for XML sources.
     */
    public const SOURCE_XPATH = 'xpath';

    /**
     * JavaScript dot notation for JSON/array sources.
     */
    public const SOURCE_JSDOT = 'jsdot';

    /**
     * JSONPath query for JSON sources.
     */
    public const SOURCE_JSONPATH = 'jsonpath';

    /**
     * JMESPath query for JSON sources.
     */
    public const SOURCE_JMESPATH = 'jmespath';

    /**
     * Column index for spreadsheet sources.
     */
    public const SOURCE_INDEX = 'index';

    /**
     * No source (for fixed values).
     */
    public const SOURCE_NONE = 'none';

    /**
     * All source types.
     */
    public const SOURCE_TYPES = [
        self::SOURCE_XPATH,
        self::SOURCE_JSDOT,
        self::SOURCE_JSONPATH,
        self::SOURCE_JMESPATH,
        self::SOURCE_INDEX,
        self::SOURCE_NONE,
    ];

    // =========================================================================
    // Transform Type Constants (mod.type)
    // =========================================================================

    /**
     * No transformation, use source value as-is.
     */
    public const TRANSFORM_NONE = 'none';

    /**
     * Fixed raw value (ignores source).
     */
    public const TRANSFORM_RAW = 'raw';

    /**
     * Pattern with replacements and/or Twig filters.
     */
    public const TRANSFORM_PATTERN = 'pattern';

    /**
     * All transform types.
     */
    public const TRANSFORM_TYPES = [
        self::TRANSFORM_NONE,
        self::TRANSFORM_RAW,
        self::TRANSFORM_PATTERN,
    ];

    /**
     * Empty canonical map structure.
     *
     * Null values have specific semantics:
     * - `to.datatype`: null = use default datatype (usually 'literal')
     * - `to.language`: null = no language tag
     * - `to.is_public`: null = inherit from resource or use default (true)
     *                   true = explicitly public, false = explicitly private
     * - `mod.raw`: null = no fixed value
     * - `mod.pattern`: null = no pattern transformation
     * - `mod.prepend`/`mod.append`: null = no prefix/suffix
     */
    public const EMPTY_MAP = [
        'from' => [
            'type' => self::SOURCE_NONE,
            'path' => null,
            'index' => null,
        ],
        'to' => [
            'field' => null,
            'property_id' => null,
            'datatype' => null,
            'language' => null,
            'is_public' => null,
        ],
        'mod' => [
            'type' => self::TRANSFORM_NONE,
            'raw' => null,
            'pattern' => null,
            'prepend' => null,
            'append' => null,
        ],
    ];

    /**
     * @var ApiManager
     */
    protected $api;

    /**
     * @var EasyMeta
     */
    protected $easyMeta;

    /**
     * Default querier type inherited from mapping info.
     */
    protected ?string $defaultQuerier = null;

    /**
     * Cached custom vocab labels to IDs.
     */
    protected ?array $customVocabLabels = null;

    public function __construct(ApiManager $api, EasyMeta $easyMeta)
    {
        $this->api = $api;
        $this->easyMeta = $easyMeta;
    }

    /**
     * Set the default querier for maps that don't specify one.
     */
    public function setDefaultQuerier(?string $querier): self
    {
        $this->defaultQuerier = $querier;
        return $this;
    }

    /**
     * Normalize a map from any input format to the canonical format.
     *
     * @param mixed $map The map in any supported format.
     * @param array $options Normalization options.
     * @return array The normalized map in canonical format.
     */
    public function normalize($map, array $options = []): array
    {
        if (empty($map)) {
            return self::EMPTY_MAP;
        }

        if (is_string($map)) {
            return $this->normalizeFromString($map, $options);
        }

        if (is_array($map)) {
            return $this->normalizeFromArray($map, $options);
        }

        return self::EMPTY_MAP;
    }

    /**
     * Normalize multiple maps.
     *
     * @param array $maps List of maps in any format.
     * @param array $options Normalization options.
     * @return array List of normalized maps.
     */
    public function normalizeAll(array $maps, array $options = []): array
    {
        $result = [];
        foreach ($maps as $index => $map) {
            $options['index'] = $index;
            $normalized = $this->normalize($map, $options);

            // A single map can expand to multiple maps (rare case).
            if ($this->isListOfMaps($normalized)) {
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
     * Normalize a map from a string (INI line or simple field spec).
     */
    protected function normalizeFromString(string $input, array $options = []): array
    {
        $input = trim($input);
        if ($input === '') {
            return self::EMPTY_MAP;
        }

        // Check for XML format.
        if (mb_substr($input, 0, 1) === '<') {
            return $this->normalizeFromXmlString($input, $options);
        }

        // Check for INI-style "from = to" format.
        $parts = $this->splitIniLine($input);
        if ($parts !== null) {
            return $this->normalizeFromIniParts($parts['from'], $parts['to'], $options);
        }

        // Simple field specification (e.g., "dcterms:title ^^literal").
        return $this->normalizeSimpleFieldSpec($input, $options);
    }

    /**
     * Normalize a map from an array.
     */
    protected function normalizeFromArray(array $map, array $options = []): array
    {
        // Check if this is a list of maps (numeric keys).
        if ($this->isListOfMaps($map)) {
            return array_map(fn($m) => $this->normalize($m, $options), $map);
        }

        $result = self::EMPTY_MAP;

        // Normalize 'from' section.
        $result['from'] = $this->normalizeFromSection($map['from'] ?? null, $options);

        // Normalize 'to' section (includes qualifiers).
        $result['to'] = $this->normalizeToSection($map['to'] ?? null);

        // Normalize 'mod' section.
        $result['mod'] = $this->normalizeModSection($map['mod'] ?? null);

        return $result;
    }

    /**
     * Normalize the 'from' section.
     */
    protected function normalizeFromSection($from, array $options = []): array
    {
        $result = [
            'type' => self::SOURCE_NONE,
            'path' => null,
            'index' => null,
        ];

        if (empty($from)) {
            // Check for index from options.
            if (isset($options['index'])) {
                $result['type'] = self::SOURCE_INDEX;
                $result['index'] = $options['index'];
            }
            return $result;
        }

        if (is_string($from)) {
            $result['type'] = $this->defaultQuerier ?? self::SOURCE_XPATH;
            $result['path'] = $from;
            return $result;
        }

        if (is_array($from)) {
            // Explicit querier.
            if (isset($from['querier'])) {
                $result['type'] = $from['querier'];
            } elseif (isset($from['xpath'])) {
                $result['type'] = self::SOURCE_XPATH;
                $result['path'] = $from['xpath'];
            } elseif (isset($from['jsdot'])) {
                $result['type'] = self::SOURCE_JSDOT;
                $result['path'] = $from['jsdot'];
            } elseif (isset($from['jsonpath'])) {
                $result['type'] = self::SOURCE_JSONPATH;
                $result['path'] = $from['jsonpath'];
            } elseif (isset($from['jmespath'])) {
                $result['type'] = self::SOURCE_JMESPATH;
                $result['path'] = $from['jmespath'];
            } elseif (isset($from['path'])) {
                $result['type'] = $this->defaultQuerier ?? self::SOURCE_XPATH;
                $result['path'] = $from['path'];
            } elseif (isset($from['index'])) {
                $result['type'] = self::SOURCE_INDEX;
                $result['index'] = $from['index'];
            }

            // Copy path if not set but type is.
            if ($result['path'] === null && isset($from['path'])) {
                $result['path'] = $from['path'];
            }
        }

        return $result;
    }

    /**
     * Normalize the 'to' section.
     *
     * Returns field, property_id, and any qualifiers parsed from string.
     */
    protected function normalizeToSection($to): array
    {
        $result = [
            'field' => null,
            'property_id' => null,
            'datatype' => null,
            'language' => null,
            'is_public' => null,
        ];

        if (empty($to)) {
            return $result;
        }

        if (is_string($to)) {
            // Parse field specification string (includes qualifiers).
            return $this->parseFieldSpec($to);
        }

        if (is_array($to)) {
            $result['field'] = $to['field'] ?? null;
            $result['property_id'] = $to['property_id'] ?? null;

            // Also capture qualifiers from array format.
            if (isset($to['datatype'])) {
                $result['datatype'] = is_array($to['datatype'])
                    ? $to['datatype']
                    : [$to['datatype']];
            }
            $result['language'] = $to['language'] ?? null;
            $result['is_public'] = $to['is_public'] ?? null;

            // Resolve property_id if not set.
            if ($result['field'] && !$result['property_id']) {
                $result['property_id'] = $this->easyMeta->propertyId($result['field']);
            }
        }

        return $result;
    }

    /**
     * Normalize the 'mod' section.
     */
    protected function normalizeModSection($mod): array
    {
        $result = [
            'type' => self::TRANSFORM_NONE,
            'raw' => null,
            'pattern' => null,
            'prepend' => null,
            'append' => null,
        ];

        if (empty($mod)) {
            return $result;
        }

        if (is_string($mod)) {
            // Treat string as pattern.
            $result['type'] = self::TRANSFORM_PATTERN;
            $result['pattern'] = $mod;
            return $result;
        }

        if (is_array($mod)) {
            // Raw value.
            if (isset($mod['raw'])) {
                $result['type'] = self::TRANSFORM_RAW;
                $result['raw'] = $mod['raw'];
            } elseif (isset($mod['val'])) {
                // 'val' is similar to 'raw' but only when source has value.
                $result['type'] = self::TRANSFORM_RAW;
                $result['raw'] = $mod['val'];
            }

            // Pattern.
            if (isset($mod['pattern'])) {
                $result['type'] = self::TRANSFORM_PATTERN;
                $result['pattern'] = $mod['pattern'];
            }

            // Prepend/append.
            $result['prepend'] = $mod['prepend'] ?? null;
            $result['append'] = $mod['append'] ?? null;
        }

        return $result;
    }

    /**
     * Split an INI line into 'from' and 'to' parts.
     *
     * Handles the tilde (~) separator for patterns.
     *
     * @return array|null ['from' => string, 'to' => string] or null.
     */
    protected function splitIniLine(string $line): ?array
    {
        // Find the equals sign, but account for tilde patterns.
        $tildePos = mb_strpos($line, '~');

        // If there's a tilde, find equals before it.
        if ($tildePos !== false) {
            $equalsPos = mb_strpos(mb_substr($line, 0, $tildePos), '=');
            if ($equalsPos !== false) {
                return [
                    'from' => trim(mb_substr($line, 0, $equalsPos)),
                    'to' => trim(mb_substr($line, $equalsPos + 1)),
                ];
            }
        }

        // No tilde or no equals before tilde - find last equals.
        $equalsPos = mb_strrpos($line, '=');
        if ($equalsPos === false) {
            return null;
        }

        return [
            'from' => trim(mb_substr($line, 0, $equalsPos)),
            'to' => trim(mb_substr($line, $equalsPos + 1)),
        ];
    }

    /**
     * Normalize from INI-style "from = to" parts.
     */
    protected function normalizeFromIniParts(string $from, string $to, array $options = []): array
    {
        $result = self::EMPTY_MAP;

        // Check if 'to' is a raw value (quoted).
        if ($this->isQuotedString($to)) {
            $result['mod']['type'] = self::TRANSFORM_RAW;
            $result['mod']['raw'] = $this->unquote($to);
            // For raw values, 'from' is actually the target field.
            $result['to'] = $this->normalizeToSection($from);
            return $result;
        }

        // Parse source path.
        if ($from !== '~' && $from !== '') {
            $result['from']['type'] = $this->defaultQuerier ?? self::SOURCE_XPATH;
            $result['from']['path'] = $from;
        } elseif (isset($options['index'])) {
            $result['from']['type'] = self::SOURCE_INDEX;
            $result['from']['index'] = $options['index'];
        }

        // Parse destination with optional pattern (separated by ~).
        $tildePos = mb_strpos($to, '~');
        if ($tildePos !== false) {
            $fieldPart = trim(mb_substr($to, 0, $tildePos));
            $patternPart = trim(mb_substr($to, $tildePos + 1));

            $result['to'] = $this->parseFieldSpec($fieldPart);
            $result['mod']['type'] = self::TRANSFORM_PATTERN;
            $result['mod']['pattern'] = $patternPart;
        } else {
            $result['to'] = $this->parseFieldSpec($to);
        }

        return $result;
    }

    /**
     * Normalize a simple field specification without source.
     */
    protected function normalizeSimpleFieldSpec(string $spec, array $options = []): array
    {
        $result = self::EMPTY_MAP;

        // Set index if available.
        if (isset($options['index'])) {
            $result['from']['type'] = self::SOURCE_INDEX;
            $result['from']['index'] = $options['index'];
        }

        $result['to'] = $this->parseFieldSpec($spec);

        return $result;
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
            'property_id' => null,
            'datatype' => null,
            'language' => null,
            'is_public' => null,
        ];

        $spec = trim($spec);
        if ($spec === '') {
            return $result;
        }

        // Remove pattern part if present.
        $tildePos = mb_strpos($spec, '~');
        if ($tildePos !== false) {
            $spec = trim(mb_substr($spec, 0, $tildePos));
        }

        // Split by whitespace.
        $parts = preg_split('/\s+/', $spec);
        $datatypes = [];

        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }

            $prefix = mb_substr($part, 0, 2);
            $prefixSingle = mb_substr($part, 0, 1);

            if ($prefix === '^^') {
                // Datatype.
                $datatypes[] = mb_substr($part, 2);
            } elseif ($prefixSingle === '@') {
                // Language.
                $result['language'] = mb_substr($part, 1);
            } elseif ($prefixSingle === '§') {
                // Visibility.
                $visibility = mb_strtolower(mb_substr($part, 1));
                $result['is_public'] = ($visibility !== 'private');
            } elseif ($result['field'] === null) {
                // First non-prefixed part is the field.
                $result['field'] = $part;
            }
        }

        if (!empty($datatypes)) {
            $result['datatype'] = $this->normalizeDatatypes($datatypes);
        }

        // Resolve property ID.
        if ($result['field']) {
            $propertyId = $this->easyMeta->propertyId($result['field']);
            if ($propertyId) {
                $result['property_id'] = $propertyId;
            }
        }

        return $result;
    }

    /**
     * Normalize from an XML string (single <map> element).
     */
    protected function normalizeFromXmlString(string $xml, array $options = []): array
    {
        try {
            $element = new \SimpleXMLElement($xml);
            return $this->normalizeFromXmlElement($element, $options);
        } catch (\Exception $e) {
            return self::EMPTY_MAP;
        }
    }

    /**
     * Normalize from a SimpleXMLElement.
     */
    public function normalizeFromXmlElement(\SimpleXMLElement $element, array $options = []): array
    {
        $result = self::EMPTY_MAP;

        // Parse <from> element.
        if (isset($element->from)) {
            $from = $element->from;
            if (!empty($from['xpath'])) {
                $result['from']['type'] = self::SOURCE_XPATH;
                $result['from']['path'] = (string) $from['xpath'];
            } elseif (!empty($from['jsdot'])) {
                $result['from']['type'] = self::SOURCE_JSDOT;
                $result['from']['path'] = (string) $from['jsdot'];
            } elseif (!empty($from['jsonpath'])) {
                $result['from']['type'] = self::SOURCE_JSONPATH;
                $result['from']['path'] = (string) $from['jsonpath'];
            } elseif (!empty($from['jmespath'])) {
                $result['from']['type'] = self::SOURCE_JMESPATH;
                $result['from']['path'] = (string) $from['jmespath'];
            }
        }

        // Parse <to> element.
        if (isset($element->to)) {
            $to = $element->to;
            $result['to']['field'] = (string) ($to['field'] ?? '');

            if ($result['to']['field']) {
                $propertyId = $this->easyMeta->propertyId($result['to']['field']);
                if ($propertyId) {
                    $result['to']['property_id'] = $propertyId;
                }
            }

            // Qualifiers (stored in 'to').
            if (!empty($to['datatype'])) {
                $datatypes = explode(' ', (string) $to['datatype']);
                $result['to']['datatype'] = $this->normalizeDatatypes($datatypes);
            }
            if (!empty($to['language'])) {
                $result['to']['language'] = (string) $to['language'];
            }
            if (isset($to['visibility'])) {
                $result['to']['is_public'] = ((string) $to['visibility']) !== 'private';
            }
        }

        // Parse <mod> element.
        if (isset($element->mod)) {
            $mod = $element->mod;
            if (!empty($mod['raw'])) {
                $result['mod']['type'] = self::TRANSFORM_RAW;
                $result['mod']['raw'] = (string) $mod['raw'];
            }
            if (!empty($mod['pattern'])) {
                $result['mod']['type'] = self::TRANSFORM_PATTERN;
                $result['mod']['pattern'] = (string) $mod['pattern'];
            }
            if (!empty($mod['prepend'])) {
                $result['mod']['prepend'] = (string) $mod['prepend'];
            }
            if (!empty($mod['append'])) {
                $result['mod']['append'] = (string) $mod['append'];
            }
        }

        return $result;
    }

    /**
     * Check if an array is a list of maps (numeric keys).
     */
    protected function isListOfMaps(array $arr): bool
    {
        if (empty($arr)) {
            return false;
        }
        return is_int(key($arr)) && !isset($arr['from']) && !isset($arr['to']);
    }

    /**
     * Check if a string is quoted.
     */
    protected function isQuotedString(string $str): bool
    {
        $first = mb_substr($str, 0, 1);
        $last = mb_substr($str, -1);
        return ($first === '"' && $last === '"') || ($first === "'" && $last === "'");
    }

    /**
     * Remove quotes from a string.
     */
    protected function unquote(string $str): string
    {
        if ($this->isQuotedString($str)) {
            return mb_substr($str, 1, -1);
        }
        return $str;
    }

    /**
     * Convert old format map to new canonical format.
     *
     * This method helps with backwards compatibility.
     */
    public function convertFromLegacy(array $map): array
    {
        // If already in new format (has from.type), return as-is.
        if (isset($map['from']['type'])) {
            return $map;
        }

        $result = self::EMPTY_MAP;

        // Convert 'from'.
        if (isset($map['from'])) {
            $from = $map['from'];
            if (isset($from['querier'])) {
                $result['from']['type'] = $from['querier'];
            } elseif (isset($from['path'])) {
                $result['from']['type'] = $this->defaultQuerier ?? self::SOURCE_XPATH;
            } elseif (isset($from['index'])) {
                $result['from']['type'] = self::SOURCE_INDEX;
            }
            $result['from']['path'] = $from['path'] ?? null;
            $result['from']['index'] = $from['index'] ?? null;
        }

        // Convert 'to' (qualifiers stay in 'to').
        if (isset($map['to'])) {
            $to = $map['to'];
            $result['to']['field'] = $to['field'] ?? null;
            $result['to']['property_id'] = $to['property_id'] ?? null;
            $result['to']['language'] = $to['language'] ?? null;
            $result['to']['is_public'] = $to['is_public'] ?? null;
            // Normalize datatypes.
            if (!empty($to['datatype'])) {
                $datatypes = is_array($to['datatype'])
                    ? $to['datatype']
                    : [$to['datatype']];
                $result['to']['datatype'] = $this->normalizeDatatypes($datatypes);
            }
        }

        // Convert 'mod'.
        if (isset($map['mod'])) {
            $mod = $map['mod'];
            if (isset($mod['raw'])) {
                $result['mod']['type'] = self::TRANSFORM_RAW;
                $result['mod']['raw'] = $mod['raw'];
            } elseif (isset($mod['pattern'])) {
                $result['mod']['type'] = self::TRANSFORM_PATTERN;
                $result['mod']['pattern'] = $mod['pattern'];
            }
            $result['mod']['prepend'] = $mod['prepend'] ?? null;
            $result['mod']['append'] = $mod['append'] ?? null;
        }

        return $result;
    }

    /**
     * Normalize datatypes, resolving custom vocab labels to IDs.
     *
     * @param array $datatypes List of datatype strings.
     * @return array Normalized datatypes.
     */
    protected function normalizeDatatypes(array $datatypes): array
    {
        $result = [];
        foreach ($datatypes as $datatype) {
            if (strpos($datatype, 'customvocab:') === 0) {
                $datatype = $this->resolveCustomVocabDatatype($datatype);
            }
            $normalized = $this->easyMeta->dataTypeName($datatype);
            if ($normalized !== null) {
                $result[] = $normalized;
            } else {
                $result[] = $datatype;
            }
        }
        return array_values(array_unique(array_filter($result)));
    }

    /**
     * Resolve custom vocab datatype with label to ID.
     *
     * Converts "customvocab:'My List'" or 'customvocab:"My List"' to
     * "customvocab:123".
     */
    protected function resolveCustomVocabDatatype(string $datatype): string
    {
        $suffix = substr($datatype, 12);

        // Already an ID.
        if (is_numeric($suffix)) {
            return $datatype;
        }

        // Extract label from quotes.
        $first = mb_substr($suffix, 0, 1);
        $last = mb_substr($suffix, -1);
        if (($first === '"' && $last === '"')
            || ($first === "'" && $last === "'")
        ) {
            $label = mb_substr($suffix, 1, -1);
        } else {
            $label = $suffix;
        }

        // Lookup custom vocab ID by label.
        if ($this->customVocabLabels === null) {
            $this->loadCustomVocabLabels();
        }

        $id = $this->customVocabLabels[$label] ?? null;
        if ($id !== null) {
            return 'customvocab:' . $id;
        }

        return $datatype;
    }

    /**
     * Load custom vocab labels to IDs mapping.
     */
    protected function loadCustomVocabLabels(): void
    {
        $this->customVocabLabels = [];

        try {
            $customVocabs = $this->api
                ->search('custom_vocabs', [], ['responseContent' => 'resource'])
                ->getContent();
            foreach ($customVocabs as $customVocab) {
                $this->customVocabLabels[$customVocab->getLabel()] = $customVocab->getId();
            }
        } catch (\Exception $e) {
            // Custom vocab module not installed.
        }
    }
}
