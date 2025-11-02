<?php

namespace Tourze\AccessTokenBundle\Service;

use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Http\AccessToken\AccessTokenHandlerInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;

#[Autoconfigure(public: true)]
readonly class AccessTokenHandler implements AccessTokenHandlerInterface
{
    public function __construct(
        private AccessTokenService $accessTokenService,
    ) {
    }

    public function getUserBadgeFrom(string $accessToken): UserBadge
    {
        // 使用服务验证并续期令牌
        // 默认自动续期一小时
        $renewalTime = $_ENV['ACCESS_TOKEN_RENEWAL_TIME'] ?? 3600;
        $accessTokenEntity = $this->accessTokenService->validateAndExtendToken(
            $accessToken,
            is_numeric($renewalTime) ? (int) $renewalTime : 3600,
        );

        if (null === $accessTokenEntity) {
            throw new BadCredentialsException('无效的访问令牌');
        }

        // 返回用户标识
        return new UserBadge($accessTokenEntity->getUser()->getUserIdentifier());
    }
}
