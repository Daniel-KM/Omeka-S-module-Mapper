<?php declare(strict_types=1);

namespace MapperTest\Stdlib;

use Mapper\Stdlib\MapperConfig;
use MapperTest\MapperTestTrait;
use Omeka\Test\AbstractHttpControllerTestCase;

/**
 * Tests for the MapperConfig class.
 *
 * Note: The MapperConfig expects file references for string inputs (INI/XML).
 * These tests use array format which is parsed directly.
 *
 * @covers \Mapper\Stdlib\MapperConfig
 */
class MapperConfigTest extends AbstractHttpControllerTestCase
{
    use MapperTestTrait;

    protected MapperConfig $mapperConfig;

    public function setUp(): void
    {
        parent::setUp();
        $this->loginAdmin();

        // Get the MapperConfig service from the container.
        $this->mapperConfig = $this->getServiceLocator()->get('Mapper\MapperConfig');
    }

    // Basic invoke tests

    public function testInvokeReturnsItself(): void
    {
        $result = $this->mapperConfig->__invoke();
        $this->assertSame($this->mapperConfig, $result);
    }

    public function testInvokeWithEmptyMapping(): void
    {
        $result = $this->mapperConfig->__invoke('empty', '');
        $this->assertIsArray($result);
        $this->assertArrayHasKey('info', $result);
        $this->assertArrayHasKey('maps', $result);
    }

    public function testInvokeWithNullGeneratesName(): void
    {
        $result = $this->mapperConfig->__invoke(null, ['maps' => []]);
        $this->assertIsArray($result);
    }

    // getCurrentName tests

    public function testGetCurrentName(): void
    {
        $this->mapperConfig->__invoke('test_name', '');
        $this->assertSame('test_name', $this->mapperConfig->getCurrentName());
    }

    // getMapping tests

    public function testGetMappingNull(): void
    {
        $this->assertNull($this->mapperConfig->getMapping('nonexistent'));
    }

    public function testGetMappingByName(): void
    {
        $this->mapperConfig->__invoke('test', '');
        $mapping = $this->mapperConfig->getMapping('test');
        $this->assertIsArray($mapping);
    }

    // hasError tests

    public function testHasErrorTrue(): void
    {
        // Nonexistent mapping returns true.
        $this->assertTrue($this->mapperConfig->hasError('nonexistent'));
    }

    public function testHasErrorFalse(): void
    {
        $this->mapperConfig->__invoke('valid', '');
        $this->assertFalse($this->mapperConfig->hasError('valid'));
    }

    // getSection tests

    public function testGetSectionEmpty(): void
    {
        $this->mapperConfig->__invoke('test', '');
        $result = $this->mapperConfig->getSection('maps');
        $this->assertSame([], $result);
    }

    public function testGetSectionNonexistent(): void
    {
        $this->mapperConfig->__invoke('test', '');
        $result = $this->mapperConfig->getSection('nonexistent');
        $this->assertSame([], $result);
    }

    // Array parsing tests (arrays are parsed directly, unlike strings which expect file refs)

    public function testParseArrayWithInfo(): void
    {
        $input = [
            'info' => [
                'label' => 'Array Mapping',
                'from' => 'json',
                'to' => 'omeka',
            ],
            'maps' => [
                ['from' => 'title', 'to' => 'dcterms:title'],
            ],
        ];

        $result = $this->mapperConfig->__invoke('array_test', $input);
        $this->assertSame('Array Mapping', $result['info']['label']);
        $this->assertNotEmpty($result['maps']);
    }

    public function testParseArraySimpleList(): void
    {
        // Simple list of maps (like spreadsheet headers).
        $input = [
            'dcterms:title',
            'dcterms:creator',
            'dcterms:date',
        ];

        $result = $this->mapperConfig->__invoke('list_test', $input);
        $this->assertCount(3, $result['maps']);
        $this->assertSame('dcterms:title', $result['maps'][0]['to']['field']);
    }

    public function testParseArrayWithDefaultSection(): void
    {
        $input = [
            'info' => ['label' => 'Default Test'],
            'default' => [
                ['from' => '', 'to' => 'dcterms:type', 'mod' => ['raw' => 'Default Type']],
            ],
            'maps' => [],
        ];

        $result = $this->mapperConfig->__invoke('default_test', $input);
        $this->assertNotEmpty($result['default']);
    }

    public function testParseArrayWithQuerier(): void
    {
        $input = [
            'info' => ['label' => 'Querier Test', 'querier' => 'jsdot'],
            'maps' => [
                ['from' => 'data.title', 'to' => 'dcterms:title'],
            ],
        ];

        $result = $this->mapperConfig->__invoke('querier_test', $input);
        $this->assertSame('jsdot', $result['info']['querier']);
    }

    // normalizeMap tests

    public function testNormalizeMapEmpty(): void
    {
        $result = $this->mapperConfig->normalizeMap([]);
        $this->assertArrayHasKey('from', $result);
        $this->assertArrayHasKey('to', $result);
        $this->assertArrayHasKey('mod', $result);
    }

    public function testNormalizeMapString(): void
    {
        $result = $this->mapperConfig->normalizeMap('dcterms:title');
        $this->assertSame('dcterms:title', $result['to']['field']);
    }

    public function testNormalizeMapStringWithPattern(): void
    {
        $result = $this->mapperConfig->normalizeMap('source = dcterms:title ~ {{ value|upper }}');
        $this->assertSame('source', $result['from']['path']);
        $this->assertSame('dcterms:title', $result['to']['field']);
        $this->assertContains('{{ value|upper }}', $result['mod']['twig']);
    }

