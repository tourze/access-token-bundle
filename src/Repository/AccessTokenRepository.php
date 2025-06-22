<?php

namespace AccessTokenBundle\Repository;

use AccessTokenBundle\Entity\AccessToken;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @extends ServiceEntityRepository<AccessToken>
 */
class AccessTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AccessToken::class);
    }

    /**
     * 通过token值查找有效的访问令牌
     */
    public function findOneByValue(string $value): ?AccessToken
    {
        return $this->createQueryBuilder('t')
            ->where('t.token = :token')
            ->andWhere('t.valid = :valid')
            ->andWhere('t.expiresAt > :now')
            ->setParameter('token', $value)
            ->setParameter('valid', true)
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * 查找用户的所有有效令牌
     *
     * @return AccessToken[]
     */
    public function findValidTokensByUser(UserInterface $user): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.user = :user')
            ->andWhere('t.valid = :valid')
            ->andWhere('t.expiresAt > :now')
            ->setParameter('user', $user)
            ->setParameter('valid', true)
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('t.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * 删除过期的令牌
     */
    public function removeExpiredTokens(): int
    {
        $now = new \DateTimeImmutable();

        $qb = $this->createQueryBuilder('t');
        $expiredTokenIds = $qb->select('t.id')
            ->where($qb->expr()->orX(
                $qb->expr()->lt('t.expiresAt', ':now'),
                $qb->expr()->eq('t.valid', ':invalid')
            ))
            ->setParameter('now', $now)
            ->setParameter('invalid', false)
            ->getQuery()
            ->getResult();

        if (empty($expiredTokenIds)) {
            return 0;
        }

        $ids = array_column($expiredTokenIds, 'id');

        return $this->createQueryBuilder('t')
            ->delete()
            ->where('t.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->execute();
    }

    /**
     * 保存访问令牌
     */
    public function save(AccessToken $accessToken, bool $flush = true): void
    {
        $this->getEntityManager()->persist($accessToken);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * 删除访问令牌
     */
    public function remove(AccessToken $accessToken, bool $flush = true): void
    {
        $this->getEntityManager()->remove($accessToken);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
