<?php declare(strict_types=1);

namespace MapperTest\Stdlib;

use Mapper\Stdlib\MapNormalizer;
use MapperTest\MapperTestTrait;
use Omeka\Test\AbstractHttpControllerTestCase;

/**
 * Tests for the MapNormalizer class.
 *
 * @covers \Mapper\Stdlib\MapNormalizer
 */
class MapNormalizerTest extends AbstractHttpControllerTestCase
{
    use MapperTestTrait;

    protected MapNormalizer $normalizer;

    public function setUp(): void
    {
        parent::setUp();
        $this->loginAdmin();

        $this->normalizer = $this->getServiceLocator()->get(MapNormalizer::class);
    }

    // Basic tests

    public function testEmptyMapReturnsEmptyStructure(): void
    {
        $result = $this->normalizer->normalize([]);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('from', $result);
        $this->assertArrayHasKey('to', $result);
        $this->assertArrayHasKey('mod', $result);
        // Qualifiers are now in 'to', not a separate 'qual' section.
        $this->assertArrayHasKey('datatype', $result['to']);
        $this->assertArrayHasKey('language', $result['to']);
        $this->assertArrayHasKey('is_public', $result['to']);
    }

    public function testNullMapReturnsEmptyStructure(): void
    {
        $result = $this->normalizer->normalize(null);
        $this->assertSame(MapNormalizer::EMPTY_MAP, $result);
    }

    // Source type tests

    public function testSourceTypeXpath(): void
    {
        $result = $this->normalizer->normalize([
            'from' => ['xpath' => '//title'],
        ]);
        $this->assertSame(MapNormalizer::SOURCE_XPATH, $result['from']['type']);
        $this->assertSame('//title', $result['from']['path']);
    }

    public function testSourceTypeJsdot(): void
    {
        $result = $this->normalizer->normalize([
            'from' => ['jsdot' => 'record.title'],
        ]);
        $this->assertSame(MapNormalizer::SOURCE_JSDOT, $result['from']['type']);
        $this->assertSame('record.title', $result['from']['path']);
    }

    public function testSourceTypeJsonpath(): void
    {
        $result = $this->normalizer->normalize([
            'from' => ['jsonpath' => '$.record.title'],
        ]);
        $this->assertSame(MapNormalizer::SOURCE_JSONPATH, $result['from']['type']);
        $this->assertSame('$.record.title', $result['from']['path']);
    }

    public function testSourceTypeJmespath(): void
    {
        $result = $this->normalizer->normalize([
            'from' => ['jmespath' => 'record.title'],
        ]);
        $this->assertSame(MapNormalizer::SOURCE_JMESPATH, $result['from']['type']);
        $this->assertSame('record.title', $result['from']['path']);
    }

    public function testSourceTypeIndex(): void
    {
        $result = $this->normalizer->normalize([
            'from' => ['index' => 0],
        ]);
        $this->assertSame(MapNormalizer::SOURCE_INDEX, $result['from']['type']);
        $this->assertSame(0, $result['from']['index']);
    }

    public function testSourceTypeNone(): void
    {
        $result = $this->normalizer->normalize([
            'from' => [],
        ]);
        $this->assertSame(MapNormalizer::SOURCE_NONE, $result['from']['type']);
    }

    // Default querier tests

    public function testDefaultQuerierXpath(): void
    {
        $this->normalizer->setDefaultQuerier('xpath');
        $result = $this->normalizer->normalize([
            'from' => ['path' => '//title'],
        ]);
        $this->assertSame(MapNormalizer::SOURCE_XPATH, $result['from']['type']);
    }

    public function testDefaultQuerierJsdot(): void
    {
        $this->normalizer->setDefaultQuerier('jsdot');
        $result = $this->normalizer->normalize([
            'from' => ['path' => 'record.title'],
        ]);
        $this->assertSame(MapNormalizer::SOURCE_JSDOT, $result['from']['type']);
    }

    // Target field tests

