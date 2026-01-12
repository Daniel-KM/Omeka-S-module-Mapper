<?php declare(strict_types=1);

namespace MapperTest\Stdlib;

use Mapper\Stdlib\Mapper;
use MapperTest\MapperDbTestCase;

/**
 * Tests for Content-DM mapping using example fixtures from mapping info section.
 *
 * These tests verify that the Content-DM mappings work correctly with real-world
 * data from the example URLs specified in the mapping files.
 *
 * @covers \Mapper\Stdlib\Mapper
 * @covers \Mapper\Stdlib\MapperConfig
 */
class ContentDmMappingTest extends MapperDbTestCase
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

    public function testUnistraApiFixtureExists(): void
    {
        $fixturePath = $this->fixturesPath . '/content-dm/unistra.api.json';
        $this->assertFileExists($fixturePath);
    }

    public function testUnistraApiFixtureIsValidJson(): void
    {
        $fixturePath = $this->fixturesPath . '/content-dm/unistra.api.json';
        $content = file_get_contents($fixturePath);
        $data = json_decode($content, true);

        $this->assertNotNull($data, 'Fixture should be valid JSON');
        $this->assertIsArray($data);
    }

    public function testUnistraApiFixtureHasContentDmStructure(): void
    {
        $data = $this->loadUnistraApiFixture();

        // Check Content-DM API required fields.
        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('collectionAlias', $data);
        $this->assertArrayHasKey('fields', $data);
        $this->assertArrayHasKey('itemLink', $data);
    }

    // =========================================================================
    // Mapping file tests
    // =========================================================================

    public function testContentDmBaseMappingFileExists(): void
    {
        $mappingPath = dirname(__DIR__, 3) . '/data/mapping/content-dm/content-dm.base.jmespath.ini';
        $this->assertFileExists($mappingPath);
    }

    public function testContentDmUnistraMappingFileExists(): void
    {
        $mappingPath = dirname(__DIR__, 3) . '/data/mapping/content-dm/content-dm.unistra_collection-3.jmespath.ini';
        $this->assertFileExists($mappingPath);
    }

    public function testContentDmMappingFileHasExampleUrl(): void
    {
        $mappingPath = dirname(__DIR__, 3) . '/data/mapping/content-dm/content-dm.unistra_collection-3.jmespath.ini';
        $content = file_get_contents($mappingPath);

        $this->assertStringContainsString('example =', $content);
        $this->assertStringContainsString('cdm21057.contentdm.oclc.org', $content);
    }

    public function testLoadContentDmMapping(): void
    {
        $mappingPath = dirname(__DIR__, 3) . '/data/mapping/content-dm/content-dm.base.jmespath.ini';
        $content = file_get_contents($mappingPath);

        $this->mapper->setMapping('contentdm_test', $content);
        $mapping = $this->mapper->getMapping();

        $this->assertNotNull($mapping);
        $this->assertArrayHasKey('info', $mapping);
        $this->assertArrayHasKey('maps', $mapping);
        $this->assertNotEmpty($mapping['maps']);
    }

    // =========================================================================
    // Field extraction tests (direct array access)
    // =========================================================================

    public function testExtractId(): void
    {
        $data = $this->loadUnistraApiFixture();

        $this->assertEquals('359', $data['id']);
    }

    public function testExtractCollectionAlias(): void
    {
        $data = $this->loadUnistraApiFixture();

        $this->assertEquals('coll3', $data['collectionAlias']);
    }

    public function testExtractText(): void
    {
        $data = $this->loadUnistraApiFixture();

        $this->assertStringContainsString('Acer pseudoplatanus', $data['text']);
    }

    public function testExtractContentType(): void
    {
        $data = $this->loadUnistraApiFixture();

        $this->assertEquals('image/jpeg', $data['contentTyp']);
    }

    public function testExtractFilename(): void
    {
        $data = $this->loadUnistraApiFixture();

        $this->assertEquals('coll3_0359.jpg', $data['filename']);
    }

    public function testExtractThumbnailUri(): void
    {
        $data = $this->loadUnistraApiFixture();

        $this->assertStringContainsString('/digital/api/singleitem/image/', $data['thumbnailUri']);
    }

    public function testExtractIiifInfoUri(): void
    {
        $data = $this->loadUnistraApiFixture();

        $this->assertEquals('/iiif/2/coll3:359/info.json', $data['iiifInfoUri']);
    }

    // =========================================================================
    // Fields array extraction tests
    // =========================================================================

    public function testExtractTitleFromFields(): void
    {
        $data = $this->loadUnistraApiFixture();
        $title = $this->getFieldValue($data, 'title');

        $this->assertNotNull($title);
        $this->assertStringContainsString('Acer pseudoplatanus', $title);
    }

    public function testExtractCreatorFromFields(): void
    {
        $data = $this->loadUnistraApiFixture();
        $creator = $this->getFieldValue($data, 'creato');

        $this->assertEquals('Ourisson, Nicole', $creator);
    }

    public function testExtractPublisherFromFields(): void
    {
        $data = $this->loadUnistraApiFixture();
        $publisher = $this->getFieldValue($data, 'editeu');

        $this->assertNotNull($publisher);
        $this->assertStringContainsString('Université de Strasbourg', $publisher);
    }

    public function testExtractDateFromFields(): void
    {
        $data = $this->loadUnistraApiFixture();
        $date = $this->getFieldValue($data, 'datea');

        $this->assertEquals('1982-10', $date);
    }

    public function testExtractRightsFromFields(): void
    {
        $data = $this->loadUnistraApiFixture();
        $rights = $this->getFieldValue($data, 'droits');

        $this->assertNotNull($rights);
        $this->assertStringContainsString('reproduction interdite', $rights);
    }

    public function testExtractSubjectFromFields(): void
    {
        $data = $this->loadUnistraApiFixture();
        $subject = $this->getFieldValue($data, 'audien');

        $this->assertNotNull($subject);
        $this->assertStringContainsString('Dicotylédones', $subject);
    }

    // =========================================================================
    // Mapping conversion tests
    // =========================================================================

    public function testMappingParsesInfo(): void
    {
        $mappingPath = dirname(__DIR__, 3) . '/data/mapping/content-dm/content-dm.base.jmespath.ini';
        $content = file_get_contents($mappingPath);

        $this->mapper->setMapping('contentdm_info_test', $content);
        $mapping = $this->mapper->getMapping();

        $this->assertEquals('jmespath', $mapping['info']['querier']);
        $this->assertEquals('Content-dm (2022-01)', $mapping['info']['label']);
    }

    public function testMappingHasResourceNameMap(): void
    {
        $mappingPath = dirname(__DIR__, 3) . '/data/mapping/content-dm/content-dm.base.jmespath.ini';
        $content = file_get_contents($mappingPath);

        $this->mapper->setMapping('contentdm_resource_test', $content);
        $mapping = $this->mapper->getMapping();

        // Find a map for resource_name.
        $hasResourceNameMap = false;
        foreach ($mapping['maps'] as $map) {
            if (($map['to']['field'] ?? '') === 'resource_name') {
                $hasResourceNameMap = true;
                break;
            }
        }

        $this->assertTrue($hasResourceNameMap, 'Mapping should have resource_name map');
    }

    public function testMappingHasMediaTypeMap(): void
    {
        $mappingPath = dirname(__DIR__, 3) . '/data/mapping/content-dm/content-dm.base.jmespath.ini';
        $content = file_get_contents($mappingPath);

        $this->mapper->setMapping('contentdm_media_test', $content);
        $mapping = $this->mapper->getMapping();

        // Find a map for contentTyp -> o:media[o:media_type].
        $hasMediaTypeMap = false;
        foreach ($mapping['maps'] as $map) {
            if (($map['from']['path'] ?? '') === 'contentTyp') {
                $hasMediaTypeMap = true;
                break;
            }
        }

        $this->assertTrue($hasMediaTypeMap, 'Mapping should have contentTyp map');
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    protected function loadUnistraApiFixture(): array
    {
        $fixturePath = $this->fixturesPath . '/content-dm/unistra.api.json';
        $content = file_get_contents($fixturePath);
        return json_decode($content, true);
    }

    /**
     * Get a field value from the Content-DM fields array.
     *
     * @param array $data The Content-DM API response.
     * @param string $key The field key to search for.
     * @return string|null The field value or null if not found.
     */
    protected function getFieldValue(array $data, string $key): ?string
    {
        $fields = $data['fields'] ?? [];
        foreach ($fields as $field) {
            if (($field['key'] ?? '') === $key) {
                return $field['value'] ?? null;
            }
        }
        return null;
    }
}
