<?php

namespace AccessTokenBundle\Command;

use AccessTokenBundle\Service\AccessTokenService;
use Symfony\Bridge\Doctrine\Security\User\UserLoaderInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: self::NAME,
    description: '为用户创建新的访问令牌',
)]
class CreateAccessTokenCommand extends Command
{
    public const NAME = 'app:create-access-token';
    public function __construct(
        private readonly UserLoaderInterface $userLoader,
        private readonly AccessTokenService $accessTokenService,
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('username', InputArgument::REQUIRED, '用户名')
            ->addOption('expires', 't', InputOption::VALUE_OPTIONAL, '令牌有效期（秒）', 86400)
            ->addOption('device', 'd', InputOption::VALUE_OPTIONAL, '设备信息', null);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $username = $input->getArgument('username');
        $expiresIn = (int)$input->getOption('expires');
        $deviceInfo = $input->getOption('device');

        // 查找用户
        $user = $this->userLoader->loadUserByIdentifier($username);

        if ($user === null) {
            $io->error(sprintf('用户 "%s" 不存在', $username));
            return Command::FAILURE;
        }

        // 使用服务创建访问令牌
        $accessToken = $this->accessTokenService->createToken($user, $expiresIn, $deviceInfo);
        $expireDate = $accessToken->getExpiresAt()->format('Y-m-d H:i:s');

        $io->success([
            sprintf('为用户 "%s" 生成了新的访问令牌', $username),
            sprintf('令牌值: %s', $accessToken->getToken()),
            sprintf('过期时间: %s', $expireDate),
            sprintf('使用方式: Authorization: Bearer %s', $accessToken->getToken()),
        ]);

        return Command::SUCCESS;
    }
}
