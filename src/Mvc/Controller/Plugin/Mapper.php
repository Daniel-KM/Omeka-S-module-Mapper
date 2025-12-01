<?php declare(strict_types=1);

namespace Mapper\Mvc\Controller\Plugin;

use Laminas\Mvc\Controller\Plugin\AbstractPlugin;
use Mapper\Stdlib\Mapper as StdlibMapper;
use Mapper\Stdlib\MapperConfig;

/**
 * Controller plugin for accessing mapper functionality.
 *
 * Provides a convenient interface for controllers to perform data mapping
 * operations.
 */
class Mapper extends AbstractPlugin
{
    /**
     * @var StdlibMapper
     */
    protected $stdlibMapper;

    /**
     * @var MapperConfig
     */
    protected $mapperConfig;

    public function __construct(StdlibMapper $stdlibMapper, MapperConfig $mapperConfig)
    {
        $this->stdlibMapper = $stdlibMapper;
        $this->mapperConfig = $mapperConfig;
    }

    /**
     * Get this plugin instance.
     */
    public function __invoke(): self
    {
        return $this;
    }

    /**
     * Get the Mapper service.
     */
    public function mapper(): StdlibMapper
    {
        return $this->stdlibMapper;
    }

    /**
     * Get the MapperConfig service.
     */
    public function config(): MapperConfig
    {
        return $this->mapperConfig;
    }

    /**
     * Convert source data using a mapping.
     *
     * @param array|\SimpleXMLElement $data Source data to transform.
     * @param array|string $mappingRef Mapping reference or inline definition.
     * @param string|null $name Optional mapping name for caching.
     * @return array Transformed resource data.
     */
    public function convert($data, $mappingRef, ?string $name = null): array
    {
        if ($name === null) {
            $name = is_string($mappingRef)
                ? $mappingRef
                : md5(serialize($mappingRef));
        }

        return $this->stdlibMapper
            ->setMapping($name, $mappingRef)
            ->convert($data);
    }

    /**
     * Extract a value from source data using a path.
     *
     * @param array|\SimpleXMLElement|\DOMDocument $data Source data.
     * @param string $path Query path.
     * @param string $querier Querier type (xpath, jsdot, jmespath, jsonpath, index).
     * @return mixed Extracted value(s).
     */
    public function extractValue($data, string $path, string $querier = 'jsdot')
    {
        return $this->stdlibMapper->extractValue($data, $path, $querier);
    }

    /**
     * Convert a string value using a map definition.
     *
     * @param string|null $value The input value.
     * @param array $map Map definition with 'mod' key.
     * @return string The converted value.
     */
    public function convertString(?string $value, array $map = []): string
    {
        return $this->stdlibMapper->convertString($value, $map);
    }

    /**
     * Set variables for use during mapping.
     *
     * @param array $variables Key-value pairs available in patterns.
     */
    public function setVariables(array $variables): self
    {
        $this->stdlibMapper->setVariables($variables);
        return $this;
    }

    /**
     * Load a mapping configuration.
     *
     * @param string $name Mapping identifier.
     * @param array|string $mappingRef Mapping content or reference.
     */
    public function loadMapping(string $name, $mappingRef): self
    {
        $this->stdlibMapper->setMapping($name, $mappingRef);
        return $this;
    }

    /**
     * Get a parsed mapping by name.
     *
     * @param string|null $name Mapping name (null for current).
     */
    public function getMapping(?string $name = null): ?array
    {
        return $name === null
            ? $this->stdlibMapper->getMapping()
            : $this->mapperConfig->getMapping($name);
    }
}
