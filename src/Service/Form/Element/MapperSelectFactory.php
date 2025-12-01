<?php declare(strict_types=1);

namespace Mapper\Service\Form\Element;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Mapper\Form\Element\MapperSelect;

class MapperSelectFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, ?array $options = null)
    {
        $element = new MapperSelect(null, $options ?? []);
        $element
            ->setMapperConfig($services->get('Mapper\MapperConfig'))
            ->setApiManager($services->get('Omeka\ApiManager'));
        return $element;
    }
}
