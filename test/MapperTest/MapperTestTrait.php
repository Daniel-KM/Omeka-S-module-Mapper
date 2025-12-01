<?php declare(strict_types=1);

namespace MapperTest;

use Laminas\ServiceManager\ServiceLocatorInterface;
use Omeka\Api\Manager as ApiManager;
use Omeka\Entity\Job;

/**
 * Shared test helpers for Mapper module tests.
 *
 * Based on Urify test patterns for comprehensive testing.
 */
trait MapperTestTrait
{
    /**
     * @var ServiceLocatorInterface
     */
    protected $services;

    /**
     * @var array IDs of items created during tests (for cleanup).
     */
    protected array $createdItemIds = [];

    /**
     * @var array IDs of mappers created during tests (for cleanup).
     */
    protected array $createdMapperIds = [];

    /**
     * Get the service locator.
     *
     * Gets the service manager from the current application.
     * Uses $this->application if available, otherwise gets it fresh.
     */
    protected function getServiceLocator(): ServiceLocatorInterface
    {
        if ($this->services === null) {
            // Try to use the application property from AbstractHttpControllerTestCase.
            if (isset($this->application) && $this->application !== null) {
                $this->services = $this->application->getServiceManager();
            } else {
                $this->services = $this->getApplication()->getServiceManager();
            }
        }
        return $this->services;
    }

    /**
     * Reset the cached service locator.
     */
    protected function resetServiceLocator(): void
    {
        $this->services = null;
    }

    /**
     * Get the API manager.
     */
    protected function api(): ApiManager
    {
        return $this->getServiceLocator()->get('Omeka\ApiManager');
    }

    /**
     * Get the Entity Manager.
     */
    protected function getEntityManager(): \Doctrine\ORM\EntityManager
    {
        return $this->getServiceLocator()->get('Omeka\EntityManager');
    }

    /**
     * Login as admin user.
     */
    protected function loginAdmin(): void
    {
        $auth = $this->getServiceLocator()->get('Omeka\AuthenticationService');
        $adapter = $auth->getAdapter();
        $adapter->setIdentity('admin@example.com');
        $adapter->setCredential('root');
        $auth->authenticate();
    }

    /**
     * Logout current user.
     */
    protected function logout(): void
    {
        $auth = $this->getServiceLocator()->get('Omeka\AuthenticationService');
        $auth->clearIdentity();
    }

    /**
     * Create a test item with the given data.
     *
     * @param array $data Item data with property terms as keys.
     * @return \Omeka\Api\Representation\ItemRepresentation
     */
    protected function createItem(array $data = []): \Omeka\Api\Representation\ItemRepresentation
    {
        $easyMeta = $this->getServiceLocator()->get('Common\EasyMeta');

        // Build the proper API format.
        $itemData = [];
        foreach ($data as $term => $values) {
            // Skip special keys.
            if (in_array($term, ['o:is_public', 'o:item_set', 'o:resource_template', 'o:resource_class'])) {
                $itemData[$term] = $values;
                continue;
            }

            // Handle property values.
            $propertyId = $easyMeta->propertyId($term);
            if (!$propertyId) {
                continue;
            }

            if (!is_array($values)) {
                $values = [$values];
            }

            $itemData[$term] = [];
            foreach ($values as $value) {
                if (is_array($value)) {
                    // Already formatted.
                    $value['property_id'] = $propertyId;
                    $itemData[$term][] = $value;
                } else {
                    $itemData[$term][] = [
                        'type' => 'literal',
                        'property_id' => $propertyId,
                        '@value' => (string) $value,
                    ];
                }
            }
        }

        $response = $this->api()->create('items', $itemData);
        $item = $response->getContent();
        $this->createdItemIds[] = $item->id();

        return $item;
    }

    /**
     * Create a Mapper entity in the database.
     *
     * @param string $label Mapper label.
     * @param string $mapping Mapping content (INI or XML).
     * @return \Mapper\Api\Representation\MapperRepresentation
     */
    protected function createMapper(string $label, string $mapping): \Mapper\Api\Representation\MapperRepresentation
    {
        $response = $this->api()->create('mappers', [
            'o:label' => $label,
            'o-mapper:mapping' => $mapping,
        ]);
        $mapper = $response->getContent();
        $this->createdMapperIds[] = $mapper->id();

        return $mapper;
    }

    /**
     * Get the path to the fixtures directory.
     */
    protected function getFixturesPath(): string
    {
        return dirname(__DIR__) . '/fixtures';
    }

