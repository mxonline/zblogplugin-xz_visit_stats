<?php

if (!defined('ZBP_PATH')) {
    exit('Access denied');
}

function xz_visit_stats_detect_bot($userAgent)
{
    $bots = array(
        'Baiduspider' => 'Baiduspider',
        'Sogou'       => 'Sogou',
        '360Spider'   => '360Spider',
        'HaosouSpider'=> 'HaosouSpider',
        'Bytespider'  => 'Bytespider',
        'PetalBot'    => 'PetalBot',
        'Googlebot'   => 'Googlebot',
        'bingbot'     => 'bingbot',
        'YandexBot'   => 'YandexBot',
        'DuckDuckBot' => 'DuckDuckBot',
        'Applebot'    => 'Applebot',
        'GPTBot'      => 'GPTBot',
        'OAI-SearchBot' => 'OAI-SearchBot',
        'ClaudeBot'   => 'ClaudeBot',
        'PerplexityBot' => 'PerplexityBot',
        'Google-Extended' => 'Google-Extended',
        'Applebot-Extended' => 'Applebot-Extended',
    );

    foreach ($bots as $needle => $name) {
        if (stripos($userAgent, $needle) !== false) {
            return array('is_bot' => true, 'name' => $name);
        }
    }

    return array('is_bot' => false, 'name' => '');
}
