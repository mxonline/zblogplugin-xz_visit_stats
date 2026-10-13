<?php

error_reporting(E_ALL);
ini_set('display_errors', 'stderr');
$root = getenv('XZVS_ZBLOG_ROOT');
$pluginId = getenv('XZVS_PLUGIN_ID');
$t5BeaconTarget = isset($argv[1]) ? (string) $argv[1] : '';
$t5BeaconExpected = isset($argv[2]) ? (string) $argv[2] : '';
if (!is_string($root) || $root === '' || !is_string($pluginId) || !preg_match('/^[a-z0-9_]+$/', $pluginId) || !in_array($t5BeaconTarget, array('0', '1'), true) || !in_array($t5BeaconExpected, array('0', '1'), true)) {
    fwrite(STDERR, "Invalid beacon config request.\n");
    exit(2);
}
$root = rtrim(str_replace('\\', '/', $root), '/') . '/';
defined('ZBP_OBSTART') || define('ZBP_OBSTART', false);
require $root . 'zb_system/function/c_system_base.php';
$zbp = $GLOBALS['zbp'];
$config = $zbp->Config($pluginId);
$current = isset($config->beacon_enabled) ? ((int) $config->beacon_enabled === 1 ? '1' : '0') : '0';
if ($current !== $t5BeaconExpected) {
    fwrite(STDERR, "Beacon config precondition did not match; no config was changed.\n");
    exit(3);
}
$config->beacon_enabled = (int) $t5BeaconTarget;
$zbp->SaveConfig($pluginId);
$readback = $zbp->Config($pluginId);
$stored = isset($readback->beacon_enabled) ? ((int) $readback->beacon_enabled === 1 ? '1' : '0') : '0';
if ($stored !== $t5BeaconTarget) {
    fwrite(STDERR, "Beacon config readback did not match.\n");
    exit(4);
}
echo json_encode(array('status' => 'PASS', 'beacon_enabled' => (int) $stored), JSON_UNESCAPED_SLASHES) . "\n";
