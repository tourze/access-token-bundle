<?php

declare(strict_types=1);

namespace Tourze\AccessTokenBundle\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\AccessTokenBundle\AccessTokenBundle;
use Tourze\PHPUnitSymfonyKernelTest\AbstractBundleTestCase;

/**
 * @internal
 */
#[CoversClass(AccessTokenBundle::class)]
#[RunTestsInSeparateProcesses]
final class AccessTokenBundleTest extends AbstractBundleTestCase
{
}