    public function testNormalizeMapArray(): void
    {
        $input = [
            'from' => 'source_field',
            'to' => 'dcterms:title',
        ];
        $result = $this->mapperConfig->normalizeMap($input);
        $this->assertSame('source_field', $result['from']['path']);
        $this->assertSame('dcterms:title', $result['to']['field']);
    }

    public function testNormalizeMapArrayWithFullSpec(): void
    {
        $input = [
            'from' => ['path' => 'data.title', 'querier' => 'jsdot'],
            'to' => ['field' => 'dcterms:title', 'language' => 'en'],
            'mod' => ['prepend' => 'Title: '],
        ];
        $result = $this->mapperConfig->normalizeMap($input);
        $this->assertSame('data.title', $result['from']['path']);
        $this->assertSame('jsdot', $result['from']['querier']);
        $this->assertSame('en', $result['to']['language']);
        $this->assertSame('Title: ', $result['mod']['prepend']);
    }

    public function testNormalizeMapWithDatatype(): void
    {
        $result = $this->mapperConfig->normalizeMap('source = dcterms:source ^^uri');
        $this->assertContains('uri', $result['to']['datatype']);
    }

    public function testNormalizeMapWithLanguage(): void
    {
        $result = $this->mapperConfig->normalizeMap('title = dcterms:title @en');
        $this->assertSame('en', $result['to']['language']);
    }

    public function testNormalizeMapWithVisibility(): void
    {
        $result = $this->mapperConfig->normalizeMap('source = dcterms:description §private');
        $this->assertFalse($result['to']['is_public']);
    }

    public function testNormalizeMapWithPublicVisibility(): void
    {
        $result = $this->mapperConfig->normalizeMap('source = dcterms:description §public');
        $this->assertTrue($result['to']['is_public']);
    }

    public function testNormalizeMapMultipleDataTypes(): void
    {
        $result = $this->mapperConfig->normalizeMap('source = dcterms:identifier ^^uri ^^literal');
        $this->assertContains('uri', $result['to']['datatype']);
        $this->assertContains('literal', $result['to']['datatype']);
    }

    public function testNormalizeMapFieldSpecWithAll(): void
    {
        $result = $this->mapperConfig->normalizeMap('source = dcterms:title ^^literal @fr §public');
        $this->assertSame('dcterms:title', $result['to']['field']);
        $this->assertContains('literal', $result['to']['datatype']);
        $this->assertSame('fr', $result['to']['language']);
        $this->assertTrue($result['to']['is_public']);
    }

    public function testNormalizeMapWithRawValue(): void
    {
        $result = $this->mapperConfig->normalizeMap('~ = dcterms:type ~ "Static Type"');
        $this->assertArrayHasKey('mod', $result);
    }

    public function testNormalizeMapComplexPattern(): void
    {
        $result = $this->mapperConfig->normalizeMap('~ = dcterms:identifier ~ https://example.org/{{ value }}/item');
        $this->assertStringContainsString('https://example.org/', $result['mod']['pattern']);
    }

    // normalizeMaps tests

    public function testNormalizeMapsEmpty(): void
    {
        $result = $this->mapperConfig->normalizeMaps([]);
        $this->assertSame([], $result);
    }

    public function testNormalizeMapsMultiple(): void
    {
        $input = [
            'dcterms:title',
            'dcterms:creator',
        ];
        $result = $this->mapperConfig->normalizeMaps($input);
        $this->assertCount(2, $result);
    }

    // getSectionSetting tests

    public function testGetSectionSettingDefault(): void
    {
        $this->mapperConfig->__invoke('default_setting_test', '');
        $result = $this->mapperConfig->getSectionSetting('info', 'nonexistent', 'default_value');
        $this->assertSame('default_value', $result);
    }

    public function testGetSectionSettingFromArray(): void
    {
        $input = [
            'info' => [
                'label' => 'Test',
                'querier' => 'jsdot',
            ],
        ];
        $this->mapperConfig->__invoke('info_test', $input);
        $this->assertSame('Test', $this->mapperConfig->getSectionSetting('info', 'label'));
        $this->assertSame('jsdot', $this->mapperConfig->getSectionSetting('info', 'querier'));
    }

    // Caching tests

    public function testMappingIsCached(): void
    {
        $input = ['info' => ['label' => 'Cached']];

        // First call.
        $result1 = $this->mapperConfig->__invoke('cache_test', $input);

        // Second call should return cached.
        $result2 = $this->mapperConfig->__invoke('cache_test', null);

        $this->assertSame($result1, $result2);
    }

    public function testDifferentNamesAreSeparate(): void
    {
        $input1 = ['info' => ['label' => 'First']];
        $input2 = ['info' => ['label' => 'Second']];

        $this->mapperConfig->__invoke('name1', $input1);
        $this->mapperConfig->__invoke('name2', $input2);

        $this->assertSame('First', $this->mapperConfig->getMapping('name1')['info']['label']);
        $this->assertSame('Second', $this->mapperConfig->getMapping('name2')['info']['label']);
    }

    // XML map normalization test (using normalizeMap which can handle XML strings)

    public function testNormalizeMapXmlString(): void
    {
        $xmlMap = '<map><from xpath="//title"/><to field="dcterms:title"/></map>';
        $result = $this->mapperConfig->normalizeMap($xmlMap);
        $this->assertSame('dcterms:title', $result['to']['field']);
        $this->assertSame('xpath', $result['from']['querier']);
    }
}
