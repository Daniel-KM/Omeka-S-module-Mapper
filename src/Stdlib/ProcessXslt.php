<?php declare(strict_types=1);

/**
 * Process transformation of xml via xsl.
 *
 * Supports both php native xsl extension (xslt 1.0) and external processors
 * like Saxon (xslt 2.0/3.0) via command line.
 *
 * @copyright Daniel Berthereau, 2015-2026
 * @license http://www.cecill.info/licences/Licence_CeCILL_V2.1-en.txt
 */

namespace Mapper\Stdlib;

use DomDocument;
use Exception;
use Laminas\Log\Logger;
use XsltProcessor;

class ProcessXslt
{
    /**
     * @var Logger
     */
    protected $logger;

    /**
     * External xslt processor command (generally saxon).
     *
     * Command with placeholders %1$s (input), %2$s (stylesheet), %3$s (output).
     *
     * @var string|null
     */
    protected $command;

    /**
     * @var string
     */
    protected $tempDir;

    /**
     * Original url for error messages (kept when file is downloaded).
     *
     * @var string|null
     */
    protected $originalUrl;

    public function __construct(
        Logger $logger,
        ?string $command = null,
        ?string $tempDir = null
    ) {
        $this->logger = $logger;
        $this->command = $command;
        $this->tempDir = $tempDir ?: sys_get_temp_dir();
    }

    /**
     * Apply an xslt stylesheet on a xml file and save result.
     *
     * @param string $url Path or url of input file.
     * @param string $stylesheet Path of the stylesheet.
     * @param string $output Path of the output file. If empty, use temp file.
     * @param array $parameters Parameters array.
     * @return string|null Path to the output file if ok, null else.
     * @throws Exception
     */
    public function __invoke(
        string $url,
        string $stylesheet,
        string $output = '',
        array $parameters = []
    ): ?string {
        return $this->process($url, $stylesheet, $output, $parameters);
    }

    /**
     * Apply an xslt stylesheet on a xml file and save result.
     *
     * @param string $url Path or url of input file.
     * @param string $stylesheet Path of the stylesheet.
     * @param string $output Path of the output file. If empty, use temp file.
     * @param array $parameters Parameters array.
     * @return string|null Path to the output file if ok, null else.
     * @throws Exception
     */
    public function process(
        string $url,
        string $stylesheet,
        string $output = '',
        array $parameters = []
    ): ?string {
        // Store original url for error messages.
        $this->originalUrl = $url;

        // Input should be local to be processed by php or cli.
        $filepath = $url;
        $isRemote = $this->isRemote($url);
        if ($isRemote) {
            $filepath = $this->downloadToTemp($url);
            if ($filepath === null) {
                throw new Exception(sprintf(
                    'The remote file %s is not readable or empty.', // @translate
                    $url
                ));
            }
        } elseif (!is_file($filepath) || !is_readable($filepath) || !filesize($filepath)) {
            throw new Exception(sprintf(
                'The input file %s is not readable or empty.', // @translate
                $filepath
            ));
        }

        try {
            // Default is the internal xslt processor of php.
            $result = empty($this->command)
                ? $this->processXsltViaPhp($filepath, $stylesheet, $output, $parameters)
                : $this->processXsltViaExternal($filepath, $stylesheet, $output, $parameters);
        } finally {
            if ($isRemote && $filepath && file_exists($filepath)) {
                unlink($filepath);
            }
            $this->originalUrl = null;
        }

        return $result;
    }

    /**
     * Download a remote file to a temporary location.
     */
    protected function downloadToTemp(string $url): ?string
    {
        $filepath = @tempnam($this->tempDir, 'omk_xml_');
        if ($filepath === false) {
            return null;
        }
        rename($filepath, $filepath . '.xml');
        $filepath .= '.xml';

        $content = @file_get_contents($url);
        if ($content === false || $content === '') {
            @unlink($filepath);
            return null;
        }

        $result = file_put_contents($filepath, $content);
        if ($result === false) {
            @unlink($filepath);
            return null;
        }

        return $filepath;
    }

    /**
     * Apply xslt stylesheet on xml file via php and save output.
     *
     * @throws Exception
     */
    protected function processXsltViaPhp(
        string $filepath,
        string $stylesheet,
        string $output = '',
        array $parameters = []
    ): ?string {
        if (empty($output)) {
            $output = $this->createTempFile();
            if ($output === null) {
                throw new Exception('Unable to create a temporary file.'); // @translate
            }
        }

        $domXml = $this->domXmlLoad($filepath);
        $domXsl = $this->domXmlLoad($stylesheet);

        libxml_use_internal_errors(true);

        $proc = new XsltProcessor();
        $result = $proc->importStyleSheet($domXsl);
        if ($result === false) {
            $errors = $this->getLibxmlErrors();
            libxml_clear_errors();
            throw new Exception(sprintf(
                'An error occurred during the xsl transformation of the file %1$s with the sheet %2$s: %3$s', // @translate
                $this->formatFileRef($filepath),
                $this->formatFileRef($stylesheet),
                $errors
            ));
        }

        $proc->setParameter('', $parameters);
        $result = $proc->transformToURI($domXml, $output);

        if ($result === false) {
            if (file_exists($output)) {
                unlink($output);
            }
            $errors = $this->getLibxmlErrors();
            libxml_clear_errors();
            throw new Exception(sprintf(
                'An error occurred during the xsl transformation of the file %1$s with the sheet %2$s: %3$s', // @translate
                $this->formatFileRef($filepath),
                $this->formatFileRef($stylesheet),
                $errors
            ));
        }

        if (!file_exists($output) || !filesize($output)) {
            if (file_exists($output)) {
                unlink($output);
            }
            $errors = $this->getLibxmlErrors();
            libxml_clear_errors();
            throw new Exception(sprintf(
                'An error occurred during the xsl transformation of the file %1$s with the sheet %2$s: %3$s', // @translate
                $this->formatFileRef($filepath),
                $this->formatFileRef($stylesheet),
                $errors ?: 'The output is empty.'
            ));
        }

        libxml_clear_errors();
        @chmod($output, 0640);

        return $output;
    }

