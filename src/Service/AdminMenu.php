<?php

namespace Tourze\AccessTokenBundle\Service;

use Knp\Menu\ItemInterface;
use Tourze\AccessTokenBundle\Entity\AccessToken;
use Tourze\EasyAdminMenuBundle\Service\LinkGeneratorInterface;
use Tourze\EasyAdminMenuBundle\Service\MenuProviderInterface;

/**
 * 访问令牌管理菜单服务
 */
readonly class AdminMenu implements MenuProviderInterface
{
    public function __construct(
        private LinkGeneratorInterface $linkGenerator,
    ) {
    }

    public function __invoke(ItemInterface $item): void
    {
        if (null === $item->getChild('安全管理')) {
            $item->addChild('安全管理');
        }

        $securityMenu = $item->getChild('安全管理');
        if (null === $securityMenu) {
            return;
        }

        // 访问令牌管理菜单
        $securityMenu->addChild('访问令牌')
            ->setUri($this->linkGenerator->getCurdListPage(AccessToken::class))
            ->setAttribute('icon', 'fas fa-key')
            ->setAttribute('description', '管理系统访问令牌')
        ;
    }
}
