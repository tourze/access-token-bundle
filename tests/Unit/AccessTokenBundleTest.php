<?php

namespace AccessTokenBundle\Tests\Unit;

use AccessTokenBundle\AccessTokenBundle;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class AccessTokenBundleTest extends TestCase
{
    public function testBundleExtendsSymfonyBundle(): void
    {
        $bundle = new AccessTokenBundle();
        $this->assertInstanceOf(Bundle::class, $bundle);
    }

    public function testGetPath(): void
    {
        $bundle = new AccessTokenBundle();
        $this->assertStringEndsWith('access-token-bundle/src', $bundle->getPath());
    }
}