    public function testTargetFieldFromString(): void
    {
        $result = $this->normalizer->normalize([
            'to' => 'dcterms:title',
        ]);
        $this->assertSame('dcterms:title', $result['to']['field']);
        $this->assertIsInt($result['to']['property_id']);
    }

    public function testTargetFieldFromArray(): void
    {
        $result = $this->normalizer->normalize([
            'to' => ['field' => 'dcterms:title'],
        ]);
        $this->assertSame('dcterms:title', $result['to']['field']);
    }

    public function testTargetFieldWithPropertyId(): void
    {
        $result = $this->normalizer->normalize([
            'to' => ['field' => 'dcterms:title', 'property_id' => 1],
        ]);
        $this->assertSame(1, $result['to']['property_id']);
    }

    // Field specification parsing

    public function testFieldSpecWithDatatype(): void
    {
        $result = $this->normalizer->normalize([
            'to' => 'dcterms:title ^^literal',
        ]);
        $this->assertSame('dcterms:title', $result['to']['field']);
        $this->assertSame(['literal'], $result['to']['datatype']);
    }

    public function testFieldSpecWithMultipleDatatypes(): void
    {
        $result = $this->normalizer->normalize([
            'to' => 'dcterms:title ^^literal ^^uri',
        ]);
        $this->assertSame(['literal', 'uri'], $result['to']['datatype']);
    }

    public function testFieldSpecWithLanguage(): void
    {
        $result = $this->normalizer->normalize([
            'to' => 'dcterms:title @fra',
        ]);
        $this->assertSame('fra', $result['to']['language']);
    }

    public function testFieldSpecWithVisibilityPublic(): void
    {
        $result = $this->normalizer->normalize([
            'to' => 'dcterms:title §public',
        ]);
        $this->assertTrue($result['to']['is_public']);
    }

    public function testFieldSpecWithVisibilityPrivate(): void
    {
        $result = $this->normalizer->normalize([
            'to' => 'dcterms:title §private',
        ]);
        $this->assertFalse($result['to']['is_public']);
    }

    public function testFieldSpecComplete(): void
    {
        $result = $this->normalizer->normalize([
            'to' => 'dcterms:title ^^literal @fra §public',
        ]);
        $this->assertSame('dcterms:title', $result['to']['field']);
        $this->assertSame(['literal'], $result['to']['datatype']);
        $this->assertSame('fra', $result['to']['language']);
        $this->assertTrue($result['to']['is_public']);
    }

    // Qualifiers tests

    public function testQualifiersFromToSection(): void
    {
        $result = $this->normalizer->normalize([
            'to' => [
                'field' => 'dcterms:title',
                'datatype' => 'literal',
                'language' => 'fra',
                'is_public' => true,
            ],
        ]);
        $this->assertSame(['literal'], $result['to']['datatype']);
        $this->assertSame('fra', $result['to']['language']);
        $this->assertTrue($result['to']['is_public']);
    }

    public function testQualifiersWithArrayDatatype(): void
    {
        $result = $this->normalizer->normalize([
            'to' => [
                'field' => 'dcterms:title',
                'datatype' => ['literal', 'uri'],
            ],
        ]);
        $this->assertSame(['literal', 'uri'], $result['to']['datatype']);
    }

    // Mod/Transform tests

    public function testModTypeNone(): void
    {
        $result = $this->normalizer->normalize([
            'mod' => [],
        ]);
        $this->assertSame(MapNormalizer::TRANSFORM_NONE, $result['mod']['type']);
    }

    public function testModTypeRaw(): void
    {
        $result = $this->normalizer->normalize([
            'mod' => ['raw' => 'fixed value'],
        ]);
        $this->assertSame(MapNormalizer::TRANSFORM_RAW, $result['mod']['type']);
        $this->assertSame('fixed value', $result['mod']['raw']);
    }

    public function testModTypeVal(): void
    {
        $result = $this->normalizer->normalize([
            'mod' => ['val' => 'conditional value'],
        ]);
        $this->assertSame(MapNormalizer::TRANSFORM_RAW, $result['mod']['type']);
        $this->assertSame('conditional value', $result['mod']['raw']);
    }

