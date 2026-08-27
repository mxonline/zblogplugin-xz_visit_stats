<?php

use PHPUnit\Framework\TestCase;

if (!defined('ZBP_PATH')) {
    define('ZBP_PATH', __DIR__ . '/');
}

require_once dirname(__DIR__) . '/inc/query.php';
require_once dirname(__DIR__) . '/inc/rollup.php';
require_once dirname(__DIR__) . '/inc/query_v2.php';
require_once dirname(__DIR__) . '/inc/v3_query.php';
require_once dirname(__DIR__) . '/inc/v4_report_filters.php';

final class V4ReportFiltersTest extends TestCase
{
    protected function setUp(): void
    {
        global $zbp;
        $zbp = new stdClass();
        $zbp->option = array('ZC_TIME_ZONE' => 'Asia/Shanghai');
    }

    public function testReportFiltersKeepSupportedRangesAndDimensions(): void
    {
        $filters = xz_visit_stats_v4_report_filters(array(
            'range' => '7d',
            'device' => 'mobile',
            'new_returning' => 'returning',
            'source_type' => 'search',
            'country' => 'CN',
            'province' => 'Guangdong',
            'visit_type' => 'human',
            'cursor' => '1720000000:42',
            'page_size' => '100',
        ));

        $this->assertSame('7d', $filters['range']);
        $this->assertSame('mobile', $filters['device']);
        $this->assertSame('returning', $filters['new_returning']);
        $this->assertSame('search', $filters['source_type']);
        $this->assertSame('CN', $filters['country']);
        $this->assertSame('Guangdong', $filters['province']);
        $this->assertSame('human', $filters['visit_type']);
        $this->assertSame('1720000000:42', $filters['cursor']);
        $this->assertSame(100, $filters['page_size']);
    }

    public function testReportFiltersKeepDateRangeContractAndRejectInvalidValues(): void
    {
        foreach (array('today', 'yesterday', '7d', '30d') as $range) {
            $this->assertSame($range, xz_visit_stats_v4_report_filters(array('range' => $range))['range']);
        }

        $custom = xz_visit_stats_v4_report_filters(array(
            'range' => 'custom',
            'start' => '2026-08-01T09:30',
            'end' => '2026-08-02T18:45',
        ));
        $this->assertSame('custom', $custom['range']);
        $this->assertSame('2026-08-01T09:30', $custom['start']);
        $this->assertSame('2026-08-02T18:45', $custom['end']);

        $invalid = xz_visit_stats_v4_report_filters(array(
            'range' => 'not-a-range',
            'device' => 'console',
            'new_returning' => 'unknown',
            'source_type' => 'not-a-source',
            'country' => str_repeat('x', 80),
            'province' => str_repeat('y', 160),
            'cursor' => 'bad cursor!',
            'page_size' => '10000',
        ));
        $this->assertSame('today', $invalid['range']);
        $this->assertSame('', $invalid['device']);
        $this->assertSame('all', $invalid['new_returning']);
        $this->assertSame('all', $invalid['source_type']);
        $this->assertSame(64, strlen($invalid['country']));
        $this->assertSame(128, strlen($invalid['province']));
        $this->assertSame('', $invalid['cursor']);
        $this->assertSame(50, $invalid['page_size']);

        $invalidCustom = xz_visit_stats_v4_report_filters(array(
            'range' => 'custom',
            'start' => '2026-08-02T18:45',
            'end' => 'not-a-date',
        ));
        $this->assertSame('today', $invalidCustom['range']);
    }

    public function testLifecycleAvailabilityDoesNotFabricateHistoricalZeros(): void
    {
        $filters = xz_visit_stats_v4_report_filters(array('range' => '30d'));

        $available = xz_visit_stats_v4_report_availability($filters, array(
            'total_sessions' => 4,
            'lifecycle_sessions' => 4,
            'range_complete' => true,
        ));
        $this->assertSame('available', $available['status']);
        $this->assertSame(1.0, $available['coverage_ratio']);

        $partial = xz_visit_stats_v4_report_availability($filters, array(
            'total_sessions' => 4,
            'lifecycle_sessions' => 2,
            'range_complete' => true,
        ));
        $this->assertSame('partial', $partial['status']);
        $this->assertSame(0.5, $partial['coverage_ratio']);

        $unavailable = xz_visit_stats_v4_report_availability($filters, array(
            'total_sessions' => 0,
            'lifecycle_sessions' => 0,
            'range_complete' => false,
        ));
        $this->assertSame('unavailable', $unavailable['status']);
        $this->assertNull($unavailable['coverage_ratio']);
    }
}
