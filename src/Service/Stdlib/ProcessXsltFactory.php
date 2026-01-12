<?php declare(strict_types=1);

namespace Mapper\Service\Stdlib;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Mapper\Stdlib\ProcessXslt;

class ProcessXsltFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, ?array $options = null)
    {
        $logger = $services->get('Omeka\Logger');

        // External XSLT processor command (e.g., Saxon for XSLT 2.0/3.0).
        // Setting can be defined in Mapper or fallback to BulkImport setting.
        $settings = $services->get('Omeka\Settings');
        $command = $settings->get('mapper_xslt_processor')
            ?? $settings->get('bulkimport_xslt_processor');

        $config = $services->get('Config');
        $tempDir = $config['temp_dir'] ?? sys_get_temp_dir();

        return new ProcessXslt($logger, $command, $tempDir);
    }
}
