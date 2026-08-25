<?php

require '../../../zb_system/function/c_system_base.php';
require '../../../zb_system/function/c_system_admin.php';
$zbp->Load();
if (!$zbp->CheckRights('root') || !$zbp->CheckPlugin('xz_visit_stats')) { http_response_code(403); exit; }
require_once __DIR__ . '/inc/realtime.php';
require_once __DIR__ . '/inc/v3_query.php';
header('Content-Type: application/json; charset=UTF-8');
try {
    echo json_encode(array('active' => xz_visit_stats_v3_active_summary(xz_visit_stats_settings_values()['realtime_window']), 'rows' => xz_visit_stats_realtime_rows(50)), JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) { http_response_code(503); echo '{"error":"realtime_unavailable"}'; }
