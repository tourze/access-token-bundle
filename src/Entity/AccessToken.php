<?php

namespace AccessTokenBundle\Entity;

use AccessTokenBundle\Repository\AccessTokenRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: AccessTokenRepository::class)]
#[ORM\Table(name: 'access_token', options: ['comment' => '访问令牌'])]
#[ORM\Index(name: 'idx_access_token_token', columns: ['token'])]
#[ORM\Index(name: 'idx_access_token_expires', columns: ['expires_at'])]
class AccessToken implements \Stringable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(options: ['comment' => '主键ID'])]
    private ?int $id = null;

    #[ORM\Column(length: 255, unique: true, options: ['comment' => '令牌值'])]
    private ?string $token = null;

    #[ORM\ManyToOne(targetEntity: UserInterface::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private UserInterface $user;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, options: ['comment' => '创建时间'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, options: ['comment' => '过期时间'])]
    private ?\DateTimeImmutable $expiresAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true, options: ['comment' => '最后访问时间'])]
    private ?\DateTimeImmutable $lastAccessedAt = null;

    #[ORM\Column(length: 255, nullable: true, options: ['comment' => '设备信息'])]
    private ?string $deviceInfo = null;

    #[ORM\Column(length: 45, nullable: true, options: ['comment' => '最后访问IP'])]
    private ?string $lastIp = null;

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

    public function setToken(string $token): static
    {
        $this->token = $token;

        return $this;
    }

    public function getUser(): UserInterface
    {
        return $this->user;
    }

    public function setUser(UserInterface $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getExpiresAt(): ?\DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(\DateTimeImmutable $expiresAt): static
    {
        $this->expiresAt = $expiresAt;

        return $this;
    }

    public function getLastAccessedAt(): ?\DateTimeImmutable
    {
        return $this->lastAccessedAt;
    }

    public function setLastAccessedAt(?\DateTimeImmutable $lastAccessedAt): static
    {
        $this->lastAccessedAt = $lastAccessedAt;

        return $this;
    }

    public function getDeviceInfo(): ?string
    {
        return $this->deviceInfo;
    }

    public function setDeviceInfo(?string $deviceInfo): static
    {
        $this->deviceInfo = $deviceInfo;

        return $this;
    }

    public function getLastIp(): ?string
    {
        return $this->lastIp;
    }

    public function setLastIp(?string $lastIp): static
    {
        $this->lastIp = $lastIp;

        return $this;
    }

    public function isValid(): bool
    {
        return $this->valid;
    }

    public function setValid(bool $valid): static
    {
        $this->valid = $valid;

        return $this;
    }

    /**
     * 判断令牌是否已过期
     */
    public function isExpired(): bool
    {
        return $this->expiresAt < new \DateTimeImmutable();
    }

    /**
     * 续期令牌有效期
     */
    public function extend(int $expiresInSeconds = 3600): static
    {
        $this->expiresAt = new \DateTimeImmutable(sprintf('+%d seconds', $expiresInSeconds));
        $this->lastAccessedAt = new \DateTimeImmutable();

        return $this;
    }

    /**
     * 更新令牌最后访问信息
     */
    public function updateAccessInfo(?string $ip = null): static
    {
        $this->lastAccessedAt = new \DateTimeImmutable();
        if ($ip !== null) {
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
        $token->setCreatedAt(new \DateTimeImmutable());
        $token->setExpiresAt(new \DateTimeImmutable(sprintf('+%d seconds', $expiresInSeconds)));
        $token->setDeviceInfo($deviceInfo);
        
        return $token;
    }
}
