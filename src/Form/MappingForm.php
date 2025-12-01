<?php declare(strict_types=1);

namespace Mapper\Form;

use Laminas\Form\Element;
use Laminas\Form\Form;

class MappingForm extends Form
{
    public function init(): void
    {
        parent::init();

        $defaultMapping = <<<'INI'
            ; Sample mapping configuration
            ; Use INI format (like this) or XML format
            
            [info]
            label = "My Mapping"
            querier = xpath
            
            [default]
            ; Default values applied to all records
            dcterms:rights = "Public Domain"
            
            [maps]
            ; XPath (for XML) or jsdot/jsonpath (for JSON) to Omeka field mappings
            ; Format: /source/path = destination:field ^^datatype @language §visibility ~ {{ value|filter }}
            
            /record/title = dcterms:title ^^literal
            /record/date = dcterms:issued ~ {{ value|dateIso }}
            /record/creator = dcterms:creator
            
            ; Combine multiple sources into one field:
            ; ~ = foaf:name ~ {{ /record/firstname }} {{ /record/lastname }}
            
            INI;

        $this
            ->setAttribute('id', 'mapper-mapping-form')

            ->add([
                'name' => 'o:label',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Label', // @translate
                ],
                'attributes' => [
                    'id' => 'o-label',
                    'required' => true,
                ],
            ])
            ->add([
                'name' => 'o-module-mapper:mapping',
                'type' => Element\Textarea::class,
                'options' => [
                    // Don't use "Mapping" alone to avoid issue in translation.
                    'label' => 'Mapping (ini, json or xml)', // @translate
                    'info' => 'Define the mapping using ini, json or xml format. See documentation for details.', // @translate
                ],
                'attributes' => [
                    'id' => 'o-mapper-mapping',
                    'rows' => 30,
                    'class' => 'codemirror-code',
                    'placeholder' => $defaultMapping,
                ],
            ])
            ->add([
                'name' => 'submit',
                'type' => Element\Submit::class,
                'attributes' => [
                    'id' => 'submitbutton',
                    'value' => 'Save', // @translate
                ],
            ]);
    }
}
