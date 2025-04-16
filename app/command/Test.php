<?php

namespace app\command;

use support\Log;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;


class Test extends Command
{
    protected static $defaultName = 'test';
    protected static $defaultDescription = 'test';

    /**
     * @return void
     */
    protected function configure()
    {
        $this->addArgument('name', InputArgument::OPTIONAL, 'Name description');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $data = [
          'code' => 200,
          'msg' => 'success',
        ];
        $this->log("test",$data);
        return self::SUCCESS;
    }
    private function log($msg, $context = [])
    {
        $logger = log_daily("test"); // 获取 Logger 实例
        $logger->info($msg, $context);
    }

}
