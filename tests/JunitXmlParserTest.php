<?php declare(strict_types = 1);

namespace ShipMonkTests\PHPUnitParallelJobBalancer;

use PHPUnit\Framework\TestCase;
use ShipMonk\PHPUnitParallelJobBalancer\Exception\InvalidPathException;
use ShipMonk\PHPUnitParallelJobBalancer\Exception\RuntimeException;
use ShipMonk\PHPUnitParallelJobBalancer\JunitXmlParser;
use function file_put_contents;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

class JunitXmlParserTest extends TestCase
{

    public function testParseSingleFile(): void
    {
        $parser = new JunitXmlParser();
        $timings = $parser->parse([__DIR__ . '/data/part1.xml'], 'tests');

        self::assertNotEmpty($timings);
        self::assertArrayHasKey('src/4ea3ff88/c93a0b16/ee97be03/3eb08e3b.php', $timings);
        self::assertEqualsWithDelta(3.548714, $timings['src/4ea3ff88/c93a0b16/ee97be03/3eb08e3b.php'], 0.0001);
    }

    public function testParseMultipleFiles(): void
    {
        $parser = new JunitXmlParser();
        $timings = $parser->parse([
            __DIR__ . '/data/part1.xml',
            __DIR__ . '/data/part2.xml',
        ], 'tests');

        self::assertNotEmpty($timings);
    }

    public function testParseNonExistentFile(): void
    {
        $parser = new JunitXmlParser();

        $this->expectException(InvalidPathException::class);
        $this->expectExceptionMessage('File does not exist');
        $parser->parse(['/nonexistent/file.xml'], 'tests');
    }

    public function testParseInvalidXml(): void
    {
        $tempFile = sys_get_temp_dir() . '/invalid-' . uniqid() . '.xml';
        file_put_contents($tempFile, 'not valid xml');

        try {
            $parser = new JunitXmlParser();

            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Failed to parse XML');
            $parser->parse([$tempFile], 'tests');
        } finally {
            @unlink($tempFile);
        }
    }

    public function testParseEmptyTestsuite(): void
    {
        $tempFile = sys_get_temp_dir() . '/empty-' . uniqid() . '.xml';
        file_put_contents($tempFile, '<?xml version="1.0"?><testsuites></testsuites>');

        try {
            $parser = new JunitXmlParser();

            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('No testsuite element found');
            $parser->parse([$tempFile], 'tests');
        } finally {
            @unlink($tempFile);
        }
    }

    public function testTimingsAreSummed(): void
    {
        // Create two files with same test file but different times
        $projectDir = '/project';
        $testFile = "{$projectDir}/tests/Example.php";

        $xml1 = <<<XML
<?xml version="1.0"?>
<testsuites>
    <testsuite name="{$projectDir}/phpunit.xml">
        <testsuite file="{$testFile}" time="1.5"/>
    </testsuite>
</testsuites>
XML;

        $xml2 = <<<XML
<?xml version="1.0"?>
<testsuites>
    <testsuite name="{$projectDir}/phpunit.xml">
        <testsuite file="{$testFile}" time="2.5"/>
    </testsuite>
</testsuites>
XML;

        $tempFile1 = sys_get_temp_dir() . '/test1-' . uniqid() . '.xml';
        $tempFile2 = sys_get_temp_dir() . '/test2-' . uniqid() . '.xml';
        file_put_contents($tempFile1, $xml1);
        file_put_contents($tempFile2, $xml2);

        try {
            $parser = new JunitXmlParser();
            $timings = $parser->parse([$tempFile1, $tempFile2], 'tests');

            self::assertArrayHasKey('Example.php', $timings);
            self::assertEqualsWithDelta(4.0, $timings['Example.php'], 0.0001);
        } finally {
            @unlink($tempFile1);
            @unlink($tempFile2);
        }
    }

}
