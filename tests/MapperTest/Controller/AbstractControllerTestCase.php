<?php declare(strict_types=1);

namespace MapperTest\Controller;

use CommonTest\AbstractHttpControllerTestCase;

/**
 * Abstract controller test case for Mapper module.
 *
 * Extends CommonTest\AbstractHttpControllerTestCase which provides
 * authentication handling that persists across application resets.
 */
abstract class AbstractControllerTestCase extends AbstractHttpControllerTestCase
{
    // Module-specific test helpers can be added here.
}
