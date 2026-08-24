<?php

if (!defined('ZBP_PATH')) {
    exit('Access denied');
}

function xz_visit_stats_settings_defaults()
{
    return array(
        'enabled' => 1,
        'exclude_admin' => 0,
        'record_bots' => 1,
        'record_baiduspider' => 1,
        'record_googlebot' => 1,
        'record_bingbot' => 1,
        'record_other_bots' => 1,
        'record_referer' => 1,
        'record_user_agent' => 1,
        'retention_days' => 180,
        'auto_cleanup' => 0,
        'ip_mode' => 'full',
        'write_mode' => 'realtime',
        'log_alert_count' => 100000,
        'trusted_proxies' => '',
        'real_ip_header' => 'X-Forwarded-For',
        'geo_db_path' => '',
        'realtime_window' => 5,
        'enhanced_collect' => 0,
    );
}

function xz_visit_stats_ensure_settings()
{
    global $zbp;

    $config = $zbp->Config('xz_visit_stats');
    $changed = false;
    foreach (xz_visit_stats_settings_defaults() as $key => $value) {
        if (!isset($config->$key) || $config->$key === '') {
            $config->$key = $value;
            $changed = true;
        }
    }
    if ($changed) {
        $zbp->SaveConfig('xz_visit_stats');
    }
}

function xz_visit_stats_settings_values()
{
    global $zbp;

    xz_visit_stats_ensure_settings();
    $config = $zbp->Config('xz_visit_stats');
    $settings = xz_visit_stats_settings_defaults();
    foreach ($settings as $key => $default) {
        $settings[$key] = $config->$key;
    }
    foreach (array('enabled', 'exclude_admin', 'record_bots', 'record_baiduspider', 'record_googlebot', 'record_bingbot', 'record_other_bots', 'record_referer', 'record_user_agent', 'auto_cleanup') as $key) {
        $settings[$key] = (int) $settings[$key] === 1 ? 1 : 0;
    }
    $settings['retention_days'] = in_array((int) $settings['retention_days'], array(30, 90, 180, 365), true) ? (int) $settings['retention_days'] : 180;
    $settings['ip_mode'] = $settings['ip_mode'] === 'masked' ? 'masked' : 'full';
    $settings['write_mode'] = $settings['write_mode'] === 'batch' ? 'batch' : 'realtime';
    $settings['log_alert_count'] = max(10000, min(10000000, (int) $settings['log_alert_count']));
    $settings['trusted_proxies'] = xz_visit_stats_limit($settings['trusted_proxies'], 2048);
    $settings['real_ip_header'] = xz_visit_stats_limit($settings['real_ip_header'], 64);
    $settings['realtime_window'] = in_array((int) $settings['realtime_window'], array(5, 10, 15, 30), true) ? (int) $settings['realtime_window'] : 5;
    $settings['enhanced_collect'] = (int) $settings['enhanced_collect'] === 1 ? 1 : 0;

    return $settings;
}

function xz_visit_stats_settings_is_admin_visitor()
{
    global $zbp;

    return isset($zbp->user) && isset($zbp->user->Level) && (int) $zbp->user->Level === 1;
}

function xz_visit_stats_settings_mask_ip($ip)
{
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
        $parts = explode('.', $ip);
        return count($parts) === 4 ? $parts[0] . '.' . $parts[1] . '.' . $parts[2] . '.0' : '';
    }
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
        $parts = explode(':', $ip);
        return implode(':', array_slice($parts, 0, 4)) . '::';
    }

    return '';
}

function xz_visit_stats_settings_record_bot($name, $settings)
{
    if ($settings['record_bots'] !== 1) {
        return false;
    }
    if ($name === 'Baiduspider') {
        return $settings['record_baiduspider'] === 1;
    }
    if ($name === 'Googlebot') {
        return $settings['record_googlebot'] === 1;
    }
    if ($name === 'bingbot') {
        return $settings['record_bingbot'] === 1;
    }

    return $settings['record_other_bots'] === 1;
}

function xz_visit_stats_settings_save($source)
{
    global $zbp;

    if (!CheckCSRFTokenValid('csrfToken', 'post')) {
        return array('type' => 'error', 'message' => '安全校验失败，请刷新页面后重试。');
    }
    $settings = xz_visit_stats_settings_defaults();
    foreach (array('enabled', 'exclude_admin', 'record_bots', 'record_baiduspider', 'record_googlebot', 'record_bingbot', 'record_other_bots', 'record_referer', 'record_user_agent', 'auto_cleanup') as $key) {
        $settings[$key] = xz_visit_stats_query_value($source, $key, '') === '1' ? 1 : 0;
    }
    $days = (int) xz_visit_stats_query_value($source, 'retention_days', 180);
    $settings['retention_days'] = in_array($days, array(30, 90, 180, 365), true) ? $days : 180;
    $ipMode = xz_visit_stats_query_text(xz_visit_stats_query_value($source, 'ip_mode', 'full'), 16);
    $settings['ip_mode'] = $ipMode === 'masked' ? 'masked' : 'full';
    $settings['write_mode'] = 'realtime';
    $alert = (int) xz_visit_stats_query_value($source, 'log_alert_count', 100000);
    $settings['log_alert_count'] = max(10000, min(10000000, $alert));
    $settings['trusted_proxies'] = xz_visit_stats_limit(xz_visit_stats_query_value($source, 'trusted_proxies', ''), 2048);
    $settings['real_ip_header'] = xz_visit_stats_limit(xz_visit_stats_query_value($source, 'real_ip_header', 'X-Forwarded-For'), 64);
    $window = (int) xz_visit_stats_query_value($source, 'realtime_window', 5);
    $settings['realtime_window'] = in_array($window, array(5, 10, 15, 30), true) ? $window : 5;
    $settings['enhanced_collect'] = xz_visit_stats_query_value($source, 'enhanced_collect', '') === '1' ? 1 : 0;
    $config = $zbp->Config('xz_visit_stats');
    foreach ($settings as $key => $value) {
        $config->$key = $value;
    }
    $zbp->SaveConfig('xz_visit_stats');

    return array('type' => 'success', 'message' => '设置已保存。');
}
