<?php

error_reporting(E_ALL);
ini_set('display_errors', 'stderr');

$root = getenv('XZVS_ZBLOG_ROOT');
$pluginId = getenv('XZVS_PLUGIN_ID');
$xzvsT5Action = isset($argv[1]) ? (string) $argv[1] : '';
if (!is_string($root) || $root === '' || !is_string($pluginId) || !preg_match('/^[a-z0-9_]+$/', $pluginId)) {
    fwrite(STDERR, "Missing or invalid runtime target.\n");
    exit(2);
}
if (!in_array($xzvsT5Action, array('status', 'install', 'disable', 'enable'), true)) {
    fwrite(STDERR, "Action must be status, install, disable, or enable.\n");
    exit(3);
}

$root = rtrim(str_replace('\\', '/', $root), '/') . '/';
defined('ZBP_OBSTART') || define('ZBP_OBSTART', false);
require $root . 'zb_system/function/c_system_base.php';
$zbp = $GLOBALS['zbp'];
require_once $root . 'zb_users/plugin/' . $pluginId . '/include.php';

if (!function_exists('InstallPlugin') || !function_exists('EnablePlugin') || !function_exists('DisablePlugin')) {
    fwrite(STDERR, "Z-Blog lifecycle APIs were not loaded.\n");
    exit(4);
}

try {
    if ($xzvsT5Action === 'install') {
        InstallPlugin($pluginId);
    } elseif ($xzvsT5Action === 'disable') {
        DisablePlugin($pluginId);
    } elseif ($xzvsT5Action === 'enable') {
        EnablePlugin($pluginId);
        InstallPlugin($pluginId);
    }
    $pluginList = isset($zbp->option['ZC_USING_PLUGIN_LIST']) ? explode('|', (string) $zbp->option['ZC_USING_PLUGIN_LIST']) : array();
    $enabled = in_array($pluginId, $pluginList, true);
    $expected = $xzvsT5Action !== 'disable';
    if ($enabled !== $expected) throw new RuntimeException('plugin_enabled_state_mismatch');
    echo json_encode(array('status' => 'PASS', 'action' => $xzvsT5Action, 'enabled' => $enabled), JSON_UNESCAPED_SLASHES) . "\n";
} catch (Throwable $exception) {
    fwrite(STDERR, "Z-Blog lifecycle operation failed.\n");
    exit(10);
}
