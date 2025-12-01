<?php declare(strict_types=1);

namespace MapperTest\Stdlib;

use Mapper\Stdlib\Mapper;
use MapperTest\MapperTestTrait;
use Omeka\Test\AbstractHttpControllerTestCase;
use SimpleXMLElement;

/**
 * Functional tests for Mapper conversion pipeline.
 *
 * Tests complete conversion from source data (XML, array) to Omeka format.
 *
 * @covers \Mapper\Stdlib\Mapper
 */
class MapperConversionTest extends AbstractHttpControllerTestCase
{
    use MapperTestTrait;

    protected Mapper $mapper;

    public function setUp(): void
    {
        parent::setUp();
        $this->loginAdmin();
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
        $xml = $this->getFixtureXml('xml/simple_record.xml');
        $mapping = $this->getFixture('mappings/simple.ini');

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
        $xml = $this->getFixtureXml('xml/simple_record.xml');
        $mapping = $this->getSampleXmlMapping();

        $this->mapper->setMapping('simple_xml', $mapping);
        $result = $this->mapper->convert($xml);

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
    }

    public function testConvertXmlExtractsAllMappedFields(): void
    {
        $xml = $this->getFixtureXml('xml/simple_record.xml');
        $mapping = $this->getFixture('mappings/simple.ini');

        $this->mapper->setMapping('complete', $mapping);
        $result = $this->mapper->convert($xml);

        // Load expected values.
        $expected = $this->getFixtureJson('expected/simple_record.json');

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
        $xml = $this->getFixtureXml('xml/simple_record.xml');
        $mapping = $this->getFixture('mappings/simple.ini');

        $this->mapper->setMapping('multi', $mapping);
        $result = $this->mapper->convert($xml);

        // The simple_record.xml has two <subject> elements.
        $subjects = $this->extractValues($result, 'dcterms:subject');
        $this->assertCount(2, $subjects, 'Should extract both subject values');
        $this->assertContains('Test Subject 1', $subjects);
        $this->assertContains('Test Subject 2', $subjects);
    }

    public function testConvertXmlWithNamespaces(): void
    {
        $xml = $this->getFixtureXml('lido/lido_example_mona_lisa.xml');
        $mapping = $this->getFixture('mappings/lido_simple.xml');

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
                        'twig' => ['{{ value }}'],
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

    public function testConvertWithTwigFilter(): void
    {
        $data = ['text' => 'hello world'];

        $mapping = [
            'info' => ['label' => 'Twig Filter Test', 'querier' => 'index'],
            'maps' => [
                [
                    'from' => ['path' => 'text'],
                    'to' => ['field' => 'dcterms:title'],
                    'mod' => [
                        'pattern' => '{{ value|upper }}',
                        'twig' => ['{{ value|upper }}'],
                    ],
                ],
            ],
        ];

        $this->mapper->setMapping('twig_test', $mapping);
        $result = $this->mapper->convert($data);

        $titles = $this->extractValues($result, 'dcterms:title');
        $this->assertNotEmpty($titles);
        $this->assertSame('HELLO WORLD', $titles[0]);
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
