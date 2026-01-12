<?php declare(strict_types=1);

namespace MapperTest\Stdlib;

use Mapper\Stdlib\Mapper;
use MapperTest\MapperDbTestCase;

/**
 * Tests for IIIF mapping using example fixtures from mapping info section.
 *
 * These tests verify that the IIIF mappings work correctly with real-world
 * data from the example URLs specified in the mapping files.
 *
 * @covers \Mapper\Stdlib\Mapper
 * @covers \Mapper\Stdlib\MapperConfig
 */
class IiifMappingTest extends MapperDbTestCase
{
    protected Mapper $mapper;

    protected string $fixturesPath;

    public function setUp(): void
    {
        parent::setUp();

        $this->fixturesPath = dirname(__DIR__, 2) . '/fixtures';

        // Get the Mapper service from the container.
        $services = $this->getApplication()->getServiceManager();
        $this->mapper = $services->get('Mapper\Mapper');
    }

    // =========================================================================
    // Fixture validation tests
    // =========================================================================

    public function testBodleianFixtureExists(): void
    {
        $fixturePath = $this->fixturesPath . '/iiif/bodleian.manifest.json';
        $this->assertFileExists($fixturePath);
    }

    public function testBodleianFixtureIsValidJson(): void
    {
        $fixturePath = $this->fixturesPath . '/iiif/bodleian.manifest.json';
        $content = file_get_contents($fixturePath);
        $data = json_decode($content, true);

        $this->assertNotNull($data, 'Fixture should be valid JSON');
        $this->assertIsArray($data);
    }

    public function testBodleianFixtureHasIiifStructure(): void
    {
        $data = $this->loadBodleianFixture();

        // Check IIIF v2 required fields.
        $this->assertArrayHasKey('@context', $data);
        $this->assertArrayHasKey('@type', $data);
        $this->assertArrayHasKey('label', $data);
        $this->assertEquals('sc:Manifest', $data['@type']);
    }

    // =========================================================================
    // Mapping file tests
    // =========================================================================

    public function testIiifMappingFileExists(): void
    {
        $mappingPath = dirname(__DIR__, 3) . '/data/mapping/iiif/iiif2xx.base.jsdot.ini';
        $this->assertFileExists($mappingPath);
    }

    public function testIiifMappingFileHasExampleUrl(): void
    {
        $mappingPath = dirname(__DIR__, 3) . '/data/mapping/iiif/iiif2xx.base.jsdot.ini';
        $content = file_get_contents($mappingPath);

        $this->assertStringContainsString('example =', $content);
        $this->assertStringContainsString('iiif.bodleian.ox.ac.uk', $content);
    }

    public function testLoadIiifMapping(): void
    {
        $mappingPath = dirname(__DIR__, 3) . '/data/mapping/iiif/iiif2xx.base.jsdot.ini';
        $content = file_get_contents($mappingPath);

        $this->mapper->setMapping('iiif_test', $content);
        $mapping = $this->mapper->getMapping();

        $this->assertNotNull($mapping);
        $this->assertArrayHasKey('info', $mapping);
        $this->assertArrayHasKey('maps', $mapping);
        $this->assertNotEmpty($mapping['maps']);
    }

    // =========================================================================
    // Field extraction tests
    // =========================================================================

    public function testExtractLabel(): void
    {
        $data = $this->loadBodleianFixture();

        // Direct label extraction.
        $label = $this->mapper->extractValue($data, 'label', 'jsdot');
        $this->assertEquals('Bodleian Library MS. Ind. Inst. Misc. 22', $label);
    }

    public function testExtractDescription(): void
    {
        $data = $this->loadBodleianFixture();

        $description = $this->mapper->extractValue($data, 'description', 'jsdot');
        $this->assertStringContainsString('Kalighat paintings', $description);
    }

    public function testExtractNavDate(): void
    {
        $data = $this->loadBodleianFixture();

        $navDate = $this->mapper->extractValue($data, 'navDate', 'jsdot');
        $this->assertEquals('1875-01-01T00:00:00Z', $navDate);
    }

    public function testExtractAttribution(): void
    {
        $data = $this->loadBodleianFixture();

        $attribution = $this->mapper->extractValue($data, 'attribution', 'jsdot');
        $this->assertStringContainsString('Access to this manuscript is restricted', $attribution);
    }

