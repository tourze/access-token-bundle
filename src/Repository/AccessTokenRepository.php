<?php

namespace Tourze\AccessTokenBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\User\UserInterface;
use Tourze\AccessTokenBundle\Entity\AccessToken;
use Tourze\PHPUnitSymfonyKernelTest\Attribute\AsRepository;

/**
 * @extends ServiceEntityRepository<AccessToken>
 */
#[AsRepository(entityClass: AccessToken::class)]
class AccessTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AccessToken::class);
    }

    /**
     * 保存访问令牌实体
     */
    public function save(AccessToken $entity, bool $flush = true): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * 删除访问令牌实体
     */
    public function remove(AccessToken $entity, bool $flush = true): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * 通过token值查找有效的访问令牌
     */
    public function findOneByValue(string $value): ?AccessToken
    {
        /** @var AccessToken|null $result */
        $result = $this->createQueryBuilder('t')
            ->where('t.token = :token')
            ->andWhere('t.valid = :valid')
            ->andWhere('t.expireTime > :now')
            ->setParameter('token', $value)
            ->setParameter('valid', true)
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getOneOrNullResult()
        ;

        return $result;
    }

    /**
     * 查找用户的所有有效令牌
     *
     * @return list<AccessToken>
     */
    public function findValidTokensByUser(UserInterface $user): array
    {
        /** @var list<AccessToken> $result */
        $result = $this->createQueryBuilder('t')
            ->where('t.user = :user')
            ->andWhere('t.valid = :valid')
            ->andWhere('t.expireTime > :now')
            ->setParameter('user', $user)
            ->setParameter('valid', true)
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('t.createTime', 'DESC')
            ->getQuery()
            ->getResult()
        ;

        return $result;
    }

    /**
     * 删除过期的令牌
     */
    public function removeExpiredTokens(): int
    {
        $now = new \DateTimeImmutable();

        $qb = $this->createQueryBuilder('t');
        /** @var array<int, array{id: int}> $expiredTokenIds */
        $expiredTokenIds = $qb->select('t.id')
            ->where($qb->expr()->orX(
                $qb->expr()->lt('t.expireTime', ':now'),
                $qb->expr()->eq('t.valid', ':invalid')
            ))
            ->setParameter('now', $now)
            ->setParameter('invalid', false)
            ->getQuery()
            ->getResult()
        ;

        if ([] === $expiredTokenIds) {
            return 0;
        }

        /** @var list<int> $ids */
        $ids = array_column($expiredTokenIds, 'id');

        /** @var int $deletedCount */
        $deletedCount = $this->createQueryBuilder('t')
            ->delete()
            ->where('t.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->execute()
        ;

        return $deletedCount;
    }
}
