(function() {
    'use strict';

    var cfg = window.APP_CONFIG || {};
    var state = {
        currentMc: cfg.currentMc || 1743052,
        currentDot: '',
        currentCarrier: null,
        activeSearchType: 'auto',
        shiftSeconds: 0,
        shiftTimer: null,
        isBreak: cfg.isBreak || false,
        callSeconds: 0,
        callTimer: null,
        isCalling: false
    };

    // Tab Navigation
    var tabBtns = document.querySelectorAll('.ols-nav-item');
    tabBtns.forEach(function(btn) {
        btn.addEventListener('click', function() {
            var tab = btn.getAttribute('data-tab');
            tabBtns.forEach(function(b) { b.classList.remove('active'); });
            btn.classList.add('active');

            document.querySelectorAll('.tab-pane').forEach(function(p) { p.style.display = 'none'; });
            var target = document.getElementById('tab-' + tab);
            if (target) target.style.display = 'block';

            if (tab === 'calls') loadCallLogs();
            if (tab === 'saved') loadSavedLeads();
            if (tab === 'performance') loadPerformance();
        });
    });

    // Dark Mode Toggle
    var themeToggle = document.getElementById('theme-toggle');
    if (themeToggle) {
        themeToggle.addEventListener('click', function() {
            document.body.classList.toggle('ols-dark-mode');
            var isDark = document.body.classList.contains('ols-dark-mode');
            localStorage.setItem('ols_dark', isDark ? '1' : '0');
            themeToggle.innerText = isDark ? '☀️' : '🌙';
        });
        if (localStorage.getItem('ols_dark') === '1') {
            document.body.classList.add('ols-dark-mode');
            themeToggle.innerText = '☀️';
        }
    }

    // Shift Clock Timer
    function startShiftTimer() {
        if (cfg.checkinTime) {
            var checkinMs = new Date(cfg.checkinTime.replace(' ', 'T')).getTime();
            state.shiftSeconds = Math.max(0, Math.floor((Date.now() - checkinMs) / 1000));
        }
        state.shiftTimer = setInterval(function() {
            if (!state.isBreak) {
                state.shiftSeconds++;
                var hrs = String(Math.floor(state.shiftSeconds / 3600)).padStart(2, '0');
                var mins = String(Math.floor((state.shiftSeconds % 3600) / 60)).padStart(2, '0');
                var secs = String(state.shiftSeconds % 60).padStart(2, '0');
                var el = document.getElementById('shift-clock-timer');
                if (el) el.innerText = hrs + ':' + mins + ':' + secs;
            }
        }, 1000);
    }
    startShiftTimer();

    // Clock Buttons
    var btnBreakOut = document.getElementById('btn-break-out');
    var btnBreakIn = document.getElementById('btn-break-in');
    var btnCheckOut = document.getElementById('btn-check-out');

    if (btnBreakOut) {
        btnBreakOut.addEventListener('click', function() {
            postAjax('/carrier/clock', { clock_type: 'break_out', session_id: cfg.sessionId }, function() {
                state.isBreak = true;
                btnBreakOut.style.display = 'none';
                btnBreakIn.style.display = 'inline-block';
                var pill = document.getElementById('shift-status-pill');
                if (pill) {
                    pill.className = 'ols-pill-status break';
                    pill.innerText = '● On Break';
                }
            });
        });
    }

    if (btnBreakIn) {
        btnBreakIn.addEventListener('click', function() {
            postAjax('/carrier/clock', { clock_type: 'break_in', session_id: cfg.sessionId }, function() {
                state.isBreak = false;
                btnBreakIn.style.display = 'none';
                btnBreakOut.style.display = 'inline-block';
                var pill = document.getElementById('shift-status-pill');
                if (pill) {
                    pill.className = 'ols-pill-status';
                    pill.innerText = '● Active Shift';
                }
            });
        });
    }

    if (btnCheckOut) {
        btnCheckOut.addEventListener('click', function() {
            if (confirm('Are you sure you want to end your shift and check out?')) {
                postAjax('/carrier/clock', { clock_type: 'check_out', session_id: cfg.sessionId }, function() {
                    window.location.href = '/logout';
                });
            }
        });
    }

    // Search Type Buttons
    var typeBtns = document.querySelectorAll('.ols-type-btn');
    typeBtns.forEach(function(b) {
        b.addEventListener('click', function() {
            typeBtns.forEach(function(x) { x.classList.remove('active'); });
            b.classList.add('active');
            state.activeSearchType = b.getAttribute('data-type');
        });
    });

    // Run Search
    var btnRunSearch = document.getElementById('btn-run-search');
    var searchInput = document.getElementById('carrier-search-input');

    function executeSearch(query, type) {
        var q = query || searchInput.value.trim();
        var t = type || state.activeSearchType;
        if (!q) return;

        btnRunSearch.disabled = true;
        btnRunSearch.innerText = 'Searching...';

        postAjax('/carrier/search', { type: t, query: q, session_id: cfg.sessionId }, function(res) {
            btnRunSearch.disabled = false;
            btnRunSearch.innerText = 'Find Carrier';
            if (res.success && res.data) {
                renderCarrier(res.data);
            } else {
                alert(res.message || 'Carrier not found.');
            }
        }, function(err) {
            btnRunSearch.disabled = false;
            btnRunSearch.innerText = 'Find Carrier';
            alert('Carrier not found in FMCSA / Census index.');
        });
    }

    if (btnRunSearch) {
        btnRunSearch.addEventListener('click', function() { executeSearch(); });
    }
    if (searchInput) {
        searchInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') executeSearch();
        });
    }

    // Render Carrier Dossier
    function renderCarrier(c) {
        state.currentCarrier = c;
        state.currentDot = c.dot_number || '';
        
        var nameEl = document.getElementById('disp-legal-name');
        var dbaEl = document.getElementById('disp-dba-name');
        var badgeEl = document.getElementById('disp-status-badge');

        if (nameEl) nameEl.innerText = c.legal_name || 'Carrier Dossier';
        if (dbaEl) dbaEl.innerText = c.dba_name ? 'DBA: ' + c.dba_name : 'DBA: None';

        if (badgeEl) {
            if (c.allowed_to_operate) {
                badgeEl.className = 'ols-badge authorized';
                badgeEl.innerText = 'Authorized';
            } else {
                badgeEl.className = 'ols-badge not-authorized';
                badgeEl.innerText = 'NOT AUTHORIZED';
            }
        }

        setTxt('disp-mc', c.mc_number ? 'MC ' + c.mc_number : '—');
        setTxt('disp-dot', c.dot_number || '—');
        setTxt('disp-phone', c.phone || 'No phone recorded');
        setTxt('disp-email', c.email || 'No email recorded');
        setTxt('disp-units', c.power_units || '0');
        setTxt('disp-drivers', c.drivers || '0');
        setTxt('disp-operation', c.operation || 'Interstate');
        setTxt('disp-authority', 'Common: ' + (c.auth_common || 'Active'));
        setTxt('disp-address', c.address || '—');

        var saferBtn = document.getElementById('btn-safer-link');
        if (saferBtn && c.dot_number) {
            saferBtn.href = 'https://safer.fmcsa.dot.gov/query.asp?searchtype=ANY&query_type=queryCarrierSnapshot&query_param=USDOT&query_string=' + c.dot_number;
        }

        // Reset calling panel
        resetCallingPanel();
    }

    function setTxt(id, val) {
        var el = document.getElementById(id);
        if (el) el.innerText = val;
    }

    // Copy to clipboard helper
    window.copyField = function(elementId, fieldName) {
        var el = document.getElementById(elementId);
        if (!el) return;
        var text = el.innerText.replace(/^MC\s*/, '').trim();
        if (!text || text === '—') return;

        navigator.clipboard.writeText(text).then(function() {
            // Visual feedback
            var orig = el.style.backgroundColor;
            el.style.backgroundColor = '#dbeafe';
            setTimeout(function() { el.style.backgroundColor = orig; }, 300);

            // Log activity to DB
            postAjax('/carrier/log-copy', {
                session_id: cfg.sessionId,
                target_type: fieldName,
                carrier_dot: state.currentDot,
                carrier_name: state.currentCarrier ? state.currentCarrier.legal_name : '',
                copied_value: text
            });
        });
    };

    // Sequential MC Navigation
    var btnPrev = document.getElementById('btn-mc-prev');
    var btnNext = document.getElementById('btn-mc-next');

    function advanceMc(step) {
        var newMc = state.currentMc + step;
        state.currentMc = newMc;

        var lbl = document.getElementById('lbl-current-mc');
        if (lbl) lbl.innerText = 'MC ' + newMc;

        if (searchInput) searchInput.value = newMc;

        // Persist range progress
        postAjax('/carrier/update-range', {
            range_id: cfg.rangeId,
            current_mc: newMc
        });

        // Search the new MC
        executeSearch(newMc, 'mc');
    }

    if (btnPrev) btnPrev.addEventListener('click', function() { advanceMc(-1); });
    if (btnNext) btnNext.addEventListener('click', function() { advanceMc(1); });

    // Calling Panel & Disposition
    var btnDial = document.getElementById('btn-trigger-call');
    var timerDisplay = document.getElementById('call-timer-display');
    var btnSaveCall = document.getElementById('btn-save-call');

    function resetCallingPanel() {
        if (state.callTimer) clearInterval(state.callTimer);
        state.isCalling = false;
        state.callSeconds = 0;
        if (timerDisplay) timerDisplay.innerText = 'Ready to Call';
        if (btnDial) {
            btnDial.innerText = '📞 Dial Carrier Phone';
            btnDial.style.background = '#10b981';
        }
    }

    if (btnDial) {
        btnDial.addEventListener('click', function() {
            if (!state.currentCarrier || !state.currentCarrier.phone) {
                alert('No phone number available for this carrier.');
                return;
            }

            var cleanPhone = state.currentCarrier.phone.replace(/\D/g, '');

            if (!state.isCalling) {
                // Start call timer
                state.isCalling = true;
                state.callSeconds = 0;
                btnDial.innerText = '🔴 Hang Up Call';
                btnDial.style.background = '#ef4444';

                state.callTimer = setInterval(function() {
                    state.callSeconds++;
                    var mins = String(Math.floor(state.callSeconds / 60)).padStart(2, '0');
                    var secs = String(state.callSeconds % 60).padStart(2, '0');
                    if (timerDisplay) timerDisplay.innerText = 'Call in progress ' + mins + ':' + secs;
                }, 1000);

                // Trigger VOIP
                if (cfg.voip === 'ringcentral') {
                    window.location.href = 'rcmobile://call?number=' + cleanPhone;
                } else if (cfg.voip === '8x8') {
                    window.location.href = '8x8work://call?number=' + cleanPhone;
                } else {
                    window.location.href = 'tel:' + cleanPhone;
                }
            } else {
                // Hang up
                if (state.callTimer) clearInterval(state.callTimer);
                state.isCalling = false;
                btnDial.innerText = '📞 Redial Carrier';
                btnDial.style.background = '#10b981';
                if (timerDisplay) timerDisplay.innerText = 'Call Ended (' + state.callSeconds + 's)';
            }
        });
    }

    if (btnSaveCall) {
        btnSaveCall.addEventListener('click', function() {
            if (!state.currentCarrier) {
                alert('Please search a carrier first.');
                return;
            }

            var outcome = document.getElementById('call-outcome').value;
            var notes = document.getElementById('call-notes').value;

            postAjax('/carrier/record-call', {
                session_id: cfg.sessionId,
                mc_number: state.currentCarrier.mc_number || state.currentMc,
                dot_number: state.currentCarrier.dot_number || '',
                carrier_name: state.currentCarrier.legal_name || '',
                phone: state.currentCarrier.phone || '',
                email: state.currentCarrier.email || '',
                outcome: outcome,
                duration_seconds: state.callSeconds,
                notes: notes,
                provider: cfg.voip
            }, function() {
                // Clear notes & advance
                document.getElementById('call-notes').value = '';
                advanceMc(1);
            });
        });
    }

    // Save Lead Button
    var btnSaveLead = document.getElementById('btn-save-lead');
    if (btnSaveLead) {
        btnSaveLead.addEventListener('click', function() {
            if (!state.currentCarrier) return;
            postAjax('/carrier/save-lead', {
                mc_number: state.currentCarrier.mc_number || state.currentMc,
                dot_number: state.currentCarrier.dot_number || '',
                carrier_name: state.currentCarrier.legal_name || '',
                phone: state.currentCarrier.phone || '',
                email: state.currentCarrier.email || '',
                address: state.currentCarrier.address || '',
                raw_data: state.currentCarrier
            }, function() {
                btnSaveLead.innerText = '✔ Lead Saved!';
                setTimeout(function() { btnSaveLead.innerText = '★ Save to Leads'; }, 2000);
            });
        });
    }

    // Load Call Logs
    window.loadCallLogs = function() {
        var tbody = document.getElementById('tbl-calls-body');
        if (!tbody) return;
        tbody.innerHTML = '<tr><td colspan="7" style="text-align:center; padding:15px;">Loading...</td></tr>';

        fetch('/carrier/calls')
        .then(function(r) { return r.json(); })
        .then(function(res) {
            if (res.calls && res.calls.length) {
                tbody.innerHTML = res.calls.map(function(c) {
                    return '<tr>' +
                        '<td>' + c.created_at + '</td>' +
                        '<td><strong>MC ' + c.mc_number + '</strong></td>' +
                        '<td>' + (c.dot_number || '—') + '</td>' +
                        '<td>' + (c.carrier_name || '—') + '</td>' +
                        '<td>' + (c.phone || '—') + '</td>' +
                        '<td><span class="ols-badge authorized">' + c.outcome + '</span></td>' +
                        '<td>' + (c.notes || '') + '</td>' +
                    '</tr>';
                }).join('');
            } else {
                tbody.innerHTML = '<tr><td colspan="7" style="text-align:center; padding:15px;">No calls logged yet.</td></tr>';
            }
        });
    };

    // Load Saved Leads
    window.loadSavedLeads = function() {
        var tbody = document.getElementById('tbl-saved-body');
        if (!tbody) return;
        tbody.innerHTML = '<tr><td colspan="8" style="text-align:center; padding:15px;">Loading...</td></tr>';

        fetch('/carrier/saved')
        .then(function(r) { return r.json(); })
        .then(function(res) {
            if (res.saved && res.saved.length) {
                tbody.innerHTML = res.saved.map(function(s) {
                    return '<tr>' +
                        '<td>' + s.saved_at + '</td>' +
                        '<td><strong>MC ' + s.mc_number + '</strong></td>' +
                        '<td>' + (s.dot_number || '—') + '</td>' +
                        '<td>' + s.carrier_name + '</td>' +
                        '<td>' + (s.phone || '—') + '</td>' +
                        '<td>' + (s.email || '—') + '</td>' +
                        '<td>' + (s.address || '—') + '</td>' +
                        '<td><button type="button" class="ols-btn-step" onclick="loadLeadMc(' + s.mc_number + ')">Load into Rig</button></td>' +
                    '</tr>';
                }).join('');
            } else {
                tbody.innerHTML = '<tr><td colspan="8" style="text-align:center; padding:15px;">No saved carriers in lead pipeline.</td></tr>';
            }
        });
    };

    window.loadLeadMc = function(mc) {
        document.querySelector('[data-tab="dialer"]').click();
        if (searchInput) searchInput.value = mc;
        executeSearch(mc, 'mc');
    };

    // Load Performance
    window.loadPerformance = function() {
        fetch('/carrier/performance')
        .then(function(r) { return r.json(); })
        .then(function(res) {
            if (res.stats) {
                var s = res.stats;
                setTxt('perf-calls', s.total_calls);
                setTxt('perf-conversions', s.conversions);
                setTxt('perf-rate', s.conversion_rate + '%');
                setTxt('perf-leads', s.saved_leads);
            }
        });
    };

    // Helper POST
    function postAjax(url, data, onSuccess, onError) {
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

    // Auto-search initial MC on boot
    setTimeout(function() {
        if (state.currentMc) executeSearch(state.currentMc, 'mc');
    }, 400);

})();

