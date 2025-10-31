<?php

namespace Tourze\AccessTokenBundle\Exception;

class UserNotFoundException extends \RuntimeException
{
    public function __construct(string|int $identifier, ?\Throwable $previous = null)
    {
        parent::__construct(sprintf('用户标识符 %s 不存在', $identifier), 0, $previous);
    }
}
