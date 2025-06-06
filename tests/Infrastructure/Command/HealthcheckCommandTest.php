<?php

/**
 * @copyright
 */

namespace App\Tests\Infrastructure\Command;

use App\Infrastructure\Command\HealthcheckCommand;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @author Razvan Alin Munteanu <arazvan@elconfidencial.com>
 *
 */
#[CoversClass(HealthcheckCommand::class)]
class HealthcheckCommandTest extends TestCase
{
    #[Test]
    public function executeShouldReturnTrue(): void
    {
        $inputMock = $this->createMock(InputInterface::class);
        $outputMock = $this->createMock(OutputInterface::class);

        $command = new HealthcheckCommand();
        $return = $command->execute($inputMock, $outputMock);

        static::assertSame(Command::SUCCESS, $return);
    }
}
