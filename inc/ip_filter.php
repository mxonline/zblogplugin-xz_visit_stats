<?php

if (!defined('ZBP_PATH')) exit('Access denied');

require_once dirname(__DIR__) . '/vendor/mlocati/ip-lib/ip-lib.php';

function xz_visit_stats_v4_ip_rule_normalize($rule)
{
    $raw = trim((string) $rule);
    $range = \IPLib\Factory::parseRangeString($raw);
    if ($range === null) return null;
    $value = $range->toString();
    $type = strpos($raw, '/') === false ? 'ip' : 'cidr';
    return array('type' => $type, 'value' => $value, 'hash' => hash('sha256', $type . '|' . $value));
}

function xz_visit_stats_v4_ip_rule_matches($ip, $rules)
{
    $address = \IPLib\Factory::parseAddressString((string) $ip);
    if ($address === null) return false;
    foreach ((array) $rules as $rule) {
        $normalized = xz_visit_stats_v4_ip_rule_normalize($rule);
        if ($normalized === null) continue;
        $range = \IPLib\Factory::parseRangeString($normalized['value']);
        if ($range !== null && $range->contains($address)) return true;
    }
    return false;
}

function xz_visit_stats_v4_ip_is_filtered($ip)
{
    global $zbp;
    $table = $zbp->db->dbpre . 'xz_visit_stats_ip_filters';
    $rows = (array) $zbp->db->Query('SELECT if_Value FROM `' . $table . '` WHERE if_Enabled=1');
    return xz_visit_stats_v4_ip_rule_matches($ip, array_map(function ($row) { return isset($row['if_Value']) ? $row['if_Value'] : ''; }, $rows));
}
