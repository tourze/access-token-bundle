<?php

namespace Tourze\AccessTokenBundle\Tests\DependencyInjection;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\AccessTokenBundle\DependencyInjection\AccessTokenExtension;
use Tourze\PHPUnitSymfonyUnitTest\AbstractDependencyInjectionExtensionTestCase;

/**
 * @internal
 */
#[CoversClass(AccessTokenExtension::class)]
final class AccessTokenExtensionTest extends AbstractDependencyInjectionExtensionTestCase
{
}
