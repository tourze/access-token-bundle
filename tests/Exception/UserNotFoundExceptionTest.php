<?php

declare(strict_types=1);

namespace Tourze\AccessTokenBundle\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\AccessTokenBundle\Exception\UserNotFoundException;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;

/**
 * @internal
 */
#[CoversClass(UserNotFoundException::class)]
final class UserNotFoundExceptionTest extends AbstractExceptionTestCase
{
    public function testExceptionMessage(): void
    {
        $userId = 123;
        $exception = new UserNotFoundException($userId);

        $this->assertEquals('用户标识符 123 不存在', $exception->getMessage());
        $this->assertEquals(0, $exception->getCode());
    }

    public function testExceptionWithPrevious(): void
    {
        $userId = 456;
        $previousException = new \RuntimeException('Previous error');
        $exception = new UserNotFoundException($userId, $previousException);

        $this->assertEquals('用户标识符 456 不存在', $exception->getMessage());
        $this->assertSame($previousException, $exception->getPrevious());
    }

    public function testExceptionIsInstanceOfRuntimeException(): void
    {
        $exception = new UserNotFoundException(789);
        $this->assertInstanceOf(\RuntimeException::class, $exception);
    }
}
