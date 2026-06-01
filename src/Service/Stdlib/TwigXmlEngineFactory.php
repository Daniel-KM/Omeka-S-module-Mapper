<?php declare(strict_types=1);

namespace Mapper\Service\Stdlib;

use Laminas\ServiceManager\Factory\FactoryInterface;
use Mapper\Stdlib\TwigXmlEngine;
use Psr\Container\ContainerInterface;

class TwigXmlEngineFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, ?array $options = null)
    {
        return new TwigXmlEngine(
            $services->get('MvcTranslator'),
            $services->get('Mapper\MapperConfig')
        );
    }
}
