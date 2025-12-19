<?php

namespace Tourze\AccessTokenBundle\Command;

use Symfony\Bridge\Doctrine\Security\User\UserLoaderInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tourze\AccessTokenBundle\Service\AccessTokenService;

#[AsCommand(
    name: self::NAME,
    description: '为用户创建新的访问令牌',
)]
final class CreateAccessTokenCommand extends Command
{
    public const NAME = 'app:create-access-token';

    public function __construct(
        private readonly UserLoaderInterface $userLoader,
        private readonly AccessTokenService $accessTokenService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('username', InputArgument::REQUIRED, '用户名')
            ->addOption('expires', 't', InputOption::VALUE_OPTIONAL, '令牌有效期（秒）', 86400)
            ->addOption('device', 'd', InputOption::VALUE_OPTIONAL, '设备信息', null)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $params = $this->getValidatedParams($input);
        } catch (\InvalidArgumentException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $username = $params['username'];
        $expiresIn = $params['expiresIn'];
        $deviceInfo = $params['deviceInfo'];

        // 查找用户
        $user = $this->userLoader->loadUserByIdentifier($username);

        if (null === $user) {
            $io->error(sprintf('用户 "%s" 不存在', $username));

            return Command::FAILURE;
        }

        // 使用服务创建访问令牌
        $accessToken = $this->accessTokenService->createToken($user, $expiresIn, $deviceInfo);
        $expireTime = $accessToken->getExpireTime();
        $expireDate = null !== $expireTime ? $expireTime->format('Y-m-d H:i:s') : '未知';

        $io->success([
            sprintf('为用户 "%s" 生成了新的访问令牌', $username),
            sprintf('令牌值: %s', $accessToken->getToken()),
            sprintf('过期时间: %s', $expireDate),
            sprintf('使用方式: Authorization: Bearer %s', $accessToken->getToken()),
        ]);

        return Command::SUCCESS;
    }

    /**
     * 解析与校验所有入参,失败抛出 InvalidArgumentException
     *
     * @return array{username: string, expiresIn: int, deviceInfo: string|null}
     */
    private function getValidatedParams(InputInterface $input): array
    {
        $rawUsername = $input->getArgument('username');
        $rawExpires = $input->getOption('expires');
        $rawDevice = $input->getOption('device');

        $username = $this->assertUsername($rawUsername);
        $expiresIn = $this->parseExpires($rawExpires);
        $deviceInfo = $this->parseDevice($rawDevice);

        return [
            'username' => $username,
            'expiresIn' => $expiresIn,
            'deviceInfo' => $deviceInfo,
        ];
    }

    /**
     * 校验用户名:必须为非空字符串
     *
     * @param mixed $rawUsername
     */
    private function assertUsername($rawUsername): string
    {
        if (!is_string($rawUsername) || '' === trim($rawUsername)) {
            throw new \InvalidArgumentException('参数 "username" 必须为非空字符串');
        }

        return $rawUsername;
    }

    /**
     * 校验并解析过期时间:null 使用默认值;接受整数或数字字符串;必须为正整数
     *
     * @param mixed $rawExpires
     */
    private function parseExpires($rawExpires): int
    {
        $expiresInDefault = 86400;

        if (null === $rawExpires) {
            $expiresIn = $expiresInDefault;
        } elseif (is_int($rawExpires)) {
            $expiresIn = $rawExpires;
        } elseif (is_string($rawExpires) && is_numeric($rawExpires)) {
            $expiresIn = (int) $rawExpires;
        } else {
            throw new \InvalidArgumentException('参数 "--expires/-t" 必须为整数或数字字符串');
        }

        if ($expiresIn <= 0) {
            throw new \InvalidArgumentException('参数 "--expires/-t" 必须为正整数');
        }

        return $expiresIn;
    }

    /**
     * 校验设备信息:允许 null;非 null 时必须为字符串
     *
     * @param mixed $rawDevice
     */
    private function parseDevice($rawDevice): ?string
    {
        if (null === $rawDevice) {
            return null;
        }
        if (!is_string($rawDevice)) {
            throw new \InvalidArgumentException('参数 "--device/-d" 必须为字符串');
        }

        return $rawDevice;
    }
}
