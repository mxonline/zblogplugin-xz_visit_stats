<?php

if (!defined('ZBP_PATH')) {
    exit('Access denied');
}

function xz_visit_stats_parse_ua($userAgent, $isBot = false)
{
    if ($isBot) {
        return array(
            'type' => 'bot',
            'browser' => '',
            'os' => '',
            'device' => 'bot',
        );
    }

    $browser = xz_visit_stats_detect_browser($userAgent);
    $os = xz_visit_stats_detect_os($userAgent);
    $device = xz_visit_stats_detect_device($userAgent);

    return array(
        'type' => $browser === '' ? 'other' : 'browser',
        'browser' => $browser,
        'os' => $os,
        'device' => $device,
    );
}

function xz_visit_stats_detect_browser($userAgent)
{
    $browsers = array(
        'Edge'             => '/(?:Edg|Edge)\//i',
        'Opera'            => '/(?:OPR|Opera)\//i',
        'Samsung Internet' => '/SamsungBrowser\//i',
        'Firefox'          => '/(?:Firefox|FxiOS)\//i',
        'Chrome'           => '/(?:Chrome|CriOS)\//i',
        'Internet Explorer'=> '/(?:MSIE\s|Trident\/)/i',
        'Safari'           => '/Safari\//i',
    );

    foreach ($browsers as $name => $pattern) {
        if (preg_match($pattern, $userAgent)) {
            return $name;
        }
    }

    return '';
}

function xz_visit_stats_detect_os($userAgent)
{
    $systems = array(
        'Windows'  => '/Windows NT/i',
        'Android'  => '/Android/i',
        'iOS'      => '/(?:iPhone|iPad|iPod)/i',
        'macOS'    => '/Macintosh|Mac OS X/i',
        'Chrome OS'=> '/CrOS/i',
        'Linux'    => '/Linux/i',
    );

    foreach ($systems as $name => $pattern) {
        if (preg_match($pattern, $userAgent)) {
            return $name;
        }
    }

    return '';
}

function xz_visit_stats_detect_device($userAgent)
{
    if (preg_match('/iPad|Tablet|Nexus 7|Nexus 9|Nexus 10/i', $userAgent)) {
        return 'tablet';
    }
    if (preg_match('/Mobile|iPhone|iPod|Android/i', $userAgent)) {
        return 'mobile';
    }
    if ($userAgent !== '') {
        return 'desktop';
    }

    return 'unknown';
}
