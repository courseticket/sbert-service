<?php
declare(strict_types=1);

namespace SbertService\Lib;

class QueryParser
{
    public static function parseAiSearchQuery(string $query): string
    {
        $query = trim(html_entity_decode($query));
        $replace1 = str_replace(['+', '\n', '\t', '\xa0', "\n", "\t", "\xa0"], ' ', $query);
        $addedSpacesBetweenHtmlTags = str_replace(['<'], ' <', $replace1);
        $removeSlashes = strip_tags(str_replace(['/'], '', $addedSpacesBetweenHtmlTags));
        $removeDoubleSpaces = str_replace(['     ', '    ', '   ', '  '], ' ', $removeSlashes);
        return trim($removeDoubleSpaces);
    }

    public static function parseHtml(string $query): string
    {
        $query = trim(html_entity_decode($query));
        $replace1 = str_replace(['\t', '\xa0', "\t", "\xa0"], ' ', $query);
        $replaceNewLines = str_replace('\n', "\n", $replace1);
        $addNewLinesBetweenParagraphs = str_replace('</p><p>', '</p>' . "\n" . '<p>', $replaceNewLines);
        $addedSpacesBetweenHtmlTags = str_replace(['<'], ' <', $addNewLinesBetweenParagraphs);
        $removeDoubleSpaces = str_replace(['     ', '    ', '   ', '  '], ' ', strip_tags($addedSpacesBetweenHtmlTags));
        return trim($removeDoubleSpaces);
    }
}