    public function testExtractMetadataTitle(): void
    {
        $data = $this->loadBodleianFixture();

        // Find Title in metadata array.
        $metadata = $data['metadata'] ?? [];
        $title = null;
        foreach ($metadata as $item) {
            if (($item['label'] ?? '') === 'Title') {
                $title = $item['value'] ?? null;
                break;
            }
        }

        $this->assertNotNull($title);
        $this->assertStringContainsString('Kalighat paintings', $title);
    }

    public function testExtractMetadataShelfmark(): void
    {
        $data = $this->loadBodleianFixture();

        // Find Shelfmark in metadata array.
        $metadata = $data['metadata'] ?? [];
        $shelfmark = null;
        foreach ($metadata as $item) {
            if (($item['label'] ?? '') === 'Shelfmark') {
                $shelfmark = $item['value'] ?? null;
                break;
            }
        }

        $this->assertNotNull($shelfmark);
        $this->assertEquals('Bodleian Library MS. Ind. Inst. Misc. 22', $shelfmark);
    }

    public function testExtractMetadataDateStatement(): void
    {
        $data = $this->loadBodleianFixture();

        // Find Date Statement in metadata array.
        $metadata = $data['metadata'] ?? [];
        $date = null;
        foreach ($metadata as $item) {
            if (($item['label'] ?? '') === 'Date Statement') {
                $date = $item['value'] ?? null;
                break;
            }
        }

        $this->assertNotNull($date);
        $this->assertEquals('1875', $date);
    }

    public function testExtractMetadataSubject(): void
    {
        $data = $this->loadBodleianFixture();

        // Find Subject in metadata array.
        $metadata = $data['metadata'] ?? [];
        $subject = null;
        foreach ($metadata as $item) {
            if (($item['label'] ?? '') === 'Subject') {
                $subject = $item['value'] ?? null;
                break;
            }
        }

        $this->assertNotNull($subject);
        $this->assertStringContainsString('Kalighat', $subject);
    }

    // =========================================================================
    // Mapping conversion tests
    // =========================================================================

    public function testMappingParsesInfo(): void
    {
        $mappingPath = dirname(__DIR__, 3) . '/data/mapping/iiif/iiif2xx.base.jsdot.ini';
        $content = file_get_contents($mappingPath);

        $this->mapper->setMapping('iiif_info_test', $content);
        $mapping = $this->mapper->getMapping();

        $this->assertEquals('jsdot', $mapping['info']['querier']);
        $this->assertEquals('IIIF manifest v2', $mapping['info']['label']);
    }

    public function testMappingHasLabelMap(): void
    {
        $mappingPath = dirname(__DIR__, 3) . '/data/mapping/iiif/iiif2xx.base.jsdot.ini';
        $content = file_get_contents($mappingPath);

        $this->mapper->setMapping('iiif_label_test', $content);
        $mapping = $this->mapper->getMapping();

        // Find a map for 'label' -> 'dcterms:title'.
        $hasLabelMap = false;
        foreach ($mapping['maps'] as $map) {
            if (($map['from']['path'] ?? '') === 'label'
                && ($map['to']['field'] ?? '') === 'dcterms:title') {
                $hasLabelMap = true;
                break;
            }
        }

        $this->assertTrue($hasLabelMap, 'Mapping should have label -> dcterms:title');
    }

    public function testMappingHasDescriptionMap(): void
    {
        $mappingPath = dirname(__DIR__, 3) . '/data/mapping/iiif/iiif2xx.base.jsdot.ini';
        $content = file_get_contents($mappingPath);

        $this->mapper->setMapping('iiif_desc_test', $content);
        $mapping = $this->mapper->getMapping();

        // Find a map for 'description' -> 'dcterms:description'.
        $hasDescriptionMap = false;
        foreach ($mapping['maps'] as $map) {
            if (($map['from']['path'] ?? '') === 'description'
                && ($map['to']['field'] ?? '') === 'dcterms:description') {
                $hasDescriptionMap = true;
                break;
            }
        }

        $this->assertTrue($hasDescriptionMap, 'Mapping should have description -> dcterms:description');
    }

    // =========================================================================
    // Expected field value tests (data provider)
    // =========================================================================

