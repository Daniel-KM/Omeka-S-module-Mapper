<?php declare(strict_types=1);

/**
 * Bootstrap file for module tests.
 *
 * Use Common module Bootstrap helper for test setup.
 */

// Load Omeka autoloader first.
require dirname(__DIR__, 3) . '/vendor/autoload.php';

// Register the module's namespace for PSR-4 autoloading before bootstrap.
// It's needed because test classes may use module classes before module install.
$loader = new \Composer\Autoload\ClassLoader();
$loader->addPsr4('Mapper\\', dirname(__DIR__) . '/src/');
$loader->register(true);

require dirname(__DIR__, 3) . '/modules/Common/test/Bootstrap.php';

\CommonTest\Bootstrap::bootstrap(
    [
        'Common',
        'Mapper',
    ],
    'MapperTest',
    __DIR__ . '/MapperTest'
);
