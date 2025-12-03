<?php declare(strict_types = 1);

namespace ShipMonk\PHPUnitParallelJobBalancer;

use DOMDocument;
use DOMDocumentFragment;
use DOMElement;
use ShipMonk\PHPUnitParallelJobBalancer\Exception\RuntimeException;
use function array_filter;
use function sort;
use function sprintf;
use function str_ends_with;
use function str_starts_with;
use function usort;

final class PhpunitXmlGenerator
{

    /**
     * @param list<string> $excludedPaths
     *
     * @throws RuntimeException
     */
    public function generate(
        BalancingResult $result,
        array $excludedPaths,
        string $testsuitePrefix = 'part',
    ): string
    {
        $xmlFragment = $this->createXmlFragment($result->jobs, $excludedPaths, $testsuitePrefix);
        return $this->serializeXml($xmlFragment);
    }

    /**
     * @param array<int, list<BalanceTestJobNode>> $jobs
     * @param list<string> $excludedPaths
     *
     * @throws RuntimeException
     */
    private function createXmlFragment(
        array $jobs,
        array $excludedPaths,
        string $testsuitePrefix,
    ): DOMDocumentFragment
    {
        $indent = '    ';

        $xmlDocument = new DOMDocument();
        $xmlFragment = $xmlDocument->createDocumentFragment();
        $xmlDocument->append($xmlFragment);

        foreach ($jobs as $bucketIndex => $nodes) {
            $testSuite = $this->createElement($xmlDocument, 'testsuite');
            $testSuite->setAttribute('name', $testsuitePrefix . ($bucketIndex + 1));

            $directories = array_filter($nodes, static function (BalanceTestJobNode $node): bool {
                return !str_ends_with($node->getPath(), '.php');
            });

            $files = array_filter($nodes, static function (BalanceTestJobNode $node): bool {
                return str_ends_with($node->getPath(), '.php');
            });

            $excludes = $this->filterRelevantExcludes($excludedPaths, $nodes);

            usort($directories, BalanceTestJobNode::compareByPath(...));
            usort($files, BalanceTestJobNode::compareByPath(...));
            sort($excludes);

            foreach ($directories as $directory) {
                $testSuite->append(
                    $xmlDocument->createTextNode("\n{$indent}"),
                    $xmlDocument->createComment(sprintf(' %6.3f s ', $directory->getTime())),
                    $this->createElement($xmlDocument, 'directory', $directory->getPath()),
                );
            }

            foreach ($files as $file) {
                $testSuite->append(
                    $xmlDocument->createTextNode("\n{$indent}"),
                    $xmlDocument->createComment(sprintf(' %6.3f s ', $file->getTime())),
                    $this->createElement($xmlDocument, 'file', $file->getPath()),
                );
            }

            foreach ($excludes as $exclude) {
                $testSuite->append(
                    $xmlDocument->createTextNode("\n{$indent}"),
                    $this->createElement($xmlDocument, 'exclude', $exclude),
                );
            }

            $testSuite->append($xmlDocument->createTextNode("\n"));

            $xmlFragment->append(
                $testSuite,
                $xmlDocument->createTextNode("\n"),
            );
        }

        return $xmlFragment;
    }

    /**
     * @throws RuntimeException
     */
    private function createElement(
        DOMDocument $document,
        string $tagName,
        string $value = '',
    ): DOMElement
    {
        $element = $document->createElement($tagName, $value);

        if ($element === false) {
            throw new RuntimeException("Failed to create element: {$tagName}");
        }

        return $element;
    }

    /**
     * Filters exclusion paths to only include those relevant to the given nodes.
     *
     * @param list<string> $excludedPaths
     * @param list<BalanceTestJobNode> $nodes
     * @return list<string>
     */
    private function filterRelevantExcludes(
        array $excludedPaths,
        array $nodes,
    ): array
    {
        $relevant = [];

        foreach ($excludedPaths as $exclude) {
            foreach ($nodes as $node) {
                if (str_starts_with($exclude, $node->getPath() . '/')) {
                    $relevant[] = $exclude;
                    break;
                }
            }
        }

        return $relevant;
    }

    /**
     * @throws RuntimeException
     */
    private function serializeXml(DOMDocumentFragment $xmlFragment): string
    {
        $xmlDocument = $xmlFragment->ownerDocument;

        if ($xmlDocument === null) {
            throw new RuntimeException('XML fragment has no owner document');
        }

        $xmlString = $xmlDocument->saveXML($xmlFragment);

        if ($xmlString === false) {
            throw new RuntimeException('Failed to serialize XML');
        }

        return $xmlString;
    }

}
