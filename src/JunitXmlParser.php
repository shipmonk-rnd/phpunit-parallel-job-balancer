<?php declare(strict_types = 1);

namespace ShipMonk\PHPUnitParallelJobBalancer;

use ShipMonk\PHPUnitParallelJobBalancer\Exception\InvalidPathException;
use ShipMonk\PHPUnitParallelJobBalancer\Exception\RuntimeException;
use SimpleXMLElement;
use function dirname;
use function file_exists;
use function is_readable;
use function ltrim;
use function simplexml_load_file;
use function str_replace;

final class JunitXmlParser
{

    /**
     * @param list<string> $junitFilePaths
     * @return array<string, float> Map of relative file path to total time
     *
     * @throws RuntimeException When file does not exist or XML parsing fails
     */
    public function parse(
        array $junitFilePaths,
        string $testsDir,
    ): array
    {
        $timings = [];

        foreach ($junitFilePaths as $path) {
            $this->validatePath($path);
            $timings = $this->parseFile($path, $testsDir, $timings);
        }

        return $timings;
    }

    /**
     * @throws InvalidPathException
     */
    private function validatePath(string $path): void
    {
        if (!file_exists($path)) {
            throw new InvalidPathException("File does not exist: {$path}");
        }

        if (!is_readable($path)) {
            throw new InvalidPathException("File is not readable: {$path}");
        }
    }

    /**
     * @param array<string, float> $timings
     * @return array<string, float>
     *
     * @throws RuntimeException
     */
    private function parseFile(
        string $path,
        string $testsDir,
        array $timings,
    ): array
    {
        $xml = @simplexml_load_file($path);

        if ($xml === false) {
            throw new RuntimeException("Failed to parse XML from: {$path}");
        }

        $projectDir = $this->extractProjectDir($xml, $path);
        // Normalize testsDir: strip leading "./" for path matching
        $normalizedTestsDir = ltrim($testsDir, './');
        $fullTestsDir = "{$projectDir}/{$normalizedTestsDir}";

        $suites = $xml->xpath('//testsuite[@file]') ?? [];

        foreach ($suites as $suite) {
            $file = str_replace("{$fullTestsDir}/", '', (string) $suite['file']);
            $time = (float) $suite['time'];
            $timings[$file] = ($timings[$file] ?? 0.0) + $time;
        }

        return $timings;
    }

    /**
     * @throws RuntimeException
     */
    private function extractProjectDir(
        SimpleXMLElement $xml,
        string $path,
    ): string
    {
        if (!isset($xml->testsuite[0])) {
            throw new RuntimeException("No testsuite element found in: {$path}");
        }

        $name = (string) $xml->testsuite[0]['name'];

        if ($name === '') {
            throw new RuntimeException("Testsuite name attribute is empty in: {$path}");
        }

        return dirname($name);
    }

}
