<?php

declare(strict_types=1);

namespace Tourze\AccessTokenBundle\Param;

use Symfony\Component\Validator\Constraints as Assert;
use Tourze\JsonRPC\Core\Attribute\MethodParam;
use Tourze\JsonRPC\Core\Contracts\RpcParamInterface;

/**
 * GenerateAccessToken Procedure 的参数对象
 *
 * 用于生成访问令牌的请求参数
 */
readonly class GenerateAccessTokenParam implements RpcParamInterface
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Type(type: 'string')]
        #[MethodParam(description: '用户标识符')]
        public string $identifier,

        #[Assert\Type(type: 'integer')]
        #[Assert\Positive]
        #[MethodParam(description: '令牌有效期（秒），默认为1天')]
        public ?int $expiresIn = 86400,

        #[Assert\Length(max: 255)]
        #[MethodParam(description: '设备信息')]
        public ?string $deviceInfo = null,
    ) {
    }
}