    /**
     * @dataProvider expectedFieldValuesProvider
     */
    public function testExpectedFieldValue(string $path, string $expectedValue): void
    {
        $data = $this->loadBodleianFixture();
        $value = $this->mapper->extractValue($data, $path, 'jsdot');

        if ($value === null) {
            $this->fail("Path '$path' returned null");
        }

        $this->assertStringContainsString($expectedValue, (string) $value);
    }

    public function expectedFieldValuesProvider(): array
    {
        return [
            'label' => ['label', 'Bodleian Library MS. Ind. Inst. Misc. 22'],
            'description' => ['description', 'Kalighat paintings'],
            'navDate' => ['navDate', '1875'],
            '@type' => ['@type', 'sc:Manifest'],
            '@context' => ['@context', 'iiif.io/api/presentation/2'],
        ];
    }

    // =========================================================================
    // Expected output validation tests
    // =========================================================================

    public function testExpectedOutputFileExists(): void
    {
        $expectedPath = $this->fixturesPath . '/iiif/bodleian.manifest.expected.json';
        $this->assertFileExists($expectedPath);
    }

    public function testExpectedOutputIsValidJson(): void
    {
        $expectedPath = $this->fixturesPath . '/iiif/bodleian.manifest.expected.json';
        $content = file_get_contents($expectedPath);
        $data = json_decode($content, true);

        $this->assertNotNull($data, 'Expected output should be valid JSON');
        $this->assertIsArray($data);
    }

    public function testExpectedOutputHasRequiredFields(): void
    {
        $expected = $this->loadExpectedOutput();

        $this->assertArrayHasKey('dcterms:title', $expected);
        $this->assertArrayHasKey('dcterms:description', $expected);
        $this->assertArrayHasKey('dcterms:isFormatOf', $expected);
    }

    public function testExpectedTitleMatchesFixture(): void
    {
        $expected = $this->loadExpectedOutput();
        $fixture = $this->loadBodleianFixture();

        $expectedTitle = $expected['dcterms:title'][0]['@value'] ?? null;
        $fixtureLabel = $fixture['label'] ?? null;

        $this->assertNotNull($expectedTitle);
        $this->assertEquals($fixtureLabel, $expectedTitle);
    }

    public function testExpectedDescriptionMatchesFixture(): void
    {
        $expected = $this->loadExpectedOutput();
        $fixture = $this->loadBodleianFixture();

        $expectedDesc = $expected['dcterms:description'][0]['@value'] ?? null;
        $fixtureDesc = $fixture['description'] ?? null;

        $this->assertNotNull($expectedDesc);
        $this->assertEquals($fixtureDesc, $expectedDesc);
    }

    // =========================================================================
    // BnF Gallica fixture tests
    // =========================================================================

    public function testBnfFixtureExists(): void
    {
        $fixturePath = $this->fixturesPath . '/iiif/bnf.manifest.json';
        $this->assertFileExists($fixturePath);
    }

    public function testBnfFixtureIsValidJson(): void
    {
        $fixturePath = $this->fixturesPath . '/iiif/bnf.manifest.json';
        $content = file_get_contents($fixturePath);
        $data = json_decode($content, true);

        $this->assertNotNull($data, 'BnF fixture should be valid JSON');
        $this->assertIsArray($data);
    }

    public function testBnfFixtureHasIiifStructure(): void
    {
        $data = $this->loadBnfFixture();

        $this->assertArrayHasKey('@context', $data);
        $this->assertArrayHasKey('@type', $data);
        $this->assertArrayHasKey('label', $data);
        $this->assertEquals('sc:Manifest', $data['@type']);
    }

    public function testBnfExtractLabel(): void
    {
        $data = $this->loadBnfFixture();

        $label = $this->mapper->extractValue($data, 'label', 'jsdot');
        $this->assertEquals('Les très riches heures du duc de Berry', $label);
    }

    public function testBnfExtractDescription(): void
    {
        $data = $this->loadBnfFixture();

        $description = $this->mapper->extractValue($data, 'description', 'jsdot');
        $this->assertStringContainsString('Manuscrit enluminé', $description);
    }

    public function testBnfExtractAttribution(): void
    {
        $data = $this->loadBnfFixture();

        $attribution = $this->mapper->extractValue($data, 'attribution', 'jsdot');
        $this->assertEquals('Bibliothèque nationale de France', $attribution);
    }

