<?php

if (!defined('ZBP_PATH')) exit('Access denied');

function xz_visit_stats_v4_metrics($sessions, $events)
{
    $sessionCount = count((array) $sessions); $completed = 0; $bounces = 0; $pages = 0; $dwell = array();
    foreach ((array) $sessions as $session) {
        $pages += max(0, (int) (isset($session['page_count']) ? $session['page_count'] : 0));
        if (!empty($session['is_complete'])) { $completed++; if ((int) $session['page_count'] === 1) $bounces++; }
        if (!empty($session['is_complete']) && isset($session['duration_ms']) && $session['duration_ms'] !== null && (int) $session['duration_ms'] >= 0) $dwell[] = (int) $session['duration_ms'];
    }
    $visitors = array(); foreach ((array) $events as $event) if (!empty($event['visitor_hash'])) $visitors[(string) $event['visitor_hash']] = true;
    $eventTotal = count((array) $events); $unique = count($visitors);
    return array('session_count' => $sessionCount, 'average_page_depth' => $sessionCount ? (float) ($pages / $sessionCount) : null, 'bounce_sessions' => $bounces, 'bounce_rate' => $completed ? (float) round($bounces * 100 / $completed, 2) : null, 'average_dwell_ms' => $dwell ? (float) (array_sum($dwell) / count($dwell)) : null, 'event_total' => $eventTotal, 'event_unique_visitors' => $unique, 'event_average_per_user' => $unique ? (float) ($eventTotal / $unique) : null);
}
