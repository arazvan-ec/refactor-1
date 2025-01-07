<?php

/**
 * @copyright
 */

declare(strict_types=1);

namespace App\Infrastructure\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @author Razvan Alin Munteanu <arazvan@elconfidencial.com>
 */
#[AsCommand(name: 'app:worker', description: 'Worker')]
class WorkerCommand extends Command
{
    private string $env;

    public function __construct(string $env = 'dev')
    {
        parent::__construct();
        $this->env = $env;
    }

    public function configure(): void
    {
        $this
            ->setName('app:worker')
            ->addOption(
                'time-limit',
                't',
                InputOption::VALUE_OPTIONAL,
                'Execution time limit',
                1
            )
            ->addOption(
                'time-sleep',
                's',
                InputOption::VALUE_OPTIONAL,
                'Execution time limit',
                60
            );
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $timeLimit = (int) $input->getOption('time-limit');
        $timeSleep = (int) $input->getOption('time-sleep');

        $executeStart = time();
        $executeEnd = $executeStart + $timeLimit;

        while (('dev' === $this->env) && ((0 === $timeLimit) || ($executeEnd >= $executeStart))) {
            $executeStart = time();
            $output->writeln((new \DateTime())->format('Y-m-d H:i:s').': Test OK');
            sleep($timeSleep);
        }

        echo 'Test OK';

        return Command::SUCCESS;
    }
}
