<?php declare(strict_types = 1);

namespace ShipMonkTests\PHPUnitParallelJobBalancer;

use PHPUnit\Framework\TestCase;
use ShipMonk\PHPUnitParallelJobBalancer\BalancePhpunitJobsCommand;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class BalancePhpunitJobsCommandTest extends TestCase
{

    private CommandTester $commandTester;

    protected function setUp(): void
    {
        $application = new Application();
        $application->addCommands([new BalancePhpunitJobsCommand()]);

        $command = $application->find('balance-phpunit-jobs');
        $this->commandTester = new CommandTester($command);
    }

    public function testExecuteBasic(): void
    {
        $this->commandTester->execute([
            'junit-files' => [__DIR__ . '/data/part1.xml'],
            '--jobs' => '4',
        ]);

        $output = $this->commandTester->getDisplay();

        self::assertSame(0, $this->commandTester->getStatusCode());
        self::assertStringContainsString('Job time:', $output);
        self::assertStringContainsString('<testsuite name="part1">', $output);
        self::assertStringContainsString('<testsuite name="part2">', $output);
        self::assertStringContainsString('<testsuite name="part3">', $output);
        self::assertStringContainsString('<testsuite name="part4">', $output);
    }

    public function testExecuteWithMultipleFiles(): void
    {
        $this->commandTester->execute([
            'junit-files' => [
                __DIR__ . '/data/part1.xml',
                __DIR__ . '/data/part2.xml',
            ],
            '--jobs' => '2',
        ]);

        self::assertSame(0, $this->commandTester->getStatusCode());
    }

    public function testExecuteWithExcludes(): void
    {
        $this->commandTester->execute([
            'junit-files' => [__DIR__ . '/data/part1.xml'],
            '--jobs' => '2',
            '--exclude' => ['./tests/src/excluded'],
        ]);

        self::assertSame(0, $this->commandTester->getStatusCode());
    }

    public function testExecuteWithCustomTestsDir(): void
    {
        $this->commandTester->execute([
            'junit-files' => [__DIR__ . '/data/part1.xml'],
            '--jobs' => '2',
            '--tests-dir' => './custom-tests',
        ]);

        $output = $this->commandTester->getDisplay();

        self::assertSame(0, $this->commandTester->getStatusCode());
        self::assertStringContainsString('./custom-tests', $output);
    }

    public function testExecuteWithInvalidJobCount(): void
    {
        $this->commandTester->execute([
            'junit-files' => [__DIR__ . '/data/part1.xml'],
            '--jobs' => '0',
        ]);

        $output = $this->commandTester->getDisplay();

        self::assertSame(1, $this->commandTester->getStatusCode());
        self::assertStringContainsString('Job count must be a positive integer', $output);
    }

    public function testExecuteWithNonExistentFile(): void
    {
        $this->commandTester->execute([
            'junit-files' => ['/nonexistent/file.xml'],
            '--jobs' => '2',
        ]);

        $output = $this->commandTester->getDisplay();

        self::assertSame(1, $this->commandTester->getStatusCode());
        self::assertStringContainsString('File does not exist', $output);
    }

    public function testExecuteShortOptions(): void
    {
        $this->commandTester->execute([
            'junit-files' => [__DIR__ . '/data/part1.xml'],
            '-j' => '2',
        ]);

        self::assertSame(0, $this->commandTester->getStatusCode());
    }

}
