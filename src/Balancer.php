<?php declare(strict_types = 1);

namespace ShipMonk\PHPUnitParallelJobBalancer;

use function array_pop;
use function array_sum;
use function count;
use function explode;
use function max;
use function min;
use function usort;
use const PHP_INT_MAX;

final class Balancer
{

    /**
     * @param array<string, float> $timings Relative path to execution time
     * @param positive-int $jobCount Number of parallel jobs to balance across
     */
    public function balance(
        array $timings,
        int $jobCount,
        string $testsDir,
    ): BalancingResult
    {
        $timingTree = $this->createTimingTree($timings, $testsDir);
        return $this->createJobs($timingTree, $jobCount);
    }

    /**
     * @param array<string, float> $timings
     */
    private function createTimingTree(
        array $timings,
        string $testsDir,
    ): BalanceTestJobNode
    {
        $tree = new BalanceTestJobNode($testsDir);

        foreach ($timings as $dir => $time) {
            $node = $tree;
            $node->addTime($time);

            foreach (explode('/', $dir) as $part) {
                $node = $node->getOrCreateChild($part);
                $node->addTime($time);
            }
        }

        return $tree;
    }

    /**
     * @param positive-int $jobCount
     */
    private function createJobs(
        BalanceTestJobNode $rootNode,
        int $jobCount,
    ): BalancingResult
    {
        $jobs = [];
        $jobTimes = [];

        for ($i = 0; $i < $jobCount; $i++) {
            $jobs[$i] = [];
            $jobTimes[$i] = 0.0;
        }

        $totalTime = $rootNode->getTime();
        $jobTimeLimit = $totalTime / $jobCount;
        $nodes = [$rootNode];

        while (count($nodes) > 0) {
            $node = array_pop($nodes);

            // Find job with least time
            $minJobIndex = 0;

            for ($i = 1; $i < $jobCount; $i++) {
                if ($jobTimes[$i] < $jobTimes[$minJobIndex]) {
                    $minJobIndex = $i;
                }
            }

            // Try to put node in min job
            if ($jobTimes[$minJobIndex] + $node->getTime() < $jobTimeLimit || !$node->hasChildren()) {
                $jobTimes[$minJobIndex] += $node->getTime();
                $jobs[$minJobIndex][] = $node;
                continue;
            }

            // Split node by adding children to stack
            foreach ($node->getChildren() as $child) {
                $nodes[] = $child;
            }

            // Sort nodes by time (ascending, smallest first)
            usort($nodes, static function (BalanceTestJobNode $a, BalanceTestJobNode $b): int {
                return $a->getTime() <=> $b->getTime();
            });
        }

        $avg = array_sum($jobTimes) / $jobCount;
        $maxTime = max(0.0, ...$jobTimes);
        $minTime = min(PHP_INT_MAX, ...$jobTimes);
        $deviation = max($maxTime - $avg, $avg - $minTime);

        return new BalancingResult($jobs, $avg, $deviation, $totalTime);
    }

}
