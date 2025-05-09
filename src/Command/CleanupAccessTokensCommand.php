<?php

namespace AccessTokenBundle\Command;

use AccessTokenBundle\Service\AccessTokenService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:cleanup-access-tokens',
    description: '清理过期的访问令牌',
)]
class CleanupAccessTokensCommand extends Command
{
    public function __construct(
        private readonly AccessTokenService $accessTokenService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, '仅显示将删除的令牌数量，但不实际删除')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $isDryRun = $input->getOption('dry-run');

        if ($isDryRun) {
            $io->note('正在以"仅显示模式"运行，不会删除任何令牌');
        }

        $io->section('清理过期访问令牌');
        
        try {
            // 执行清理
            $count = $this->accessTokenService->cleanupExpiredTokens();
            
            if ($isDryRun) {
                $io->success(sprintf('找到 %d 个过期访问令牌需要清理', $count));
            } else {
                $io->success(sprintf('成功清理 %d 个过期访问令牌', $count));
            }
            
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error(sprintf('清理过程中发生错误: %s', $e->getMessage()));
            return Command::FAILURE;
        }
    }
}
