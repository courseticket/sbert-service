<?php
declare(strict_types=1);

namespace SbertService\Test\TestCase\Lib;

use Ai\Lib\Helpers\QueryParser;
use PHPUnit\Framework\TestCase;

class QueryParserTest extends TestCase
{
    public function testParseAiSearchQuery()
    {
        $expected = 'üä my dear hello world';
        $query = '&uuml;&auml; my+de/ar\n\xa0+<p>hello</p><p>world</p>'."\n\t";

        $queryParsed = QueryParser::parseAiSearchQuery($query);

        $this->assertEquals($expected, $queryParsed);
    }

    public function testParseHtml()
    {
        $expected = 'üä my+de/ar' . "\n"
            . ' + hello ' . "\n"
            . ' world';
        $query = '&uuml;&auml; my+de/ar\n\xa0+<p><b>hello</b></p><p>world</p>'."\n\t";

        $queryParsed = QueryParser::parseHtml($query);

        $this->assertEquals($expected, $queryParsed);
    }
}
