<?php declare(strict_types=1);

namespace MapperTest\Service\Stdlib;

use Mapper\Stdlib\Mapper;
use Mapper\Stdlib\MapperConfig;
use Omeka\Test\AbstractHttpControllerTestCase;

/**
 * Tests for the Mapper service factories.
 *
 * @covers \Mapper\Service\Stdlib\MapperFactory
 * @covers \Mapper\Service\Stdlib\MapperConfigFactory
 */
class MapperFactoryTest extends AbstractHttpControllerTestCase
{
    /**
     * Get the service locator from the application.
     */
    protected function getServiceLocator()
    {
        return $this->getApplication()->getServiceManager();
    }

    // =========================================================================
    // Mapper Factory Tests
    // =========================================================================

    public function testMapperServiceIsRegistered(): void
    {
        $services = $this->getServiceLocator();
        $this->assertTrue($services->has('Mapper\Mapper'));
    }

    public function testMapperServiceAliasIsRegistered(): void
    {
        $services = $this->getServiceLocator();
        $this->assertTrue($services->has(\Mapper\Stdlib\Mapper::class));
    }

    public function testMapperServiceReturnsMapperInstance(): void
    {
        $mapper = $this->getServiceLocator()->get('Mapper\Mapper');
        $this->assertInstanceOf(Mapper::class, $mapper);
    }

    public function testMapperServiceIsSingleton(): void
    {
        $services = $this->getServiceLocator();

        $mapper1 = $services->get('Mapper\Mapper');
        $mapper2 = $services->get('Mapper\Mapper');

        $this->assertSame($mapper1, $mapper2);
    }

    public function testMapperHasMapperConfig(): void
    {
        $mapper = $this->getServiceLocator()->get('Mapper\Mapper');
        $config = $mapper->getMapperConfig();

        $this->assertInstanceOf(MapperConfig::class, $config);
    }

    // =========================================================================
    // MapperConfig Factory Tests
    // =========================================================================

    public function testMapperConfigServiceIsRegistered(): void
    {
        $services = $this->getServiceLocator();
        $this->assertTrue($services->has('Mapper\MapperConfig'));
    }

    public function testMapperConfigServiceAliasIsRegistered(): void
    {
        $services = $this->getServiceLocator();
        $this->assertTrue($services->has(\Mapper\Stdlib\MapperConfig::class));
    }

    public function testMapperConfigServiceReturnsMapperConfigInstance(): void
    {
        $config = $this->getServiceLocator()->get('Mapper\MapperConfig');
        $this->assertInstanceOf(MapperConfig::class, $config);
    }

    public function testMapperConfigServiceIsSingleton(): void
    {
        $services = $this->getServiceLocator();

        $config1 = $services->get('Mapper\MapperConfig');
        $config2 = $services->get('Mapper\MapperConfig');

        $this->assertSame($config1, $config2);
    }

    // =========================================================================
    // Controller Plugin Tests
    // =========================================================================

    public function testMapperControllerPluginIsRegistered(): void
    {
        $plugins = $this->getServiceLocator()->get('ControllerPluginManager');
        $this->assertTrue($plugins->has('mapper'));
    }

    public function testMapperConfigListControllerPluginIsRegistered(): void
    {
        $plugins = $this->getServiceLocator()->get('ControllerPluginManager');
        $this->assertTrue($plugins->has('mapperConfigList'));
    }

    // =========================================================================
    // Form Element Tests
    // =========================================================================

    public function testMapperSelectFormElementIsRegistered(): void
    {
        $formElements = $this->getServiceLocator()->get('FormElementManager');
        $this->assertTrue($formElements->has(\Mapper\Form\Element\MapperSelect::class));
    }

    // =========================================================================
    // Mapper Invocable Tests
    // =========================================================================

    public function testMapperIsInvocable(): void
    {
        $mapper = $this->getServiceLocator()->get('Mapper\Mapper');

        // Test __invoke returns self.
        $result = $mapper();
        $this->assertSame($mapper, $result);
    }

    public function testMapperInvokeWithMappingName(): void
    {
        $mapper = $this->getServiceLocator()->get('Mapper\Mapper');

        $result = $mapper('test_mapping');
        $this->assertSame($mapper, $result);
        $this->assertSame('test_mapping', $mapper->getMappingName());
    }

    public function testMapperInvokeWithMappingNameAndContent(): void
    {
        $mapper = $this->getServiceLocator()->get('Mapper\Mapper');
        $mapping = [
            'info' => ['label' => 'Test'],
            'maps' => [],
        ];

        $result = $mapper('test_with_content', $mapping);
        $this->assertSame($mapper, $result);
        $this->assertSame('test_with_content', $mapper->getMappingName());

        // Mapping should be loaded.
        $loadedMapping = $mapper->getMapping();
        $this->assertNotNull($loadedMapping);
    }

    // =========================================================================
    // MapperConfig Invocable Tests
    // =========================================================================

    public function testMapperConfigIsInvocable(): void
    {
        $config = $this->getServiceLocator()->get('Mapper\MapperConfig');

        // Test __invoke returns self when called without args.
        $result = $config();
        $this->assertSame($config, $result);
    }

    public function testMapperConfigInvokeWithMapping(): void
    {
        $config = $this->getServiceLocator()->get('Mapper\MapperConfig');
        $mapping = ['info' => ['label' => 'Config Test'], 'maps' => []];

        $result = $config('config_test', $mapping);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('info', $result);
        $this->assertSame('Config Test', $result['info']['label']);
    }
}
