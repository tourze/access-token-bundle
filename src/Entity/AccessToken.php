<?php

namespace Tourze\AccessTokenBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Tourze\AccessTokenBundle\Repository\AccessTokenRepository;
use Tourze\DoctrineIndexedBundle\Attribute\IndexColumn;

#[ORM\Entity(repositoryClass: AccessTokenRepository::class)]
#[ORM\Table(name: 'access_token', options: ['comment' => '访问令牌'])]
class AccessToken implements \Stringable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(options: ['comment' => '主键ID'])]
    private ?int $id = null;

    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    #[IndexColumn]
    #[ORM\Column(length: 255, unique: true, options: ['comment' => '令牌值'])]
    private ?string $token = null;

    #[ORM\ManyToOne(targetEntity: UserInterface::class, cascade: ['persist'])]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private UserInterface $user;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, options: ['comment' => '创建时间'])]
    private ?\DateTimeImmutable $createTime = null;

    #[Assert\NotNull]
    #[IndexColumn]
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, options: ['comment' => '过期时间'])]
    private ?\DateTimeImmutable $expireTime = null;

    #[Assert\Type(type: \DateTimeImmutable::class)]
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true, options: ['comment' => '最后访问时间'])]
    private ?\DateTimeImmutable $lastAccessTime = null;

    #[Assert\Length(max: 255)]
    #[ORM\Column(length: 255, nullable: true, options: ['comment' => '设备信息'])]
    private ?string $deviceInfo = null;

    #[Assert\Length(max: 45)]
    #[ORM\Column(length: 45, nullable: true, options: ['comment' => '最后访问IP'])]
    private ?string $lastIp = null;

    #[Assert\Type(type: 'bool')]
    #[ORM\Column(options: ['comment' => '是否有效'])]
    private bool $valid = true;

    public function __toString(): string
    {
        return $this->token ?? '';
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getToken(): ?string
    {
        return $this->token;
    }

    public function setToken(string $token): void
    {
        $this->token = $token;
    }

    public function getUser(): UserInterface
    {
        return $this->user;
    }

    public function setUser(UserInterface $user): void
    {
        $this->user = $user;
    }

    public function getCreateTime(): ?\DateTimeImmutable
    {
        return $this->createTime;
    }

    public function setCreateTime(\DateTimeImmutable $createTime): void
    {
        $this->createTime = $createTime;
    }

    public function getExpireTime(): ?\DateTimeImmutable
    {
        return $this->expireTime;
    }

    public function setExpireTime(\DateTimeImmutable $expireTime): void
    {
        $this->expireTime = $expireTime;
    }

    public function getLastAccessTime(): ?\DateTimeImmutable
    {
        return $this->lastAccessTime;
    }

    public function setLastAccessTime(?\DateTimeImmutable $lastAccessTime): void
    {
        $this->lastAccessTime = $lastAccessTime;
    }

    public function getDeviceInfo(): ?string
    {
        return $this->deviceInfo;
    }

    public function setDeviceInfo(?string $deviceInfo): void
    {
        $this->deviceInfo = $deviceInfo;
    }

    public function getLastIp(): ?string
    {
        return $this->lastIp;
    }

    public function setLastIp(?string $lastIp): void
    {
        $this->lastIp = $lastIp;
    }

    public function isValid(): bool
    {
        return $this->valid;
    }

    public function setValid(bool $valid): void
    {
        $this->valid = $valid;
    }

    /**
     * 判断令牌是否已过期
     */
    public function isExpired(): bool
    {
        return $this->expireTime < new \DateTimeImmutable();
    }

    /**
     * 续期令牌有效期
     */
    public function extend(int $expiresInSeconds = 3600): static
    {
        // 从当前过期时间延长，如果已过期则从当前时间开始
        $now = new \DateTimeImmutable();
        $baseTime = null !== $this->expireTime && $this->expireTime > $now
            ? $this->expireTime
            : $now;

        $this->expireTime = $baseTime->modify(sprintf('+%d seconds', $expiresInSeconds));
        $this->lastAccessTime = new \DateTimeImmutable();

        return $this;
    }

    /**
     * 更新令牌最后访问信息
     */
    public function updateAccessInfo(?string $ip = null): static
    {
        $this->lastAccessTime = new \DateTimeImmutable();
        if (null !== $ip) {
            $this->lastIp = $ip;
        }

        return $this;
    }

    /**
     * 创建新的访问令牌
     */
    public static function create(UserInterface $user, int $expiresInSeconds = 3600, ?string $deviceInfo = null): self
    {
        $token = new self();
        $token->setUser($user);
        $token->setToken(bin2hex(random_bytes(32))); // 生成64字符的随机令牌
        $token->setCreateTime(new \DateTimeImmutable());
        $token->setExpireTime(new \DateTimeImmutable(sprintf('+%d seconds', $expiresInSeconds)));
        $token->setDeviceInfo($deviceInfo);

        return $token;
    }
}
