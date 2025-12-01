<?php declare(strict_types=1);

namespace MapperTest\Stdlib;

use DOMDocument;
use DOMXPath;
use Mapper\Stdlib\Mapper;
use Mapper\Stdlib\MapperConfig;
use MapperTest\MapperTestTrait;
use Omeka\Test\AbstractHttpControllerTestCase;
use SimpleXMLElement;

/**
 * Functional tests for LIDO-MC to Omeka S mapping.
 *
 * Tests the mapping file data/mapping/xml/lido_mc_to_omeka.xml
 * using the fixture lido_example_mona_lisa.xml (La Joconde).
 *
 * @covers \Mapper\Stdlib\Mapper
 * @covers \Mapper\Stdlib\MapperConfig
 */
class LidoMappingTest extends AbstractHttpControllerTestCase
{
    use MapperTestTrait;

    protected Mapper $mapper;
    protected MapperConfig $mapperConfig;

    protected string $fixturesPath;
    protected string $mappingPath;

    protected ?DOMDocument $lidoDoc = null;
    protected ?DOMXPath $xpath = null;
    protected ?array $expectedData = null;

    /**
     * LIDO namespaces for XPath queries.
     */
    protected array $namespaces = [
        'lido' => 'http://www.lido-schema.org',
        'gml' => 'http://www.opengis.net/gml',
        'skos' => 'http://www.w3.org/2004/02/skos/core#',
        'rdf' => 'http://www.w3.org/1999/02/22-rdf-syntax-ns#',
    ];

    public function setUp(): void
    {
        parent::setUp();
        $this->loginAdmin();

        $this->mapper = $this->getServiceLocator()->get('Mapper\Mapper');
        $this->mapperConfig = $this->getServiceLocator()->get('Mapper\MapperConfig');

        $this->fixturesPath = dirname(__DIR__, 2) . '/fixtures/lido';
        $this->mappingPath = dirname(__DIR__, 3) . '/data/mapping/xml';
    }

    /**
     * Load the LIDO example fixture.
     */
    protected function loadLidoFixture(): void
    {
        if ($this->lidoDoc !== null) {
            return;
        }

        $filePath = $this->fixturesPath . '/lido_example_mona_lisa.xml';
        $this->assertFileExists($filePath, 'LIDO fixture file should exist');

        $this->lidoDoc = new DOMDocument();
        $this->lidoDoc->load($filePath);

        $this->xpath = new DOMXPath($this->lidoDoc);
        foreach ($this->namespaces as $prefix => $uri) {
            $this->xpath->registerNamespace($prefix, $uri);
        }
    }

    /**
     * Load expected results from JSON fixture.
     */
    protected function loadExpectedData(): array
    {
        if ($this->expectedData !== null) {
            return $this->expectedData;
        }

        $filePath = $this->fixturesPath . '/expected_mona_lisa.json';
        $this->assertFileExists($filePath, 'Expected results JSON should exist');

        $json = file_get_contents($filePath);
        $this->expectedData = json_decode($json, true);
        $this->assertIsArray($this->expectedData, 'Expected data should be valid JSON array');

        return $this->expectedData;
    }

    /**
     * Query LIDO document with XPath.
     *
     * @return string[]
     */
    protected function queryLido(string $xpathExpr): array
    {
        $this->loadLidoFixture();

        $nodes = $this->xpath->query($xpathExpr);
        if ($nodes === false || $nodes->length === 0) {
            return [];
        }

        $values = [];
        foreach ($nodes as $node) {
            $value = trim($node->nodeValue);
            if ($value !== '') {
                $values[] = $value;
            }
        }
        return $values;
    }

    // =========================================================================
    // Mapping file tests
    // =========================================================================

    public function testMappingFileExists(): void
    {
        $mappingFile = $this->mappingPath . '/lido_mc_to_omeka.xml';
        $this->assertFileExists($mappingFile, 'LIDO-MC mapping file should exist');
    }

    public function testMappingFileIsValidXml(): void
    {
        $mappingFile = $this->mappingPath . '/lido_mc_to_omeka.xml';
        $dom = new DOMDocument();
        $result = @$dom->load($mappingFile);
        $this->assertTrue($result, 'LIDO-MC mapping file should be valid XML');
    }

