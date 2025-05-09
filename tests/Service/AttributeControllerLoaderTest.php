<?php

namespace AccessTokenBundle\Tests\Service;

use AccessTokenBundle\Service\AttributeControllerLoader;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\RouteCollection;

class AttributeControllerLoaderTest extends TestCase
{
    private AttributeControllerLoader $loader;
    
    protected function setUp(): void
    {
        $this->loader = new AttributeControllerLoader();
    }
    
    public function testSupports_shouldReturnFalse(): void
    {
        // supports方法应始终返回false，因为这个加载器通过autoload方法自动加载路由
        $result = $this->loader->supports('some_resource');
        $this->assertFalse($result);
        
        $result = $this->loader->supports('some_resource', 'some_type');
        $this->assertFalse($result);
    }
    
    public function testLoad_shouldReturnRouteCollection(): void
    {
        // 由于load方法内部调用了autoload，我们测试它返回的集合类型
        $result = $this->loader->load('some_resource');
        
        $this->assertInstanceOf(RouteCollection::class, $result);
    }
    
    public function testAutoload_shouldReturnRouteCollection(): void
    {
        $result = $this->loader->autoload();
        
        $this->assertInstanceOf(RouteCollection::class, $result);
        
        // 由于我们无法直接测试内部的控制器加载逻辑，因为它依赖于原生的AttributeRouteControllerLoader
        // 我们只能测试返回类型，而不是具体路由内容
    }
} 