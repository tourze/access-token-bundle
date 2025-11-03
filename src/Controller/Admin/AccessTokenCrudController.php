<?php

namespace Tourze\AccessTokenBundle\Controller\Admin;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminAction;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminCrud;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\User\UserInterface;
use Tourze\AccessTokenBundle\Entity\AccessToken;

/**
 * 访问令牌管理控制器
 *
 * @extends AbstractCrudController<AccessToken>
 */
#[AdminCrud(routePath: '/access-token/token', routeName: 'access_token_token')]
final class AccessTokenCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return AccessToken::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('访问令牌')
            ->setEntityLabelInPlural('访问令牌')
            ->setPageTitle('index', '访问令牌管理')
            ->setPageTitle('detail', '令牌详情')
            ->setPageTitle('new', '创建令牌')
            ->setPageTitle('edit', '编辑令牌')
            ->setHelp('index', '管理系统中的所有访问令牌，可以查看令牌状态、过期时间等信息')
            ->setDefaultSort(['id' => 'DESC'])
            ->setSearchFields(['id', 'token', 'deviceInfo', 'lastIp'])
            ->setPaginatorPageSize(20)
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        yield from $this->getBaseFields($pageName);
        yield from $this->getConditionalFields($pageName);
        yield from $this->getDetailFields();
        yield from $this->getDateTimeFields();
    }

    /**
     * @return iterable<FieldInterface>
     */
    private function getBaseFields(string $pageName): iterable
    {
        yield IdField::new('id', 'ID')->setMaxLength(9999)->hideOnForm();

        // 列表页：截断显示（安全考虑）
        yield TextField::new('token', '令牌')
            ->setMaxLength(32)
            ->setRequired(true)
            ->formatValue(fn ($value) => $value ? $this->formatToken(is_string($value) ? $value : strval($value)) : '')
            ->onlyOnIndex()
        ;

        // 详情页和表单页：完整显示
        yield TextField::new('token', '令牌')
            ->setMaxLength(32)
            ->setRequired(true)
            ->hideOnIndex()
        ;

        yield AssociationField::new('user', '用户')
            ->setRequired(true)
            ->formatValue(fn ($value) => $this->formatUser($value))
        ;

        yield BooleanField::new('valid', '有效状态')
            ->setHelp('令牌是否处于有效状态')
        ;
    }

    /**
     * @return iterable<FieldInterface>
     */
    private function getConditionalFields(string $pageName): iterable
    {
        if (Crud::PAGE_INDEX === $pageName || Crud::PAGE_DETAIL === $pageName) {
            yield BooleanField::new('expired', '是否过期')
                ->formatValue(fn ($value, AccessToken $entity) => $entity->isExpired())
                ->setVirtual(true)
            ;
        }
    }

    /**
     * @return iterable<FieldInterface>
     */
    private function getDetailFields(): iterable
    {
        yield TextField::new('deviceInfo', '设备信息')
            ->setMaxLength(50)
            ->hideOnIndex()
        ;

        yield TextField::new('lastIp', '最后访问IP')
            ->setMaxLength(15)
            ->hideOnIndex()
        ;
    }

    /**
     * @return iterable<FieldInterface>
     */
    private function getDateTimeFields(): iterable
    {
        yield DateTimeField::new('createTime', '创建时间')
            ->setFormat('yyyy-MM-dd HH:mm:ss')
            ->hideOnForm()
        ;

        yield DateTimeField::new('expireTime', '过期时间')
            ->setFormat('yyyy-MM-dd HH:mm:ss')
            ->setRequired(true)
            ->formatValue(fn ($value) => $this->formatExpireTime($value))
        ;

        yield DateTimeField::new('lastAccessTime', '最后访问时间')
            ->setFormat('yyyy-MM-dd HH:mm:ss')
            ->hideOnForm()
            ->formatValue(fn ($value) => $this->formatLastAccessTime($value))
        ;
    }

    public function configureActions(Actions $actions): Actions
    {
        // 撤销令牌操作
        $revokeAction = Action::new('revoke', '撤销令牌')
            ->linkToCrudAction('revokeToken')
            ->setIcon('fa fa-ban')
            ->displayIf(function (AccessToken $entity) {
                return $entity->isValid() && !$entity->isExpired();
            })
        ;

        // 续期令牌操作
        $extendAction = Action::new('extend', '续期令牌')
            ->linkToCrudAction('extendToken')
            ->setIcon('fa fa-clock')
            ->displayIf(function (AccessToken $entity) {
                return $entity->isValid();
            })
        ;

        // 激活令牌操作
        $activateAction = Action::new('activate', '激活令牌')
            ->linkToCrudAction('activateToken')
            ->setCssClass('btn btn-primary')
            ->setIcon('fa fa-check')
            ->displayIf(function (AccessToken $entity) {
                return !$entity->isValid();
            })
        ;

        return $actions
            ->add(Crud::PAGE_INDEX, Action::new(Action::DETAIL, '查看详情', 'fas fa-eye')->linkToCrudAction(Action::DETAIL))
            ->add(Crud::PAGE_INDEX, $revokeAction)
            ->add(Crud::PAGE_INDEX, $extendAction)
            ->add(Crud::PAGE_INDEX, $activateAction)
            ->add(Crud::PAGE_DETAIL, $revokeAction)
            ->add(Crud::PAGE_DETAIL, $extendAction)
            ->add(Crud::PAGE_DETAIL, $activateAction)
        ;
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(TextFilter::new('token', '令牌'))
            ->add(EntityFilter::new('user', '用户'))
            ->add(BooleanFilter::new('valid', '有效状态'))
            ->add(TextFilter::new('deviceInfo', '设备信息'))
            ->add(TextFilter::new('lastIp', '最后访问IP'))
            ->add(DateTimeFilter::new('createTime', '创建时间'))
            ->add(DateTimeFilter::new('expireTime', '过期时间'))
            ->add(DateTimeFilter::new('lastAccessTime', '最后访问时间'))
        ;
    }

    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        return parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters)
            ->leftJoin('entity.user', 'user')
            ->addSelect('user')
            ->orderBy('entity.id', 'DESC')
        ;
    }

    /**
     * 撤销令牌
     */
    #[AdminAction(routePath: '{entityId}/revoke', routeName: 'revoke_token')]
    public function revokeToken(AdminContext $context, Request $request): Response
    {
        $token = $context->getEntity()->getInstance();
        assert($token instanceof AccessToken);

        if (!$token->isValid()) {
            $this->addFlash('warning', '令牌已经被撤销');
            $referer = $context->getRequest()->headers->get('referer');

            return $this->redirect(null !== $referer ? $referer : '/admin');
        }

        $token->setValid(false);
        $this->getEntityManager()->persist($token);
        $this->getEntityManager()->flush();

        $this->addFlash('success', sprintf('令牌 %s 已被撤销', $this->formatToken($token->getToken())));
        $referer = $context->getRequest()->headers->get('referer');

        return $this->redirect(null !== $referer ? $referer : '/admin');
    }

    /**
     * 续期令牌
     */
    #[AdminAction(routePath: '{entityId}/extend', routeName: 'extend_token')]
    public function extendToken(AdminContext $context, Request $request): Response
    {
        $token = $context->getEntity()->getInstance();
        assert($token instanceof AccessToken);

        if (!$token->isValid()) {
            $this->addFlash('warning', '无效的令牌无法续期');
            $referer = $context->getRequest()->headers->get('referer');

            return $this->redirect(null !== $referer ? $referer : '/admin');
        }

        // 续期24小时
        $token->extend(24 * 3600);
        $this->getEntityManager()->persist($token);
        $this->getEntityManager()->flush();

        $this->addFlash('success', sprintf(
            '令牌 %s 已续期至 %s',
            $this->formatToken($token->getToken()),
            null !== $token->getExpireTime() ? $token->getExpireTime()->format('Y-m-d H:i:s') : '未知时间'
        ));
        $referer = $context->getRequest()->headers->get('referer');

        return $this->redirect(null !== $referer ? $referer : '/admin');
    }

    /**
     * 激活令牌
     */
    #[AdminAction(routePath: '{entityId}/activate', routeName: 'activate_token')]
    public function activateToken(AdminContext $context, Request $request): Response
    {
        $token = $context->getEntity()->getInstance();
        assert($token instanceof AccessToken);

        if ($token->isValid()) {
            $this->addFlash('info', '令牌已经是激活状态');
            $referer = $context->getRequest()->headers->get('referer');

            return $this->redirect(null !== $referer ? $referer : '/admin');
        }

        $token->setValid(true);
        $this->getEntityManager()->persist($token);
        $this->getEntityManager()->flush();

        $this->addFlash('success', sprintf('令牌 %s 已被激活', $this->formatToken($token->getToken())));
        $referer = $context->getRequest()->headers->get('referer');

        return $this->redirect(null !== $referer ? $referer : '/admin');
    }

    /**
     * 获取实体管理器
     */
    private function getEntityManager(): EntityManagerInterface
    {
        /** @var Registry $doctrine */
        $doctrine = $this->container->get('doctrine');
        $manager = $doctrine->getManager();

        if (!$manager instanceof EntityManagerInterface) {
            throw new \RuntimeException('Entity manager must be an instance of EntityManagerInterface');
        }

        return $manager;
    }

    /**
     * 格式化用户显示
     * @param mixed $user
     */
    private function formatUser($user): string
    {
        if (!$user instanceof UserInterface) {
            return '';
        }

        if (method_exists($user, 'getUsername')) {
            $username = $user->getUsername();

            return is_string($username) ? $username : strval($username);
        }

        return method_exists($user, '__toString') ? (string) $user : 'User';
    }

    /**
     * 格式化过期时间显示
     * @param mixed $value
     */
    private function formatExpireTime($value): string
    {
        if (!$value instanceof \DateTimeInterface) {
            return '';
        }

        $now = new \DateTimeImmutable();
        $isExpired = $value < $now;
        $formatted = $value->format('Y-m-d H:i:s');

        return $isExpired ? "🔴 {$formatted}" : "🟢 {$formatted}";
    }

    /**
     * 格式化最后访问时间显示
     * @param mixed $value
     */
    private function formatLastAccessTime($value): string
    {
        if (!$value instanceof \DateTimeInterface) {
            return '从未访问';
        }

        return $value->format('Y-m-d H:i:s');
    }

    /**
     * 格式化令牌显示（长令牌截断显示）
     */
    private function formatToken(?string $token): string
    {
        if (null === $token) {
            return '';
        }

        // 如果令牌长度大于等于12个字符，截断显示
        if (strlen($token) >= 12) {
            return substr($token, 0, 8) . '...' . substr($token, -4);
        }

        return $token;
    }
}