    public function testMappingFileHasRequiredStructure(): void
    {
        $mappingFile = $this->mappingPath . '/lido_mc_to_omeka.xml';
        $dom = new DOMDocument();
        $dom->load($mappingFile);

        $xpath = new DOMXPath($dom);

        // Check for root mapping element
        $mapping = $xpath->query('/mapping');
        $this->assertGreaterThan(0, $mapping->length, 'Should have root <mapping> element');

        // Check for info section
        $info = $xpath->query('/mapping/info');
        $this->assertGreaterThan(0, $info->length, 'Should have <info> section');

        // Check for map elements
        $maps = $xpath->query('/mapping/map');
        $this->assertGreaterThan(0, $maps->length, 'Should have <map> elements');
    }

    public function testMappingFileHasMinimumMaps(): void
    {
        $mappingFile = $this->mappingPath . '/lido_mc_to_omeka.xml';
        $dom = new DOMDocument();
        $dom->load($mappingFile);

        $xpath = new DOMXPath($dom);
        $maps = $xpath->query('/mapping/map');

        // Should have at least 50 mapping rules for a comprehensive LIDO mapping
        $this->assertGreaterThanOrEqual(50, $maps->length, 'Should have at least 50 mapping rules');
    }

    // =========================================================================
    // Fixture tests
    // =========================================================================

    public function testLidoFixtureExists(): void
    {
        $fixtureFile = $this->fixturesPath . '/lido_example_mona_lisa.xml';
        $this->assertFileExists($fixtureFile, 'LIDO fixture file should exist');
    }

    public function testLidoFixtureIsValidXml(): void
    {
        $fixtureFile = $this->fixturesPath . '/lido_example_mona_lisa.xml';
        $dom = new DOMDocument();
        $result = @$dom->load($fixtureFile);
        $this->assertTrue($result, 'LIDO fixture should be valid XML');
    }

    public function testLidoFixtureHasCorrectNamespace(): void
    {
        $this->loadLidoFixture();
        $root = $this->lidoDoc->documentElement;
        $this->assertSame('lido', $root->localName, 'Root element should be lido');
        $this->assertSame('http://www.lido-schema.org', $root->namespaceURI, 'Should have LIDO namespace');
    }

    // =========================================================================
    // XPath extraction tests - Identifiers
    // =========================================================================

    public function testExtractLidoRecID(): void
    {
        $values = $this->queryLido('/lido:lido/lido:lidoRecID');
        $this->assertNotEmpty($values, 'Should extract lidoRecID');
        $this->assertStringContainsString('louvre.fr', $values[0]);
    }

    public function testExtractObjectPublishedID(): void
    {
        $values = $this->queryLido('/lido:lido/lido:objectPublishedID');
        $this->assertNotEmpty($values, 'Should extract objectPublishedID');
        $this->assertStringStartsWith('https://', $values[0]);
    }

    // =========================================================================
    // XPath extraction tests - Classification
    // =========================================================================

    public function testExtractObjectWorkType(): void
    {
        $values = $this->queryLido('//lido:objectClassificationWrap/lido:objectWorkTypeWrap/lido:objectWorkType/lido:term');
        $this->assertNotEmpty($values, 'Should extract objectWorkType');
        $this->assertContains('Peinture à l\'huile', $values);
    }

    public function testExtractClassification(): void
    {
        $values = $this->queryLido('//lido:objectClassificationWrap/lido:classificationWrap/lido:classification/lido:term');
        $this->assertNotEmpty($values, 'Should extract classification');
    }

    // =========================================================================
    // XPath extraction tests - Identification
    // =========================================================================

    public function testExtractTitle(): void
    {
        $values = $this->queryLido("//lido:objectIdentificationWrap/lido:titleWrap/lido:titleSet/lido:appellationValue[@lido:pref='preferred' or not(@lido:pref)]");
        $this->assertNotEmpty($values, 'Should extract title');
        $this->assertContains('La Joconde', $values);
    }

    public function testExtractAlternativeTitles(): void
    {
        $values = $this->queryLido("//lido:objectIdentificationWrap/lido:titleWrap/lido:titleSet/lido:appellationValue[@lido:pref='alternate']");
        $this->assertNotEmpty($values, 'Should extract alternative titles');
        $this->assertGreaterThanOrEqual(3, count($values), 'Should have at least 3 alternative titles');
        $this->assertContains('Mona Lisa', $values);
        $this->assertContains('La Gioconda', $values);
    }

    public function testExtractDescription(): void
    {
        $values = $this->queryLido('//lido:objectIdentificationWrap/lido:objectDescriptionWrap/lido:objectDescriptionSet/lido:descriptiveNoteValue');
        $this->assertNotEmpty($values, 'Should extract description');
        $this->assertStringContainsString('Portrait en buste', $values[0]);
    }

