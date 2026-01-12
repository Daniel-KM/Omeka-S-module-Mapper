<?php declare(strict_types=1);

namespace MapperTest\Stdlib;

use Mapper\Stdlib\Mapper;
use MapperTest\MapperDbTestCase;
use SimpleXMLElement;

/**
 * Functional tests for Mapper conversion pipeline.
 *
 * Tests complete conversion from source data (XML, array) to Omeka format.
 *
 * @covers \Mapper\Stdlib\Mapper
 */
class MapperConversionTest extends MapperDbTestCase
{
    protected Mapper $mapper;

    public function setUp(): void
    {
        parent::setUp();
        $this->mapper = $this->getServiceLocator()->get('Mapper\Mapper');
    }

    public function tearDown(): void
    {
        $this->cleanupResources();
        parent::tearDown();
    }

    // =========================================================================
    // XML Conversion Tests
    // =========================================================================

    public function testConvertSimpleXmlWithIniMapping(): void
    {
        $xml = $this->getFixtureXml('xml/simple.record.xml');
        $mapping = $this->getFixture('xml/simple.mapping.ini');

        $this->mapper->setMapping('simple_ini', $mapping);
        $result = $this->mapper->convert($xml);

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);

        // Check that title was extracted.
        $this->assertArrayHasKey('dcterms:title', $result);
        $values = $this->extractValues($result, 'dcterms:title');
        $this->assertContains('Test Record Title', $values);
    }

    public function testConvertSimpleXmlWithXmlMapping(): void
    {
        $xml = $this->getFixtureXml('xml/simple.record.xml');
        $mapping = $this->getSampleXmlMapping();

        $this->mapper->setMapping('simple_xml', $mapping);
        $result = $this->mapper->convert($xml);

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
    }

    public function testConvertXmlExtractsAllMappedFields(): void
    {
        $xml = $this->getFixtureXml('xml/simple.record.xml');
        $mapping = $this->getFixture('xml/simple.mapping.ini');

        $this->mapper->setMapping('complete', $mapping);
        $result = $this->mapper->convert($xml);

        // Load expected values.
        $expected = $this->getFixtureJson('xml/simple.record.expected.json');

        foreach ($expected as $field => $expectedValues) {
            $actualValues = $this->extractValues($result, $field);
            foreach ($expectedValues as $expectedValue) {
                $this->assertContains(
                    $expectedValue,
                    $actualValues,
                    "Field $field should contain '$expectedValue'"
                );
            }
        }
    }

    public function testConvertXmlHandlesMultipleValues(): void
    {
        $xml = $this->getFixtureXml('xml/simple.record.xml');
        $mapping = $this->getFixture('xml/simple.mapping.ini');

        $this->mapper->setMapping('multi', $mapping);
        $result = $this->mapper->convert($xml);

        // The simple.record.xml has two <subject> elements.
        $subjects = $this->extractValues($result, 'dcterms:subject');
        $this->assertCount(2, $subjects, 'Should extract both subject values');
        $this->assertContains('Test Subject 1', $subjects);
        $this->assertContains('Test Subject 2', $subjects);
    }

    public function testConvertXmlWithNamespaces(): void
    {
        $xml = $this->getFixtureXml('lido/mona_lisa.lido.xml');
        $mapping = $this->getFixture('lido/simple.mapping.xml');

        $this->mapper->setMapping('lido', $mapping);
        $result = $this->mapper->convert($xml);

        $this->assertIsArray($result);
        // LIDO mapping should extract at least the identifier.
        $identifiers = $this->extractValues($result, 'dcterms:identifier');
        $this->assertNotEmpty($identifiers, 'Should extract LIDO identifier');
    }

    // =========================================================================
    // Array Conversion Tests
    // =========================================================================

    public function testConvertArrayWithJsdotQuerier(): void
    {
        $data = [
            'metadata' => [
                'title' => 'Array Test Title',
                'creator' => 'Array Creator',
            ],
        ];

        $mapping = [
            'info' => [
                'label' => 'JSDot Test',
                'querier' => 'jsdot',
            ],
            'maps' => [
                ['from' => ['path' => 'metadata.title'], 'to' => ['field' => 'dcterms:title']],
                ['from' => ['path' => 'metadata.creator'], 'to' => ['field' => 'dcterms:creator']],
            ],
        ];

        $this->mapper->setMapping('jsdot_test', $mapping);
        $result = $this->mapper->convert($data);

        $this->assertIsArray($result);
        $titles = $this->extractValues($result, 'dcterms:title');
        $this->assertContains('Array Test Title', $titles);
    }

    public function testConvertArrayWithIndexQuerier(): void
    {
        $data = [
            'title' => 'Direct Title',
            'date' => '2024-01-01',
        ];

        $mapping = [
            'info' => [
                'label' => 'Index Test',
                'querier' => 'index',
            ],
            'maps' => [
                ['from' => ['path' => 'title'], 'to' => ['field' => 'dcterms:title']],
                ['from' => ['path' => 'date'], 'to' => ['field' => 'dcterms:date']],
            ],
        ];

        $this->mapper->setMapping('index_test', $mapping);
        $result = $this->mapper->convert($data);

        $this->assertIsArray($result);
        $titles = $this->extractValues($result, 'dcterms:title');
        $this->assertContains('Direct Title', $titles);
    }

    /**
     * @group optional
     */
    public function testConvertArrayWithJmesPath(): void
    {
        if (!class_exists('JmesPath\Env')) {
            $this->markTestSkipped('JMESPath library not available');
        }

        $data = [
            'items' => [
                ['name' => 'First Item'],
                ['name' => 'Second Item'],
            ],
        ];

        $mapping = [
            'info' => [
                'label' => 'JMESPath Test',
                'querier' => 'jmespath',
            ],
            'maps' => [
                ['from' => ['path' => 'items[0].name'], 'to' => ['field' => 'dcterms:title']],
            ],
        ];

        $this->mapper->setMapping('jmespath_test', $mapping);
        $result = $this->mapper->convert($data);

        $this->assertIsArray($result);
        $titles = $this->extractValues($result, 'dcterms:title');
        $this->assertContains('First Item', $titles);
    }

    // =========================================================================
    // Pattern and Modifier Tests
    // =========================================================================

    public function testConvertWithPrependAppend(): void
    {
        $data = ['value' => 'test'];

        $mapping = [
            'info' => ['label' => 'Prepend/Append Test', 'querier' => 'index'],
            'maps' => [
                [
                    'from' => ['path' => 'value'],
                    'to' => ['field' => 'dcterms:title'],
                    'mod' => [
                        'prepend' => 'Prefix: ',
                        'append' => ' :Suffix',
                        'pattern' => '{{ value }}',
                        'filters' => ['{{ value }}'],
                        'filters_has_replace' => [false],
                    ],
                ],
            ],
        ];

        $this->mapper->setMapping('prepend_test', $mapping);
        $result = $this->mapper->convert($data);

        $titles = $this->extractValues($result, 'dcterms:title');
        $this->assertNotEmpty($titles);
        $this->assertStringContainsString('Prefix:', $titles[0]);
        $this->assertStringContainsString(':Suffix', $titles[0]);
    }

    public function testConvertWithRawValue(): void
    {
        $data = ['ignored' => 'value'];

        $mapping = [
            'info' => ['label' => 'Raw Test', 'querier' => 'index'],
            'default' => [
                [
                    'from' => [],
                    'to' => ['field' => 'dcterms:type'],
                    'mod' => ['raw' => 'Static Type Value'],
                ],
            ],
            'maps' => [],
        ];

        $this->mapper->setMapping('raw_test', $mapping);
        $result = $this->mapper->convert($data);

        $types = $this->extractValues($result, 'dcterms:type');
        $this->assertContains('Static Type Value', $types);
    }

    public function testConvertWithFilter(): void
    {
        $data = ['text' => 'hello world'];

        $mapping = [
            'info' => ['label' => 'Filter Test', 'querier' => 'index'],
            'maps' => [
                [
                    'from' => ['path' => 'text'],
                    'to' => ['field' => 'dcterms:title'],
                    'mod' => [
                        'pattern' => '{{ value|upper }}',
                        'filters' => ['{{ value|upper }}'],
                        'filters_has_replace' => [false],
                    ],
                ],
            ],
        ];

        $this->mapper->setMapping('filter_test', $mapping);
        $result = $this->mapper->convert($data);

        $titles = $this->extractValues($result, 'dcterms:title');
        $this->assertNotEmpty($titles);
        $this->assertSame('HELLO WORLD', $titles[0]);
    }

    // =========================================================================
    // Combining Source Values Tests
    // =========================================================================

    public function testConvertCombinesMultipleSourceValues(): void
    {
        $data = [
            'firstName' => 'John',
            'lastName' => 'Doe',
        ];

        // Default maps go in 'maps' section with empty 'from.path'.
        // Use INI format to ensure proper parsing of the pattern.
        $mappingIni = <<<'INI'
[info]
label = Combine Test
querier = index

[maps]
~ = dcterms:contributor ~ {firstName} {lastName}
INI;

        $this->mapper->setMapping('combine_test', $mappingIni);
        $result = $this->mapper->convert($data);

        $contributors = $this->extractValues($result, 'dcterms:contributor');
        $this->assertNotEmpty($contributors, 'Should have combined contributor value');
        $this->assertSame('John Doe', $contributors[0]);
    }

    public function testConvertCombinesCoordinates(): void
    {
        $data = [
            'latitude' => '48.8584',
            'longitude' => '2.2945',
        ];

        $mappingIni = <<<'INI'
[info]
label = Coordinates Test
querier = index

[maps]
~ = geo:coordinates ~ {latitude},{longitude}
INI;

        $this->mapper->setMapping('coords_test', $mappingIni);
        $result = $this->mapper->convert($data);

        $coords = $this->extractValues($result, 'geo:coordinates');
        $this->assertNotEmpty($coords, 'Should have combined coordinates');
        $this->assertSame('48.8584,2.2945', $coords[0]);
    }

    public function testConvertCombinesWithLiteralText(): void
    {
        $data = [
            'id' => '12345',
            'year' => '2024',
        ];

        $mappingIni = <<<'INI'
[info]
label = Literal Combine Test
querier = index

[maps]
~ = dcterms:identifier ~ ID-{id}-{year}
INI;

        $this->mapper->setMapping('literal_combine', $mappingIni);
        $result = $this->mapper->convert($data);

        $ids = $this->extractValues($result, 'dcterms:identifier');
        $this->assertNotEmpty($ids, 'Should have combined identifier');
        $this->assertSame('ID-12345-2024', $ids[0]);
    }

    public function testConvertNestedPathsInPattern(): void
    {
        $data = [
            'person' => [
                'name' => [
                    'first' => 'Jane',
                    'last' => 'Smith',
                ],
            ],
        ];

        $mappingIni = <<<'INI'
[info]
label = Nested Combine Test
querier = jsdot

[maps]
~ = dcterms:creator ~ {person.name.first} {person.name.last}
INI;

        $this->mapper->setMapping('nested_combine', $mappingIni);
        $result = $this->mapper->convert($data);

        $creators = $this->extractValues($result, 'dcterms:creator');
        $this->assertNotEmpty($creators, 'Should have combined nested values');
        $this->assertSame('Jane Smith', $creators[0]);
    }

    public function testConvertCombineTrimsResult(): void
    {
        // When one value is missing, result should be trimmed.
        $data = [
            'firstName' => 'John',
            // lastName is missing
        ];

        $mappingIni = <<<'INI'
[info]
label = Trim Test
querier = index

[maps]
~ = dcterms:contributor ~ {firstName} {lastName}
INI;

        $this->mapper->setMapping('trim_test', $mappingIni);
        $result = $this->mapper->convert($data);

        $contributors = $this->extractValues($result, 'dcterms:contributor');
        $this->assertNotEmpty($contributors, 'Should have trimmed result');
        $this->assertSame('John', $contributors[0], 'Result should be trimmed');
    }

    public function testConvertCombineSkipsWhenAllEmpty(): void
    {
        // When ALL values are missing, no value should be generated.
        $data = [
            'otherField' => 'value',
            // firstName and lastName are both missing
        ];

        $mappingIni = <<<'INI'
[info]
label = Skip Empty Test
querier = index

[maps]
~ = dcterms:contributor ~ {firstName} {lastName}
INI;

        $this->mapper->setMapping('skip_empty', $mappingIni);
        $result = $this->mapper->convert($data);

        $contributors = $this->extractValues($result, 'dcterms:contributor');
        $this->assertEmpty($contributors, 'Should skip when all values are empty');
    }

    // =========================================================================
    // Datatype and Language Tests
    // =========================================================================

    public function testConvertWithUriDatatype(): void
    {
        $data = ['link' => 'https://example.org/resource'];

        $mapping = [
            'info' => ['label' => 'URI Test', 'querier' => 'index'],
            'maps' => [
                [
                    'from' => ['path' => 'link'],
                    'to' => ['field' => 'dcterms:source', 'datatype' => ['uri']],
                ],
            ],
        ];

        $this->mapper->setMapping('uri_test', $mapping);
        $result = $this->mapper->convert($data);

        $this->assertIsArray($result);
        // The datatype should be preserved in the mapping.
        $parsedMapping = $this->mapper->getMapping();
        $this->assertContains('uri', $parsedMapping['maps'][0]['to']['datatype']);
    }

    public function testConvertWithLanguage(): void
    {
        $data = ['title_fr' => 'Titre en français'];

        $mapping = [
            'info' => ['label' => 'Language Test', 'querier' => 'index'],
            'maps' => [
                [
                    'from' => ['path' => 'title_fr'],
                    'to' => ['field' => 'dcterms:title', 'language' => 'fr'],
                ],
            ],
        ];

        $this->mapper->setMapping('lang_test', $mapping);
        $result = $this->mapper->convert($data);

        // The language should be preserved in the mapping.
        $parsedMapping = $this->mapper->getMapping();
        $this->assertSame('fr', $parsedMapping['maps'][0]['to']['language']);
    }

    // =========================================================================
    // Literal Curly Braces Tests
    // =========================================================================

    /**
     * Test that output containing literal curly braces is NOT re-processed.
     *
     * When a table conversion produces a value like "{unknown}", that value
     * should remain as a literal string and not be interpreted as a placeholder.
     */
    public function testConvertTableOutputWithCurlyBracesNotReprocessed(): void
    {
        $data = ['code' => 'XYZ'];

        // Use inline table that maps XYZ to a string containing curly braces.
        $mapping = [
            'info' => ['label' => 'Curly Braces Test', 'querier' => 'index'],
            'maps' => [
                [
                    'from' => ['path' => 'code'],
                    'to' => ['field' => 'dcterms:identifier'],
                    'mod' => [
                        'pattern' => "{{ value|table({'ABC': 'Known Code', 'XYZ': '{unknown_code}'}) }}",
                        'filters' => ["{{ value|table({'ABC': 'Known Code', 'XYZ': '{unknown_code}'}) }}"],
                        'filters_has_replace' => [false],
                    ],
                ],
            ],
        ];

        $this->mapper->setMapping('curly_test', $mapping);
        $result = $this->mapper->convert($data);

        $identifiers = $this->extractValues($result, 'dcterms:identifier');
        $this->assertNotEmpty($identifiers, 'Should have identifier value');
        // The output should be the literal string "{unknown_code}", not processed further.
        $this->assertSame('{unknown_code}', $identifiers[0]);
    }

    /**
     * Test that literal curly braces in prepend/append are preserved.
     */
    public function testConvertLiteralCurlyBracesInPrependAppend(): void
    {
        $data = ['value' => 'test'];

        $mapping = [
            'info' => ['label' => 'Literal Braces Test', 'querier' => 'index'],
            'maps' => [
                [
                    'from' => ['path' => 'value'],
                    'to' => ['field' => 'dcterms:description'],
                    'mod' => [
                        'prepend' => 'Start {literal} ',
                        'append' => ' {end}',
                        'pattern' => '{{ value }}',
                        'filters' => ['{{ value }}'],
                        'filters_has_replace' => [false],
                    ],
                ],
            ],
        ];

        $this->mapper->setMapping('literal_braces', $mapping);
        $result = $this->mapper->convert($data);

        $descriptions = $this->extractValues($result, 'dcterms:description');
        $this->assertNotEmpty($descriptions);
        // The {literal} and {end} should remain as literal strings since they
        // don't exist in the data and prepend/append are not pattern-processed.
        $this->assertStringContainsString('test', $descriptions[0]);
    }

    /**
     * Test that a value containing curly braces from source data is preserved.
     */
    public function testConvertValueWithCurlyBracesFromSource(): void
    {
        $data = ['text' => 'Value with {braces} inside'];

        // Default section is "maps", so no need for [info] or [maps] headers.
        $mappingIni = 'text = dcterms:description';

        $this->mapper->setMapping('source_braces', $mappingIni);
        $result = $this->mapper->convert($data);

        $descriptions = $this->extractValues($result, 'dcterms:description');
        $this->assertNotEmpty($descriptions);
        $this->assertSame('Value with {braces} inside', $descriptions[0]);
    }

    /**
     * Test that output containing double curly braces is NOT re-processed.
     *
     * When a table conversion produces a value like "{{ twig_expr }}", that value
     * should remain as a literal string and not be interpreted as a Twig expression.
     */
    public function testConvertTableOutputWithDoubleCurlyBracesNotReprocessed(): void
    {
        $data = ['code' => 'TPL'];

        // Use inline table that maps TPL to a string containing double curly braces.
        $mapping = [
            'info' => ['label' => 'Double Curly Braces Test', 'querier' => 'index'],
            'maps' => [
                [
                    'from' => ['path' => 'code'],
                    'to' => ['field' => 'dcterms:identifier'],
                    'mod' => [
                        'pattern' => "{{ value|table({'ABC': 'Known', 'TPL': '{{ template_var }}'}) }}",
                        'filters' => ["{{ value|table({'ABC': 'Known', 'TPL': '{{ template_var }}'}) }}"],
                        'filters_has_replace' => [false],
                    ],
                ],
            ],
        ];

        $this->mapper->setMapping('double_curly_test', $mapping);
        $result = $this->mapper->convert($data);

        $identifiers = $this->extractValues($result, 'dcterms:identifier');
        $this->assertNotEmpty($identifiers, 'Should have identifier value');
        // The output should be the literal string "{{ template_var }}", not processed further.
        $this->assertSame('{{ template_var }}', $identifiers[0]);
    }

    /**
     * Test that a value containing double curly braces from source is preserved.
     */
    public function testConvertValueWithDoubleCurlyBracesFromSource(): void
    {
        $data = ['text' => 'Template: {{ variable|filter }}'];

        // Default section is "maps", so no need for [info] or [maps] headers.
        $mappingIni = 'text = dcterms:description';

        $this->mapper->setMapping('source_double_braces', $mappingIni);
        $result = $this->mapper->convert($data);

        $descriptions = $this->extractValues($result, 'dcterms:description');
        $this->assertNotEmpty($descriptions);
        $this->assertSame('Template: {{ variable|filter }}', $descriptions[0]);
    }

    // =========================================================================
    // Edge Cases and Error Handling
    // =========================================================================

    public function testConvertEmptyDataReturnsEmptyArray(): void
    {
        $mapping = [
            'info' => ['label' => 'Empty Test'],
            'maps' => [
                ['from' => ['path' => 'missing'], 'to' => ['field' => 'dcterms:title']],
            ],
        ];

        $this->mapper->setMapping('empty_test', $mapping);
        $result = $this->mapper->convert([]);

        $this->assertIsArray($result);
    }

    public function testConvertWithMissingPathReturnsNoValue(): void
    {
        $data = ['present' => 'value'];

        $mapping = [
            'info' => ['label' => 'Missing Path Test', 'querier' => 'index'],
            'maps' => [
                ['from' => ['path' => 'missing'], 'to' => ['field' => 'dcterms:title']],
            ],
        ];

        $this->mapper->setMapping('missing_test', $mapping);
        $result = $this->mapper->convert($data);

        // Should not throw, just return empty or no value for that field.
        $this->assertIsArray($result);
        $titles = $this->extractValues($result, 'dcterms:title');
        $this->assertEmpty($titles);
    }

    /**
     * @group skip-on-strict-errors
     */
    public function testConvertWithInvalidXpathLogsError(): void
    {
        $xml = new SimpleXMLElement('<root><item>value</item></root>');

        $mapping = [
            'info' => ['label' => 'Invalid XPath Test', 'querier' => 'xpath'],
            'maps' => [
                // Use a valid but non-matching XPath to test error handling.
                ['from' => ['path' => '//nonexistent'], 'to' => ['field' => 'dcterms:title']],
            ],
        ];

        $this->mapper->setMapping('invalid_xpath', $mapping);

        // Non-matching XPath should return empty result without throwing.
        $result = $this->mapper->convert($xml);
        $this->assertIsArray($result);
        $titles = $this->extractValues($result, 'dcterms:title');
        $this->assertEmpty($titles);
    }

    // =========================================================================
    // Helper Methods
    // =========================================================================

    /**
     * Extract values for a field from the conversion result.
     *
     * The result structure depends on how Mapper organizes output.
     *
     * @param array $result Conversion result.
     * @param string $field Field name (e.g., 'dcterms:title').
     * @return array List of values.
     */
    protected function extractValues(array $result, string $field): array
    {
        $values = [];

        // Handle flat result with field as key.
        if (isset($result[$field])) {
            $fieldData = $result[$field];
            if (is_array($fieldData)) {
                foreach ($fieldData as $item) {
                    if (is_array($item) && isset($item['@value'])) {
                        $values[] = $item['@value'];
                    } elseif (is_array($item) && isset($item['@id'])) {
                        $values[] = $item['@id'];
                    } elseif (is_string($item)) {
                        $values[] = $item;
                    }
                }
            } elseif (is_string($fieldData)) {
                $values[] = $fieldData;
            }
        }

        return $values;
    }
}