    public function testModTypePattern(): void
    {
        $result = $this->normalizer->normalize([
            'mod' => ['pattern' => '{{ value|upper }}'],
        ]);
        $this->assertSame(MapNormalizer::TRANSFORM_PATTERN, $result['mod']['type']);
        $this->assertSame('{{ value|upper }}', $result['mod']['pattern']);
    }

    public function testModPrependAppend(): void
    {
        $result = $this->normalizer->normalize([
            'mod' => ['prepend' => 'prefix-', 'append' => '-suffix'],
        ]);
        $this->assertSame('prefix-', $result['mod']['prepend']);
        $this->assertSame('-suffix', $result['mod']['append']);
    }

    public function testModStringAsPattern(): void
    {
        $result = $this->normalizer->normalize([
            'mod' => '{{ value|upper }}',
        ]);
        $this->assertSame(MapNormalizer::TRANSFORM_PATTERN, $result['mod']['type']);
        $this->assertSame('{{ value|upper }}', $result['mod']['pattern']);
    }

    // INI string parsing

    public function testIniSimpleLine(): void
    {
        $this->normalizer->setDefaultQuerier('xpath');
        $result = $this->normalizer->normalize('//title = dcterms:title');
        $this->assertSame('//title', $result['from']['path']);
        $this->assertSame('dcterms:title', $result['to']['field']);
    }

    public function testIniLineWithDatatype(): void
    {
        $this->normalizer->setDefaultQuerier('xpath');
        $result = $this->normalizer->normalize('//title = dcterms:title ^^literal');
        $this->assertSame(['literal'], $result['to']['datatype']);
    }

    public function testIniLineWithPattern(): void
    {
        $this->normalizer->setDefaultQuerier('xpath');
        $result = $this->normalizer->normalize('//title = dcterms:title ~ {{ value|upper }}');
        $this->assertSame(MapNormalizer::TRANSFORM_PATTERN, $result['mod']['type']);
        $this->assertSame('{{ value|upper }}', $result['mod']['pattern']);
    }

    public function testIniRawValue(): void
    {
        $result = $this->normalizer->normalize('dcterms:title = "Fixed Title"');
        $this->assertSame(MapNormalizer::TRANSFORM_RAW, $result['mod']['type']);
        $this->assertSame('Fixed Title', $result['mod']['raw']);
        $this->assertSame('dcterms:title', $result['to']['field']);
    }

    public function testIniRawValueSingleQuotes(): void
    {
        $result = $this->normalizer->normalize("dcterms:title = 'Fixed Title'");
        $this->assertSame(MapNormalizer::TRANSFORM_RAW, $result['mod']['type']);
        $this->assertSame('Fixed Title', $result['mod']['raw']);
    }

    // XML element parsing

    public function testXmlElementParsing(): void
    {
        $xml = <<<'XML'
<map>
    <from xpath="//title"/>
    <to field="dcterms:title" datatype="literal" language="fra"/>
</map>
XML;
        $element = new \SimpleXMLElement($xml);
        $result = $this->normalizer->normalizeFromXmlElement($element);

        $this->assertSame(MapNormalizer::SOURCE_XPATH, $result['from']['type']);
        $this->assertSame('//title', $result['from']['path']);
        $this->assertSame('dcterms:title', $result['to']['field']);
        $this->assertSame(['literal'], $result['to']['datatype']);
        $this->assertSame('fra', $result['to']['language']);
    }

    public function testXmlElementWithMod(): void
    {
        $xml = <<<'XML'
<map>
    <from xpath="//title"/>
    <to field="dcterms:title"/>
    <mod pattern="{{ value|upper }}"/>
</map>
XML;
        $element = new \SimpleXMLElement($xml);
        $result = $this->normalizer->normalizeFromXmlElement($element);

        $this->assertSame(MapNormalizer::TRANSFORM_PATTERN, $result['mod']['type']);
        $this->assertSame('{{ value|upper }}', $result['mod']['pattern']);
    }

