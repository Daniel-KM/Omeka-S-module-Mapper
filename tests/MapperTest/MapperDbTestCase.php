<?php declare(strict_types=1);

namespace MapperTest;

use Laminas\Mvc\Application;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Omeka\Test\TestCase;

/**
 * Base test case for Mapper tests that need module services.
 *
 * Uses a shared application instance that has modules loaded.
 * The bootstrap installs modules in the test database, and this class
 * ensures we use an application that recognizes those modules.
 */
abstract class MapperDbTestCase extends TestCase
{
    use MapperTestTrait;

    /**
     * Shared application instance with modules loaded.
     */
    protected static ?Application $sharedApplication = null;

    /**
     * Set up with admin login for API access.
     */
    public function setUp(): void
    {
        $this->getApplication()->getServiceManager()->get('Omeka\EntityManager')
            ->getConnection()->beginTransaction();
        $this->loginAdmin();
    }

    /**
     * Roll back transaction to keep test isolation.
     */
    public function tearDown(): void
    {
        $this->getApplication()->getServiceManager()->get('Omeka\EntityManager')
            ->getConnection()->rollback();
    }

    /**
     * Get the application with modules loaded.
     */
    public static function getApplication(): Application
    {
        if (self::$sharedApplication instanceof Application) {
            return self::$sharedApplication;
        }

        $config = require OMEKA_PATH . '/application/config/application.config.php';
        $reader = new \Laminas\Config\Reader\Ini;
        $testConfig = [
            'connection' => $reader->fromFile(OMEKA_PATH . '/application/test/config/database.ini'),
        ];
        $config = array_merge($config, $testConfig);
        self::$sharedApplication = \Omeka\Mvc\Application::init($config);

        return self::$sharedApplication;
    }

    /**
     * Get the service locator.
     */
    protected function getServiceLocator(): ServiceLocatorInterface
    {
        return self::getApplication()->getServiceManager();
    }

    /**
     * Get Entity Manager (required by TestCase).
     */
    public function getEntityManager(): \Doctrine\ORM\EntityManager
    {
        return $this->getServiceLocator()->get('Omeka\EntityManager');
    }
}
