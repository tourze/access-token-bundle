<?php

namespace AccessTokenBundle\Tests\Repository;

use AccessTokenBundle\Entity\AccessToken;
use AccessTokenBundle\Repository\AccessTokenRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\User\UserInterface;

class AccessTokenRepositoryTest extends TestCase
{
    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(AccessTokenRepository::class));
    }

    public function testExtendsServiceEntityRepository(): void
    {
        $reflection = new \ReflectionClass(AccessTokenRepository::class);
        $this->assertTrue($reflection->isSubclassOf(\Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository::class));
    }

    public function testHasFindOneByValueMethod(): void
    {
        $reflection = new \ReflectionClass(AccessTokenRepository::class);
        $this->assertTrue($reflection->hasMethod('findOneByValue'));
        
        $method = $reflection->getMethod('findOneByValue');
        $this->assertTrue($method->isPublic());
        $this->assertEquals(1, $method->getNumberOfParameters());
        
        $params = $method->getParameters();
        $this->assertEquals('value', $params[0]->getName());
        $this->assertEquals('string', (string)$params[0]->getType());
        
        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertTrue($returnType->allowsNull());
        $this->assertEquals('?' . AccessToken::class, (string)$returnType);
    }

    public function testHasFindValidTokensByUserMethod(): void
    {
        $reflection = new \ReflectionClass(AccessTokenRepository::class);
        $this->assertTrue($reflection->hasMethod('findValidTokensByUser'));
        
        $method = $reflection->getMethod('findValidTokensByUser');
        $this->assertTrue($method->isPublic());
        $this->assertEquals(1, $method->getNumberOfParameters());
        
        $params = $method->getParameters();
        $this->assertEquals('user', $params[0]->getName());
        $this->assertEquals(UserInterface::class, (string)$params[0]->getType());
        
        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertEquals('array', (string)$returnType);
    }

    public function testHasRemoveExpiredTokensMethod(): void
    {
        $reflection = new \ReflectionClass(AccessTokenRepository::class);
        $this->assertTrue($reflection->hasMethod('removeExpiredTokens'));
        
        $method = $reflection->getMethod('removeExpiredTokens');
        $this->assertTrue($method->isPublic());
        $this->assertEquals(0, $method->getNumberOfParameters());
        
        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertEquals('int', (string)$returnType);
    }

    public function testHasSaveMethod(): void
    {
        $reflection = new \ReflectionClass(AccessTokenRepository::class);
        $this->assertTrue($reflection->hasMethod('save'));
        
        $method = $reflection->getMethod('save');
        $this->assertTrue($method->isPublic());
        $this->assertEquals(2, $method->getNumberOfParameters());
        
        $params = $method->getParameters();
        $this->assertEquals('accessToken', $params[0]->getName());
        $this->assertEquals(AccessToken::class, (string)$params[0]->getType());
        
        $this->assertEquals('flush', $params[1]->getName());
        $this->assertEquals('bool', (string)$params[1]->getType());
        $this->assertTrue($params[1]->isDefaultValueAvailable());
        $this->assertTrue($params[1]->getDefaultValue());
        
        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertEquals('void', (string)$returnType);
    }

    public function testHasRemoveMethod(): void
    {
        $reflection = new \ReflectionClass(AccessTokenRepository::class);
        $this->assertTrue($reflection->hasMethod('remove'));
        
        $method = $reflection->getMethod('remove');
        $this->assertTrue($method->isPublic());
        $this->assertEquals(2, $method->getNumberOfParameters());
        
        $params = $method->getParameters();
        $this->assertEquals('accessToken', $params[0]->getName());
        $this->assertEquals(AccessToken::class, (string)$params[0]->getType());
        
        $this->assertEquals('flush', $params[1]->getName());
        $this->assertEquals('bool', (string)$params[1]->getType());
        $this->assertTrue($params[1]->isDefaultValueAvailable());
        $this->assertTrue($params[1]->getDefaultValue());
        
        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertEquals('void', (string)$returnType);
    }

    public function testRepositoryForCorrectEntity(): void
    {
        $reflection = new \ReflectionClass(AccessTokenRepository::class);
        $constructor = $reflection->getConstructor();
        $this->assertNotNull($constructor);
        
        // Test that it passes AccessToken::class to parent constructor
        $params = $constructor->getParameters();
        $this->assertEquals(1, count($params));
        $this->assertEquals('registry', $params[0]->getName());
    }
}