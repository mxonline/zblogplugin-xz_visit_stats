<?php

require '../../../zb_system/function/c_system_base.php';
require '../../../zb_system/function/c_system_admin.php';

$zbp->Load();

$action = 'root';
if (!$zbp->CheckRights($action)) {
    $zbp->ShowError(6);
    die();
}
if (!$zbp->CheckPlugin('xz_visit_stats')) {
    $zbp->ShowError(48);
    die();
}

require_once __DIR__ . '/inc/query.php';
require_once __DIR__ . '/inc/admin.php';
require_once __DIR__ . '/inc/stats.php';
require_once __DIR__ . '/inc/spider_stats.php';
require_once __DIR__ . '/inc/source_stats.php';
require_once __DIR__ . '/inc/ip_stats.php';
require_once __DIR__ . '/inc/maintenance.php';
require_once __DIR__ . '/inc/realtime.php';
require_once __DIR__ . '/inc/seo_report.php';
require_once __DIR__ . '/inc/settings.php';

$views = array('overview', 'records', 'spider', 'seo', 'source', 'ip', 'maintenance', 'realtime', 'settings');
$view = xz_visit_stats_query_text(GetVars('view', 'GET', 'overview'), 16);
if (!in_array($view, $views, true)) {
    $view = 'records';
}

