<?php

if (!defined('ZBP_PATH')) {
    exit('Access denied');
}

function xz_visit_stats_v4_spider_report(array $sourceFilters, string $cursor = '', int $limit = 30, string $ipMode = 'full'): array
{
    $filters = xz_visit_stats_v3_filters($sourceFilters);
    $filters['visit_type'] = 'bot';
    $ipMode = $ipMode === 'masked' ? 'masked' : 'full';

    $rows = xz_visit_stats_v2_spider_summary($filters, 50);
    $trend = xz_visit_stats_v2_trend($filters);
    $records = xz_visit_stats_v3_keyset_records($filters, $cursor, $limit);
    $ai = xz_visit_stats_v3_ai_crawler_rows($filters, 50);

    if ($ipMode === 'masked' && function_exists('xz_visit_stats_settings_mask_ip')) {
        foreach ($records as &$record) {
            $record['vs_IP'] = xz_visit_stats_settings_mask_ip(isset($record['vs_IP']) ? $record['vs_IP'] : '');
        }
        unset($record);
    }

    $chartTrend = array_map(function ($item) {
        return array('label' => (string) $item['label'], 'visits' => (int) $item['bot_pv']);
    }, $trend);
    $distribution = array_map(function ($item) {
        return array('name' => (string) $item['bot_name'], 'visits' => (int) $item['bot_pv']);
    }, $rows);
    $nextCursor = count($records) >= max(1, min(100, $limit)) && !empty($records)
        ? (string) end($records)['vs_ID']
        : '';

    return array(
        'rows' => $rows,
        'trend' => $trend,
        'chart_trend' => $chartTrend,
        'distribution' => $distribution,
        'records' => $records,
        'next_cursor' => $nextCursor,
        'ai' => $ai,
        'ip_mode' => $ipMode,
    );
}
