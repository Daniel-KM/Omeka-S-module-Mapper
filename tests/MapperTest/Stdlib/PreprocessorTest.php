<?php declare(strict_types=1);

namespace MapperTest\Stdlib;

use Mapper\Stdlib\Preprocessor;
use Mapper\Stdlib\ProcessXslt;
use Omeka\Test\AbstractHttpControllerTestCase;

/**
 * Tests for the Preprocessor class.
 *
 * @covers \Mapper\Stdlib\Preprocessor
 */
class PreprocessorTest extends AbstractHttpControllerTestCase
{
    protected Preprocessor $preprocessor;
    protected string $mappingPath;

    public function setUp(): void
    {
        parent::setUp();
        $this->preprocessor = $this->getApplication()
            ->getServiceManager()
            ->get('Mapper\Preprocessor');
        $this->mappingPath = dirname(__DIR__, 3) . '/data/mapping';
    }

    // =========================================================================
    // Service Registration Tests
    // =========================================================================

    public function testServiceIsRegistered(): void
    {
        $services = $this->getApplication()->getServiceManager();
        $this->assertTrue($services->has('Mapper\Preprocessor'));
    }

    public function testServiceClassIsRegistered(): void
    {
        $services = $this->getApplication()->getServiceManager();
        $this->assertTrue($services->has(Preprocessor::class));
    }

    public function testServiceReturnsCorrectInstance(): void
    {
        $this->assertInstanceOf(Preprocessor::class, $this->preprocessor);
    }

    // =========================================================================
    // Capability Tests
    // =========================================================================

    public function testIsXslAvailable(): void
    {
        $this->assertTrue($this->preprocessor->isXslAvailable());
    }

    public function testHasExternalProcessorReturnsBool(): void
    {
        $result = $this->preprocessor->hasExternalProcessor();
        $this->assertIsBool($result);
    }

    public function testGetBasePath(): void
    {
        $basePath = $this->preprocessor->getBasePath();
        $this->assertIsString($basePath);
        $this->assertDirectoryExists($basePath);
    }

    // =========================================================================
    // process() with Empty Preprocess Tests
    // =========================================================================

    public function testProcessWithEmptyPreprocessReturnsContent(): void
    {
        $content = '<root><data>Test</data></root>';
        $result = $this->preprocessor->process($content, []);

        $this->assertSame($content, $result);
    }

    public function testProcessWithNullPreprocessElementsSkipsThem(): void
    {
        $content = '<root><data>Test</data></root>';
        $result = $this->preprocessor->process($content, [null, '', 0]);

        $this->assertSame($content, $result);
    }

    // =========================================================================
    // process() with XSL File Reference Tests
    // =========================================================================

    public function testProcessWithModuleXslReference(): void
    {
        $content = '<?xml version="1.0"?><root><item>Test</item></root>';

        // Use identity transform.
        $result = $this->preprocessor->process(
            $content,
            ['module:common/identity.xslt1.xsl']
        );

        $this->assertIsString($result);
        $this->assertStringContainsString('Test', $result);
    }

    public function testProcessWithRelativePathInContext(): void
    {
        $content = '<?xml version="1.0"?><root><data>Value</data></root>';

        // Should find identity.xslt1.xsl in common/ directory.
        $result = $this->preprocessor->process(
            $content,
            ['identity.xslt1.xsl'],
            [],
            'common'
        );

        $this->assertIsString($result);
        $this->assertStringContainsString('Value', $result);
    }

    public function testProcessWithAbsolutePath(): void
    {
        $xslPath = $this->mappingPath . '/common/identity.xslt1.xsl';
        if (!file_exists($xslPath)) {
            $this->markTestSkipped('Identity XSL file not found.');
        }

        $content = '<?xml version="1.0"?><root><element>Content</element></root>';

        $result = $this->preprocessor->process($content, [$xslPath]);

        $this->assertIsString($result);
        $this->assertStringContainsString('Content', $result);
    }

    // =========================================================================
    // process() with XSL Content Tests
    // =========================================================================

    public function testProcessWithInlineXslContent(): void
    {
        $content = '<?xml version="1.0"?><root><title>Original</title></root>';

        $xsl = <<<XSL
<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">
    <xsl:output method="xml"/>
    <xsl:template match="/">
        <transformed><xsl:value-of select="//title"/></transformed>
    </xsl:template>
</xsl:stylesheet>
XSL;

        // Write XSL to temp file and use absolute path.
        $tempDir = sys_get_temp_dir();
        $xslPath = tempnam($tempDir, 'test_xsl_') . '.xsl';
        file_put_contents($xslPath, $xsl);

        try {
            $result = $this->preprocessor->process($content, [$xslPath]);

            $this->assertIsString($result);
            $this->assertStringContainsString('Original', $result);
            $this->assertStringContainsString('<transformed>', $result);
        } finally {
            @unlink($xslPath);
        }
    }

    // =========================================================================
    // process() with Multiple Transformations Tests
    // =========================================================================

    public function testProcessWithMultipleTransformations(): void
    {
        $content = '<?xml version="1.0"?><root><data>Test</data></root>';

        // Apply identity transform twice (should produce same result).
        $result = $this->preprocessor->process(
            $content,
            [
                'module:common/identity.xslt1.xsl',
                'module:common/identity.xslt1.xsl',
            ]
        );

        $this->assertIsString($result);
        $this->assertStringContainsString('Test', $result);
    }

    // =========================================================================
    // process() Error Handling Tests
    // =========================================================================

    public function testProcessReturnsNullOnMissingTransformation(): void
    {
        $content = '<root/>';

        $result = $this->preprocessor->process(
            $content,
            ['module:nonexistent/missing.xsl']
        );

        $this->assertNull($result);
    }

    public function testProcessReturnsNullOnInvalidXml(): void
    {
        $content = 'not valid xml';

        $result = $this->preprocessor->process(
            $content,
            ['module:common/identity.xslt1.xsl']
        );

        $this->assertNull($result);
    }

    // =========================================================================
    // Invocable Tests
    // =========================================================================

    public function testInvokeCallsProcess(): void
    {
        $content = '<?xml version="1.0"?><root><item>Test</item></root>';

        $result = ($this->preprocessor)(
            $content,
            ['module:common/identity.xslt1.xsl']
        );

        $this->assertIsString($result);
        $this->assertStringContainsString('Test', $result);
    }

    public function testInvokeWithEmptyPreprocessReturnsContent(): void
    {
        $content = '<root>Content</root>';
        $result = ($this->preprocessor)($content, []);

        $this->assertSame($content, $result);
    }

    // =========================================================================
    // Setter/Getter Tests
    // =========================================================================

    public function testSetAndGetBasePath(): void
    {
        $original = $this->preprocessor->getBasePath();

        $this->preprocessor->setBasePath('/custom/path');
        $this->assertSame('/custom/path', $this->preprocessor->getBasePath());

        // Restore original.
        $this->preprocessor->setBasePath($original);
    }

    public function testSetAndGetUserBasePath(): void
    {
        $original = $this->preprocessor->getUserBasePath();

        $this->preprocessor->setUserBasePath('/custom/user/path');
        $this->assertSame('/custom/user/path', $this->preprocessor->getUserBasePath());

        // Restore original.
        $this->preprocessor->setUserBasePath($original);
    }
}
