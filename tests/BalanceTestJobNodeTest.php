<?php declare(strict_types = 1);

namespace ShipMonkTests\PHPUnitParallelJobBalancer;

use PHPUnit\Framework\TestCase;
use ShipMonk\PHPUnitParallelJobBalancer\BalanceTestJobNode;

class BalanceTestJobNodeTest extends TestCase
{

    public function testBasicProperties(): void
    {
        $node = new BalanceTestJobNode('./tests');

        self::assertSame('./tests', $node->getPath());
        self::assertSame(0.0, $node->getTime());
        self::assertSame([], $node->getChildren());
        self::assertFalse($node->hasChildren());
    }

    public function testAddTime(): void
    {
        $node = new BalanceTestJobNode('./tests');
        $node->addTime(1.5);
        $node->addTime(2.3);

        self::assertEqualsWithDelta(3.8, $node->getTime(), 0.0001);
    }

    public function testGetOrCreateChild(): void
    {
        $node = new BalanceTestJobNode('./tests');

        $child1 = $node->getOrCreateChild('src');
        self::assertSame('./tests/src', $child1->getPath());
        self::assertTrue($node->hasChildren());
        self::assertCount(1, $node->getChildren());

        // Same child should be returned for same name
        $child1Again = $node->getOrCreateChild('src');
        self::assertSame($child1, $child1Again);

        // Different child for different name
        $child2 = $node->getOrCreateChild('vendor');
        self::assertSame('./tests/vendor', $child2->getPath());
        self::assertCount(2, $node->getChildren());
    }

    public function testNestedChildren(): void
    {
        $node = new BalanceTestJobNode('./tests');
        $child = $node->getOrCreateChild('src');
        $grandchild = $child->getOrCreateChild('Unit');

        self::assertSame('./tests/src/Unit', $grandchild->getPath());
    }

    public function testCompareByPath(): void
    {
        $nodeA = new BalanceTestJobNode('./tests/a');
        $nodeB = new BalanceTestJobNode('./tests/b');
        $nodeA2 = new BalanceTestJobNode('./tests/a');

        self::assertLessThan(0, BalanceTestJobNode::compareByPath($nodeA, $nodeB));
        self::assertGreaterThan(0, BalanceTestJobNode::compareByPath($nodeB, $nodeA));
        self::assertSame(0, BalanceTestJobNode::compareByPath($nodeA, $nodeA2));
    }

}
