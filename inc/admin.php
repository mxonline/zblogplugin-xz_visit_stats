<?php

if (!defined('ZBP_PATH')) {
    exit('Access denied');
}

function xz_visit_stats_admin_escape($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function xz_visit_stats_admin_short($value, $length = 56)
{
    $value = (string) $value;
    if (function_exists('mb_strimwidth')) {
        return mb_strimwidth($value, 0, $length, '…', 'UTF-8');
    }
    if (strlen($value) <= $length) {
        return $value;
    }

    return substr($value, 0, max(0, $length - 3)) . '...';
}

function xz_visit_stats_admin_status_class($status)
{
    $group = (int) floor((int) $status / 100);
    if ($group < 2 || $group > 5) {
        return 'xz-status-other';
    }

    return 'xz-status-' . $group . 'xx';
}

function xz_visit_stats_admin_submenu($view)
{
    $groups = array(
        '概览' => array('overview' => '总览', 'realtime' => '实时分析'),
        '流量分析' => array('records' => '访问记录', 'pages' => '页面分析', 'source' => '来源分析', 'campaign' => 'Campaign / AI 来源'),
        '访客分析' => array('ip' => 'IP 分析', 'environment' => '访客环境'),
        '技术分析' => array('spider' => '蜘蛛分析', 'errors' => '错误分析', 'performance' => '性能分析'),
        '系统' => array('settings' => '设置与维护'),
    );
    echo '<nav class="xzvs-nav" aria-label="访问分析模块">';
    foreach ($groups as $group => $items) {
        echo '<div class="xzvs-nav-group"><strong class="xzvs-nav-title">' . xz_visit_stats_admin_escape($group) . '</strong><div class="xzvs-nav-items">';
        foreach ($items as $key => $label) {
            $class = $view === $key ? 'xzvs-nav-link is-active' : 'xzvs-nav-link';
            echo '<a class="' . $class . '" href="main.php?view=' . rawurlencode($key) . '">' . xz_visit_stats_admin_escape($label) . '</a>';
        }
        echo '</div></div>';
    }
    echo '</nav>';
}

function xz_visit_stats_admin_selected($actual, $expected)
{
    return (string) $actual === (string) $expected ? ' selected="selected"' : '';
}

function xz_visit_stats_admin_checked($actual, $expected)
{
    return (string) $actual === (string) $expected ? ' checked="checked"' : '';
}

function xz_visit_stats_admin_page_url($filters, $page)
{
    global $zbp;

    $params = xz_visit_stats_query_url_params($filters);
    $params['page'] = max(1, (int) $page);

    return $zbp->host . 'zb_users/plugin/xz_visit_stats/main.php?'
        . http_build_query($params, '', '&');
}

function xz_visit_stats_admin_spider_url($filters, $page, $spider = null)
{
    global $zbp;

    $params = array(
        'view' => 'spider',
        'range' => $filters['range'],
        'start' => $filters['start'],
        'end' => $filters['end'],
        'spider' => $spider === null ? $filters['spider'] : $spider,
        'page_size' => $filters['page_size'],
        'page' => max(1, (int) $page),
    );
    foreach ($params as $key => $value) {
        if ($value === '' || $value === 'all') {
            unset($params[$key]);
        }
    }
    $params['view'] = 'spider';

    return $zbp->host . 'zb_users/plugin/xz_visit_stats/main.php?'
        . http_build_query($params, '', '&');
}

function xz_visit_stats_admin_seo_url($filters, $page)
{
    global $zbp;

    $params = array(
        'view' => 'seo', 'range' => $filters['range'], 'page_size' => $filters['page_size'],
        'page' => max(1, (int) $page),
    );

    return $zbp->host . 'zb_users/plugin/xz_visit_stats/main.php?'
        . http_build_query($params, '', '&');
}

function xz_visit_stats_admin_source_url($filters, $page, $domain = null)
{
    global $zbp;

    $params = array(
        'view' => 'source', 'range' => $filters['range'], 'start' => $filters['start'],
        'end' => $filters['end'], 'source_type' => $filters['source_type'],
        'domain' => $domain === null ? $filters['domain'] : $domain,
        'page_size' => $filters['page_size'], 'page' => max(1, (int) $page),
    );
    foreach ($params as $key => $value) {
        if ($value === '' || $value === 'all') {
            unset($params[$key]);
        }
    }
    $params['view'] = 'source';

    return $zbp->host . 'zb_users/plugin/xz_visit_stats/main.php?'
        . http_build_query($params, '', '&');
}

function xz_visit_stats_admin_ip_url($filters, $page, $ip = null)
{
    global $zbp;

    $params = array(
        'view' => 'ip', 'range' => $filters['range'], 'start' => $filters['start'],
        'end' => $filters['end'], 'ip' => $ip === null ? $filters['ip'] : $ip,
        'page_size' => $filters['page_size'], 'page' => max(1, (int) $page),
    );
    foreach ($params as $key => $value) {
        if ($value === '') {
            unset($params[$key]);
        }
    }
    $params['view'] = 'ip';

    return $zbp->host . 'zb_users/plugin/xz_visit_stats/main.php?'
        . http_build_query($params, '', '&');
}

function xz_visit_stats_admin_metric_value($value, $decimal = false)
{
    return $decimal
        ? number_format((float) $value, 1)
        : number_format((int) $value);
}

function xz_visit_stats_admin_status_distribution($row)
{
    $parts = array();
    foreach (array('2xx', '3xx', '4xx', '5xx') as $group) {
        $key = 'status_' . $group;
        $parts[] = $group . ' ' . (isset($row[$key]) ? (int) $row[$key] : 0);
    }

    return implode(' / ', $parts);
}

function xz_visit_stats_admin_delta($current, $previous, $decimal = false, $compareLabel = '昨日')
{
    $delta = xz_visit_stats_stats_delta($current, $previous);
    $value = $decimal
        ? number_format(abs((float) $delta['value']), 1)
        : number_format(abs((int) $delta['value']));

    if ($delta['percent'] === null) {
        return array('class' => 'xz-delta-flat', 'text' => '暂无' . $compareLabel . '数据');
    }
    if ($delta['value'] > 0) {
        return array('class' => 'xz-delta-up', 'text' => '较' . $compareLabel . '增加 ' . $value . ($decimal ? ' ms' : ''));
    }
    if ($delta['value'] < 0) {
        return array('class' => 'xz-delta-down', 'text' => '较' . $compareLabel . '减少 ' . $value . ($decimal ? ' ms' : ''));
    }

    return array(
        'class' => 'xz-delta-flat',
        'text' => '与' . $compareLabel . '持平',
    );
}

function xz_visit_stats_admin_head()
{
    global $zbp;

    echo '<link rel="stylesheet" href="'
        . xz_visit_stats_admin_escape($zbp->host)
        . 'zb_users/plugin/xz_visit_stats/assets/admin.css?v=3.0.0-t5" type="text/css" />';
}
