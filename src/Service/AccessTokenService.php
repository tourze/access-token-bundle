<?php

namespace AccessTokenBundle\Service;

use AccessTokenBundle\Entity\AccessToken;
use AccessTokenBundle\Repository\AccessTokenRepository;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * AccessToken 服务
 *
 * 集中管理访问令牌的创建、查询、续期等操作
 */
class AccessTokenService
{
    public function __construct(
        private readonly AccessTokenRepository $repository,
        private readonly RequestStack $requestStack,
        private readonly int $defaultExpiresIn = 86400, // 默认有效期1天
    ) {
    }

    /**
     * 创建新的访问令牌
     */
    public function createToken(UserInterface $user, ?int $expiresIn = null, ?string $deviceInfo = null): AccessToken
    {
        // 检查是否启用防止多点登录
        $preventMultipleLogin = $this->isPreventMultipleLoginEnabled();

        if ($preventMultipleLogin) {
            // 吊销用户所有现有的有效令牌
            $existingTokens = $this->findTokensByUser($user);
            foreach ($existingTokens as $existingToken) {
                $this->revokeToken($existingToken);
            }
        }

        $expiresIn = $expiresIn ?? $this->defaultExpiresIn;
        $token = AccessToken::create($user, $expiresIn, $deviceInfo);
        $this->repository->save($token);

        return $token;
    }

    /**
     * 通过令牌值获取有效的令牌
     */
    public function findToken(string $tokenValue): ?AccessToken
    {
        return $this->repository->findOneByValue($tokenValue);
    }

    /**
     * 获取用户所有有效令牌
     */
    public function findTokensByUser(UserInterface $user): array
    {
        return $this->repository->findValidTokensByUser($user);
    }

    /**
     * 验证令牌是否有效
     */
    public function validateToken(AccessToken $token): bool
    {
        return $token->isValid() && !$token->isExpired();
    }

    /**
     * 验证并自动续期访问令牌
     */
    public function validateAndExtendToken(string $tokenValue, int $expiresIn = 3600): ?AccessToken
    {
        $token = $this->findToken($tokenValue);

        if ($token === null || !$this->validateToken($token)) {
            return null;
        }

        // 获取客户端 IP
        $request = $this->requestStack->getCurrentRequest();
        $clientIp = $request?->getClientIp();

        // 更新访问信息并续期
        $token->updateAccessInfo($clientIp);
        $token->extend($expiresIn);

        $this->repository->save($token);

        return $token;
    }

    /**
     * 吊销令牌
     */
    public function revokeToken(AccessToken $token): void
    {
        $token->setValid(false);
        $this->repository->save($token);
    }

    /**
     * 删除令牌
     */
    public function deleteToken(AccessToken $token): void
    {
        $this->repository->remove($token);
    }

    /**
     * 清理过期的令牌
     */
    public function cleanupExpiredTokens(): int
    {
        return $this->repository->removeExpiredTokens();
    }

    /**
     * 检查是否启用防止多点登录
     */
    private function isPreventMultipleLoginEnabled(): bool
    {
        return filter_var(
            $_ENV['ACCESS_TOKEN_PREVENT_MULTIPLE_LOGIN'] ?? 'true',
            FILTER_VALIDATE_BOOLEAN
        );
    }
}
