<?php declare(strict_types = 1);

namespace ShipMonkTests\PHPUnitParallelJobBalancer;

use PHPUnit\Framework\TestCase;
use ShipMonk\PHPUnitParallelJobBalancer\Balancer;
use ShipMonk\PHPUnitParallelJobBalancer\JunitXmlParser;

class BalancerTest extends TestCase
{

    public function testBalanceSimple(): void
    {
        $balancer = new Balancer();

        $timings = [
            'Unit/Test1.php' => 1.0,
            'Unit/Test2.php' => 2.0,
            'Integration/Test3.php' => 3.0,
        ];

        $result = $balancer->balance($timings, 2, './tests');

        self::assertCount(2, $result->jobs);
        self::assertEqualsWithDelta(6.0, $result->totalTime, 0.0001);
        self::assertEqualsWithDelta(3.0, $result->averageJobTime, 0.0001);
    }

    public function testBalanceDistribution(): void
    {
        $balancer = new Balancer();

        $timings = [
            'A.php' => 10.0,
            'B.php' => 10.0,
            'C.php' => 10.0,
            'D.php' => 10.0,
        ];

        $result = $balancer->balance($timings, 4, './tests');

        // Each job should have exactly one test (each 10 seconds)
        foreach ($result->jobs as $job) {
            $jobTime = 0.0;

            foreach ($job as $node) {
                $jobTime += $node->getTime();
            }

            self::assertEqualsWithDelta(10.0, $jobTime, 0.0001);
        }

        self::assertEqualsWithDelta(0.0, $result->deviation, 0.0001);
    }

    public function testBalanceSingleJob(): void
    {
        $balancer = new Balancer();

        $timings = [
            'Test1.php' => 1.0,
            'Test2.php' => 2.0,
        ];

        $result = $balancer->balance($timings, 1, './tests');

        self::assertCount(1, $result->jobs);
        self::assertEqualsWithDelta(3.0, $result->totalTime, 0.0001);
    }

    public function testBalanceWithHierarchy(): void
    {
        $balancer = new Balancer();

        $timings = [
            'Unit/SubDir/Test1.php' => 5.0,
            'Unit/SubDir/Test2.php' => 5.0,
            'Integration/Test3.php' => 10.0,
        ];

        $result = $balancer->balance($timings, 2, './tests');

        self::assertCount(2, $result->jobs);
        self::assertEqualsWithDelta(20.0, $result->totalTime, 0.0001);
    }

    public function testBalanceEmptyTimings(): void
    {
        $balancer = new Balancer();

        $result = $balancer->balance([], 4, './tests');

        self::assertCount(4, $result->jobs);
        self::assertEqualsWithDelta(0.0, $result->totalTime, 0.0001);
        self::assertEqualsWithDelta(0.0, $result->averageJobTime, 0.0001);
    }

    public function testBalancePreservesPathPrefix(): void
    {
        $balancer = new Balancer();

        $timings = [
            'src/Unit/Test.php' => 1.0,
        ];

        $result = $balancer->balance($timings, 1, './tests');

        // With single job, all nodes end up in that job
        $job = $result->jobs[0];
        self::assertNotEmpty($job);

        // All paths should start with the tests dir prefix
        foreach ($job as $node) {
            self::assertStringStartsWith('./tests/', $node->getPath());
        }
    }

    public function testBalanceWithRealData(): void
    {
        $parser = new JunitXmlParser();
        $timings = $parser->parse([__DIR__ . '/data/part1.xml'], 'tests');

        $balancer = new Balancer();
        $result = $balancer->balance($timings, 4, './tests');

        self::assertCount(4, $result->jobs);
        self::assertGreaterThan(0.0, $result->totalTime);
        // Verify reasonable distribution
        self::assertLessThan($result->averageJobTime, $result->deviation);
    }

}
