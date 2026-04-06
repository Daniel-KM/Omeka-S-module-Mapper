<?php declare(strict_types=1);

namespace Mapper\Form;

use Laminas\Form\Element;
use Laminas\Form\Form;
use Mapper\Module;

class ConfigForm extends Form
{
    public function init(): void
    {
        $detected = Module::detectXsltCommand();
        $detectedLabel = $detected
            ? sprintf('Detected on this server: %s', $detected) // @translate
            : 'No external xslt processor detected on this server. Install libsaxonhe-java (Debian/Ubuntu) or saxon (Fedora/RHEL) for xslt 2/3 support.'; // @translate

        $this
            ->add([
                'name' => 'mapper_xslt_processor_mode',
                'type' => Element\Radio::class,
                'options' => [
                    'label' => 'XSLT processor', // @translate
                    'info' => $detectedLabel,
                    'value_options' => [
                        'auto' => 'Auto-detect (recommended)', // @translate
                        'custom' => 'Custom command', // @translate
                        'disabled' => 'Disabled (use PHP internal xslt 1)', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'mapper_xslt_processor_mode',
                    'value' => 'auto',
                ],
            ])
            ->add([
                'name' => 'mapper_xslt_processor',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Custom xslt processor command', // @translate
                    'info' => 'Used only when mode is "Custom". Use "%1$s" (input), "%2$s" (stylesheet), "%3$s" (output), unescaped.', // @translate
                ],
                'attributes' => [
                    'id' => 'mapper_xslt_processor',
                    'placeholder' => $detected
                        ?: 'CLASSPATH=/usr/share/java/Saxon-HE.jar java net.sf.saxon.Transform -ext:on -versionmsg:off -warnings:silent -s:%1$s -xsl:%2$s -o:%3$s',
                ],
            ])
        ;
    }
}