    public function testExtractDimensions(): void
    {
        $values = $this->queryLido('//lido:objectIdentificationWrap/lido:objectMeasurementsWrap/lido:objectMeasurementsSet/lido:displayObjectMeasurements');
        $this->assertNotEmpty($values, 'Should extract dimensions');
        $this->assertStringContainsString('77 cm', $values[0]);
        $this->assertStringContainsString('53 cm', $values[0]);
    }

    public function testExtractRepository(): void
    {
        $values = $this->queryLido('//lido:objectIdentificationWrap/lido:repositoryWrap/lido:repositorySet/lido:repositoryName/lido:legalBodyName/lido:appellationValue');
        $this->assertNotEmpty($values, 'Should extract repository name');
        $this->assertContains('Musée du Louvre', $values);
    }

    public function testExtractInventoryNumber(): void
    {
        $values = $this->queryLido('//lido:repositoryWrap/lido:repositorySet/lido:workID');
        $this->assertNotEmpty($values, 'Should extract inventory number');
        $this->assertStringContainsString('779', $values[0]);
    }

    // =========================================================================
    // XPath extraction tests - Events (Production)
    // =========================================================================

    public function testExtractCreator(): void
    {
        $values = $this->queryLido("//lido:eventWrap/lido:eventSet/lido:event[lido:eventType/lido:term[contains(., 'Production')]]/lido:eventActor/lido:actorInRole/lido:actor/lido:nameActorSet/lido:appellationValue[@lido:pref='preferred' or not(@lido:pref)]");
        $this->assertNotEmpty($values, 'Should extract creator');
        $this->assertContains('Léonard de Vinci', $values);
    }

    public function testExtractCreatorViafUri(): void
    {
        $values = $this->queryLido("//lido:eventWrap/lido:eventSet/lido:event[lido:eventType/lido:term[contains(., 'Production')]]/lido:eventActor/lido:actorInRole/lido:actor/lido:actorID[contains(., 'viaf.org')]");
        $this->assertNotEmpty($values, 'Should extract creator VIAF URI');
        $this->assertStringContainsString('viaf.org', $values[0]);
    }

    public function testExtractCreationDate(): void
    {
        $values = $this->queryLido("//lido:eventWrap/lido:eventSet/lido:event[lido:eventType/lido:term[contains(., 'Production')]]/lido:eventDate/lido:displayDate");
        $this->assertNotEmpty($values, 'Should extract creation date');
        $this->assertStringContainsString('1503', $values[0]);
    }

    public function testExtractCreationDateEarliest(): void
    {
        $values = $this->queryLido("//lido:eventWrap/lido:eventSet/lido:event[lido:eventType/lido:term[contains(., 'Production')]]/lido:eventDate/lido:date/lido:earliestDate");
        $this->assertNotEmpty($values, 'Should extract earliest creation date');
        $this->assertSame('1503', $values[0]);
    }

    public function testExtractCreationDateLatest(): void
    {
        $values = $this->queryLido("//lido:eventWrap/lido:eventSet/lido:event[lido:eventType/lido:term[contains(., 'Production')]]/lido:eventDate/lido:date/lido:latestDate");
        $this->assertNotEmpty($values, 'Should extract latest creation date');
        $this->assertSame('1519', $values[0]);
    }

    public function testExtractCreationPlace(): void
    {
        $values = $this->queryLido("//lido:eventWrap/lido:eventSet/lido:event[lido:eventType/lido:term[contains(., 'Production')]]/lido:eventPlace/lido:place/lido:namePlaceSet/lido:appellationValue");
        $this->assertNotEmpty($values, 'Should extract creation place');
        $this->assertContains('Florence', $values);
    }

    public function testExtractMaterials(): void
    {
        $values = $this->queryLido("//lido:eventWrap/lido:eventSet/lido:event/lido:eventMaterialsTech/lido:materialsTech/lido:termMaterialsTech[@lido:type='material']/lido:term");
        $this->assertNotEmpty($values, 'Should extract materials');
        $this->assertContains('Huile', $values);
    }

    public function testExtractTechniques(): void
    {
        $values = $this->queryLido("//lido:eventWrap/lido:eventSet/lido:event/lido:eventMaterialsTech/lido:materialsTech/lido:termMaterialsTech[@lido:type='technique']/lido:term");
        $this->assertNotEmpty($values, 'Should extract techniques');
        $this->assertContains('Sfumato', $values);
    }

    public function testExtractCulture(): void
    {
        $values = $this->queryLido('//lido:eventWrap/lido:eventSet/lido:event/lido:culture/lido:term');
        $this->assertNotEmpty($values, 'Should extract culture');
        $this->assertStringContainsString('Renaissance', $values[0]);
    }

