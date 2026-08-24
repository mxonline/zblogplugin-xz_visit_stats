<?php

use PHPUnit\Framework\TestCase;

if (!defined('ZBP_PATH')) {
    define('ZBP_PATH', __DIR__ . '/');
}

require_once dirname(__DIR__) . '/inc/query.php';
require_once dirname(__DIR__) . '/inc/rollup.php';
require_once dirname(__DIR__) . '/inc/query_v2.php';

class QueryV2Test extends TestCase
{
    protected function setUp(): void
    {
        global $zbp;
        $zbp = new stdClass();
        $zbp->option = array('ZC_TIME_ZONE' => 'Asia/Shanghai');
    }

    public function testFiltersKeepUnifiedDrilldownInputsAndRejectInvalidValues(): void
    {
        $filters = xz_visit_stats_v2_filters(array(
            'range' => '7d',
            'visit_type' => 'bot',
            'status_group' => '4xx',
            'path_key' => str_repeat('a', 64),
            'page_size' => '100',
        ));

        $this->assertSame('7d', $filters['range']);
        $this->assertSame('bot', $filters['visit_type']);
        $this->assertSame('4xx', $filters['status_group']);
        $this->assertSame(str_repeat('a', 64), $filters['path_key']);
        $this->assertSame(100, $filters['page_size']);

        $params = xz_visit_stats_v2_drilldown_params($filters);
        $this->assertSame('records', $params['view']);
        $this->assertSame($filters['path_key'], $params['path_key']);
        $this->assertSame('bot', $params['visit_type']);
    }

    public function testRangeUsesSiteTimezoneAndHalfOpenDayBounds(): void
    {
        $filters = xz_visit_stats_v2_filters(array('range' => 'yesterday'));
        $range = xz_visit_stats_v2_range($filters, strtotime('2026-08-25 03:00:00 UTC'));
        $zone = new DateTimeZone('Asia/Shanghai');
        $start = new DateTime('@' . $range['start']);
        $end = new DateTime('@' . $range['end']);
        $start->setTimezone($zone);
        $end->setTimezone($zone);

        $this->assertSame('Asia/Shanghai', $range['timezone']);
        $this->assertSame('2026-08-24 00:00:00', $start->format('Y-m-d H:i:s'));
        $this->assertSame('2026-08-25 00:00:00', $end->format('Y-m-d H:i:s'));
    }

    public function testSummaryWhereKeepsUvAndIpOutOfDailyAdditivePath(): void
    {
        $filters = xz_visit_stats_v2_filters(array('range' => '30d'));
        $range = xz_visit_stats_v2_range($filters, strtotime('2026-08-25 03:00:00 UTC'));
        $days = xz_visit_stats_v2_rollup_days($range);

        $this->assertNotEmpty($days);
        $source = file_get_contents(dirname(__DIR__) . '/inc/query_v2.php');
        $this->assertIsString($source);
        $this->assertStringContainsString('COUNT(DISTINCT CASE WHEN vs_IsBot=0 THEN vs_VisitorHash END)', $source);
        $this->assertStringContainsString('raw_exact_uv_ip', $source);
    }
}
