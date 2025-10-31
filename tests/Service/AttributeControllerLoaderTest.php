<?php

namespace Tourze\AccessTokenBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Routing\RouteCollection;
use Tourze\AccessTokenBundle\Service\AttributeControllerLoader;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(AttributeControllerLoader::class)]
#[RunTestsInSeparateProcesses]
final class AttributeControllerLoaderTest extends AbstractIntegrationTestCase
{
    protected function onSetUp(): void
    {
        // 集成测试不需要额外的设置
    }

    public function testSupportsShouldReturnFalse(): void
    {
        // supports方法应始终返回false，因为这个加载器通过autoload方法自动加载路由
        $loader = self::getService(AttributeControllerLoader::class);
        $result = $loader->supports('some_resource');
        $this->assertFalse($result);

        $result = $loader->supports('some_resource', 'some_type');
        $this->assertFalse($result);
    }

    public function testLoadShouldReturnRouteCollection(): void
    {
        // 由于load方法内部调用了autoload，我们测试它返回的集合类型
        $loader = self::getService(AttributeControllerLoader::class);
        $result = $loader->load('some_resource');

        $this->assertInstanceOf(RouteCollection::class, $result);
    }

    public function testAutoloadShouldReturnRouteCollection(): void
    {
        $loader = self::getService(AttributeControllerLoader::class);
        $result = $loader->autoload();

        $this->assertInstanceOf(RouteCollection::class, $result);

        // 由于我们无法直接测试内部的控制器加载逻辑，因为它依赖于原生的AttributeRouteControllerLoader
        // 我们只能测试返回类型，而不是具体路由内容
    }
}
