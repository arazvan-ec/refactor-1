<?php

declare(strict_types=1);

namespace App\Infrastructure\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:worker', description: 'Worker')]
class WorkerCommand extends Command
{
    private string $env;

    public function __construct(string $env = 'dev')
    {
        parent::__construct();
        $this->env = $env;
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        while ('dev' === $this->env) {
            echo 'Test OK';
            sleep(60);
        }

        echo 'Test OK';

        return Command::SUCCESS;
    }
}
