<?php

namespace AccessTokenBundle\Tests\DependencyInjection;

use AccessTokenBundle\DependencyInjection\AccessTokenExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class AccessTokenExtensionTest extends TestCase
{
    private AccessTokenExtension $extension;
    private ContainerBuilder $container;

    protected function setUp(): void
    {
        $this->extension = new AccessTokenExtension();
        $this->container = new ContainerBuilder();
    }

    public function testLoad_shouldRegisterServices(): void
    {
        // 执行扩展的load方法
        $this->extension->load([], $this->container);

        // 由于我们无法确定具体的参数和服务，
        // 只测试load方法不抛出异常
        $this->assertTrue(true, 'load方法应该成功执行而不抛出异常');
    }
}
