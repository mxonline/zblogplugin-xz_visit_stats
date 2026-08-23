<?php

if (!defined('ZBP_PATH')) {
    exit('Access denied');
}

/**
 * Update v2 page statistics.
 *
 * Aggregate page PV/UV data separately from raw visit logs.
 */
function xz_visit_stats_update_page_stats($url, $title = '', $visitorHash = '')
{
    global $zbp;

    if ($url === '') {
        return false;
    }

    $table = $zbp->db->dbpre . 'xz_visit_stats_pages';
    $safeUrl = addslashes($url);
    $row = $zbp->GetOne("SELECT * FROM {$table} WHERE Url='{$safeUrl}' LIMIT 1");

    if ($row) {
        $pv = intval($row['PV']) + 1;
        $uv = intval($row['UV']);
        $sql = "UPDATE {$table} SET PV={$pv}, LastVisit=" . time() . " WHERE ID=" . intval($row['ID']);
        $zbp->db->Query($sql);
    } else {
        $data = array(
            'Url' => $url,
            'Title' => $title,
            'PV' => 1,
            'UV' => $visitorHash ? 1 : 0,
            'LastVisit' => time(),
        );
        $sql = $zbp->db->sql->Insert($table, $data);
        $zbp->db->Query($sql);
    }

    return true;
}
