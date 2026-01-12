<?php declare(strict_types=1);

namespace Mapper\Form\Element;

use Mapper\Stdlib\MapperConfig;
use Omeka\Form\Element\AbstractGroupByOwnerSelect;

class MapperSelect extends AbstractGroupByOwnerSelect
{
    /**
     * @var string
     */
    protected $basePath;

    /**
     * @var MapperConfig
     */
    protected $mapperConfig;

    public function getResourceName()
    {
        return 'mappers';
    }

    public function getValueLabel($resource)
    {
        return $resource->label();
    }

    public function setBasePath(string $basePath): self
    {
        $this->basePath = $basePath;
        return $this;
    }

    public function getBasePath(): ?string
    {
        return $this->basePath;
    }

    public function setMapperConfig(MapperConfig $mapperConfig): self
    {
        $this->mapperConfig = $mapperConfig;
        return $this;
    }

    public function getMapperConfig(): ?MapperConfig
    {
        return $this->mapperConfig;
    }

    public function getValueOptions(): array
    {
        // Get database mappings from parent.
        $valueOptions = parent::getValueOptions();

        // Add file-based mappings if option is enabled.
        if (!$this->getOption('exclude_files')) {
            $fileMappings = $this->getFileMappings();
            if (!empty($fileMappings)) {
                // Wrap database mappings in a group when files are shown.
                if (!empty($valueOptions)) {
                    $dbMappings = [];
                    foreach ($valueOptions as $key => $option) {
                        if (is_array($option) && isset($option['label'], $option['options'])) {
                            // Already a group (e.g., grouped by owner).
                            $dbMappings[] = $option;
                        } else {
                            // Simple key => value.
                            $dbMappings[$key] = $option;
                        }
                    }
                    $valueOptions = [
                        [
                            'label' => 'Mappings', // @translate
                            'options' => $dbMappings,
                        ],
                    ];
                }
                // Add file mappings as a separate group.
                $valueOptions[] = [
                    'label' => 'File mappings', // @translate
                    'options' => $fileMappings,
                ];
            }
        }

        return $valueOptions;
    }

    /**
     * Get mappings stored as files in the data/mapping directory.
     *
     * @return array Associative array of file reference => label.
     */
    protected function getFileMappings(): array
    {
        $mappingDir = dirname(__DIR__, 3) . '/data/mapping';
        if (!is_dir($mappingDir)) {
            return [];
        }

        $mappings = [];
        $subdirs = ['xml', 'base', 'json'];

        foreach ($subdirs as $subdir) {
            $dir = $mappingDir . '/' . $subdir;
            if (!is_dir($dir)) {
                continue;
            }

            $files = scandir($dir);
            foreach ($files as $file) {
                if ($file === '.' || $file === '..') {
                    continue;
                }

                $ext = pathinfo($file, PATHINFO_EXTENSION);
                if (!in_array($ext, ['xml', 'ini'])) {
                    continue;
                }

                $name = pathinfo($file, PATHINFO_FILENAME);
                // Use module: prefix to identify file-based mappings.
                $reference = 'module:' . $subdir . '/' . $file;

                // Try to get label from mapping info section.
                $label = $this->getMappingLabel($reference);
                if (!$label) {
                    // Fallback: create a readable label from the filename.
                    $label = ucfirst(strtr($name, ['_' => ' ', '-' => ' ', '.' => ' ']));
                }
                $label .= ' (' . $subdir . '/' . $ext . ')';

                $mappings[$reference] = $label;
            }
        }

        asort($mappings);

        return $mappings;
    }

    /**
     * Get the label from a mapping file info section.
     */
    protected function getMappingLabel(string $reference): ?string
    {
        if (!$this->mapperConfig) {
            return null;
        }

        try {
            $mapping = $this->mapperConfig->__invoke(null, $reference);
            if ($mapping && !empty($mapping['info']['label'])) {
                return $mapping['info']['label'];
            }
        } catch (\Exception $e) {
            // Ignore errors, fallback to filename.
        }

        return null;
    }
}
