<?php

namespace Tourze\AccessTokenBundle\Procedure;

use Symfony\Bridge\Doctrine\Security\User\UserLoaderInterface;
use Tourze\AccessTokenBundle\Exception\UserNotFoundException;
use Tourze\AccessTokenBundle\Param\GenerateAccessTokenParam;
use Tourze\AccessTokenBundle\Service\AccessTokenService;
use Tourze\JsonRPC\Core\Attribute\MethodDoc;
use Tourze\JsonRPC\Core\Attribute\MethodExpose;
use Tourze\JsonRPC\Core\Attribute\MethodTag;
use Tourze\JsonRPC\Core\Contracts\RpcParamInterface;
use Tourze\JsonRPC\Core\Procedure\BaseProcedure;
use Tourze\JsonRPC\Core\Result\ArrayResult;

#[MethodTag(name: '访问令牌管理')]
#[MethodDoc(summary: '为指定用户生成访问令牌')]
#[MethodExpose(method: 'GenerateAccessToken')]
class GenerateAccessToken extends BaseProcedure
{
    public function __construct(
        private readonly AccessTokenService $accessTokenService,
        private readonly UserLoaderInterface $userService,
    ) {
    }

    /**
     * @phpstan-param GenerateAccessTokenParam $param
     */
    public function execute(GenerateAccessTokenParam|RpcParamInterface $param): ArrayResult
    {
        $user = $this->userService->loadUserByIdentifier($param->identifier);
        if (null === $user) {
            throw new UserNotFoundException($param->identifier);
        }

        $token = $this->accessTokenService->createToken(
            $user,
            $param->expiresIn ?? 60 * 60 * 24,
            $param->deviceInfo
        );

        return new ArrayResult([
            'token' => $token->getToken(),
            'expire_time' => $token->getExpireTime()?->format('Y-m-d H:i:s'),
            'create_time' => $token->getCreateTime()?->format('Y-m-d H:i:s'),
            'user_id' => $token->getUser()->getUserIdentifier(),
            'device_info' => $token->getDeviceInfo(),
        ]);
    }
}
