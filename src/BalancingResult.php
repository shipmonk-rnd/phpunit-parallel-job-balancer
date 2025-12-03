<?php declare(strict_types = 1);

namespace ShipMonk\PHPUnitParallelJobBalancer;

final class BalancingResult
{

    /**
     * @param array<int, list<BalanceTestJobNode>> $jobs
     */
    public function __construct(
        public readonly array $jobs,
        public readonly float $averageJobTime,
        public readonly float $deviation,
        public readonly float $totalTime,
    )
    {
    }

}
