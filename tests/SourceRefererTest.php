<?php

use PHPUnit\Framework\TestCase;

if (!defined('ZBP_PATH')) {
    define('ZBP_PATH', dirname(__DIR__) . '/');
}

require_once dirname(__DIR__) . '/inc/source_stats.php';

class SourceRefererTest extends TestCase
{
    protected function setUp(): void
    {
        global $zbp;

        $zbp = (object) array('host' => 'https://www.xzhao.net/');
    }

    public function testBaiduRefererIsClassifiedAndKeywordExtracted(): void
    {
        $details = xz_visit_stats_source_referer_details('https://www.baidu.com/s?wd=wifi6');

        $this->assertSame('search', $details['type']);
        $this->assertSame('百度', $details['search_engine']);
        $this->assertSame('wifi6', $details['keyword']);
        $this->assertSame('www.baidu.com', $details['domain']);
    }

    public function testExternalRefererKeepsFullUrlInHoverDetails(): void
    {
        $referer = 'https://example.com/path?x=1&y=2';
        $html = xz_visit_stats_source_referer_cell($referer);

        $this->assertStringContainsString('完整 Referer URL', $html);
        $this->assertStringContainsString('https://example.com/path?x=1&amp;y=2', $html);
        $this->assertStringContainsString('example.com', $html);
    }

    public function testEmptyRefererIsDirectVisit(): void
    {
        $details = xz_visit_stats_source_referer_details('');

        $this->assertSame('direct', $details['type']);
        $this->assertSame('直接访问', $details['name']);
    }
}
