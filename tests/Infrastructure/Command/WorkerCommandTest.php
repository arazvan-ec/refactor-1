<?php

/**
 * @copyright
 */

namespace App\Tests\Infrastructure\Command;

use App\Infrastructure\Command\WorkerCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @author Razvan Alin Munteanu <arazvan@elconfidencial.com>
 *
 * @covers \App\Infrastructure\Command\WorkerCommand
 */
class WorkerCommandTest extends TestCase
{
    /**
     * @test
     */
    public function executeShouldReturnTrue(): void
    {
        $inputMock = $this->createMock(InputInterface::class);
        $outputMock = $this->createMock(OutputInterface::class);

        $command = new WorkerCommand('test');
        $return = $command->execute($inputMock, $outputMock);

        static::assertSame(Command::SUCCESS, $return);
    }
}