    /**
     * Load a fixture file by name.
     *
     * @param string $name Fixture filename (with extension).
     * @return string File contents.
     */
    protected function getFixture(string $name): string
    {
        $path = $this->getFixturesPath() . '/' . $name;
        if (!file_exists($path)) {
            throw new \RuntimeException("Fixture not found: $path");
        }
        return file_get_contents($path);
    }

    /**
     * Load a fixture file and parse as JSON.
     *
     * @param string $name Fixture filename.
     * @return array Parsed JSON.
     */
    protected function getFixtureJson(string $name): array
    {
        return json_decode($this->getFixture($name), true);
    }

    /**
     * Load a fixture file and parse as XML.
     *
     * @param string $name Fixture filename.
     * @return \SimpleXMLElement
     */
    protected function getFixtureXml(string $name): \SimpleXMLElement
    {
        return new \SimpleXMLElement($this->getFixture($name));
    }

    /**
     * Cleanup resources created during tests.
     */
    protected function cleanupResources(): void
    {
        // Delete created items.
        foreach ($this->createdItemIds as $id) {
            try {
                $this->api()->delete('items', $id);
            } catch (\Exception $e) {
                // Ignore errors during cleanup.
            }
        }
        $this->createdItemIds = [];

        // Delete created mappers.
        foreach ($this->createdMapperIds as $id) {
            try {
                $this->api()->delete('mappers', $id);
            } catch (\Exception $e) {
                // Ignore errors during cleanup.
            }
        }
        $this->createdMapperIds = [];
    }

    /**
     * Get a sample INI mapping for testing.
     */
    protected function getSampleIniMapping(): string
    {
        return <<<'INI'
[info]
label = "Test INI Mapping"
from = xml
to = omeka
querier = xpath

[maps]
//title = dcterms:title
//creator = dcterms:creator
//date = dcterms:date
//description = dcterms:description
INI;
    }

    /**
     * Get a sample XML mapping for testing.
     */
    protected function getSampleXmlMapping(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<mapping>
    <info>
        <label>Test XML Mapping</label>
        <from>xml</from>
        <to>omeka</to>
    </info>
    <map>
        <from xpath="//title"/>
        <to field="dcterms:title" datatype="literal"/>
    </map>
    <map>
        <from xpath="//creator"/>
        <to field="dcterms:creator" datatype="literal"/>
    </map>
    <map>
        <from xpath="//date"/>
        <to field="dcterms:date" datatype="literal"/>
    </map>
</mapping>
XML;
    }

    /**
     * Get a sample IdRef RDF mapping for testing.
     */
    protected function getIdRefRdfMapping(): string
    {
        return <<<'INI'
[info]
label = "IdRef RDF Mapping"
from = rdf
to = omeka
querier = xpath

[maps]
//foaf:Person/foaf:name = dcterms:title
//foaf:Person/foaf:familyName = foaf:familyName
//foaf:Person/foaf:givenName = foaf:givenName
//foaf:Person/bio:birth = bio:birth
//foaf:Person/bio:death = bio:death
//foaf:Person/foaf:page/@rdf:resource = dcterms:source ^^uri
INI;
    }

    /**
     * Get a sample LIDO mapping for testing.
     */
    protected function getLidoMapping(): string
    {
        return $this->getFixture('mappings/lido_simple.xml');
    }

    /**
     * Assert that a string contains another string (helper for older PHPUnit).
     */
    protected function assertStringContains(string $needle, string $haystack, string $message = ''): void
    {
        $this->assertStringContainsString($needle, $haystack, $message);
    }

    /**
     * Run a job synchronously and return the Job entity.
     *
     * @param string $jobClass The job class name.
     * @param array $args Job arguments.
     * @param bool $expectError Whether to expect an error.
     * @return Job The job entity after execution.
     */
    protected function runJob(string $jobClass, array $args, bool $expectError = false): Job
    {
        $em = $this->getEntityManager();
        $auth = $this->getServiceLocator()->get('Omeka\AuthenticationService');

        // Create job entity.
        $job = new Job();
        $job->setClass($jobClass);
        $job->setArgs($args);
        $job->setStatus(Job::STATUS_STARTING);
        if ($auth->hasIdentity()) {
            $job->setOwner($auth->getIdentity());
        }

        $em->persist($job);
        $em->flush();

        // Create and execute the job instance.
        try {
            $jobInstance = new $jobClass($job, $this->getServiceLocator());
            $jobInstance->perform();
            $job->setStatus(Job::STATUS_COMPLETED);
        } catch (\Exception $e) {
            $job->setStatus(Job::STATUS_ERROR);
            if (!$expectError) {
                throw $e;
            }
        }

        $em->flush();

        return $job;
    }
}
