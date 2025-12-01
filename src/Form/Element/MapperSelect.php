<?php declare(strict_types=1);

namespace Mapper\Form\Element;

use Omeka\Form\Element\AbstractGroupByOwnerSelect;

class MapperSelect extends AbstractGroupByOwnerSelect
{
    public function getResourceName()
    {
        return 'mappers';
    }

    public function getValueLabel($resource)
    {
        return $resource->label();
    }
}
