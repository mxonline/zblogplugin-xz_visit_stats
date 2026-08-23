<?php

if (!defined('ZBP_PATH')) {
    exit('Access denied');
}

function xz_visit_stats_update_page_stats($url, $title = '', $visitorHash = '')
{
    global $zbp;

    if ($url === '') {
        return false;
    }

    $table = $zbp->db->dbpre . 'xz_visit_stats_pages';
    $uvTable = $zbp->db->dbpre . 'xz_visit_stats_page_uv';
    $safeUrl = addslashes($url);
    $row = $zbp->GetOne("SELECT * FROM {$table} WHERE Url='{$safeUrl}' LIMIT 1");

    if ($row) {
        $pv = intval($row['PV']) + 1;
        $uv = intval($row['UV']);

        if ($visitorHash) {
            $safeHash = addslashes($visitorHash);
            $exists = $zbp->GetOne("SELECT ID FROM {$uvTable} WHERE Url='{$safeUrl}' AND VisitorHash='{$safeHash}' LIMIT 1");
            if (!$exists) {
                $zbp->db->Query($zbp->db->sql->Insert($uvTable, array('Url'=>$url,'VisitorHash'=>$visitorHash,'Created'=>time())));
                $uv++;
            }
        }

        $zbp->db->Query("UPDATE {$table} SET PV={$pv}, UV={$uv}, LastVisit=".time()." WHERE ID=".intval($row['ID']));
    } else {
        $zbp->db->Query($zbp->db->sql->Insert($table, array(
            'Url'=>$url,
            'Title'=>$title,
            'PV'=>1,
            'UV'=>$visitorHash ? 1 : 0,
            'LastVisit'=>time(),
        )));

        if ($visitorHash) {
            $zbp->db->Query($zbp->db->sql->Insert($uvTable, array('Url'=>$url,'VisitorHash'=>$visitorHash,'Created'=>time())));
        }
    }

    return true;
}
