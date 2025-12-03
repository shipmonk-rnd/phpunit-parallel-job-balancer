<?php declare(strict_types = 1);

namespace ShipMonkTests\PHPUnitParallelJobBalancer;

use PHPUnit\Framework\TestCase;
use ShipMonk\PHPUnitParallelJobBalancer\BalanceTestJobNode;
use ShipMonk\PHPUnitParallelJobBalancer\BalancingResult;
use ShipMonk\PHPUnitParallelJobBalancer\PhpunitXmlGenerator;
use function strpos;

class PhpunitXmlGeneratorTest extends TestCase
{

    public function testGenerateBasic(): void
    {
        $node1 = new BalanceTestJobNode('./tests/Unit');
        $node1->addTime(5.0);

        $node2 = new BalanceTestJobNode('./tests/Integration');
        $node2->addTime(3.0);

        $result = new BalancingResult(
            jobs: [[$node1], [$node2]],
            averageJobTime: 4.0,
            deviation: 1.0,
            totalTime: 8.0,
        );

        $generator = new PhpunitXmlGenerator();
        $xml = $generator->generate($result, []);

        self::assertStringContainsString('<testsuite name="part1">', $xml);
        self::assertStringContainsString('<testsuite name="part2">', $xml);
        self::assertStringContainsString('<directory>./tests/Unit</directory>', $xml);
        self::assertStringContainsString('<directory>./tests/Integration</directory>', $xml);
    }

    public function testGenerateWithFiles(): void
    {
        $node = new BalanceTestJobNode('./tests/Unit/Example.php');
        $node->addTime(2.5);

        $result = new BalancingResult(
            jobs: [[$node]],
            averageJobTime: 2.5,
            deviation: 0.0,
            totalTime: 2.5,
        );

        $generator = new PhpunitXmlGenerator();
        $xml = $generator->generate($result, []);

        self::assertStringContainsString('<file>./tests/Unit/Example.php</file>', $xml);
        self::assertStringNotContainsString('<directory>', $xml);
    }

    public function testGenerateWithTimeComments(): void
    {
        $node = new BalanceTestJobNode('./tests/Unit');
        $node->addTime(5.123);

        $result = new BalancingResult(
            jobs: [[$node]],
            averageJobTime: 5.123,
            deviation: 0.0,
            totalTime: 5.123,
        );

        $generator = new PhpunitXmlGenerator();
        $xml = $generator->generate($result, []);

        self::assertStringContainsString('<!--  5.123 s -->', $xml);
    }

    public function testGenerateWithExcludes(): void
    {
        $node = new BalanceTestJobNode('./tests/Unit');
        $node->addTime(5.0);

        $result = new BalancingResult(
            jobs: [[$node]],
            averageJobTime: 5.0,
            deviation: 0.0,
            totalTime: 5.0,
        );

        $excludes = [
            './tests/Unit/Slow',
            './tests/Unit/Flaky',
            './tests/Integration/E2E', // Should not appear - not under ./tests/Unit
        ];

        $generator = new PhpunitXmlGenerator();
        $xml = $generator->generate($result, $excludes);

        self::assertStringContainsString('<exclude>./tests/Unit/Flaky</exclude>', $xml);
        self::assertStringContainsString('<exclude>./tests/Unit/Slow</exclude>', $xml);
        self::assertStringNotContainsString('./tests/Integration/E2E', $xml);
    }

    public function testGenerateSortsElements(): void
    {
        $node1 = new BalanceTestJobNode('./tests/Z');
        $node1->addTime(1.0);

        $node2 = new BalanceTestJobNode('./tests/A');
        $node2->addTime(1.0);

        $result = new BalancingResult(
            jobs: [[$node1, $node2]],
            averageJobTime: 2.0,
            deviation: 0.0,
            totalTime: 2.0,
        );

        $generator = new PhpunitXmlGenerator();
        $xml = $generator->generate($result, []);

        // A should come before Z
        $posA = strpos($xml, './tests/A');
        $posZ = strpos($xml, './tests/Z');
        self::assertNotFalse($posA);
        self::assertNotFalse($posZ);
        self::assertLessThan($posZ, $posA);
    }

    public function testGenerateEmptyJobs(): void
    {
        $result = new BalancingResult(
            jobs: [[], []],
            averageJobTime: 0.0,
            deviation: 0.0,
            totalTime: 0.0,
        );

        $generator = new PhpunitXmlGenerator();
        $xml = $generator->generate($result, []);

        self::assertStringContainsString('<testsuite name="part1">', $xml);
        self::assertStringContainsString('<testsuite name="part2">', $xml);
    }

    public function testGenerateMixedDirectoriesAndFiles(): void
    {
        $dir = new BalanceTestJobNode('./tests/Unit');
        $dir->addTime(10.0);

        $file = new BalanceTestJobNode('./tests/Specific.php');
        $file->addTime(2.0);

        $result = new BalancingResult(
            jobs: [[$file, $dir]],
            averageJobTime: 12.0,
            deviation: 0.0,
            totalTime: 12.0,
        );

        $generator = new PhpunitXmlGenerator();
        $xml = $generator->generate($result, []);

        // Directories should come before files in the output
        $posDir = strpos($xml, '<directory>');
        $posFile = strpos($xml, '<file>');
        self::assertNotFalse($posDir);
        self::assertNotFalse($posFile);
        self::assertLessThan($posFile, $posDir);
    }

}