    public function testXmlElementWithRaw(): void
    {
        $xml = <<<'XML'
<map>
    <to field="dcterms:title"/>
    <mod raw="Fixed value"/>
</map>
XML;
        $element = new \SimpleXMLElement($xml);
        $result = $this->normalizer->normalizeFromXmlElement($element);

        $this->assertSame(MapNormalizer::TRANSFORM_RAW, $result['mod']['type']);
        $this->assertSame('Fixed value', $result['mod']['raw']);
    }

    public function testXmlElementWithVisibility(): void
    {
        $xml = <<<'XML'
<map>
    <from xpath="//title"/>
    <to field="dcterms:title" visibility="private"/>
</map>
XML;
        $element = new \SimpleXMLElement($xml);
        $result = $this->normalizer->normalizeFromXmlElement($element);

        $this->assertFalse($result['to']['is_public']);
    }

    // normalizeAll tests

    public function testNormalizeAllWithArray(): void
    {
        $maps = [
            ['from' => ['xpath' => '//title'], 'to' => ['field' => 'dcterms:title']],
            ['from' => ['xpath' => '//creator'], 'to' => ['field' => 'dcterms:creator']],
        ];
        $result = $this->normalizer->normalizeAll($maps);

        $this->assertCount(2, $result);
        $this->assertSame('//title', $result[0]['from']['path']);
        $this->assertSame('//creator', $result[1]['from']['path']);
    }

    // Legacy conversion tests

    public function testConvertFromLegacy(): void
    {
        $legacy = [
            'from' => ['querier' => 'xpath', 'path' => '//title'],
            'to' => [
                'field' => 'dcterms:title',
                'property_id' => 1,
                'datatype' => ['literal'],
                'language' => 'fra',
                'is_public' => true,
            ],
            'mod' => [
                'pattern' => '{{ value }}',
                'prepend' => 'prefix-',
            ],
        ];
        $result = $this->normalizer->convertFromLegacy($legacy);

        $this->assertSame('xpath', $result['from']['type']);
        $this->assertSame(['literal'], $result['to']['datatype']);
        $this->assertSame('fra', $result['to']['language']);
        $this->assertSame(MapNormalizer::TRANSFORM_PATTERN, $result['mod']['type']);
    }

    public function testConvertFromLegacyAlreadyCanonical(): void
    {
        $canonical = MapNormalizer::EMPTY_MAP;
        $canonical['from']['type'] = MapNormalizer::SOURCE_XPATH;
        $canonical['from']['path'] = '//title';

        $result = $this->normalizer->convertFromLegacy($canonical);
        $this->assertSame($canonical, $result);
    }

    // Edge cases

    public function testEmptyStringInput(): void
    {
        $result = $this->normalizer->normalize('');
        $this->assertSame(MapNormalizer::EMPTY_MAP, $result);
    }

    public function testWhitespaceStringInput(): void
    {
        $result = $this->normalizer->normalize('   ');
        $this->assertSame(MapNormalizer::EMPTY_MAP, $result);
    }

    public function testFieldSpecWithOnlyDatatype(): void
    {
        $result = $this->normalizer->normalize([
            'to' => '^^literal',
        ]);
        $this->assertSame(['literal'], $result['to']['datatype']);
        $this->assertNull($result['to']['field']);
    }

    public function testSourceWithIndexOption(): void
    {
        $result = $this->normalizer->normalize([
            'from' => [],
        ], ['index' => 5]);
        $this->assertSame(MapNormalizer::SOURCE_INDEX, $result['from']['type']);
        $this->assertSame(5, $result['from']['index']);
    }

    public function testListOfMapsDetection(): void
    {
        $maps = [
            ['from' => ['xpath' => '//a']],
            ['from' => ['xpath' => '//b']],
        ];
        $result = $this->normalizer->normalize($maps);
        // A list of maps should be processed recursively.
        $this->assertCount(2, $result);
    }
}