$blogtitle = '访问统计';
$filters = null;
$pageData = null;
$overviewFilters = null;
$overviewData = null;
$spiderFilters = null;
$spiderData = null;
$sourceFilters = null;
$sourceData = null;
$ipFilters = null;
$ipData = null;
$maintenanceOverview = null;
$maintenanceResult = null;
$realtimeFilters = null;
$realtimeData = null;
$seoFilters = null;
$seoData = null;
$settings = null;
$settingsResult = array('type' => '', 'message' => '');
if ($view === 'records') {
    $filters = xz_visit_stats_query_filters($_GET);
    $pageData = xz_visit_stats_query_page($filters);
} elseif ($view === 'overview') {
    $overviewFilters = xz_visit_stats_stats_filters($_GET);
    $overviewData = xz_visit_stats_stats_build($overviewFilters);
} elseif ($view === 'spider') {
    $spiderFilters = xz_visit_stats_spider_filters($_GET);
    $spiderData = xz_visit_stats_spider_build($spiderFilters);
} elseif ($view === 'seo') {
    $seoFilters = xz_visit_stats_seo_report_filters($_GET);
    $seoData = xz_visit_stats_seo_report_build($seoFilters);
} elseif ($view === 'source') {
    $sourceFilters = xz_visit_stats_source_filters($_GET);
    $sourceData = xz_visit_stats_source_build($sourceFilters);
} elseif ($view === 'ip') {
    $ipFilters = xz_visit_stats_ip_filters($_GET);
    $ipData = xz_visit_stats_ip_build($ipFilters);
} elseif ($view === 'maintenance') {
    $maintenanceResult = isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST'
        ? xz_visit_stats_maintenance_handle_post($_POST)
        : array('type' => '', 'message' => '', 'filters' => null, 'count' => 0);
    $maintenanceOverview = xz_visit_stats_maintenance_overview();
} elseif ($view === 'realtime') {
    $realtimeFilters = xz_visit_stats_realtime_filters($_GET);
    $realtimeData = xz_visit_stats_realtime_payload($realtimeFilters);
    if (xz_visit_stats_query_value($_GET, 'ajax', '') === '1') {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($realtimeData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        exit;
    }
} elseif ($view === 'settings') {
    $settingsResult = isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST'
        ? xz_visit_stats_settings_save($_POST)
        : $settingsResult;
    $settings = xz_visit_stats_settings_values();
}

Add_Filter_Plugin('Filter_Plugin_Admin_Header', 'xz_visit_stats_admin_head');
require $blogpath . 'zb_system/admin/admin_header.php';
require $blogpath . 'zb_system/admin/admin_top.php';
?>
<div id="divMain">
      <div class="divHeader"><?php echo $view === 'records' ? '访问记录' : ($view === 'overview' ? '统计概览' : ($view === 'spider' ? '蜘蛛分析' : ($view === 'seo' ? 'SEO 报告' : ($view === 'source' ? '来源分析' : ($view === 'ip' ? 'IP 分析' : ($view === 'maintenance' ? '数据维护' : ($view === 'realtime' ? '实时访问' : ($view === 'settings' ? '设置中心' : '访问统计')))))))); ?></div>
  <div class="SubMenu"><?php xz_visit_stats_admin_submenu($view); ?></div>
  <div id="divMain2">
<?php if ($view === 'overview') {
    $current = $overviewData['summary']['current'];
    $previous = $overviewData['summary']['previous'];
    $topPath = !empty($overviewData['top_paths']) ? $overviewData['top_paths'][0] : array('path' => '', 'visits' => 0);
    $metricCards = array(
        array('key' => 'pv', 'label' => '访问量', 'decimal' => false, 'suffix' => ''),
        array('key' => 'uv', 'label' => '访客数', 'decimal' => false, 'suffix' => ''),
        array('key' => 'bot', 'label' => '蜘蛛抓取', 'decimal' => false, 'suffix' => ''),
        array('key' => 'top_path', 'label' => '热门页面', 'decimal' => false, 'suffix' => ''),
        array('key' => 'errors', 'label' => '错误请求', 'decimal' => false, 'suffix' => ''),
        array('key' => 'avg_ms', 'label' => '平均耗时', 'decimal' => true, 'suffix' => ' ms'),
    );
?>
    <form class="xz-overview-filter" method="get" action="main.php">
      <input type="hidden" name="view" value="overview" />
      <label for="overview-range">时间范围</label>
      <select id="overview-range" name="range">
        <option value="today"<?php echo xz_visit_stats_admin_selected($overviewFilters['range'], 'today'); ?>>今天</option>
        <option value="yesterday"<?php echo xz_visit_stats_admin_selected($overviewFilters['range'], 'yesterday'); ?>>昨天</option>
        <option value="7d"<?php echo xz_visit_stats_admin_selected($overviewFilters['range'], '7d'); ?>>最近 7 天</option>
        <option value="30d"<?php echo xz_visit_stats_admin_selected($overviewFilters['range'], '30d'); ?>>最近 30 天</option>
        <option value="custom"<?php echo xz_visit_stats_admin_selected($overviewFilters['range'], 'custom'); ?>>自定义</option>
      </select>
      <label for="overview-start">开始时间</label>
      <input id="overview-start" type="datetime-local" name="start" value="<?php echo xz_visit_stats_admin_escape($overviewFilters['start']); ?>" />
      <label for="overview-end">结束时间</label>
      <input id="overview-end" type="datetime-local" name="end" value="<?php echo xz_visit_stats_admin_escape($overviewFilters['end']); ?>" />
      <button type="submit" class="button">查询</button>
    </form>
    <p class="xz-overview-note">当前范围：<?php echo xz_visit_stats_admin_escape($overviewData['range']['label']); ?>；指标对比：<?php echo xz_visit_stats_admin_escape($overviewData['range']['compare_label']); ?></p>
    <div class="xz-metric-grid">
<?php foreach ($metricCards as $card) {
    $value = $card['key'] === 'errors'
        ? $current['status_4xx'] + $current['status_5xx']
        : ($card['key'] === 'top_path' ? $topPath['visits'] : $current[$card['key']]);
    $before = $card['key'] === 'errors'
        ? $previous['status_4xx'] + $previous['status_5xx']
        : ($card['key'] === 'top_path' ? 0 : $previous[$card['key']]);
    $delta = xz_visit_stats_admin_delta($value, $before, $card['decimal'], $overviewData['range']['compare_label']);
?>
      <section class="xz-metric-card">
        <h3><?php echo xz_visit_stats_admin_escape($card['label']); ?></h3>
<?php if ($card['key'] === 'top_path') { ?>
        <strong class="xz-overview-top-path" title="<?php echo xz_visit_stats_admin_escape($topPath['path']); ?>"><?php echo xz_visit_stats_admin_escape($topPath['path'] !== '' ? $topPath['path'] : '暂无数据'); ?></strong>
        <p><?php echo $topPath['visits'] > 0 ? number_format($topPath['visits']) . ' 次访问' : '有访问记录后将在这里展示。'; ?></p>
<?php } else { ?>
        <strong><?php echo xz_visit_stats_admin_metric_value($value, $card['decimal']); ?><?php echo $card['suffix']; ?></strong>
        <p class="<?php echo $delta['class']; ?>"><?php echo xz_visit_stats_admin_escape($delta['text']); ?></p>
<?php } ?>
      </section>
<?php } ?>
    </div>
    <section class="xz-overview-section">
      <h2>访问趋势</h2>
      <div id="xz-trend-chart" class="xz-chart"></div>
    </section>
    <div class="xz-overview-split">
      <section class="xz-overview-section"><h2>热门页面</h2><table class="tableFull tableBorder tableBorder-thcenter xz-status-overview"><thead><tr><th>页面</th><th>访问量</th></tr></thead><tbody><?php if (empty($overviewData['top_paths'])) { ?><tr><td colspan="2" class="tdCenter">暂无数据，有访问记录后将在这里展示。</td></tr><?php } ?><?php foreach ($overviewData['top_paths'] as $pathRow) { ?><tr><td><span class="xz-cell-clip" title="<?php echo xz_visit_stats_admin_escape($pathRow['path']); ?>"><?php echo xz_visit_stats_admin_escape($pathRow['path']); ?></span></td><td><?php echo (int) $pathRow['visits']; ?></td></tr><?php } ?></tbody></table></section>
      <section class="xz-overview-section"><h2>搜索蜘蛛</h2><table class="tableFull tableBorder tableBorder-thcenter xz-status-overview"><thead><tr><th>蜘蛛</th><th>抓取次数</th></tr></thead><tbody><?php if (empty($overviewData['top_spiders'])) { ?><tr><td colspan="2" class="tdCenter">暂无数据，有访问记录后将在这里展示。</td></tr><?php } ?><?php foreach ($overviewData['top_spiders'] as $spiderRow) { ?><tr><td><?php echo xz_visit_stats_admin_escape($spiderRow['name']); ?></td><td><?php echo (int) $spiderRow['visits']; ?></td></tr><?php } ?></tbody></table></section>
    </div>
    <div class="xz-overview-split">
      <section class="xz-overview-section"><h2>访问来源</h2><table class="tableFull tableBorder tableBorder-thcenter xz-status-overview"><thead><tr><th>来源</th><th>访问量</th></tr></thead><tbody><tr><td>搜索来源</td><td><?php echo (int) $overviewData['sources']['search']; ?></td></tr><tr><td>外部来源</td><td><?php echo (int) $overviewData['sources']['external']; ?></td></tr><tr><td>直接访问</td><td><?php echo (int) $overviewData['sources']['direct']; ?></td></tr></tbody></table></section>
      <section class="xz-overview-section"><h2>异常请求</h2><table class="tableFull tableBorder tableBorder-thcenter xz-status-overview"><thead><tr><th>状态</th><th>数量</th><th>占比</th><th>说明</th></tr></thead><tbody>
<?php foreach ($overviewData['statuses'] as $statusItem) {
    $percent = $current['pv'] > 0 ? ($statusItem['value'] / $current['pv']) * 100 : 0;
    $note = isset($statusItem['not_found']) ? '其中 404：' . (int) $statusItem['not_found'] : '-';
    $statusName = array('2xx' => '正常请求', '3xx' => '跳转请求', '4xx' => '错误请求', '5xx' => '异常请求');
?>
        <tr><td><span class="xz-status xz-status-<?php echo substr($statusItem['label'], 0, 1); ?>xx"><?php echo xz_visit_stats_admin_escape($statusName[$statusItem['label']]); ?></span></td><td><?php echo (int) $statusItem['value']; ?></td><td><?php echo number_format($percent, 1); ?>%</td><td><?php echo xz_visit_stats_admin_escape($note); ?></td></tr>
<?php } ?>
      </tbody></table></section>
    </div>
    <script>window.XZVisitStatsOverview=<?php echo json_encode(array('trend' => $overviewData['trend'], 'hours' => $overviewData['hours'], 'types' => $overviewData['types']), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;</script>
    <script src="assets/overview.js?v=0.1.0-b3"></script>
<?php } elseif ($view === 'spider') {
    $spiderSummary = $spiderData['summary'];
    $spiderMetrics = array(
        array('visits', '蜘蛛访问量', false, ''), array('ips', '蜘蛛独立 IP', false, ''),
        array('paths', '抓取 URL 数', false, ''), array('status_2xx', '2xx', false, ''),
        array('status_3xx', '3xx', false, ''), array('status_4xx', '4xx', false, ''),
        array('status_5xx', '5xx', false, ''), array('not_found', '404', false, ''),
        array('avg_ms', '平均响应时间', true, ' ms'),
    );
?>
    <form class="xz-filter xz-filter-panel xz-spider-filter" method="get" action="main.php">
      <input type="hidden" name="view" value="spider" />
      <div class="xz-filter-basic">
        <div><label for="spider-range">时间范围</label><select id="spider-range" name="range">
          <option value="today"<?php echo xz_visit_stats_admin_selected($spiderFilters['range'], 'today'); ?>>今天</option>
          <option value="yesterday"<?php echo xz_visit_stats_admin_selected($spiderFilters['range'], 'yesterday'); ?>>昨天</option>
          <option value="7d"<?php echo xz_visit_stats_admin_selected($spiderFilters['range'], '7d'); ?>>最近 7 天</option>
          <option value="30d"<?php echo xz_visit_stats_admin_selected($spiderFilters['range'], '30d'); ?>>最近 30 天</option>
          <option value="custom"<?php echo xz_visit_stats_admin_selected($spiderFilters['range'], 'custom'); ?>>自定义</option>
        </select></div>
        <div><label for="spider-name">蜘蛛类型</label><select id="spider-name" name="spider"><option value="all"<?php echo xz_visit_stats_admin_selected($spiderFilters['spider'], 'all'); ?>>全部蜘蛛</option>
<?php foreach (array_merge(xz_visit_stats_spider_names(), array('Other Bot')) as $spiderName) { ?>
        <option value="<?php echo xz_visit_stats_admin_escape($spiderName); ?>"<?php echo xz_visit_stats_admin_selected($spiderFilters['spider'], $spiderName); ?>><?php echo xz_visit_stats_admin_escape($spiderName); ?></option>
<?php } ?>
        </select></div>
        <div class="xz-filter-submit"><button type="submit" class="button">查询</button></div>
        <div class="xz-filter-toggle-wrap"><button type="button" class="button xz-filter-toggle" aria-expanded="false" aria-controls="spider-advanced-filter">高级筛选</button></div>
      </div>
      <div id="spider-advanced-filter" class="xz-advanced-filter">
        <div class="xz-filter-sections"><fieldset class="xz-filter-section"><legend>时间与分页</legend><div class="xz-filter-grid xz-filter-grid-time">
          <div><label for="spider-start">开始时间</label><input id="spider-start" type="datetime-local" name="start" value="<?php echo xz_visit_stats_admin_escape($spiderFilters['start']); ?>" /></div>
          <div><label for="spider-end">结束时间</label><input id="spider-end" type="datetime-local" name="end" value="<?php echo xz_visit_stats_admin_escape($spiderFilters['end']); ?>" /></div>
          <div><label for="spider-page-size">URL 每页</label><select id="spider-page-size" name="page_size"><option value="20"<?php echo xz_visit_stats_admin_selected($spiderFilters['page_size'], 20); ?>>20</option><option value="50"<?php echo xz_visit_stats_admin_selected($spiderFilters['page_size'], 50); ?>>50</option><option value="100"<?php echo xz_visit_stats_admin_selected($spiderFilters['page_size'], 100); ?>>100</option></select></div>
        </div></fieldset></div>
      </div>
    </form>
    <p class="xz-overview-note">当前范围：<?php echo xz_visit_stats_admin_escape($spiderData['range']['label']); ?>。蜘蛛名称来自 User-Agent 识别，尚未进行反向 DNS、正向 DNS 或官方 IP 段真实性验证。</p>
    <div class="xz-metric-grid xz-spider-metrics">
<?php foreach ($spiderMetrics as $metric) { ?>
      <section class="xz-metric-card"><h3><?php echo xz_visit_stats_admin_escape($metric[1]); ?></h3><strong><?php echo xz_visit_stats_admin_metric_value($spiderSummary[$metric[0]], $metric[2]); ?><?php echo $metric[3]; ?></strong></section>
<?php } ?>
    </div>
    <div class="xz-overview-split">
      <section class="xz-overview-section"><h2>蜘蛛类型分布</h2><div id="xz-spider-distribution-chart" class="xz-chart"></div></section>
      <section class="xz-overview-section"><h2>蜘蛛类型明细</h2><table class="tableFull tableBorder tableBorder-thcenter xz-status-overview"><thead><tr><th>蜘蛛</th><th>访问次数</th><th>占比</th></tr></thead><tbody>
<?php if (empty($spiderData['distribution'])) { ?><tr><td colspan="3" class="tdCenter">暂无数据</td></tr><?php } ?>
<?php foreach ($spiderData['distribution'] as $item) { ?><tr><td><a href="<?php echo xz_visit_stats_admin_escape(xz_visit_stats_admin_spider_url($spiderFilters, 1, $item['name'])); ?>"><?php echo xz_visit_stats_admin_escape($item['name']); ?></a></td><td><?php echo (int) $item['visits']; ?></td><td><?php echo number_format($item['percent'], 1); ?>%</td></tr><?php } ?>
      </tbody></table></section>
    </div>
    <section class="xz-overview-section"><h2>蜘蛛抓取趋势</h2><div id="xz-spider-trend-chart" class="xz-chart"></div></section>
    <section class="xz-overview-section"><h2>24 小时抓取时段</h2><div id="xz-spider-hour-chart" class="xz-chart"></div></section>
    <section class="xz-overview-section"><h2>HTTP 状态分析</h2><table class="tableFull tableBorder tableBorder-thcenter xz-status-overview"><thead><tr><th>状态</th><th>数量</th><th>占比</th><th>说明</th></tr></thead><tbody>
<?php foreach (array('2xx' => 'status_2xx', '3xx' => 'status_3xx', '4xx' => 'status_4xx', '5xx' => 'status_5xx') as $statusLabel => $statusKey) { $value = $spiderSummary[$statusKey]; $percent = $spiderSummary['visits'] > 0 ? ($value / $spiderSummary['visits']) * 100 : 0; ?>
      <tr><td><span class="xz-status xz-status-<?php echo substr($statusLabel, 0, 1); ?>xx"><?php echo $statusLabel; ?></span></td><td><?php echo (int) $value; ?></td><td><?php echo number_format($percent, 1); ?>%</td><td><?php echo $statusLabel === '4xx' ? '其中 404：' . (int) $spiderSummary['not_found'] : '-'; ?></td></tr>
<?php } ?>
    </tbody></table></section>
    <section class="xz-overview-section"><h2>热门抓取 URL</h2><div class="xz-table-wrap xz-spider-url-wrap"><table class="tableFull tableBorder tableBorder-thcenter table_hover xz-spider-url-table"><thead><tr><th>URL</th><th>抓取次数</th><th>蜘蛛类型</th><th>独立 IP</th><th>最近抓取</th><th>2xx</th><th>3xx</th><th>4xx</th><th>5xx</th><th>404</th><th>平均响应</th></tr></thead><tbody>
<?php if (empty($spiderData['urls'])) { ?><tr><td colspan="11" class="tdCenter">暂无数据</td></tr><?php } ?>
<?php foreach ($spiderData['urls'] as $urlRow) { ?><tr><td><span class="xz-cell-clip" title="<?php echo xz_visit_stats_admin_escape($urlRow['path']); ?>"><?php echo xz_visit_stats_admin_escape($urlRow['path']); ?></span></td><td><?php echo $urlRow['visits']; ?></td><td><?php echo xz_visit_stats_admin_escape($urlRow['spiders']); ?></td><td><?php echo $urlRow['ips']; ?></td><td><?php echo $urlRow['last_visit'] > 0 ? xz_visit_stats_admin_escape(date('Y-m-d H:i:s', $urlRow['last_visit'])) : '-'; ?></td><td><?php echo $urlRow['status_2xx']; ?></td><td><?php echo $urlRow['status_3xx']; ?></td><td><?php echo $urlRow['status_4xx']; ?></td><td><?php echo $urlRow['status_5xx']; ?></td><td><?php echo $urlRow['not_found']; ?></td><td><?php echo number_format($urlRow['avg_ms'], 1); ?> ms</td></tr><?php } ?>
    </tbody></table></div>
<?php $spiderPage = $spiderData['page']; $spiderPageAll = $spiderData['page_all']; ?>
      <nav class="xz-pagination" aria-label="蜘蛛 URL 分页"><span class="xz-page-state">共 <?php echo $spiderData['url_count']; ?> 个 URL，当前 <?php echo $spiderPage; ?> / <?php echo $spiderPageAll; ?> 页</span><?php if ($spiderPage > 1) { ?><a href="<?php echo xz_visit_stats_admin_escape(xz_visit_stats_admin_spider_url($spiderFilters, $spiderPage - 1)); ?>">上一页</a><?php } ?><?php if ($spiderPage < $spiderPageAll) { ?><a href="<?php echo xz_visit_stats_admin_escape(xz_visit_stats_admin_spider_url($spiderFilters, $spiderPage + 1)); ?>">下一页</a><?php } ?></nav>
    </section>
    <section class="xz-overview-section"><h2>SEO 蜘蛛报告（规则型）</h2><ul class="xz-spider-report"><?php foreach ($spiderData['report'] as $reportItem) { ?><li><?php echo xz_visit_stats_admin_escape($reportItem); ?></li><?php } ?></ul></section>
<?php if ($spiderFilters['spider'] !== 'all') { ?><section class="xz-overview-section"><h2><?php echo xz_visit_stats_admin_escape($spiderFilters['spider']); ?> 详情</h2><p>访问 <?php echo $spiderSummary['visits']; ?> 次，独立 IP <?php echo $spiderSummary['ips']; ?> 个，抓取 URL <?php echo $spiderSummary['paths']; ?> 个，最近抓取 <?php echo $spiderSummary['last_visit'] > 0 ? xz_visit_stats_admin_escape(date('Y-m-d H:i:s', $spiderSummary['last_visit'])) : '-'; ?>。</p></section><?php } ?>
    <script>window.XZVisitStatsSpider=<?php echo json_encode(array('trend' => $spiderData['trend'], 'hours' => $spiderData['hours'], 'distribution' => $spiderData['distribution']), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;</script><script src="assets/spider.js?v=0.1.0-b4"></script>
<?php } elseif ($view === 'seo') {
    $seoSummary = $seoData['summary'];
    $seoMetrics = array(
        array('visits', '蜘蛛抓取', false, ''), array('ips', '独立访客', false, ''),
        array('paths', '抓取页面数量', false, ''), array('success_rate', '抓取成功率', true, '%'),
        array('not_found', '404 数量', false, ''), array('avg_ms', '平均耗时', true, ' ms'),
    );
?>
    <form class="xz-filter xz-filter-panel xz-seo-filter" method="get" action="main.php"><input type="hidden" name="view" value="seo" />
      <div class="xz-filter-basic"><div><label for="seo-range">时间范围</label><select id="seo-range" name="range"><option value="today"<?php echo xz_visit_stats_admin_selected($seoFilters['range'], 'today'); ?>>今天</option><option value="7d"<?php echo xz_visit_stats_admin_selected($seoFilters['range'], '7d'); ?>>最近 7 天</option><option value="30d"<?php echo xz_visit_stats_admin_selected($seoFilters['range'], '30d'); ?>>最近 30 天</option></select></div><div class="xz-filter-submit"><button type="submit" class="button">查询</button></div><div class="xz-filter-toggle-wrap"><button type="button" class="button xz-filter-toggle" aria-expanded="false" aria-controls="seo-advanced-filter">高级筛选</button></div></div>
      <div id="seo-advanced-filter" class="xz-advanced-filter"><div class="xz-filter-sections"><fieldset class="xz-filter-section"><legend>分页</legend><div class="xz-filter-grid"><div><label for="seo-page-size">排行每页</label><select id="seo-page-size" name="page_size"><option value="20"<?php echo xz_visit_stats_admin_selected($seoFilters['page_size'], 20); ?>>20</option><option value="50"<?php echo xz_visit_stats_admin_selected($seoFilters['page_size'], 50); ?>>50</option><option value="100"<?php echo xz_visit_stats_admin_selected($seoFilters['page_size'], 100); ?>>100</option></select></div></div></fieldset></div></div>
    </form>
    <p class="xz-overview-note">当前范围：<?php echo xz_visit_stats_admin_escape($seoData['range']['label']); ?>。统计基于已识别的蜘蛛 User-Agent，不包含反向 DNS 或官方 IP 段真实性验证。</p>
    <div class="xz-metric-grid xz-seo-metrics"><?php foreach ($seoMetrics as $metric) { ?><section class="xz-metric-card"><h3><?php echo xz_visit_stats_admin_escape($metric[1]); ?></h3><strong><?php echo xz_visit_stats_admin_metric_value($seoSummary[$metric[0]], $metric[2]); ?><?php echo $metric[3]; ?></strong></section><?php } ?></div>
    <section class="xz-overview-section"><h2>蜘蛛分类</h2><div class="xz-table-wrap xz-seo-table-wrap"><table class="tableFull tableBorder tableBorder-thcenter table_hover xz-seo-engine-table"><thead><tr><th>蜘蛛</th><th>访问次数</th><th>占比</th><th>独立 IP</th><th>成功状态（2xx）</th><th>成功率</th><th>404 数量</th></tr></thead><tbody>
<?php if ($seoSummary['visits'] === 0) { ?><tr><td colspan="7" class="tdCenter">当前范围暂无蜘蛛数据</td></tr><?php } else { ?>
<?php foreach ($seoData['engines'] as $engineRow) { ?><tr><td><?php echo xz_visit_stats_admin_escape($engineRow['name']); ?></td><td><?php echo $engineRow['visits']; ?></td><td><?php echo number_format($engineRow['percent'], 1); ?>%</td><td><?php echo $engineRow['ips']; ?></td><td><?php echo $engineRow['status_2xx']; ?></td><td><?php echo number_format($engineRow['success_rate'], 1); ?>%</td><td><?php echo $engineRow['not_found']; ?></td></tr><?php } ?>
<?php } ?>
    </tbody></table></div></section>
    <div class="xz-overview-split"><section class="xz-overview-section"><h2>蜘蛛访问趋势</h2><div id="xz-seo-trend-chart" class="xz-chart xz-seo-chart"></div></section><section class="xz-overview-section"><h2>各蜘蛛占比</h2><div id="xz-seo-distribution-chart" class="xz-chart xz-seo-chart"></div></section></div>
    <section class="xz-overview-section"><h2>24 小时抓取分布</h2><div id="xz-seo-hour-chart" class="xz-chart xz-seo-chart"></div></section>
    <section class="xz-overview-section"><h2>抓取成功 URL 排行</h2><div class="xz-table-wrap xz-seo-table-wrap"><table class="tableFull tableBorder tableBorder-thcenter table_hover xz-seo-effect-table"><thead><tr><th>URL</th><th>成功次数</th><th>抓取次数</th><th>状态码分布</th><th>最近抓取时间</th></tr></thead><tbody>
<?php if (empty($seoData['success_urls'])) { ?><tr><td colspan="5" class="tdCenter">当前范围暂无抓取成功页面</td></tr><?php } ?>
<?php foreach ($seoData['success_urls'] as $seoUrl) { ?><tr><td><span class="xz-cell-clip" title="<?php echo xz_visit_stats_admin_escape($seoUrl['path']); ?>"><?php echo xz_visit_stats_admin_escape($seoUrl['path']); ?></span></td><td><?php echo $seoUrl['status_2xx']; ?></td><td><?php echo $seoUrl['visits']; ?></td><td><?php echo xz_visit_stats_admin_escape(xz_visit_stats_admin_status_distribution($seoUrl)); ?></td><td><?php echo $seoUrl['last_visit'] > 0 ? xz_visit_stats_admin_escape(date('Y-m-d H:i:s', $seoUrl['last_visit'])) : '-'; ?></td></tr><?php } ?>
    </tbody></table></div></section>
    <section class="xz-overview-section"><h2>404 URL 排行</h2><div class="xz-table-wrap xz-seo-table-wrap"><table class="tableFull tableBorder tableBorder-thcenter table_hover xz-seo-effect-table"><thead><tr><th>URL</th><th>404 次数</th><th>抓取次数</th><th>状态码分布</th><th>最近抓取时间</th></tr></thead><tbody>
<?php if (empty($seoData['not_found_urls'])) { ?><tr><td colspan="5" class="tdCenter">当前范围暂无蜘蛛 404 页面</td></tr><?php } ?>
<?php foreach ($seoData['not_found_urls'] as $seoUrl) { ?><tr><td><span class="xz-cell-clip" title="<?php echo xz_visit_stats_admin_escape($seoUrl['path']); ?>"><?php echo xz_visit_stats_admin_escape($seoUrl['path']); ?></span></td><td><?php echo $seoUrl['not_found']; ?></td><td><?php echo $seoUrl['visits']; ?></td><td><?php echo xz_visit_stats_admin_escape(xz_visit_stats_admin_status_distribution($seoUrl)); ?></td><td><?php echo $seoUrl['last_visit'] > 0 ? xz_visit_stats_admin_escape(date('Y-m-d H:i:s', $seoUrl['last_visit'])) : '-'; ?></td></tr><?php } ?>
    </tbody></table></div></section>
    <section class="xz-overview-section"><h2>全部抓取页面排行</h2><div class="xz-table-wrap xz-seo-table-wrap"><table class="tableFull tableBorder tableBorder-thcenter table_hover xz-seo-url-table"><thead><tr><th>URL</th><th>抓取次数</th><th>蜘蛛类型</th><th>状态码分布</th><th>最近抓取时间</th></tr></thead><tbody>
<?php if (empty($seoData['urls'])) { ?><tr><td colspan="5" class="tdCenter">当前范围暂无蜘蛛抓取页面</td></tr><?php } ?>
<?php foreach ($seoData['urls'] as $seoUrl) { ?><tr><td><span class="xz-cell-clip" title="<?php echo xz_visit_stats_admin_escape($seoUrl['path']); ?>"><?php echo xz_visit_stats_admin_escape($seoUrl['path']); ?></span></td><td><?php echo $seoUrl['visits']; ?></td><td><?php echo xz_visit_stats_admin_escape($seoUrl['bot_names'] !== '' ? $seoUrl['bot_names'] : '-'); ?></td><td><?php echo xz_visit_stats_admin_escape(xz_visit_stats_admin_status_distribution($seoUrl)); ?></td><td><?php echo $seoUrl['last_visit'] > 0 ? xz_visit_stats_admin_escape(date('Y-m-d H:i:s', $seoUrl['last_visit'])) : '-'; ?></td></tr><?php } ?>
    </tbody></table></div><nav class="xz-pagination" aria-label="全部抓取页面排行分页"><span class="xz-page-state">共 <?php echo $seoData['url_count']; ?> 个页面，当前 <?php echo $seoData['page']; ?> / <?php echo $seoData['page_all']; ?> 页</span><?php if ($seoData['page'] > 1) { ?><a href="<?php echo xz_visit_stats_admin_escape(xz_visit_stats_admin_seo_url($seoFilters, $seoData['page'] - 1)); ?>">上一页</a><?php } ?><?php if ($seoData['page'] < $seoData['page_all']) { ?><a href="<?php echo xz_visit_stats_admin_escape(xz_visit_stats_admin_seo_url($seoFilters, $seoData['page'] + 1)); ?>">下一页</a><?php } ?></nav></section>
    <section class="xz-overview-section"><h2>HTTP 状态分析</h2><div class="xz-table-wrap"><table class="tableFull tableBorder tableBorder-thcenter"><thead><tr><th>状态码</th><th>数量</th><th>比例</th></tr></thead><tbody><?php foreach ($seoData['statuses'] as $statusRow) { ?><tr><td><?php echo $statusRow['status_code']; ?></td><td><?php echo $statusRow['visits']; ?></td><td><?php echo number_format($statusRow['percent'], 1); ?>%</td></tr><?php } ?></tbody></table></div></section>
    <div class="xz-overview-split"><section class="xz-overview-section"><h2>最近抓取页面</h2><div class="xz-table-wrap"><table class="tableFull tableBorder tableBorder-thcenter"><thead><tr><th>URL</th><th>蜘蛛名称</th><th>访问时间</th><th>状态码</th></tr></thead><tbody><?php if (empty($seoData['recent_pages'])) { ?><tr><td colspan="4" class="tdCenter">暂无数据，有访问记录后将在这里展示。</td></tr><?php } ?><?php foreach ($seoData['recent_pages'] as $recentRow) { ?><tr><td><?php echo xz_visit_stats_admin_escape($recentRow['path']); ?></td><td><?php echo xz_visit_stats_admin_escape($recentRow['bot_name']); ?></td><td><?php echo xz_visit_stats_admin_escape(date('Y-m-d H:i:s', (int) $recentRow['visited_at'])); ?></td><td><?php echo (int) $recentRow['status_code']; ?></td></tr><?php } ?></tbody></table></div></section><section class="xz-overview-section"><h2>未抓取页面分析</h2><div class="xz-table-wrap"><table class="tableFull tableBorder tableBorder-thcenter"><thead><tr><th>URL</th><th>最后抓取时间</th><th>未抓取天数</th></tr></thead><tbody><?php if (empty($seoData['uncrawled_pages'])) { ?><tr><td colspan="3" class="tdCenter">暂无数据，有访问记录后将在这里展示。</td></tr><?php } ?><?php foreach ($seoData['uncrawled_pages'] as $uncrawledRow) { ?><tr><td><?php echo xz_visit_stats_admin_escape($uncrawledRow['path']); ?></td><td><?php echo $uncrawledRow['last_crawl'] ? xz_visit_stats_admin_escape(date('Y-m-d H:i:s', $uncrawledRow['last_crawl'])) : '暂无抓取记录'; ?></td><td><?php echo $uncrawledRow['uncrawled_days']; ?></td></tr><?php } ?></tbody></table></div></section></div>
    <section class="xz-overview-section"><h2>搜索进入页面分析</h2><div class="xz-table-wrap"><table class="tableFull tableBorder tableBorder-thcenter"><thead><tr><th>页面 URL</th><th>搜索来源</th><th>访问次数</th><th>最近访问时间</th></tr></thead><tbody><?php if (empty($seoData['search_pages'])) { ?><tr><td colspan="4" class="tdCenter">暂无数据，有访问记录后将在这里展示。</td></tr><?php } ?><?php foreach ($seoData['search_pages'] as $searchPage) { ?><tr><td><?php echo xz_visit_stats_admin_escape($searchPage['path']); ?></td><td><?php echo xz_visit_stats_admin_escape($searchPage['source']); ?></td><td><?php echo (int) $searchPage['visits']; ?></td><td><?php echo xz_visit_stats_admin_escape(date('Y-m-d H:i:s', (int) $searchPage['last_visit'])); ?></td></tr><?php } ?></tbody></table></div></section>
    <section class="xz-overview-section"><h2>蜘蛛效率分析</h2><div class="xz-table-wrap"><table class="tableFull tableBorder tableBorder-thcenter"><thead><tr><th>蜘蛛</th><th>平均抓取间隔</th><th>最近抓取时间</th><th>抓取次数</th></tr></thead><tbody><?php if (empty($seoData['efficiency'])) { ?><tr><td colspan="4" class="tdCenter">暂无数据，有访问记录后将在这里展示。</td></tr><?php } ?><?php foreach ($seoData['efficiency'] as $efficiencyRow) { ?><tr><td><?php echo xz_visit_stats_admin_escape($efficiencyRow['name']); ?></td><td><?php echo $efficiencyRow['avg_interval'] > 0 ? number_format($efficiencyRow['avg_interval'] / 60, 1) . ' 分钟' : '-'; ?></td><td><?php echo $efficiencyRow['last_visit'] ? xz_visit_stats_admin_escape(date('Y-m-d H:i:s', $efficiencyRow['last_visit'])) : '-'; ?></td><td><?php echo $efficiencyRow['visits']; ?></td></tr><?php } ?></tbody></table></div></section>
    <section class="xz-overview-section"><h2>来源概览</h2><div class="xz-metric-grid xz-seo-source-metrics"><section class="xz-metric-card"><h3>搜索来源数量</h3><strong><?php echo number_format($seoData['source_summary']['search']); ?></strong></section><section class="xz-metric-card"><h3>外部来源数量</h3><strong><?php echo number_format($seoData['source_summary']['external']); ?></strong></section><section class="xz-metric-card"><h3>直接访问数量</h3><strong><?php echo number_format($seoData['source_summary']['direct']); ?></strong></section></div></section>
    <section class="xz-overview-section"><h2>来源域名排行 TOP100</h2><div class="xz-table-wrap xz-seo-table-wrap"><table class="tableFull tableBorder tableBorder-thcenter table_hover xz-seo-source-table"><thead><tr><th>来源分类</th><th>来源域名</th><th>访问量</th><th>目标页面</th><th>最近访问</th></tr></thead><tbody>
<?php if (empty($seoData['source_domains'])) { ?><tr><td colspan="5" class="tdCenter">暂无数据，有访问记录后将在这里展示。</td></tr><?php } ?>
<?php foreach ($seoData['source_domains'] as $sourceDomain) { ?><tr><td><?php echo xz_visit_stats_admin_escape($sourceDomain['type']); ?></td><td><?php echo xz_visit_stats_admin_escape($sourceDomain['domain'] !== '' ? $sourceDomain['domain'] : $sourceDomain['name']); ?></td><td><?php echo number_format($sourceDomain['visits']); ?></td><td><?php echo number_format($sourceDomain['paths']); ?></td><td><?php echo $sourceDomain['last_visit'] > 0 ? xz_visit_stats_admin_escape(date('Y-m-d H:i:s', $sourceDomain['last_visit'])) : '-'; ?></td></tr><?php } ?>
    </tbody></table></div></section>
    <section class="xz-overview-section"><h2>来源链接排行 TOP100</h2><div class="xz-table-wrap xz-seo-table-wrap"><table class="tableFull tableBorder tableBorder-thcenter table_hover xz-seo-link-table"><thead><tr><th>来路域名</th><th>完整来路 URL</th><th>访问量</th><th>目标页面</th><th>最近访问</th></tr></thead><tbody>
<?php if (empty($seoData['source_links'])) { ?><tr><td colspan="5" class="tdCenter">暂无数据，有访问记录后将在这里展示。</td></tr><?php } ?>
<?php foreach ($seoData['source_links'] as $sourceLink) { ?><tr><td><?php echo xz_visit_stats_admin_escape($sourceLink['domain'] !== '' ? $sourceLink['domain'] : $sourceLink['name']); ?></td><td><span class="xz-cell-clip" title="<?php echo xz_visit_stats_admin_escape($sourceLink['referer']); ?>"><?php echo xz_visit_stats_admin_escape($sourceLink['referer'] !== '' ? $sourceLink['referer'] : '直接访问'); ?></span></td><td><?php echo number_format($sourceLink['visits']); ?></td><td><?php echo number_format($sourceLink['paths']); ?></td><td><?php echo $sourceLink['last_visit'] > 0 ? xz_visit_stats_admin_escape(date('Y-m-d H:i:s', $sourceLink['last_visit'])) : '-'; ?></td></tr><?php } ?>
    </tbody></table></div></section>
    <section class="xz-overview-section"><h2>目标页面分析</h2><div class="xz-table-wrap xz-seo-table-wrap"><table class="tableFull tableBorder tableBorder-thcenter table_hover xz-seo-link-table"><thead><tr><th>目标页面</th><th>访问量</th><th>来源域名</th><th>搜索来源</th><th>外部来源</th><th>直接访问</th><th>最近访问</th></tr></thead><tbody>
<?php if (empty($seoData['source_targets'])) { ?><tr><td colspan="7" class="tdCenter">暂无数据，有访问记录后将在这里展示。</td></tr><?php } ?>
<?php foreach ($seoData['source_targets'] as $sourceTarget) { ?><tr><td><span class="xz-cell-clip" title="<?php echo xz_visit_stats_admin_escape($sourceTarget['path']); ?>"><?php echo xz_visit_stats_admin_escape($sourceTarget['path']); ?></span></td><td><?php echo number_format($sourceTarget['visits']); ?></td><td><?php echo number_format($sourceTarget['domains']); ?></td><td><?php echo number_format($sourceTarget['search']); ?></td><td><?php echo number_format($sourceTarget['external']); ?></td><td><?php echo number_format($sourceTarget['direct']); ?></td><td><?php echo $sourceTarget['last_visit'] > 0 ? xz_visit_stats_admin_escape(date('Y-m-d H:i:s', $sourceTarget['last_visit'])) : '-'; ?></td></tr><?php } ?>
    </tbody></table></div></section>
    <section class="xz-overview-section"><h2>来路链接明细</h2><div class="xz-table-wrap xz-seo-table-wrap"><table class="tableFull tableBorder tableBorder-thcenter table_hover xz-seo-record-table"><thead><tr><th>日志 ID</th><th>来路域名</th><th>完整来路 URL</th><th>访问 IP</th><th>目标页面</th><th>访问时间</th></tr></thead><tbody>
<?php if (empty($seoData['source_records'])) { ?><tr><td colspan="6" class="tdCenter">暂无数据，有访问记录后将在这里展示。</td></tr><?php } ?>
<?php foreach ($seoData['source_records'] as $sourceRecord) { $targetUrl = $sourceRecord['url'] !== '' ? $sourceRecord['url'] : $sourceRecord['path']; ?><tr><td><?php echo $sourceRecord['id']; ?></td><td><?php echo xz_visit_stats_admin_escape($sourceRecord['domain'] !== '' ? $sourceRecord['domain'] : $sourceRecord['name']); ?></td><td><span class="xz-cell-clip" title="<?php echo xz_visit_stats_admin_escape($sourceRecord['referer']); ?>"><?php echo xz_visit_stats_admin_escape($sourceRecord['referer'] !== '' ? $sourceRecord['referer'] : '直接访问'); ?></span></td><td><?php echo xz_visit_stats_admin_escape($sourceRecord['ip']); ?></td><td><span class="xz-cell-clip" title="<?php echo xz_visit_stats_admin_escape($targetUrl); ?>"><?php echo xz_visit_stats_admin_escape($targetUrl); ?></span></td><td><?php echo $sourceRecord['visited_at'] > 0 ? xz_visit_stats_admin_escape(date('Y-m-d H:i:s', $sourceRecord['visited_at'])) : '-'; ?></td></tr><?php } ?>
    </tbody></table></div></section>
    <section class="xz-overview-section"><h2>蜘蛛异常分析</h2><table class="tableFull tableBorder tableBorder-thcenter xz-status-overview"><thead><tr><th>异常项目</th><th>当前值</th><th>判定阈值</th></tr></thead><tbody><?php if (empty($seoData['anomalies'])) { ?><tr><td colspan="3" class="tdCenter">当前范围未发现命中规则的蜘蛛异常</td></tr><?php } ?><?php foreach ($seoData['anomalies'] as $anomaly) { ?><tr><td><?php echo xz_visit_stats_admin_escape($anomaly['item']); ?></td><td><?php echo xz_visit_stats_admin_escape($anomaly['value']); ?></td><td><?php echo xz_visit_stats_admin_escape($anomaly['threshold']); ?></td></tr><?php } ?></tbody></table></section>
    <script>window.XZVisitStatsSeo=<?php echo json_encode(array('trend' => $seoData['trend'], 'engines' => $seoData['engines'], 'hours' => $seoData['hours']), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;</script><script src="assets/seo.js?v=0.9.0"></script>
<?php } elseif ($view === 'source') {
    $sourceSummary = $sourceData['summary'];
    $sourceMetrics = array(
        array('visits', '访问来源次数', false, ''), array('uv', '来源 UV', false, ''),
        array('domains', '来源域名数量', false, ''), array('direct', '直接访问', false, ''),
        array('search', '搜索来源', false, ''), array('external', '外链来源', false, ''),
        array('avg_ms', '平均响应时间', true, ' ms'),
    );
?>
    <form class="xz-filter xz-filter-panel xz-source-filter" method="get" action="main.php"><input type="hidden" name="view" value="source" />
      <div class="xz-filter-basic"><div><label for="source-range">时间范围</label><select id="source-range" name="range"><option value="today"<?php echo xz_visit_stats_admin_selected($sourceFilters['range'], 'today'); ?>>今天</option><option value="yesterday"<?php echo xz_visit_stats_admin_selected($sourceFilters['range'], 'yesterday'); ?>>昨天</option><option value="7d"<?php echo xz_visit_stats_admin_selected($sourceFilters['range'], '7d'); ?>>最近 7 天</option><option value="30d"<?php echo xz_visit_stats_admin_selected($sourceFilters['range'], '30d'); ?>>最近 30 天</option><option value="custom"<?php echo xz_visit_stats_admin_selected($sourceFilters['range'], 'custom'); ?>>自定义</option></select></div>
      <div><label for="source-type">来源类型</label><select id="source-type" name="source_type"><option value="all"<?php echo xz_visit_stats_admin_selected($sourceFilters['source_type'], 'all'); ?>>全部</option><?php foreach (xz_visit_stats_source_type_labels() as $typeKey => $typeLabel) { ?><option value="<?php echo $typeKey; ?>"<?php echo xz_visit_stats_admin_selected($sourceFilters['source_type'], $typeKey); ?>><?php echo xz_visit_stats_admin_escape($typeLabel); ?></option><?php } ?></select></div>
      <div class="xz-filter-submit"><button type="submit" class="button">查询</button></div><div class="xz-filter-toggle-wrap"><button type="button" class="button xz-filter-toggle" aria-expanded="false" aria-controls="source-advanced-filter">高级筛选</button></div></div>
      <div id="source-advanced-filter" class="xz-advanced-filter"><div class="xz-filter-sections"><fieldset class="xz-filter-section"><legend>时间、来源与分页</legend><div class="xz-filter-grid"><div><label for="source-start">开始时间</label><input id="source-start" type="datetime-local" name="start" value="<?php echo xz_visit_stats_admin_escape($sourceFilters['start']); ?>" /></div><div><label for="source-end">结束时间</label><input id="source-end" type="datetime-local" name="end" value="<?php echo xz_visit_stats_admin_escape($sourceFilters['end']); ?>" /></div><div><label for="source-domain">来源域名</label><input id="source-domain" name="domain" value="<?php echo xz_visit_stats_admin_escape($sourceFilters['domain']); ?>" placeholder="例如 partner.example" /></div><div><label for="source-referer">来路 URL</label><input id="source-referer" name="referer" value="<?php echo xz_visit_stats_admin_escape($sourceFilters['referer']); ?>" placeholder="关键词" /></div><div><label for="source-ip">访问 IP</label><input id="source-ip" name="ip" value="<?php echo xz_visit_stats_admin_escape($sourceFilters['ip']); ?>" placeholder="IPv4 / IPv6" /></div><div><label for="source-page-size">每页</label><select id="source-page-size" name="page_size"><option value="20"<?php echo xz_visit_stats_admin_selected($sourceFilters['page_size'], 20); ?>>20</option><option value="50"<?php echo xz_visit_stats_admin_selected($sourceFilters['page_size'], 50); ?>>50</option><option value="100"<?php echo xz_visit_stats_admin_selected($sourceFilters['page_size'], 100); ?>>100</option></select></div></div></fieldset></div></div>
    </form>
    <p class="xz-overview-note">当前范围：<?php echo xz_visit_stats_admin_escape($sourceData['range']['label']); ?>。来源分析仅统计普通访客，来源域名由 Referer URL 的 host 提取。</p>
    <div class="xz-metric-grid xz-source-metrics"><?php foreach ($sourceMetrics as $metric) { ?><section class="xz-metric-card"><h3><?php echo xz_visit_stats_admin_escape($metric[1]); ?></h3><strong><?php echo xz_visit_stats_admin_metric_value($sourceSummary[$metric[0]], $metric[2]); ?><?php echo $metric[3]; ?></strong></section><?php } ?></div>
    <div class="xz-overview-split"><section class="xz-overview-section"><h2>来源类型分析</h2><div id="xz-source-type-chart" class="xz-chart xz-source-chart"></div></section><section class="xz-overview-section"><h2>搜索引擎来源</h2><div id="xz-source-search-chart" class="xz-chart xz-source-chart"></div></section></div>
    <section class="xz-overview-section"><h2>来源趋势</h2><div id="xz-source-trend-chart" class="xz-chart xz-source-chart"></div></section>
    <section class="xz-overview-section"><h2>外链来源排行</h2><div class="xz-table-wrap xz-source-url-wrap"><table class="tableFull tableBorder tableBorder-thcenter table_hover xz-source-table"><thead><tr><th>来源域名</th><th>类型</th><th>访问次数</th><th>UV</th><th>最近访问</th><th>平均响应</th></tr></thead><tbody>
<?php if (empty($sourceData['domains'])) { ?><tr><td colspan="6" class="tdCenter">暂无数据</td></tr><?php } ?>
<?php foreach ($sourceData['domains'] as $domainRow) { ?><tr><td><a href="<?php echo xz_visit_stats_admin_escape(xz_visit_stats_admin_source_url($sourceFilters, 1, $domainRow['domain'])); ?>"><?php echo xz_visit_stats_admin_escape($domainRow['domain']); ?></a></td><td><?php echo xz_visit_stats_admin_escape($domainRow['type']); ?></td><td><?php echo $domainRow['visits']; ?></td><td><?php echo $domainRow['uv']; ?></td><td><?php echo $domainRow['last_visit'] > 0 ? xz_visit_stats_admin_escape(date('Y-m-d H:i:s', $domainRow['last_visit'])) : '-'; ?></td><td><?php echo number_format($domainRow['avg_ms'], 1); ?> ms</td></tr><?php } ?>
    </tbody></table></div><nav class="xz-pagination" aria-label="来源排行分页"><span class="xz-page-state">共 <?php echo $sourceData['domain_count']; ?> 个外链域名，当前 <?php echo $sourceData['page']; ?> / <?php echo $sourceData['page_all']; ?> 页</span><?php if ($sourceData['page'] > 1) { ?><a href="<?php echo xz_visit_stats_admin_escape(xz_visit_stats_admin_source_url($sourceFilters, $sourceData['page'] - 1)); ?>">上一页</a><?php } ?><?php if ($sourceData['page'] < $sourceData['page_all']) { ?><a href="<?php echo xz_visit_stats_admin_escape(xz_visit_stats_admin_source_url($sourceFilters, $sourceData['page'] + 1)); ?>">下一页</a><?php } ?></nav></section>
    <section class="xz-overview-section"><h2>来源链接排行 TOP100</h2><div class="xz-table-wrap"><table class="tableFull tableBorder tableBorder-thcenter table_hover xz-source-link-table"><thead><tr><th>来源（悬停查看详情）</th><th>访问次数</th><th>最近访问</th></tr></thead><tbody><?php if (empty($sourceData['links'])) { ?><tr><td colspan="3" class="tdCenter">暂无数据，有访问记录后将在这里展示。</td></tr><?php } ?><?php foreach ($sourceData['links'] as $linkRow) { ?><tr><td><?php echo xz_visit_stats_source_referer_cell($linkRow['referer']); ?></td><td><?php echo $linkRow['visits']; ?></td><td><?php echo $linkRow['last_visit'] ? xz_visit_stats_admin_escape(date('Y-m-d H:i:s', $linkRow['last_visit'])) : '-'; ?></td></tr><?php } ?></tbody></table></div></section>
    <section class="xz-overview-section"><h2>来路链接明细</h2><div class="xz-table-wrap"><table class="tableFull tableBorder tableBorder-thcenter table_hover xz-source-record-table"><thead><tr><th>日志 ID</th><th>来路域名</th><th>来源（悬停查看详情）</th><th>访问 IP</th><th>目标页面</th><th>访问时间</th></tr></thead><tbody><?php if (empty($sourceData['records'])) { ?><tr><td colspan="6" class="tdCenter">暂无数据，有访问记录后将在这里展示。</td></tr><?php } ?><?php foreach ($sourceData['records'] as $recordRow) { ?><tr><td><?php echo $recordRow['id']; ?></td><td><?php echo xz_visit_stats_admin_escape($recordRow['domain']); ?></td><td><?php echo xz_visit_stats_source_referer_cell($recordRow['referer']); ?></td><td><?php echo xz_visit_stats_admin_escape($recordRow['ip']); ?></td><td><span class="xz-cell-clip" title="<?php echo xz_visit_stats_admin_escape($recordRow['url']); ?>"><?php echo xz_visit_stats_admin_escape($recordRow['path']); ?></span></td><td><?php echo $recordRow['visited_at'] ? xz_visit_stats_admin_escape(date('Y-m-d H:i:s', $recordRow['visited_at'])) : '-'; ?></td></tr><?php } ?></tbody></table></div><nav class="xz-pagination" aria-label="来路链接明细分页"><span class="xz-page-state">共 <?php echo $sourceData['record_count']; ?> 条记录，当前 <?php echo $sourceData['page']; ?> / <?php echo $sourceData['page_all']; ?> 页</span></nav></section>
<?php if ($sourceFilters['domain'] !== '') { ?><section class="xz-overview-section"><h2>来源详情：<?php echo xz_visit_stats_admin_escape($sourceFilters['domain']); ?></h2><p>当前筛选下访问 <?php echo $sourceSummary['visits']; ?> 次、UV <?php echo $sourceSummary['uv']; ?>、平均响应 <?php echo number_format($sourceSummary['avg_ms'], 1); ?> ms。</p></section><?php } ?>
    <script>window.XZVisitStatsSource=<?php echo json_encode(array('types' => $sourceData['types'], 'searches' => $sourceData['searches'], 'trend' => $sourceData['trend']), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;</script><script src="assets/source.js?v=0.1.0-b5"></script>
<?php } elseif ($view === 'ip') {
    $ipSummary = $ipData['summary'];
    $ipMetrics = array(
        array('ips', '总访问 IP 数量', false, ''),
        array('visits', '总访问次数', false, ''),
        array('avg_per_ip', '平均每 IP 访问次数', true, ''),
        array('high_frequency_ips', '高频访问 IP 数量', false, ''),
        array('error_ips', '404 异常 IP 数量', false, ''),
    );
?>
    <form class="xz-filter xz-filter-panel xz-ip-filter" method="get" action="main.php"><input type="hidden" name="view" value="ip" />
      <div class="xz-filter-basic"><div><label for="ip-range">时间范围</label><select id="ip-range" name="range"><option value="today"<?php echo xz_visit_stats_admin_selected($ipFilters['range'], 'today'); ?>>今天</option><option value="yesterday"<?php echo xz_visit_stats_admin_selected($ipFilters['range'], 'yesterday'); ?>>昨天</option><option value="7d"<?php echo xz_visit_stats_admin_selected($ipFilters['range'], '7d'); ?>>最近 7 天</option><option value="30d"<?php echo xz_visit_stats_admin_selected($ipFilters['range'], '30d'); ?>>最近 30 天</option><option value="custom"<?php echo xz_visit_stats_admin_selected($ipFilters['range'], 'custom'); ?>>自定义</option></select></div><div class="xz-filter-submit"><button type="submit" class="button">查询</button></div><div class="xz-filter-toggle-wrap"><button type="button" class="button xz-filter-toggle" aria-expanded="false" aria-controls="ip-advanced-filter">高级筛选</button></div></div>
      <div id="ip-advanced-filter" class="xz-advanced-filter"><div class="xz-filter-sections"><fieldset class="xz-filter-section"><legend>时间与 IP</legend><div class="xz-filter-grid xz-filter-grid-time"><div><label for="ip-start">开始时间</label><input id="ip-start" type="datetime-local" name="start" value="<?php echo xz_visit_stats_admin_escape($ipFilters['start']); ?>" /></div><div><label for="ip-end">结束时间</label><input id="ip-end" type="datetime-local" name="end" value="<?php echo xz_visit_stats_admin_escape($ipFilters['end']); ?>" /></div><div><label for="ip-address">IP 地址</label><input id="ip-address" name="ip" maxlength="45" value="<?php echo xz_visit_stats_admin_escape($ipFilters['ip']); ?>" placeholder="IPv4 或 IPv6" /></div><div><label for="ip-page-size">每页</label><select id="ip-page-size" name="page_size"><option value="20"<?php echo xz_visit_stats_admin_selected($ipFilters['page_size'], 20); ?>>20</option><option value="50"<?php echo xz_visit_stats_admin_selected($ipFilters['page_size'], 50); ?>>50</option><option value="100"<?php echo xz_visit_stats_admin_selected($ipFilters['page_size'], 100); ?>>100</option></select></div></div></fieldset></div></div>
    </form>
    <p class="xz-overview-note">当前范围：<?php echo xz_visit_stats_admin_escape($ipData['range']['label']); ?>。异常项仅用于辅助识别，不会自动封禁或拉黑 IP。</p>
    <div class="xz-metric-grid xz-ip-metrics"><?php foreach ($ipMetrics as $metric) { ?><section class="xz-metric-card"><h3><?php echo xz_visit_stats_admin_escape($metric[1]); ?></h3><strong><?php echo xz_visit_stats_admin_metric_value($ipSummary[$metric[0]], $metric[2]); ?><?php echo $metric[3]; ?></strong></section><?php } ?></div>
    <section class="xz-overview-section"><h2>IP 访问排行</h2><div class="xz-table-wrap xz-ip-table-wrap"><table class="tableFull tableBorder tableBorder-thcenter table_hover xz-ip-table"><thead><tr><th>IP 地址</th><th>访问次数</th><th>访问页面数量</th><th>首次访问</th><th>最后访问</th><th>状态码分布</th><th>平均响应</th></tr></thead><tbody>
<?php if (empty($ipData['rows'])) { ?><tr><td colspan="7" class="tdCenter">暂无数据</td></tr><?php } ?>
<?php foreach ($ipData['rows'] as $ipRow) { ?><tr><td><a href="<?php echo xz_visit_stats_admin_escape(xz_visit_stats_admin_ip_url($ipFilters, 1, $ipRow['ip'])); ?>"><?php echo xz_visit_stats_admin_escape($ipRow['ip']); ?></a></td><td><?php echo $ipRow['visits']; ?></td><td><?php echo $ipRow['paths']; ?></td><td><?php echo $ipRow['first_visit'] > 0 ? xz_visit_stats_admin_escape(date('Y-m-d H:i:s', $ipRow['first_visit'])) : '-'; ?></td><td><?php echo $ipRow['last_visit'] > 0 ? xz_visit_stats_admin_escape(date('Y-m-d H:i:s', $ipRow['last_visit'])) : '-'; ?></td><td><span class="xz-status xz-status-2xx">2xx <?php echo $ipRow['status_2xx']; ?></span> <span class="xz-status xz-status-3xx">3xx <?php echo $ipRow['status_3xx']; ?></span> <span class="xz-status xz-status-4xx">4xx <?php echo $ipRow['status_4xx']; ?></span> <span class="xz-status xz-status-5xx">5xx <?php echo $ipRow['status_5xx']; ?></span></td><td><?php echo number_format($ipRow['avg_ms'], 1); ?> ms</td></tr><?php } ?>
    </tbody></table></div><nav class="xz-pagination" aria-label="IP 排行分页"><span class="xz-page-state">共 <?php echo $ipData['count']; ?> 个 IP，当前 <?php echo $ipData['page']; ?> / <?php echo $ipData['page_all']; ?> 页</span><?php if ($ipData['page'] > 1) { ?><a href="<?php echo xz_visit_stats_admin_escape(xz_visit_stats_admin_ip_url($ipFilters, $ipData['page'] - 1)); ?>">上一页</a><?php } ?><?php if ($ipData['page'] < $ipData['page_all']) { ?><a href="<?php echo xz_visit_stats_admin_escape(xz_visit_stats_admin_ip_url($ipFilters, $ipData['page'] + 1)); ?>">下一页</a><?php } ?></nav></section>
    <section class="xz-overview-section"><h2>异常访问列表</h2><p class="xz-overview-note">规则：单分钟 ≥ <?php echo $ipData['thresholds']['high_frequency_per_minute']; ?> 次；至少 <?php echo $ipData['thresholds']['minimum_404_requests']; ?> 次请求且 404 比例 ≥ <?php echo $ipData['thresholds']['high_404_ratio']; ?>%；扫描工具 User-Agent；或至少 <?php echo $ipData['thresholds']['scan_404_paths']; ?> 个不同 404 路径。</p><div class="xz-table-wrap xz-ip-table-wrap"><table class="tableFull tableBorder tableBorder-thcenter table_hover xz-ip-table"><thead><tr><th>IP 地址</th><th>访问次数</th><th>404</th><th>单分钟峰值</th><th>异常原因</th></tr></thead><tbody>
<?php if (empty($ipData['anomalies'])) { ?><tr><td colspan="5" class="tdCenter">当前范围未发现命中规则的异常访问</td></tr><?php } ?>
<?php foreach ($ipData['anomalies'] as $anomaly) { ?><tr><td><a href="<?php echo xz_visit_stats_admin_escape(xz_visit_stats_admin_ip_url($ipFilters, 1, $anomaly['ip'])); ?>"><?php echo xz_visit_stats_admin_escape($anomaly['ip']); ?></a></td><td><?php echo $anomaly['visits']; ?></td><td><?php echo $anomaly['not_found']; ?></td><td><?php echo $anomaly['max_per_minute']; ?></td><td><?php echo xz_visit_stats_admin_escape(implode('；', $anomaly['reasons'])); ?></td></tr><?php } ?>
    </tbody></table></div></section>
<?php if ($ipFilters['ip'] !== '') { ?><section class="xz-overview-section"><h2>IP 详情：<?php echo xz_visit_stats_admin_escape($ipFilters['ip']); ?></h2><p class="xz-overview-note">显示当前时间范围内最近 <?php echo $ipData['thresholds']['detail_limit']; ?> 条访问记录。</p><div class="xz-table-wrap xz-ip-detail-wrap"><table class="tableFull tableBorder tableBorder-thcenter table_hover xz-ip-detail-table"><thead><tr><th>访问时间</th><th>访问 URL</th><th>Referer</th><th>User-Agent</th><th>HTTP 状态</th><th>响应时间</th></tr></thead><tbody>
<?php if (empty($ipData['detail_rows'])) { ?><tr><td colspan="6" class="tdCenter">暂无数据</td></tr><?php } ?>
<?php foreach ($ipData['detail_rows'] as $detailRow) { ?><tr><td><?php echo $detailRow['visited_at'] > 0 ? xz_visit_stats_admin_escape(date('Y-m-d H:i:s', $detailRow['visited_at'])) : '-'; ?></td><td><span class="xz-cell-clip" title="<?php echo xz_visit_stats_admin_escape($detailRow['path']); ?>"><?php echo xz_visit_stats_admin_escape($detailRow['path']); ?></span></td><td><span class="xz-cell-clip" title="<?php echo xz_visit_stats_admin_escape($detailRow['referer']); ?>"><?php echo xz_visit_stats_admin_escape($detailRow['referer'] !== '' ? $detailRow['referer'] : '-'); ?></span></td><td><span class="xz-cell-clip" title="<?php echo xz_visit_stats_admin_escape($detailRow['user_agent']); ?>"><?php echo xz_visit_stats_admin_escape($detailRow['user_agent']); ?></span></td><td><span class="xz-status <?php echo xz_visit_stats_admin_status_class($detailRow['status_code']); ?>"><?php echo $detailRow['status_code']; ?></span></td><td><?php echo $detailRow['duration_ms']; ?> ms</td></tr><?php } ?>
    </tbody></table></div></section><?php } ?>
<?php } elseif ($view === 'maintenance') {
    $preview = $maintenanceResult['type'] === 'preview' ? $maintenanceResult : null;
    $previewFilters = $preview !== null ? $preview['filters'] : null;
?>
<?php if ($maintenanceResult['type'] !== '') { ?><div class="xz-maintenance-message xz-maintenance-<?php echo xz_visit_stats_admin_escape($maintenanceResult['type']); ?>"><?php echo xz_visit_stats_admin_escape($maintenanceResult['message']); ?></div><?php } ?>
    <div class="xz-metric-grid xz-maintenance-metrics"><section class="xz-metric-card"><h3>日志总数量</h3><strong><?php echo number_format($maintenanceOverview['logs']); ?></strong></section><section class="xz-metric-card"><h3>数据库占用估算</h3><strong><?php echo xz_visit_stats_admin_escape(xz_visit_stats_maintenance_format_bytes($maintenanceOverview['bytes'])); ?></strong></section><section class="xz-metric-card"><h3>最早记录时间</h3><strong class="xz-maintenance-time"><?php echo $maintenanceOverview['first_visit'] > 0 ? xz_visit_stats_admin_escape(date('Y-m-d H:i:s', $maintenanceOverview['first_visit'])) : '-'; ?></strong></section><section class="xz-metric-card"><h3>最新记录时间</h3><strong class="xz-maintenance-time"><?php echo $maintenanceOverview['last_visit'] > 0 ? xz_visit_stats_admin_escape(date('Y-m-d H:i:s', $maintenanceOverview['last_visit'])) : '-'; ?></strong></section></div>
    <section class="xz-overview-section"><h2>日志保留设置</h2><p class="xz-overview-note">日志保存周期和自动清理开关请在设置中心管理。开启自动清理后，系统每天最多清理一次超过保存周期的日志。</p><p><a class="button" href="main.php?view=settings">前往设置中心</a></p></section>
    <section class="xz-overview-section"><h2>手动清理日志</h2><p class="xz-overview-note">先预览预计删除数量，再进行二次确认。按天清理会删除早于对应保留天数的日志；自定义范围按访问时间删除。</p><form class="xz-maintenance-form xz-maintenance-purge" method="post" action="main.php?view=maintenance"><input type="hidden" name="csrfToken" value="<?php echo xz_visit_stats_admin_escape($zbp->GetCSRFToken()); ?>" /><input type="hidden" name="maintenance_action" value="preview_purge" /><label for="purge-mode">清理范围</label><select id="purge-mode" name="purge_mode"><option value="">请选择</option><option value="7">早于 7 天</option><option value="30">早于 30 天</option><option value="90">早于 90 天</option><option value="180">早于 180 天</option><option value="365">早于 365 天</option><option value="custom">自定义日期范围</option></select><label for="purge-start">开始时间</label><input id="purge-start" type="datetime-local" name="start" /><label for="purge-end">结束时间</label><input id="purge-end" type="datetime-local" name="end" /><button type="submit" class="button">预览删除数量</button></form>
<?php if ($preview !== null) { ?><div class="xz-maintenance-preview"><strong>预计删除 <?php echo number_format($preview['count']); ?> 条日志</strong><span>范围：<?php echo xz_visit_stats_admin_escape($previewFilters['label']); ?></span><form method="post" action="main.php?view=maintenance"><input type="hidden" name="csrfToken" value="<?php echo xz_visit_stats_admin_escape($zbp->GetCSRFToken()); ?>" /><input type="hidden" name="maintenance_action" value="confirm_purge" /><input type="hidden" name="purge_mode" value="<?php echo xz_visit_stats_admin_escape($previewFilters['mode']); ?>" /><?php if ($previewFilters['mode'] === 'custom') { ?><input type="hidden" name="start" value="<?php echo xz_visit_stats_admin_escape(date('Y-m-d\TH:i', $previewFilters['start'])); ?>" /><input type="hidden" name="end" value="<?php echo xz_visit_stats_admin_escape(date('Y-m-d\TH:i', $previewFilters['end'] - 60)); ?>" /><?php } ?><label><input type="checkbox" name="confirm_delete" value="yes" /> 我已确认删除以上日志，且此操作不可恢复</label><button type="submit" class="button xz-button-danger">确认删除</button></form></div><?php } ?>
    </section>
<?php } elseif ($view === 'realtime') { ?>
    <form class="xz-overview-filter xz-realtime-filter" method="get" action="main.php"><input type="hidden" name="view" value="realtime" /><label for="realtime-limit">显示数量</label><select id="realtime-limit" name="limit"><option value="20"<?php echo xz_visit_stats_admin_selected($realtimeFilters['limit'], 20); ?>>20</option><option value="50"<?php echo xz_visit_stats_admin_selected($realtimeFilters['limit'], 50); ?>>50</option><option value="100"<?php echo xz_visit_stats_admin_selected($realtimeFilters['limit'], 100); ?>>100</option></select><button type="submit" class="button">应用</button><button id="xz-realtime-refresh" type="button" class="button">立即刷新</button><span id="xz-realtime-updated" class="xz-overview-note">更新于 <?php echo xz_visit_stats_admin_escape(date('Y-m-d H:i:s', $realtimeData['generated_at'])); ?></span></form>
    <div class="xz-metric-grid xz-realtime-metrics"><section class="xz-metric-card"><h3>当前显示</h3><strong><?php echo count($realtimeData['rows']); ?></strong></section><section class="xz-metric-card"><h3>自动刷新</h3><strong>30 秒</strong></section><section class="xz-metric-card"><h3>刷新方式</h3><strong>AJAX</strong></section></div>
    <section class="xz-overview-section"><h2>最近访问</h2><div class="xz-table-wrap xz-realtime-table-wrap"><table class="tableFull tableBorder tableBorder-thcenter table_hover xz-realtime-table"><thead><tr><th>访问时间</th><th>IP</th><th>访问地址</th><th>访问类型</th><th>HTTP 状态</th><th>浏览器</th><th>蜘蛛名称</th></tr></thead><tbody id="xz-realtime-body">
<?php if (empty($realtimeData['rows'])) { ?><tr><td colspan="7" class="tdCenter">暂无实时访问记录</td></tr><?php } ?>
<?php foreach ($realtimeData['rows'] as $realtimeRow) { $isBot = $realtimeRow['is_bot'] === 1; ?><tr><td><?php echo $realtimeRow['visited_at'] > 0 ? xz_visit_stats_admin_escape(date('Y-m-d H:i:s', $realtimeRow['visited_at'])) : '-'; ?></td><td><?php echo xz_visit_stats_admin_escape($realtimeRow['ip'] !== '' ? $realtimeRow['ip'] : '-'); ?></td><td><span class="xz-cell-clip" title="<?php echo xz_visit_stats_admin_escape($realtimeRow['path']); ?>"><?php echo xz_visit_stats_admin_escape($realtimeRow['path'] !== '' ? $realtimeRow['path'] : '-'); ?></span></td><td><span class="xz-type <?php echo $isBot ? 'xz-type-bot' : 'xz-type-human'; ?>"><?php echo $isBot ? '蜘蛛' : '普通访客'; ?></span></td><td><span class="xz-status <?php echo xz_visit_stats_admin_status_class($realtimeRow['status_code']); ?>"><?php echo $realtimeRow['status_code']; ?></span></td><td><?php echo xz_visit_stats_admin_escape($realtimeRow['browser'] !== '' ? $realtimeRow['browser'] : '-'); ?></td><td><?php echo xz_visit_stats_admin_escape($isBot ? ($realtimeRow['bot_name'] !== '' ? $realtimeRow['bot_name'] : '未知蜘蛛') : '-'); ?></td></tr><?php } ?>
    </tbody></table></div></section><script>window.XZVisitStatsRealtime=<?php echo json_encode(array('endpoint' => 'main.php?view=realtime', 'limit' => $realtimeFilters['limit']), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;</script><script src="assets/realtime.js?v=0.7.0"></script>
<?php } elseif ($view === 'settings') { ?>
<?php if ($settingsResult['type'] !== '') { ?><div class="xz-maintenance-message xz-maintenance-<?php echo xz_visit_stats_admin_escape($settingsResult['type']); ?>"><?php echo xz_visit_stats_admin_escape($settingsResult['message']); ?></div><?php } ?>
    <form class="xz-settings-form" method="post" action="main.php?view=settings">
      <input type="hidden" name="csrfToken" value="<?php echo xz_visit_stats_admin_escape($zbp->GetCSRFToken()); ?>" />
      <section class="xz-overview-section"><h2>基础设置</h2><label><input type="checkbox" name="enabled" value="1"<?php echo $settings['enabled'] ? ' checked="checked"' : ''; ?> /> 开启访问统计</label><label><input type="checkbox" name="exclude_admin" value="1"<?php echo $settings['exclude_admin'] ? ' checked="checked"' : ''; ?> /> 管理员访问不计入统计</label><p class="xz-overview-note">关闭统计后，新访问不会写入日志，已有记录不受影响。</p></section>
      <section class="xz-overview-section"><h2>数据采集设置</h2><label><input type="checkbox" name="record_bots" value="1"<?php echo $settings['record_bots'] ? ' checked="checked"' : ''; ?> /> 记录蜘蛛访问</label><div class="xz-settings-options"><label><input type="checkbox" name="record_baiduspider" value="1"<?php echo $settings['record_baiduspider'] ? ' checked="checked"' : ''; ?> /> Baiduspider</label><label><input type="checkbox" name="record_googlebot" value="1"<?php echo $settings['record_googlebot'] ? ' checked="checked"' : ''; ?> /> Googlebot</label><label><input type="checkbox" name="record_bingbot" value="1"<?php echo $settings['record_bingbot'] ? ' checked="checked"' : ''; ?> /> bingbot</label><label><input type="checkbox" name="record_other_bots" value="1"<?php echo $settings['record_other_bots'] ? ' checked="checked"' : ''; ?> /> 其他蜘蛛</label></div><label><input type="checkbox" name="record_referer" value="1"<?php echo $settings['record_referer'] ? ' checked="checked"' : ''; ?> /> 保存来源地址</label><label><input type="checkbox" name="record_user_agent" value="1"<?php echo $settings['record_user_agent'] ? ' checked="checked"' : ''; ?> /> 保存浏览器、系统和设备信息</label></section>
      <section class="xz-overview-section"><h2>日志管理</h2><label for="settings-retention">日志保存周期</label><select id="settings-retention" name="retention_days"><?php foreach (array(30, 90, 180, 365) as $days) { ?><option value="<?php echo $days; ?>"<?php echo xz_visit_stats_admin_selected($settings['retention_days'], $days); ?>><?php echo $days; ?> 天</option><?php } ?></select><label><input type="checkbox" name="auto_cleanup" value="1"<?php echo $settings['auto_cleanup'] ? ' checked="checked"' : ''; ?> /> 自动清理超过保存周期的日志</label><p class="xz-overview-note">自动清理每天最多执行一次。手动清理仍可在数据维护页面使用。</p></section>
      <section class="xz-overview-section"><h2>隐私设置</h2><label><input type="radio" name="ip_mode" value="full"<?php echo xz_visit_stats_admin_checked($settings['ip_mode'], 'full'); ?> /> 完整 IP</label><label><input type="radio" name="ip_mode" value="masked"<?php echo xz_visit_stats_admin_checked($settings['ip_mode'], 'masked'); ?> /> IP 脱敏</label><p class="xz-overview-note">IP 用于访客去重、异常访问识别和来源明细。选择脱敏后仅影响新记录。</p></section>
      <section class="xz-overview-section"><h2>性能设置</h2><p>数据处理方式：实时写入。批量处理为后续兼容预留，当前不启用。</p><label for="settings-alert">日志数量提醒</label><input id="settings-alert" type="number" min="10000" max="10000000" step="10000" name="log_alert_count" value="<?php echo (int) $settings['log_alert_count']; ?>" /><p class="xz-overview-note">日志数量达到该值后，会在设置页提醒检查保留策略。</p></section>
      <section class="xz-overview-section"><h2>关于插件</h2><p>插件名称：访问统计<br />版本号：1.3.0<br /><a href="docs/CHANGELOG.md" target="_blank">更新日志</a>　<a href="docs/DEVELOPMENT.md" target="_blank">开发文档</a></p></section>
      <p><button type="submit" class="button">保存设置</button></p>
    </form>
<?php } elseif ($view !== 'records') { ?>
    <div class="xz-placeholder">该功能将在后续版本开放</div>
<?php } else { ?>
<?php
    $hasAdvancedFilters = $filters['range'] === 'custom' || $filters['start'] !== '' || $filters['end'] !== ''
        || $filters['ip'] !== '' || $filters['ip_mode'] !== 'prefix'
        || $filters['bot_name'] !== '' || $filters['browser'] !== ''
        || $filters['status_group'] !== 'all' || $filters['status_code'] !== ''
        || $filters['url'] !== '' || $filters['referer'] !== '' || $filters['page_size'] !== 50;
?>
    <form class="xz-filter xz-filter-panel<?php echo $hasAdvancedFilters ? ' is-open' : ''; ?>" method="get" action="main.php">
      <input type="hidden" name="view" value="records" />
      <div class="xz-filter-basic">
        <div><label for="record-range">时间范围</label><select id="record-range" name="range"><option value="all"<?php echo xz_visit_stats_admin_selected($filters['range'], 'all'); ?>>全部时间</option><option value="today"<?php echo xz_visit_stats_admin_selected($filters['range'], 'today'); ?>>今天</option><option value="yesterday"<?php echo xz_visit_stats_admin_selected($filters['range'], 'yesterday'); ?>>昨天</option><option value="7d"<?php echo xz_visit_stats_admin_selected($filters['range'], '7d'); ?>>最近 7 天</option><option value="30d"<?php echo xz_visit_stats_admin_selected($filters['range'], '30d'); ?>>最近 30 天</option><option value="custom"<?php echo xz_visit_stats_admin_selected($filters['range'], 'custom'); ?>>自定义</option></select></div>
        <div><label for="visit_type">访问类型</label><select id="visit_type" name="visit_type"><option value="all"<?php echo xz_visit_stats_admin_selected($filters['visit_type'], 'all'); ?>>全部</option><option value="human"<?php echo xz_visit_stats_admin_selected($filters['visit_type'], 'human'); ?>>普通访客</option><option value="bot"<?php echo xz_visit_stats_admin_selected($filters['visit_type'], 'bot'); ?>>蜘蛛</option></select></div>
        <div class="xz-filter-submit"><button type="submit" class="button">查询</button></div>
        <div class="xz-filter-toggle-wrap"><button type="button" class="button xz-filter-toggle" aria-expanded="<?php echo $hasAdvancedFilters ? 'true' : 'false'; ?>" aria-controls="records-advanced-filter"><?php echo $hasAdvancedFilters ? '收起高级筛选' : '高级筛选'; ?></button></div>
      </div>
      <div id="records-advanced-filter" class="xz-advanced-filter">
      <div class="xz-filter-sections">
        <fieldset class="xz-filter-section">
          <legend>时间条件</legend>
          <div class="xz-filter-grid xz-filter-grid-time"><div>
          <label for="start">开始时间</label>
          <input id="start" type="datetime-local" name="start" value="<?php echo xz_visit_stats_admin_escape($filters['start']); ?>" />
          </div><div>
          <label for="end">结束时间</label>
          <input id="end" type="datetime-local" name="end" value="<?php echo xz_visit_stats_admin_escape($filters['end']); ?>" />
          </div></div>
        </fieldset>
        <fieldset class="xz-filter-section">
          <legend>访客条件</legend>
          <div class="xz-filter-grid"><div>
          <label for="record-ip">IP</label>
          <input id="record-ip" name="ip" maxlength="45" value="<?php echo xz_visit_stats_admin_escape($filters['ip']); ?>" placeholder="IPv4 / IPv6" />
          </div><div>
          <label for="ip_mode">IP 查询方式</label>
          <select id="ip_mode" name="ip_mode">
            <option value="prefix"<?php echo xz_visit_stats_admin_selected($filters['ip_mode'], 'prefix'); ?>>前缀</option>
            <option value="exact"<?php echo xz_visit_stats_admin_selected($filters['ip_mode'], 'exact'); ?>>精确</option>
          </select>
          </div><div>
          <label for="bot_name">蜘蛛名称</label>
          <select id="bot_name" name="bot_name">
<?php foreach (array('', 'Googlebot', 'Baiduspider', 'bingbot', 'Sogou', '360Spider', 'HaosouSpider', 'Bytespider', 'PetalBot', 'YandexBot', 'DuckDuckBot', 'Applebot') as $botOption) { ?>
            <option value="<?php echo xz_visit_stats_admin_escape($botOption); ?>"<?php echo xz_visit_stats_admin_selected($filters['bot_name'], $botOption); ?>><?php echo $botOption === '' ? '全部' : xz_visit_stats_admin_escape($botOption); ?></option>
<?php } ?>
          </select>
          </div><div>
          <label for="browser">浏览器</label>
          <select id="browser" name="browser">
<?php foreach (array('' => '全部', 'Chrome' => 'Chrome', 'Edge' => 'Edge', 'Firefox' => 'Firefox', 'Safari' => 'Safari', 'Other' => 'Other') as $browserValue => $browserLabel) { ?>
            <option value="<?php echo xz_visit_stats_admin_escape($browserValue); ?>"<?php echo xz_visit_stats_admin_selected($filters['browser'], $browserValue); ?>><?php echo xz_visit_stats_admin_escape($browserLabel); ?></option>
<?php } ?>
          </select>
          </div></div>
        </fieldset>
        <fieldset class="xz-filter-section">
          <legend>请求条件</legend>
          <div class="xz-filter-grid"><div>
          <label for="record-status-group">HTTP 状态</label>
          <select id="record-status-group" name="status_group">
            <option value="all"<?php echo xz_visit_stats_admin_selected($filters['status_group'], 'all'); ?>>全部</option>
            <option value="2xx"<?php echo xz_visit_stats_admin_selected($filters['status_group'], '2xx'); ?>>2xx 正常</option>
            <option value="3xx"<?php echo xz_visit_stats_admin_selected($filters['status_group'], '3xx'); ?>>3xx 跳转</option>
            <option value="4xx"<?php echo xz_visit_stats_admin_selected($filters['status_group'], '4xx'); ?>>4xx 客户端错误</option>
            <option value="5xx"<?php echo xz_visit_stats_admin_selected($filters['status_group'], '5xx'); ?>>5xx 服务端错误</option>
          </select>
          </div><div>
          <label for="status_code">具体状态码</label>
          <input id="status_code" name="status_code" maxlength="3" inputmode="numeric" value="<?php echo xz_visit_stats_admin_escape($filters['status_code']); ?>" placeholder="例如 404" />
          </div><div>
          <label for="url">URL 路径关键词</label>
          <input id="url" name="url" value="<?php echo xz_visit_stats_admin_escape($filters['url']); ?>" />
          </div><div>
          <label for="page_size">每页数量</label>
          <select id="page_size" name="page_size" data-default-value="50">
            <option value="20"<?php echo xz_visit_stats_admin_selected($filters['page_size'], 20); ?>>20</option>
            <option value="50"<?php echo xz_visit_stats_admin_selected($filters['page_size'], 50); ?>>50</option>
            <option value="100"<?php echo xz_visit_stats_admin_selected($filters['page_size'], 100); ?>>100</option>
          </select>
          </div></div>
        </fieldset>
        <fieldset class="xz-filter-section">
          <legend>来源条件</legend>
          <div class="xz-filter-grid"><div>
            <label for="referer">Referer 关键词</label>
            <input id="referer" name="referer" value="<?php echo xz_visit_stats_admin_escape($filters['referer']); ?>" />
          </div></div>
        </fieldset>
      </div>
      </div>
      <p class="xz-filter-actions">
        <a class="button" href="main.php?view=records">重置</a>
      </p>
    </form>

    <div class="xz-list-summary">
      <span><strong>共 <?php echo (int) $pageData['count']; ?> 条</strong></span>
      <span>当前第 <strong><?php echo (int) $pageData['page']; ?></strong> / <?php echo (int) $pageData['page_all']; ?> 页</span>
    </div>
    <div class="xz-table-wrap">
      <table class="tableFull tableBorder tableBorder-thcenter table_hover table_striped xz-visit-table">
        <thead><tr>
          <th>访问时间</th><th>IP</th><th>访问类型</th><th>访问地址</th><th>HTTP</th><th>Referer</th>
          <th class="xz-col-secondary">浏览器</th><th class="xz-col-secondary">操作系统</th><th class="xz-col-secondary">设备</th>
          <th class="xz-col-secondary">响应时间</th><th>蜘蛛</th><th class="xz-action-col">操作</th>
        </tr></thead>
        <tbody>
<?php if (empty($pageData['rows'])) { ?>
          <tr><td colspan="12" class="tdCenter">没有符合条件的访问记录</td></tr>
<?php } ?>
<?php foreach ($pageData['rows'] as $row) {
    $id = (int) $row['vs_ID'];
    $isBot = (int) $row['vs_IsBot'] === 1;
    $status = (int) $row['vs_StatusCode'];
    $detailId = 'xz-detail-' . $id;
?>
          <tr class="xz-data-row">
            <td><?php echo xz_visit_stats_admin_escape(date('Y-m-d H:i:s', (int) $row['vs_VisitedAt'])); ?></td>
            <td><?php echo xz_visit_stats_admin_escape($row['vs_IP']); ?></td>
            <td><span class="xz-type <?php echo $isBot ? 'xz-type-bot' : 'xz-type-human'; ?>"><?php echo $isBot ? '蜘蛛' : '普通访客'; ?></span></td>
            <td><span class="xz-cell-clip" title="<?php echo xz_visit_stats_admin_escape($row['vs_Url']); ?>"><?php echo xz_visit_stats_admin_escape(xz_visit_stats_admin_short($row['vs_Path'])); ?></span></td>
            <td><span class="xz-status <?php echo xz_visit_stats_admin_status_class($status); ?>"><?php echo $status; ?></span></td>
            <td><span class="xz-cell-clip" title="<?php echo xz_visit_stats_admin_escape($row['vs_Referer']); ?>"><?php echo xz_visit_stats_admin_escape(xz_visit_stats_admin_short($row['vs_Referer'] !== '' ? $row['vs_Referer'] : '-')); ?></span></td>
            <td class="xz-col-secondary"><?php echo xz_visit_stats_admin_escape($row['vs_Browser'] !== '' ? $row['vs_Browser'] : 'Other'); ?></td>
            <td class="xz-col-secondary"><?php echo xz_visit_stats_admin_escape($row['vs_Os'] !== '' ? $row['vs_Os'] : '-'); ?></td>
            <td class="xz-col-secondary"><?php echo xz_visit_stats_admin_escape($row['vs_Device'] !== '' ? $row['vs_Device'] : '-'); ?></td>
            <td class="xz-col-secondary"><?php echo (int) $row['vs_DurationMs']; ?> ms</td>
            <td><?php echo xz_visit_stats_admin_escape($isBot ? ($row['vs_BotName'] !== '' ? $row['vs_BotName'] : '未知蜘蛛') : '-'); ?></td>
            <td class="xz-action-col"><button type="button" class="button xz-detail-toggle" data-target="<?php echo $detailId; ?>" aria-expanded="false">详情</button></td>
          </tr>
          <tr id="<?php echo $detailId; ?>" class="xz-detail-row" hidden="hidden"><td colspan="12">
            <div class="xz-detail-panel"><div class="xz-detail-grid">
<?php
    $details = array(
        '完整 URL' => $row['vs_Url'], '完整 Referer' => $row['vs_Referer'],
        '完整 User-Agent' => $row['vs_UserAgent'], 'UA Type' => $row['vs_UaType'],
        'Browser' => $row['vs_Browser'], 'OS' => $row['vs_Os'], 'Device' => $row['vs_Device'],
        'visitor_hash' => $row['vs_VisitorHash'], 'Bot Name' => $row['vs_BotName'],
        'Status Code' => $status, 'Response Time' => (int) $row['vs_DurationMs'] . ' ms',
        '访问时间' => date('Y-m-d H:i:s', (int) $row['vs_VisitedAt']),
    );
    foreach ($details as $label => $value) {
?>
              <div class="xz-detail-item"><strong><?php echo xz_visit_stats_admin_escape($label); ?></strong><div class="xz-detail-value"><?php echo xz_visit_stats_admin_escape($value !== '' ? $value : '-'); ?></div></div>
<?php } ?>
            </div></div>
          </td></tr>
<?php } ?>
        </tbody>
      </table>
    </div>
<?php
    $pageNow = (int) $pageData['page'];
    $pageAll = (int) $pageData['page_all'];
    $pageStart = max(1, $pageNow - 2);
    $pageEnd = min($pageAll, $pageNow + 2);
?>
    <nav class="xz-pagination" aria-label="访问记录分页">
<?php if ($pageNow > 1) { ?>
      <a href="<?php echo xz_visit_stats_admin_escape(xz_visit_stats_admin_page_url($filters, $pageNow - 1)); ?>">上一页</a>
<?php } else { ?>
      <span class="xz-page-disabled">上一页</span>
<?php } ?>
<?php if ($pageStart > 1) { ?>
      <a href="<?php echo xz_visit_stats_admin_escape(xz_visit_stats_admin_page_url($filters, 1)); ?>">1</a>
<?php if ($pageStart > 2) { ?><span class="xz-page-gap">…</span><?php } ?>
<?php } ?>
<?php for ($pageNumber = $pageStart; $pageNumber <= $pageEnd; $pageNumber++) { ?>
<?php if ($pageNumber === $pageNow) { ?>
      <span class="xz-page-current" aria-current="page"><?php echo $pageNumber; ?></span>
<?php } else { ?>
      <a href="<?php echo xz_visit_stats_admin_escape(xz_visit_stats_admin_page_url($filters, $pageNumber)); ?>"><?php echo $pageNumber; ?></a>
<?php } ?>
<?php } ?>
<?php if ($pageEnd < $pageAll) { ?>
<?php if ($pageEnd < $pageAll - 1) { ?><span class="xz-page-gap">…</span><?php } ?>
      <a href="<?php echo xz_visit_stats_admin_escape(xz_visit_stats_admin_page_url($filters, $pageAll)); ?>"><?php echo $pageAll; ?></a>
<?php } ?>
<?php if ($pageNow < $pageAll) { ?>
      <a href="<?php echo xz_visit_stats_admin_escape(xz_visit_stats_admin_page_url($filters, $pageNow + 1)); ?>">下一页</a>
<?php } else { ?>
      <span class="xz-page-disabled">下一页</span>
<?php } ?>
      <span class="xz-page-state">当前 <?php echo $pageNow; ?> / <?php echo $pageAll; ?> 页</span>
    </nav>
<?php } ?>
    <script type="text/javascript">ActiveLeftMenu("a_xz_visit_stats");</script>
  </div>
</div>
<script src="assets/admin.js?v=0.1.0"></script>
<script src="assets/filter.js?v=1.3.0"></script>
<?php
require $blogpath . 'zb_system/admin/admin_footer.php';

RunTime();
