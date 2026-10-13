(function (window) {
    'use strict';

    var allowedColumns = [
        'visit_time', 'visitor_id', 'ip', 'geo', 'visit_type', 'bot_name',
        'browser', 'os', 'device', 'source', 'source_domain', 'entry_path',
        'current_path', 'search_engine', 'keyword', 'session', 'dwell',
        'status', 'server_response'
    ];
    var defaultColumns = [
        'visit_time', 'visitor_id', 'ip', 'source', 'current_path',
        'session', 'dwell', 'status'
    ];
    var storageKey = 'xz_visit_stats_v4_record_columns';

    window.xzvsColumnChooser = function () {
        return {
            columns: allowedColumns.slice(),
            visibleColumns: defaultColumns.slice(),
            init: function () {
                try {
                    var saved = JSON.parse(window.localStorage.getItem(storageKey) || 'null');
                    if (Array.isArray(saved)) {
                        this.visibleColumns = allowedColumns.filter(function (column) {
                            return saved.indexOf(column) !== -1;
                        });
                    }
                } catch (error) {
                    this.visibleColumns = defaultColumns.slice();
                }
            },
            isVisible: function (column) {
                return allowedColumns.indexOf(column) !== -1 && this.visibleColumns.indexOf(column) !== -1;
            },
            save: function () {
                var safeColumns = this.visibleColumns.filter(function (column) {
                    return allowedColumns.indexOf(column) !== -1;
                });
                this.visibleColumns = safeColumns;
                try {
                    window.localStorage.setItem(storageKey, JSON.stringify(safeColumns));
                } catch (error) {
                    // The chooser remains usable for this page when browser storage is unavailable.
                }
            }
        };
    };
}(window));