    /**
     * Load xml or xslt file into a Dom document.
     *
     * @throws Exception
     */
    protected function domXmlLoad(string $filepath): DomDocument
    {
        libxml_use_internal_errors(true);

        $domDocument = new DomDocument();

        if ($this->isRemote($filepath)) {
            $xmlContent = @file_get_contents($filepath);
            if ($xmlContent === false) {
                $errors = $this->getLibxmlErrors();
                libxml_clear_errors();
                throw new Exception(sprintf(
                    'Could not load %1$s. Verify that you have rights to access this folder and subfolders. %2$s', // @translate
                    $filepath,
                    $errors
                ));
            }
            if ($xmlContent === '') {
                libxml_clear_errors();
                throw new Exception(sprintf(
                    'The file "%s" is empty. Process is aborted.', // @translate
                    $filepath
                ));
            }
            $domDocument->loadXML($xmlContent);
        } else {
            $domDocument->load($filepath);
        }

        return $domDocument;
    }

    /**
     * Apply xslt stylesheet on xml file via external processor and save output.
     *
     * @throws Exception
     */
    protected function processXsltViaExternal(
        string $filepath,
        string $stylesheet,
        string $output = '',
        array $parameters = []
    ): ?string {
        if (empty($output)) {
            $output = $this->createTempFile();
            if ($output === null) {
                throw new Exception('Unable to create a temporary file.'); // @translate
            }
        }

        $command = sprintf(
            $this->command,
            escapeshellarg($filepath),
            escapeshellarg($stylesheet),
            escapeshellarg($output)
        );
        foreach ($parameters as $name => $parameter) {
            $command .= ' ' . escapeshellarg($name . '=' . $parameter);
        }
        $result = shell_exec($command . ' 2>&1 1>&-');

        // In Shell, empty is a correct result.
        if (!empty($result)) {
            if (file_exists($output)) {
                unlink($output);
            }
            throw new Exception(sprintf(
                'An error occurred during the xsl transformation of the file %1$s with the sheet %2$s: %3$s', // @translate
                $this->formatFileRef($filepath),
                $this->formatFileRef($stylesheet),
                $result
            ));
        }

        if (!file_exists($output) || !filesize($output)) {
            if (file_exists($output)) {
                unlink($output);
            }
            throw new Exception(sprintf(
                'An error occurred during the xsl transformation of the file %1$s with the sheet %2$s: The output is empty.', // @translate
                $this->formatFileRef($filepath),
                $this->formatFileRef($stylesheet)
            ));
        }

        @chmod($output, 0640);

        return $output;
    }

    /**
     * Create a temporary file for output.
     */
    protected function createTempFile(): ?string
    {
        $output = @tempnam($this->tempDir, 'omk_xsl_');
        if ($output === false) {
            return null;
        }
        rename($output, $output . '.xml');
        return $output . '.xml';
    }

    /**
     * Determine if a uri is a remote url or a local path.
     */
    protected function isRemote(string $url): bool
    {
        return strpos($url, 'http://') === 0
            || strpos($url, 'https://') === 0
            || strpos($url, 'ftp://') === 0
            || strpos($url, 'sftp://') === 0;
    }

    /**
     * Format file reference for error messages.
     *
     * Returns original url if available, otherwise basename for local files.
     */
    protected function formatFileRef(string $filepath): string
    {
        // If this is the downloaded temp file, return original url.
        if ($this->originalUrl && !$this->isRemote($filepath)) {
            if (strpos($filepath, $this->tempDir) === 0) {
                return $this->originalUrl;
            }
        }
        // For remote urls, return full url.
        if ($this->isRemote($filepath)) {
            return $filepath;
        }
        // For local files, return basename.
        return basename($filepath);
    }

    /**
     * Get libxml errors as string.
     */
    protected function getLibxmlErrors(): string
    {
        $errors = libxml_get_errors();
        if (empty($errors)) {
            return '';
        }

        $messages = [];
        foreach ($errors as $error) {
            $messages[] = trim($error->message) . ' (line ' . $error->line . ')';
        }

        return implode('; ', $messages);
    }

    /**
     * Set the external command for xslt processing.
     */
    public function setCommand(?string $command): self
    {
        $this->command = $command;
        return $this;
    }

    /**
     * Get the external command for xslt processing.
     */
    public function getCommand(): ?string
    {
        return $this->command;
    }

    /**
     * Set the temporary directory.
     */
    public function setTempDir(string $tempDir): self
    {
        $this->tempDir = $tempDir;
        return $this;
    }

    /**
     * Get the temporary directory.
     */
    public function getTempDir(): string
    {
        return $this->tempDir;
    }

    /**
     * Check if external processor is configured.
     */
    public function hasExternalProcessor(): bool
    {
        return !empty($this->command);
    }

    /**
     * Check if php xsl extension is available.
     */
    public function isPhpXslAvailable(): bool
    {
        return extension_loaded('xsl');
    }
}
