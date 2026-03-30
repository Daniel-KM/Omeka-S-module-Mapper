<?php declare(strict_types=1);

namespace Mapper\Form;

use Laminas\Form\Element;
use Laminas\Form\Form;

class ConfigForm extends Form
{
    public function init(): void
    {
        $this
            ->add([
                'name' => 'mapper_xslt_processor',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Command of the xslt processor', // @translate
                    'info' => 'Needed to import metadata in xml files, when the process uses xslt 2/3 sheets or xsl:import. Leave empty to use the PHP internal processor (may crash with xsl:import).', // @translate
                ],
                'attributes' => [
                    'id' => 'mapper_xslt_processor',
                    'placeholder' => 'CLASSPATH=/usr/share/java/Saxon-HE.jar java net.sf.saxon.Transform -ext:on -versionmsg:off -warnings:silent -s:%1$s -xsl:%2$s -o:%3$s',
                ],
            ])
        ;
    }
}