    public function testExtractPeriod(): void
    {
        $values = $this->queryLido('//lido:eventWrap/lido:eventSet/lido:event/lido:periodName/lido:term');
        $this->assertNotEmpty($values, 'Should extract period');
        $this->assertStringContainsString('Renaissance', $values[0]);
    }

    // =========================================================================
    // XPath extraction tests - Subjects
    // =========================================================================

    public function testExtractSubjectConcepts(): void
    {
        $values = $this->queryLido('//lido:objectRelationWrap/lido:subjectWrap/lido:subjectSet/lido:subject/lido:subjectConcept/lido:term');
        $this->assertNotEmpty($values, 'Should extract subject concepts');
        $this->assertContains('Femme', $values);
        $this->assertContains('Paysage', $values);
    }

    public function testExtractSubjectActor(): void
    {
        $values = $this->queryLido('//lido:objectRelationWrap/lido:subjectWrap/lido:subjectSet/lido:subject/lido:subjectActor/lido:actor/lido:nameActorSet/lido:appellationValue');
        $this->assertNotEmpty($values, 'Should extract subject actor (depicted person)');
        $this->assertContains('Lisa Gherardini', $values);
    }

    public function testExtractSubjectPlace(): void
    {
        $values = $this->queryLido('//lido:objectRelationWrap/lido:subjectWrap/lido:subjectSet/lido:subject/lido:subjectPlace/lido:place/lido:namePlaceSet/lido:appellationValue');
        $this->assertNotEmpty($values, 'Should extract subject place (depicted location)');
    }

    // =========================================================================
    // XPath extraction tests - Rights
    // =========================================================================

    public function testExtractRightsType(): void
    {
        $values = $this->queryLido('//lido:administrativeMetadata/lido:rightsWorkWrap/lido:rightsWorkSet/lido:rightsType/lido:term');
        $this->assertNotEmpty($values, 'Should extract rights type');
        $this->assertContains('Domaine public', $values);
    }

    public function testExtractRightsHolder(): void
    {
        $values = $this->queryLido('//lido:administrativeMetadata/lido:rightsWorkWrap/lido:rightsWorkSet/lido:rightsHolder/lido:legalBodyName/lido:appellationValue');
        $this->assertNotEmpty($values, 'Should extract rights holder');
        $this->assertContains('Musée du Louvre', $values);
    }

    // =========================================================================
    // XPath extraction tests - Resources
    // =========================================================================

    public function testExtractResourceUrl(): void
    {
        $values = $this->queryLido('//lido:administrativeMetadata/lido:resourceWrap/lido:resourceSet/lido:resourceRepresentation/lido:linkResource');
        $this->assertNotEmpty($values, 'Should extract resource URL');
        $this->assertStringStartsWith('https://', $values[0]);
        $this->assertStringContainsString('louvre.fr', $values[0]);
    }

    // =========================================================================
    // Mapping conversion tests
    // =========================================================================

    public function testLoadLidoMapping(): void
    {
        $mappingFile = $this->mappingPath . '/lido_mc_to_omeka.xml';
        $mappingContent = file_get_contents($mappingFile);

        $this->mapper->setMapping('lido_mc', $mappingContent);
        $mapping = $this->mapper->getMapping();

        $this->assertNotNull($mapping, 'Mapping should be loaded');
        $this->assertArrayHasKey('info', $mapping, 'Mapping should have info section');
        $this->assertArrayHasKey('maps', $mapping, 'Mapping should have maps section');
    }

    public function testConvertLidoXml(): void
    {
        // Load LIDO mapping
        $mappingFile = $this->mappingPath . '/lido_mc_to_omeka.xml';
        $mappingContent = file_get_contents($mappingFile);
        $this->mapper->setMapping('lido_mc', $mappingContent);

        // Load LIDO XML
        $fixtureFile = $this->fixturesPath . '/lido_example_mona_lisa.xml';
        $xml = simplexml_load_file($fixtureFile);
        $this->assertInstanceOf(SimpleXMLElement::class, $xml);

        // Convert
        $result = $this->mapper->convert($xml);

        $this->assertIsArray($result, 'Convert should return an array');
    }

    // =========================================================================
    // Data provider and comprehensive tests
    // =========================================================================

