<?php declare(strict_types = 1);

namespace ShipMonk\PHPUnitParallelJobBalancer;

use ShipMonk\PHPUnitParallelJobBalancer\Exception\InvalidPathException;
use ShipMonk\PHPUnitParallelJobBalancer\Exception\RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use function file_put_contents;
use function sprintf;

final class BalancePhpunitJobsCommand extends Command
{

    private const DEFAULT_JOBS = 4;

    private const DEFAULT_TESTS_DIR = './tests';

    private const DEFAULT_TESTSUITE_PREFIX = 'part';

    protected function configure(): void
    {
        $this
            ->setName('balance-phpunit-jobs')
            ->setDescription('Balance PHPUnit test execution across parallel jobs based on JUnit XML timing data')
            ->addArgument(
                'junit-files',
                InputArgument::REQUIRED | InputArgument::IS_ARRAY,
                'JUnit XML report files',
            )
            ->addOption(
                'jobs',
                'j',
                InputOption::VALUE_REQUIRED,
                'Number of parallel jobs',
                (string) self::DEFAULT_JOBS,
            )
            ->addOption(
                'exclude',
                'e',
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'Paths to exclude from output',
                [],
            )
            ->addOption(
                'output',
                'o',
                InputOption::VALUE_REQUIRED,
                'Output file (default: stdout)',
            )
            ->addOption(
                'tests-dir',
                null,
                InputOption::VALUE_REQUIRED,
                'Base test directory',
                self::DEFAULT_TESTS_DIR,
            )
            ->addOption(
                'test-suite-prefix',
                null,
                InputOption::VALUE_REQUIRED,
                'Test suite name prefix (e.g. "part" generates part1, part2, ...)',
                self::DEFAULT_TESTSUITE_PREFIX,
            );
    }

    protected function execute(
        InputInterface $input,
        OutputInterface $output,
    ): int
    {
        /** @var list<string> $junitFiles */
        $junitFiles = $input->getArgument('junit-files');

        /** @var string $jobsOption */
        $jobsOption = $input->getOption('jobs');
        $jobCount = (int) $jobsOption;

        if ($jobCount < 1) {
            $output->writeln('<error>Job count must be a positive integer</error>');
            return Command::FAILURE;
        }

        /** @var list<string> $excludePaths */
        $excludePaths = $input->getOption('exclude');

        /** @var string|null $outputFile */
        $outputFile = $input->getOption('output');

        /** @var string $testsDir */
        $testsDir = $input->getOption('tests-dir');

        /** @var string $testsuitePrefix */
        $testsuitePrefix = $input->getOption('test-suite-prefix');

        try {
            $xmlOutput = $this->runBalancing($junitFiles, $jobCount, $excludePaths, $testsDir, $testsuitePrefix, $output);
        } catch (InvalidPathException | RuntimeException $e) {
            $output->writeln("<error>{$e->getMessage()}</error>");
            return Command::FAILURE;
        }

        if ($outputFile !== null) {
            file_put_contents($outputFile, $xmlOutput);
            $output->writeln("<info>Output written to: {$outputFile}</info>");
        } else {
            $output->write($xmlOutput);
        }

        return Command::SUCCESS;
    }

    /**
     * @param list<string> $junitFiles
     * @param positive-int $jobCount
     * @param list<string> $excludePaths
     *
     * @throws InvalidPathException
     * @throws RuntimeException
     */
    private function runBalancing(
        array $junitFiles,
        int $jobCount,
        array $excludePaths,
        string $testsDir,
        string $testsuitePrefix,
        OutputInterface $output,
    ): string
    {
        $parser = new JunitXmlParser();
        $timings = $parser->parse($junitFiles, $testsDir);

        $balancer = new Balancer();
        $result = $balancer->balance($timings, $jobCount, $testsDir);

        $output->writeln(sprintf(
            '<info>Job time: %.3fs ± %.3fs</info>',
            $result->averageJobTime,
            $result->deviation,
        ));
        $output->writeln('');

        $generator = new PhpunitXmlGenerator();
        return $generator->generate($result, $excludePaths, $testsuitePrefix);
    }

}
