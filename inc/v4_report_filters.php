<?php

if (!defined('ZBP_PATH')) {
    exit('Access denied');
}

/**
 * Normalise the shared T4 report inputs without creating a second date parser.
 *
 * The v3 filter contract remains the base because its values are also consumed
 * by legacy report views. T4 only adds dimensions that have no v3 equivalent.
 */
function xz_visit_stats_v4_report_filters(array $source): array
{
    $filters = xz_visit_stats_v3_filters($source);

    // T4 analytics views share the PRD's calendar ranges. Realtime owns its
    // short active-window selector and must not leak that concern into reports.
    if (!in_array($filters['range'], array('today', 'yesterday', '7d', '30d', 'custom'), true)) {
        $filters['range'] = 'today';
    }

    // Reuse the existing timezone-aware parser to turn invalid custom input
    // into the established today fallback rather than inventing date rules.
    $effectiveRange = xz_visit_stats_v2_range($filters);
    if ($effectiveRange['range'] !== $filters['range']) {
        $filters['range'] = $effectiveRange['range'];
        $filters['start'] = '';
        $filters['end'] = '';
    }

    $device = xz_visit_stats_query_text(xz_visit_stats_query_value($source, 'device', ''), 32);
    $filters['device'] = in_array($device, array('', 'desktop', 'mobile', 'tablet', 'bot', 'unknown'), true) ? $device : '';

    $newReturning = xz_visit_stats_query_text(
        xz_visit_stats_query_value($source, 'new_returning', xz_visit_stats_query_value($source, 'visitor_type', 'all')),
        16
    );
    $filters['new_returning'] = in_array($newReturning, array('all', 'new', 'returning'), true) ? $newReturning : 'all';

    // Geo labels may be localized, so use the existing bounded text sanitizer
    // and let query builders quote them rather than imposing an ASCII-only rule.
    $filters['country'] = xz_visit_stats_query_text(xz_visit_stats_query_value($source, 'country', ''), 64);
    $filters['province'] = xz_visit_stats_query_text(
        xz_visit_stats_query_value($source, 'province', xz_visit_stats_query_value($source, 'region', '')),
        128
    );

    // Cursor is an opaque keyset token; allow only transport-safe characters.
    $cursor = xz_visit_stats_query_text(xz_visit_stats_query_value($source, 'cursor', ''), 128);
    $filters['cursor'] = preg_match('/^[A-Za-z0-9:_-]{1,128}$/', $cursor) === 1 ? $cursor : '';

    return $filters;
}

/**
 * Describe whether lifecycle metrics are complete for a requested report.
 *
 * Coverage must be supplied by the bounded v4 session query. A missing or
 * false range_complete is deliberately conservative: historical data cannot
 * be represented as zero dwell or zero bounce.
 */
function xz_visit_stats_v4_report_availability(array $filters, array $coverage): array
{
    unset($filters); // The query layer owns range calculation; keep this API stable for report callers.

    $total = max(0, (int) (isset($coverage['total_sessions']) ? $coverage['total_sessions'] : (isset($coverage['session_count']) ? $coverage['session_count'] : 0)));
    $lifecycle = max(0, (int) (isset($coverage['lifecycle_sessions']) ? $coverage['lifecycle_sessions'] : (isset($coverage['covered_sessions']) ? $coverage['covered_sessions'] : 0)));
    $lifecycle = min($total, $lifecycle);
    $rangeComplete = isset($coverage['range_complete']) && $coverage['range_complete'] === true;
    $ratio = $total > 0 ? (float) $lifecycle / $total : null;

    if ($rangeComplete && $lifecycle === $total) {
        $status = 'available';
        $reason = '';
    } elseif ($lifecycle > 0) {
        $status = 'partial';
        $reason = $rangeComplete ? 'incomplete_lifecycle' : 'historical_coverage';
    } elseif (!$rangeComplete) {
        $status = 'unavailable';
        $reason = 'historical_coverage';
    } else {
        $status = 'unavailable';
        $reason = 'no_lifecycle_coverage';
    }

    return array(
        'status' => $status,
        'total_sessions' => $total,
        'lifecycle_sessions' => $lifecycle,
        'coverage_ratio' => $ratio,
        'reason' => $reason,
    );
}