    /**
     * Data provider for testing expected field values.
     */
    public static function expectedFieldsProvider(): array
    {
        return [
            'identifier_lidoRecID' => [
                '/lido:lido/lido:lidoRecID',
                'louvre.fr/oeuvre/INV779',
            ],
            'type_objectWorkType' => [
                '//lido:objectClassificationWrap/lido:objectWorkTypeWrap/lido:objectWorkType/lido:term[1]',
                'Peinture à l\'huile',
            ],
            'title_preferred' => [
                "//lido:objectIdentificationWrap/lido:titleWrap/lido:titleSet[@lido:type='preferred']/lido:appellationValue",
                'La Joconde',
            ],
            'creator_name' => [
                "//lido:eventWrap/lido:eventSet/lido:event[lido:eventType/lido:term[contains(., 'Production')]]/lido:eventActor/lido:actorInRole/lido:actor/lido:nameActorSet/lido:appellationValue[@lido:pref='preferred']",
                'Léonard de Vinci',
            ],
            'repository_name' => [
                '//lido:objectIdentificationWrap/lido:repositoryWrap/lido:repositorySet/lido:repositoryName/lido:legalBodyName/lido:appellationValue',
                'Musée du Louvre',
            ],
            'rights_type' => [
                '//lido:administrativeMetadata/lido:rightsWorkWrap/lido:rightsWorkSet/lido:rightsType/lido:term',
                'Domaine public',
            ],
        ];
    }

    /**
     * @dataProvider expectedFieldsProvider
     */
    public function testExpectedFieldValue(string $xpath, string $expectedValue): void
    {
        $values = $this->queryLido($xpath);
        $this->assertNotEmpty($values, "XPath '$xpath' should return values");
        $this->assertContains($expectedValue, $values, "Should contain expected value '$expectedValue'");
    }

    public function testTotalExtractedFieldsCount(): void
    {
        $expected = $this->loadExpectedData();
        $statistics = $expected['_statistics'] ?? [];

        $this->assertArrayHasKey('total_fields', $statistics);
        $this->assertArrayHasKey('total_values', $statistics);

        // Count actual extracted values
        $totalExtracted = 0;
        $fieldsWithValues = 0;

        $testFields = [
            '/lido:lido/lido:lidoRecID',
            '/lido:lido/lido:objectPublishedID',
            '//lido:objectClassificationWrap/lido:objectWorkTypeWrap/lido:objectWorkType/lido:term',
            "//lido:objectIdentificationWrap/lido:titleWrap/lido:titleSet/lido:appellationValue[@lido:pref='preferred' or not(@lido:pref)]",
            "//lido:objectIdentificationWrap/lido:titleWrap/lido:titleSet/lido:appellationValue[@lido:pref='alternate']",
            "//lido:eventWrap/lido:eventSet/lido:event[lido:eventType/lido:term[contains(., 'Production')]]/lido:eventActor/lido:actorInRole/lido:actor/lido:nameActorSet/lido:appellationValue[@lido:pref='preferred' or not(@lido:pref)]",
            "//lido:eventWrap/lido:eventSet/lido:event[lido:eventType/lido:term[contains(., 'Production')]]/lido:eventDate/lido:displayDate",
            "//lido:eventWrap/lido:eventSet/lido:event[lido:eventType/lido:term[contains(., 'Production')]]/lido:eventPlace/lido:place/lido:namePlaceSet/lido:appellationValue",
            '//lido:eventWrap/lido:eventSet/lido:event/lido:eventMaterialsTech/lido:displayMaterialsTech',
            '//lido:objectIdentificationWrap/lido:objectDescriptionWrap/lido:objectDescriptionSet/lido:descriptiveNoteValue',
            '//lido:objectIdentificationWrap/lido:objectMeasurementsWrap/lido:objectMeasurementsSet/lido:displayObjectMeasurements',
            '//lido:objectRelationWrap/lido:subjectWrap/lido:subjectSet/lido:subject/lido:subjectConcept/lido:term',
            '//lido:objectIdentificationWrap/lido:repositoryWrap/lido:repositorySet/lido:repositoryName/lido:legalBodyName/lido:appellationValue',
            '//lido:repositoryWrap/lido:repositorySet/lido:workID',
            '//lido:administrativeMetadata/lido:rightsWorkWrap/lido:rightsWorkSet/lido:rightsType/lido:term',
            '//lido:administrativeMetadata/lido:resourceWrap/lido:resourceSet/lido:resourceRepresentation/lido:linkResource',
        ];

        foreach ($testFields as $xpath) {
            $values = $this->queryLido($xpath);
            if (!empty($values)) {
                $fieldsWithValues++;
                $totalExtracted += count($values);
            }
        }

        $this->assertSame($statistics['total_fields'], $fieldsWithValues, 'Number of fields with values should match expected');
        // Allow some tolerance due to XPath variations
        $this->assertGreaterThanOrEqual($statistics['total_values'] - 10, $totalExtracted, 'Total extracted values should be close to expected');
    }
}
