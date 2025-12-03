<?php declare(strict_types = 1);

namespace ShipMonk\PHPUnitParallelJobBalancer;

use function count;

final class BalanceTestJobNode
{

    private float $time = 0.0;

    /**
     * @var array<string, self>
     */
    private array $children = [];

    public function __construct(
        private readonly string $path,
    )
    {
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getTime(): float
    {
        return $this->time;
    }

    /**
     * @return array<string, self>
     */
    public function getChildren(): array
    {
        return $this->children;
    }

    public function hasChildren(): bool
    {
        return count($this->children) > 0;
    }

    public function getOrCreateChild(string $name): self
    {
        return $this->children[$name] ??= new self("{$this->path}/{$name}");
    }

    public function addTime(float $time): void
    {
        $this->time += $time;
    }

    public static function compareByPath(
        self $a,
        self $b,
    ): int
    {
        return $a->path <=> $b->path;
    }

}
