(function() {
    'use strict';

    // Tab Navigation
    var navBtns = document.querySelectorAll('.admin-nav-btn');
    var tabPanes = document.querySelectorAll('.admin-tab-pane');

    navBtns.forEach(function(btn) {
        btn.addEventListener('click', function() {
            var tab = btn.getAttribute('data-tab');
            navBtns.forEach(function(b) { b.classList.remove('active'); });
            btn.classList.add('active');

            tabPanes.forEach(function(p) { p.style.display = 'none'; });
            var target = document.getElementById('adm-tab-' + tab);
            if (target) target.style.display = 'block';

            if (tab === 'logs') fetchAdminLogs();
            if (tab === 'sessions') fetchAdminSessions();
        });
    });

    // Create Carrier Modal
    window.toggleAddCarrierModal = function() {
        var el = document.getElementById('modal-add-carrier');
        if (el) el.style.display = (el.style.display === 'none' || el.style.display === '') ? 'block' : 'none';
    };

    var formCreateCarrier = document.getElementById('form-create-carrier');
    if (formCreateCarrier) {
        formCreateCarrier.addEventListener('submit', function(e) {
            e.preventDefault();
            var formData = new FormData(this);
            var json = {};
            formData.forEach(function(v, k) { json[k] = v; });

            postJson('/admin/create-carrier', json, function(res) {
                alert(res.message || 'Carrier created successfully!');
                window.location.reload();
            }, function(err) {
                alert(err.message || 'Error creating carrier.');
            });
        });
    }

    // Edit MC Range Modal
    window.openEditRangeModal = function(userId, userName, startMc, endMc, currentMc, label) {
        var modal = document.getElementById('modal-edit-range');
        document.getElementById('lbl-edit-user').innerText = userName;
        document.getElementById('edit-user-id').value = userId;
        document.getElementById('edit-start-mc').value = startMc;
        document.getElementById('edit-end-mc').value = endMc;
        document.getElementById('edit-current-mc').value = currentMc;
        document.getElementById('edit-range-label').value = label;
        modal.style.display = 'block';
        modal.scrollIntoView({ behavior: 'smooth' });
    };

    window.closeEditRangeModal = function() {
        var modal = document.getElementById('modal-edit-range');
        if (modal) modal.style.display = 'none';
    };

    var formUpdateRange = document.getElementById('form-update-range');
    if (formUpdateRange) {
        formUpdateRange.addEventListener('submit', function(e) {
            e.preventDefault();
            var formData = new FormData(this);
            var json = {};
            formData.forEach(function(v, k) { json[k] = v; });

            postJson('/admin/update-carrier-range', json, function(res) {
                alert(res.message || 'MC range updated!');
                window.location.reload();
            }, function(err) {
                alert(err.message || 'Failed to update MC range.');
            });
        });
    }

    // Filter Logs by User from Dispatcher table action
    window.filterLogsByUser = function(userId) {
        var btn = document.querySelector('[data-tab="logs"]');
        if (btn) btn.click();
        var sel = document.getElementById('log-filter-user');
        if (sel) {
            sel.value = userId;
            fetchAdminLogs();
        }
    };

    // Fetch Logs
    window.fetchAdminLogs = function() {
        var userId = document.getElementById('log-filter-user').value;
        var actionType = document.getElementById('log-filter-action').value;
        var tbody = document.getElementById('tbl-admin-logs-body');

        tbody.innerHTML = '<tr><td colspan="7" style="text-align:center; padding:15px;">Loading logs...</td></tr>';

        fetch('/admin/user-logs?user_id=' + userId + '&action_type=' + actionType)
        .then(function(r) { return r.json(); })
        .then(function(res) {
            if (res.logs && res.logs.length) {
                tbody.innerHTML = res.logs.map(function(l) {
                    var isCopy = l.action_type === 'copy';
                    var badge = isCopy 
                        ? '<span class="ols-badge" style="background:#fef3c7; color:#b45309;">COPY</span>'
                        : '<span class="ols-badge authorized">SEARCH</span>';
                    
                    return '<tr>' +
                        '<td>' + l.created_at + '</td>' +
                        '<td><strong>' + l.user_name + '</strong> (' + l.username + ')</td>' +
                        '<td>' + badge + '</td>' +
                        '<td><strong>' + (l.target_type || '—').toUpperCase() + '</strong></td>' +
                        '<td style="max-width:280px; word-break:break-all;">' + (l.copied_value || l.search_query || '—') + '</td>' +
                        '<td>' + (l.carrier_name ? l.carrier_name + ' (DOT ' + l.carrier_dot + ')' : '—') + '</td>' +
                        '<td style="font-family:monospace; font-size:11px;">' + (l.ip_address || '—') + '</td>' +
                    '</tr>';
                }).join('');
            } else {
                tbody.innerHTML = '<tr><td colspan="7" style="text-align:center; padding:15px;">No activity logs found for selected criteria.</td></tr>';
            }
        });
    };

    // Fetch Sessions & Attendance
    window.fetchAdminSessions = function() {
        var tbody = document.getElementById('tbl-admin-sessions-body');
        tbody.innerHTML = '<tr><td colspan="10" style="text-align:center; padding:15px;">Loading sessions...</td></tr>';

        fetch('/admin/user-sessions')
        .then(function(r) { return r.json(); })
        .then(function(res) {
            if (res.sessions && res.sessions.length) {
                tbody.innerHTML = res.sessions.map(function(s) {
                    var statusBadge = s.status === 'active' 
                        ? '<span class="ols-badge authorized">Active Now</span>'
                        : (s.status === 'break' ? '<span class="ols-badge" style="background:#fef3c7; color:#b45309;">On Break</span>' : '<span class="ols-badge" style="background:#f1f5f9; color:#64748b;">Completed</span>');

                    var breakMins = Math.round((s.total_break_seconds || 0) / 60);

                    return '<tr>' +
                        '<td>#' + s.id + '</td>' +
                        '<td><strong>' + s.name + '</strong></td>' +
                        '<td>' + s.checkin_time + '</td>' +
                        '<td>' + (s.checkout_time || '—') + '</td>' +
                        '<td>' + breakMins + ' min</td>' +
                        '<td>' + statusBadge + '</td>' +
                        '<td><strong>' + (s.mcs_covered_count || 0) + '</strong></td>' +
                        '<td>' + (s.calls_count || 0) + '</td>' +
                        '<td>' + (s.conversions_count || 0) + '</td>' +
                        '<td style="font-family:monospace; font-size:11px;">' + (s.ip_address || '—') + '</td>' +
                    '</tr>';
                }).join('');
            } else {
                tbody.innerHTML = '<tr><td colspan="10" style="text-align:center; padding:15px;">No session logs recorded.</td></tr>';
            }
        });
    };

    // Test FMCSA API Connection
    window.testFmcsaApi = function() {
        var btn = document.getElementById('btn-test-fmcsa');
        var resBox = document.getElementById('test-result-box');
        var webkey = document.getElementById('fmcsa-webkey').value;

        btn.disabled = true;
        btn.innerText = 'Connecting to FMCSA...';
        resBox.style.display = 'none';

        postJson('/admin/test-fmcsa', { fmcsa_webkey: webkey }, function(res) {
            btn.disabled = false;
            btn.innerText = '⚡ Test FMCSA API Connection';
            resBox.style.display = 'block';
            resBox.style.background = '#dcfce7';
            resBox.style.color = '#15803d';
            resBox.innerText = '✔ ' + res.message;
        }, function(err) {
            btn.disabled = false;
            btn.innerText = '⚡ Test FMCSA API Connection';
            resBox.style.display = 'block';
            resBox.style.background = '#fee2e2';
            resBox.style.color = '#b91c1c';
            resBox.innerText = '✖ ' + (err.message || 'Connection failed.');
        });
    };

    // Save Admin Settings
    var formSettings = document.getElementById('form-admin-settings');
    if (formSettings) {
        formSettings.addEventListener('submit', function(e) {
            e.preventDefault();
            var formData = new FormData(this);
            var json = {};
            formData.forEach(function(v, k) { json[k] = v; });

            postJson('/admin/settings', json, function(res) {
                alert(res.message || 'Settings saved successfully!');
            }, function(err) {
                alert(err.message || 'Error saving settings.');
            });
        });
    }

    // Helper POST
    function postJson(url, data, onSuccess, onError) {
        fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(data)
        })
        .then(function(r) { return r.json(); })
        .then(function(res) {
            if (res.success && onSuccess) onSuccess(res);
            else if (!res.success && onError) onError(res);
            else if (onSuccess) onSuccess(res);
        })
        .catch(function(err) {
            if (onError) onError(err);
        });
    }

})();

