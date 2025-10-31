<?php

namespace Tourze\AccessTokenBundle\Tests\Service;

use Knp\Menu\MenuFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\AccessTokenBundle\Entity\AccessToken;
use Tourze\AccessTokenBundle\Service\AdminMenu;
use Tourze\EasyAdminMenuBundle\Service\LinkGeneratorInterface;
use Tourze\PHPUnitSymfonyWebTest\AbstractEasyAdminMenuTestCase;

/**
 * AdminMenu 服务测试
 *
 * @internal
 */
#[CoversClass(AdminMenu::class)]
#[RunTestsInSeparateProcesses]
final class AdminMenuTest extends AbstractEasyAdminMenuTestCase
{
    protected function onSetUp(): void
    {
        // 实现抽象方法，可以为空实现
    }

    public function testAdminMenuCreatesSecurityMenu(): void
    {
        // 使用匿名类实现LinkGeneratorInterface，避免使用Mock
        $linkGenerator = new class implements LinkGeneratorInterface {
            public function getCurdListPage(string $entityClass): string
            {
                return '/admin/access-token/token';
            }

            public function extractEntityFqcn(string $url = ''): string
            {
                return AccessToken::class;
            }

            public function setDashboard(string $dashboardControllerFqcn): void
            {
                // 测试中不需要实际实现
            }
        };

        self::getContainer()->set(LinkGeneratorInterface::class, $linkGenerator);
        $adminMenu = self::getService(AdminMenu::class);

        // 创建根菜单项
        $menuFactory = new MenuFactory();
        $rootMenu = $menuFactory->createItem('root');

        // 调用菜单服务
        $adminMenu($rootMenu);

        // 验证安全管理菜单已创建
        $securityMenu = $rootMenu->getChild('安全管理');
        $this->assertNotNull($securityMenu);

        // 验证访问令牌菜单已创建
        $tokenMenu = $securityMenu->getChild('访问令牌');
        $this->assertNotNull($tokenMenu);
        $this->assertSame('/admin/access-token/token', $tokenMenu->getUri());
        $this->assertSame('fas fa-key', $tokenMenu->getAttribute('icon'));
    }

    public function testAdminMenuWorksWithExistingSecurityMenu(): void
    {
        // 使用匿名类实现LinkGeneratorInterface，避免使用Mock
        $linkGenerator = new class implements LinkGeneratorInterface {
            public function getCurdListPage(string $entityClass): string
            {
                return '/admin/access-token/token';
            }

            public function extractEntityFqcn(string $url = ''): string
            {
                return AccessToken::class;
            }

            public function setDashboard(string $dashboardControllerFqcn): void
            {
                // 测试中不需要实际实现
            }
        };

        self::getContainer()->set(LinkGeneratorInterface::class, $linkGenerator);
        $adminMenu = self::getService(AdminMenu::class);

        // 创建已有安全管理菜单的根菜单
        $menuFactory = new MenuFactory();
        $rootMenu = $menuFactory->createItem('root');
        $rootMenu->addChild('安全管理'); // 预先创建安全管理菜单

        // 调用菜单服务
        $adminMenu($rootMenu);

        // 验证不会重复创建安全管理菜单
        $securityMenu = $rootMenu->getChild('安全管理');
        $this->assertNotNull($securityMenu);

        // 验证访问令牌菜单已添加到现有菜单中
        $tokenMenu = $securityMenu->getChild('访问令牌');
        $this->assertNotNull($tokenMenu);
    }

    public function testAdminMenuIsCallable(): void
    {
        $adminMenu = self::getService(AdminMenu::class);
        $this->assertIsCallable($adminMenu);
    }
}