    public function testBnfExtractNavDate(): void
    {
        $data = $this->loadBnfFixture();

        $navDate = $this->mapper->extractValue($data, 'navDate', 'jsdot');
        $this->assertEquals('1412-01-01T00:00:00Z', $navDate);
    }

    public function testBnfMetadataCreator(): void
    {
        $data = $this->loadBnfFixture();

        $metadata = $data['metadata'] ?? [];
        $creator = null;
        foreach ($metadata as $item) {
            if (($item['label'] ?? '') === 'Creator') {
                $creator = $item['value'] ?? null;
                break;
            }
        }

        $this->assertNotNull($creator);
        $this->assertStringContainsString('Limbourg', $creator);
    }

    // =========================================================================
    // Unistra IIIF fixture tests
    // =========================================================================

    public function testUnistraFixtureExists(): void
    {
        $fixturePath = $this->fixturesPath . '/iiif/unistra.manifest.json';
        $this->assertFileExists($fixturePath);
    }

    public function testUnistraFixtureIsValidJson(): void
    {
        $fixturePath = $this->fixturesPath . '/iiif/unistra.manifest.json';
        $content = file_get_contents($fixturePath);
        $data = json_decode($content, true);

        $this->assertNotNull($data, 'Unistra fixture should be valid JSON');
        $this->assertIsArray($data);
    }

    public function testUnistraFixtureHasIiifStructure(): void
    {
        $data = $this->loadUnistraFixture();

        $this->assertArrayHasKey('@context', $data);
        $this->assertArrayHasKey('@type', $data);
        $this->assertArrayHasKey('label', $data);
        $this->assertEquals('sc:Manifest', $data['@type']);
    }

    public function testUnistraExtractLabel(): void
    {
        $data = $this->loadUnistraFixture();

        $label = $this->mapper->extractValue($data, 'label', 'jsdot');
        $this->assertEquals('Abies alba : branches et cônes dressés', $label);
    }

    public function testUnistraMetadataPhotographe(): void
    {
        $data = $this->loadUnistraFixture();

        $metadata = $data['metadata'] ?? [];
        $photographe = null;
        foreach ($metadata as $item) {
            if (($item['label'] ?? '') === 'Photographe') {
                $photographe = $item['value'] ?? null;
                break;
            }
        }

        $this->assertNotNull($photographe);
        $this->assertEquals('Ourisson, Nicole', $photographe);
    }

    public function testUnistraMetadataTaxon(): void
    {
        $data = $this->loadUnistraFixture();

        $metadata = $data['metadata'] ?? [];
        $taxon = null;
        foreach ($metadata as $item) {
            if (($item['label'] ?? '') === 'Taxon') {
                $taxon = $item['value'] ?? null;
                break;
            }
        }

        $this->assertNotNull($taxon);
        $this->assertEquals('Abies alba', $taxon);
    }

    public function testUnistraMetadataEditeur(): void
    {
        $data = $this->loadUnistraFixture();

        $metadata = $data['metadata'] ?? [];
        $editeur = null;
        foreach ($metadata as $item) {
            if (($item['label'] ?? '') === 'Editeur') {
                $editeur = $item['value'] ?? null;
                break;
            }
        }

        $this->assertNotNull($editeur);
        $this->assertStringContainsString('Université de Strasbourg', $editeur);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    protected function loadBodleianFixture(): array
    {
        $fixturePath = $this->fixturesPath . '/iiif/bodleian.manifest.json';
        $content = file_get_contents($fixturePath);
        return json_decode($content, true);
    }

    protected function loadBnfFixture(): array
    {
        $fixturePath = $this->fixturesPath . '/iiif/bnf.manifest.json';
        $content = file_get_contents($fixturePath);
        return json_decode($content, true);
    }

    protected function loadUnistraFixture(): array
    {
        $fixturePath = $this->fixturesPath . '/iiif/unistra.manifest.json';
        $content = file_get_contents($fixturePath);
        return json_decode($content, true);
    }

    protected function loadExpectedOutput(): array
    {
        $expectedPath = $this->fixturesPath . '/iiif/bodleian.manifest.expected.json';
        $content = file_get_contents($expectedPath);
        return json_decode($content, true);
    }
}
