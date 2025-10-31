<?php

namespace Tourze\AccessTokenBundle\Procedure;

use Symfony\Bridge\Doctrine\Security\User\UserLoaderInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Tourze\AccessTokenBundle\Exception\UserNotFoundException;
use Tourze\AccessTokenBundle\Service\AccessTokenService;
use Tourze\JsonRPC\Core\Attribute\MethodDoc;
use Tourze\JsonRPC\Core\Attribute\MethodExpose;
use Tourze\JsonRPC\Core\Attribute\MethodParam;
use Tourze\JsonRPC\Core\Attribute\MethodTag;
use Tourze\JsonRPC\Core\Procedure\BaseProcedure;

#[MethodTag(name: '访问令牌管理')]
#[MethodDoc(summary: '为指定用户生成访问令牌')]
#[MethodExpose(method: 'GenerateAccessToken')]
class GenerateAccessToken extends BaseProcedure
{
    #[Assert\NotBlank]
    #[Assert\Type(type: 'string')]
    #[MethodParam(description: '用户标识符')]
    public string $identifier;

    #[Assert\Type(type: 'integer')]
    #[Assert\Positive]
    #[MethodParam(description: '令牌有效期（秒），默认为1天')]
    public ?int $expiresIn = 86400;

    #[Assert\Length(max: 255)]
    #[MethodParam(description: '设备信息')]
    public ?string $deviceInfo = null;

    public function __construct(
        private readonly AccessTokenService $accessTokenService,
        private readonly UserLoaderInterface $userService,
    ) {
    }

    public function execute(): array
    {
        $user = $this->userService->loadUserByIdentifier($this->identifier);
        if (null === $user) {
            throw new UserNotFoundException($this->identifier);
        }

        $token = $this->accessTokenService->createToken(
            $user,
            $this->expiresIn ?? 60 * 60 * 24,
            $this->deviceInfo
        );

        return [
            'token' => $token->getToken(),
            'expire_time' => $token->getExpireTime()?->format('Y-m-d H:i:s'),
            'create_time' => $token->getCreateTime()?->format('Y-m-d H:i:s'),
            'user_id' => $token->getUser()->getUserIdentifier(),
            'device_info' => $token->getDeviceInfo(),
        ];
    }

    public static function getMockResult(): ?array
    {
        return [
            'token' => 'a1b2c3d4e5f6789012345678901234567890123456789012345678901234567890',
            'expire_time' => '2024-01-02 12:00:00',
            'create_time' => '2024-01-01 12:00:00',
            'user_id' => '123',
            'device_info' => 'iPhone 15',
        ];
    }
}
