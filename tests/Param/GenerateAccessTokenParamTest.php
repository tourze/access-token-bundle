<?php

declare(strict_types=1);

namespace Tourze\AccessTokenBundle\Tests\Param;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;
use Tourze\AccessTokenBundle\Param\GenerateAccessTokenParam;
use Tourze\JsonRPC\Core\Contracts\RpcParamInterface;

/**
 * GenerateAccessTokenParam 单元测试
 *
 * @internal
 */
#[CoversClass(GenerateAccessTokenParam::class)]
final class GenerateAccessTokenParamTest extends TestCase
{
    public function testImplementsRpcParamInterface(): void
    {
        $param = new GenerateAccessTokenParam(identifier: 'test-user');

        $this->assertInstanceOf(RpcParamInterface::class, $param);
    }

    public function testConstructorWithRequiredParameterOnly(): void
    {
        $param = new GenerateAccessTokenParam(identifier: 'user123');

        $this->assertSame('user123', $param->identifier);
        $this->assertSame(86400, $param->expiresIn);
        $this->assertNull($param->deviceInfo);
    }

    public function testConstructorWithAllParameters(): void
    {
        $param = new GenerateAccessTokenParam(
            identifier: 'user456',
            expiresIn: 3600,
            deviceInfo: 'iPhone 15 Pro',
        );

        $this->assertSame('user456', $param->identifier);
        $this->assertSame(3600, $param->expiresIn);
        $this->assertSame('iPhone 15 Pro', $param->deviceInfo);
    }

    public function testClassIsReadonly(): void
    {
        $reflection = new \ReflectionClass(GenerateAccessTokenParam::class);

        $this->assertTrue($reflection->isReadOnly());
    }

    public function testPropertiesArePublicReadonly(): void
    {
        $reflection = new \ReflectionClass(GenerateAccessTokenParam::class);

        $properties = ['identifier', 'expiresIn', 'deviceInfo'];

        foreach ($properties as $propertyName) {
            $property = $reflection->getProperty($propertyName);
            $this->assertTrue($property->isPublic(), "{$propertyName} should be public");
            $this->assertTrue($property->isReadOnly(), "{$propertyName} should be readonly");
        }
    }

    public function testValidationFailsWhenIdentifierIsBlank(): void
    {
        $param = new GenerateAccessTokenParam(identifier: '');

        $validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();

        $violations = $validator->validate($param);

        $this->assertGreaterThan(0, count($violations));
        $this->assertSame('identifier', $violations->get(0)->getPropertyPath());
    }

    public function testValidationFailsWhenExpiresInIsNotPositive(): void
    {
        $param = new GenerateAccessTokenParam(identifier: 'user123', expiresIn: -1);

        $validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();

        $violations = $validator->validate($param);

        $this->assertGreaterThan(0, count($violations));
        $propertyPaths = array_map(fn ($v) => $v->getPropertyPath(), iterator_to_array($violations));
        $this->assertContains('expiresIn', $propertyPaths);
    }

    public function testValidationFailsWhenDeviceInfoIsTooLong(): void
    {
        $param = new GenerateAccessTokenParam(
            identifier: 'user123',
            deviceInfo: str_repeat('a', 256),
        );

        $validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();

        $violations = $validator->validate($param);

        $this->assertGreaterThan(0, count($violations));
        $propertyPaths = array_map(fn ($v) => $v->getPropertyPath(), iterator_to_array($violations));
        $this->assertContains('deviceInfo', $propertyPaths);
    }

    public function testValidationPassesWithValidParameters(): void
    {
        $param = new GenerateAccessTokenParam(
            identifier: 'valid-user',
            expiresIn: 7200,
            deviceInfo: 'Valid Device',
        );

        $validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();

        $violations = $validator->validate($param);

        $this->assertCount(0, $violations);
    }

    public function testHasMethodParamAttributes(): void
    {
        $reflection = new \ReflectionClass(GenerateAccessTokenParam::class);
        $constructor = $reflection->getConstructor();

        $this->assertNotNull($constructor);

        foreach ($constructor->getParameters() as $parameter) {
            $attrs = $parameter->getAttributes(\Tourze\JsonRPC\Core\Attribute\MethodParam::class);
            $this->assertNotEmpty($attrs, "Parameter {$parameter->getName()} should have MethodParam attribute");
        }
    }
}
