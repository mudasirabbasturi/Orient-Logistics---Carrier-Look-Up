<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Carrier Calling Workspace - Orient Logistics</title>
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>

    <!-- Header Navigation -->
    <header class="ols-app-header">
        <div class="ols-brand">
            <div class="ols-brand-logo">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="1" y="3" width="15" height="13"></rect><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon><circle cx="5.5" cy="18.5" r="2.5"></circle><circle cx="18.5" cy="18.5" r="2.5"></circle></svg>
            </div>
            <span>DialRig</span>
            <span class="ols-brand-tag">Workspace</span>
        </div>

        <nav class="ols-nav-tabs">
            <button type="button" class="ols-nav-item active" data-tab="dialer">Dialer Rig</button>
            <button type="button" class="ols-nav-item" data-tab="calls">Call Logs</button>
            <button type="button" class="ols-nav-item" data-tab="saved">Saved Leads</button>
            <button type="button" class="ols-nav-item" data-tab="performance">My Performance</button>
        </nav>

        <div class="ols-header-actions">
            <button type="button" class="ols-btn-action" id="theme-toggle" title="Toggle Dark/Light Mode">🌙</button>
            <span style="font-size: 13px; font-weight: 600; color: rgba(255,255,255,0.9); padding: 0 8px;">
                👤 <?= htmlspecialchars($user['name'] ?? $user['username']) ?>
            </span>
            <a href="/logout" class="ols-btn-action" style="background: rgba(239, 68, 68, 0.2); border-color: rgba(239,68,68,0.4); color: #fca5a5;">Sign Out</a>
        </div>
    </header>

    <div class="ols-workspace-container">
        
        <!-- Attendance & Shift Clock Card -->
        <div class="ols-timeclock-card">
            <div class="ols-clock-info">
                <span id="shift-status-pill" class="ols-pill-status <?= ($session['status'] === 'break') ? 'break' : '' ?>">
                    ● <?= ($session['status'] === 'break') ? 'On Break' : 'Active Shift' ?>
                </span>
                <div class="ols-shift-timer">
                    Shift Elapsed: <strong id="shift-clock-timer">00:00:00</strong>
                </div>
                <div class="ols-shift-timer">
                    Allocated MC Block: <strong>MC <?= htmlspecialchars($range['start_mc']) ?> – <?= htmlspecialchars($range['end_mc']) ?></strong>
                </div>
            </div>

            <div class="ols-clock-btns">
                <button type="button" class="ols-btn-clock" id="btn-break-out" style="<?= ($session['status'] === 'break') ? 'display:none;' : '' ?>">☕ Break Out</button>
                <button type="button" class="ols-btn-clock" id="btn-break-in" style="<?= ($session['status'] !== 'break') ? 'display:none;' : '' ?>">▶ Resume Shift</button>
                <button type="button" class="ols-btn-clock primary" id="btn-check-out">🛑 Check Out</button>
            </div>
        </div>

        <!-- TAB 1: Main Sequential Calling Rig & Lookup -->
        <div id="tab-dialer" class="tab-pane">
            
            <!-- Direct Quick Search Bar -->
            <div class="ols-search-panel">
                <div style="font-weight: 700; font-size: 13px; color: var(--ols-navy); display: flex; align-items: center; gap: 6px; white-space: nowrap;">
                    🔍 Carrier Lookup:
                </div>
                <div class="ols-search-type-group">
                    <button type="button" class="ols-type-btn active" data-type="auto">Auto</button>
                    <button type="button" class="ols-type-btn" data-type="mc">MC #</button>
                    <button type="button" class="ols-type-btn" data-type="dot">USDOT</button>
                    <button type="button" class="ols-type-btn" data-type="phone">Phone</button>
                    <button type="button" class="ols-type-btn" data-type="email">Email</button>
                </div>
                <input type="text" id="carrier-search-input" class="ols-search-input" placeholder="Enter MC#, USDOT, Phone, or Email (e.g. <?= htmlspecialchars($range['current_mc']) ?>)..." value="<?= htmlspecialchars($range['current_mc']) ?>">
                <button type="button" id="btn-run-search" class="ols-btn-search">Find Carrier</button>
            </div>

            <!-- MC Step Navigator -->
            <div class="ols-nav-mc-row">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <button type="button" class="ols-btn-step" id="btn-mc-prev">⬅ Previous MC</button>
                    <button type="button" class="ols-btn-step" id="btn-mc-next">Next MC ➡</button>
                    <span style="font-size: 13px; color: var(--ols-slate-500);">Current Sequence: <strong id="lbl-current-mc" style="color:var(--ols-navy);">MC <?= htmlspecialchars($range['current_mc']) ?></strong></span>
                </div>
                <div style="font-size: 13px; color: var(--ols-slate-500);">
                    Assigned Range: <strong><?= htmlspecialchars($range['range_label'] ?? 'Sequential Block') ?></strong>
                </div>
            </div>

            <!-- Two Column Layout: Carrier Details & Calling Rig -->
            <div class="ols-dialer-grid">
                
                <!-- Left: Full Carrier Intelligence Dossier -->
                <div class="ols-carrier-card" id="carrier-profile-view">
                    <div class="ols-card-header">
                        <div>
                            <div class="ols-carrier-title">
                                <span id="disp-legal-name">Loading Carrier Data...</span>
                                <span class="ols-badge authorized" id="disp-status-badge">Authorized</span>
                            </div>
                            <div style="font-size: 13px; color: var(--ols-slate-500); margin-top: 4px;" id="disp-dba-name">
                                DBA: None
                            </div>
                        </div>
                        <div>
                            <button type="button" class="ols-btn-clock" id="btn-save-lead" style="background: #00d4ff; color: #0a2540; border: none; font-weight: 700;">★ Save to Leads</button>
                            <?php if ($showSafer === '1'): ?>
                            <a href="#" target="_blank" id="btn-safer-link" class="ols-btn-clock" style="text-decoration:none; margin-left:6px;">Open in SAFER ↗</a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Primary Contact & Meta Info -->
                    <div class="ols-meta-grid">
                        <div class="ols-meta-item">
                            <div class="ols-meta-label">MC Number</div>
                            <div class="ols-meta-val">
                                <span id="disp-mc">MC <?= htmlspecialchars($range['current_mc']) ?></span>
                                <button type="button" class="ols-copy-btn" onclick="copyField('disp-mc', 'mc')">Copy</button>
                            </div>
                        </div>
                        <div class="ols-meta-item">
                            <div class="ols-meta-label">USDOT Number</div>
                            <div class="ols-meta-val">
                                <span id="disp-dot">—</span>
                                <button type="button" class="ols-copy-btn" onclick="copyField('disp-dot', 'dot')">Copy</button>
                            </div>
                        </div>
                        <div class="ols-meta-item">
                            <div class="ols-meta-label">Direct Phone</div>
                            <div class="ols-meta-val">
                                <span id="disp-phone" style="color: #059669; font-size: 16px;">—</span>
                                <button type="button" class="ols-copy-btn" onclick="copyField('disp-phone', 'phone')">Copy</button>
                            </div>
                        </div>
                        <div class="ols-meta-item">
                            <div class="ols-meta-label">Direct Email</div>
                            <div class="ols-meta-val">
                                <span id="disp-email" style="font-size: 13px; word-break: break-all;">—</span>
                                <button type="button" class="ols-copy-btn" onclick="copyField('disp-email', 'email')">Copy</button>
                            </div>
                        </div>
                    </div>

                    <!-- Fleet & Operating Scope -->
                    <div class="ols-meta-grid">
                        <div class="ols-meta-item">
                            <div class="ols-meta-label">Power Units (Trucks)</div>
                            <div class="ols-meta-val"><span id="disp-units">0</span></div>
                        </div>
                        <div class="ols-meta-item">
                            <div class="ols-meta-label">Drivers</div>
                            <div class="ols-meta-val"><span id="disp-drivers">0</span></div>
                        </div>
                        <div class="ols-meta-item">
                            <div class="ols-meta-label">Carrier Operation</div>
                            <div class="ols-meta-val"><span id="disp-operation">Interstate</span></div>
                        </div>
                        <div class="ols-meta-item">
                            <div class="ols-meta-label">Operating Authority</div>
                            <div class="ols-meta-val"><span id="disp-authority">Common: Active</span></div>
                        </div>
                    </div>

                    <div class="ols-meta-item" style="margin-top: 10px;">
                        <div class="ols-meta-label">Physical Address</div>
                        <div class="ols-meta-val" style="font-weight: 500; font-size: 14px;">
                            <span id="disp-address">—</span>
                            <button type="button" class="ols-copy-btn" onclick="copyField('disp-address', 'address')">Copy</button>
                        </div>
                    </div>
                </div>

                <!-- Right: Disposition & Calling Console -->
                <div class="ols-rig-panel">
                    <div class="ols-call-status-box">
                        <div style="font-size: 11px; text-transform: uppercase; letter-spacing: 1px; color: #00d4ff; font-weight: 700;">Dialer Status</div>
                        <div style="font-size: 20px; font-weight: 800; margin: 4px 0;" id="call-timer-display">Ready to Call</div>
                        <div style="font-size: 12px; color: rgba(255,255,255,0.7);">VOIP Engine: <strong><?= strtoupper(htmlspecialchars($voip)) ?></strong></div>
                    </div>

                    <button type="button" class="ols-btn-dial" id="btn-trigger-call">
                        📞 Dial Carrier Phone
                    </button>

                    <div>
                        <label style="font-size: 12px; font-weight: 700; color: var(--ols-slate-500); display: block; margin-bottom: 6px;">Call Outcome / Disposition</label>
                        <select id="call-outcome" style="width: 100%; height: 38px; border: 1px solid var(--ols-border); border-radius: 8px; padding: 0 10px; font-size: 13px; background: var(--ols-card-bg); color: var(--ols-text-main);">
                            <option value="Interested">⭐ Interested / Qualified Lead</option>
                            <option value="Carrier Signed">🎉 Carrier Signed Agreement</option>
                            <option value="Call Back Later">⏰ Call Back Later</option>
                            <option value="No Answer / Voicemail">📵 No Answer / Voicemail</option>
                            <option value="Not Interested">❌ Not Interested</option>
                            <option value="Wrong Number / Disconnected">⚠️ Wrong Number</option>
                        </select>
                    </div>

                    <div>
                        <label style="font-size: 12px; font-weight: 700; color: var(--ols-slate-500); display: block; margin-bottom: 6px;">Dispatcher Notes</label>
                        <textarea id="call-notes" rows="4" style="width: 100%; border: 1px solid var(--ols-border); border-radius: 8px; padding: 10px; font-size: 13px; background: var(--ols-card-subtle); color: var(--ols-text-main);" placeholder="Add call notes, fleet details, equipment types..."></textarea>
                    </div>

                    <button type="button" class="ols-btn-clock primary" id="btn-save-call" style="background: #0a2540; border: none; color: #fff; height: 40px; font-size: 13px;">
                        Save Call & Advance to Next MC
                    </button>
                </div>
            </div>
        </div>

        <!-- TAB 2: Call Logs -->
        <div id="tab-calls" class="tab-pane" style="display: none;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                <h2 style="font-size: 18px; font-weight: 800; color: var(--ols-navy);">My Call Logs</h2>
                <button type="button" class="ols-btn-step" onclick="loadCallLogs()">🔄 Refresh</button>
            </div>
            <div class="ols-table-responsive">
                <table class="ols-table">
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>MC #</th>
                            <th>USDOT</th>
                            <th>Carrier Name</th>
                            <th>Phone</th>
                            <th>Outcome</th>
                            <th>Notes</th>
                        </tr>
                    </thead>
                    <tbody id="tbl-calls-body">
                        <tr><td colspan="7" style="text-align:center; padding:20px;">Loading call records...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- TAB 3: Saved Leads -->
        <div id="tab-saved" class="tab-pane" style="display: none;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                <h2 style="font-size: 18px; font-weight: 800; color: var(--ols-navy);">Saved Carriers (Lead Pipeline)</h2>
                <button type="button" class="ols-btn-step" onclick="loadSavedLeads()">🔄 Refresh</button>
            </div>
            <div class="ols-table-responsive">
                <table class="ols-table">
                    <thead>
                        <tr>
                            <th>Saved At</th>
                            <th>MC #</th>
                            <th>USDOT</th>
                            <th>Carrier Name</th>
                            <th>Phone</th>
                            <th>Email</th>
                            <th>Physical Address</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="tbl-saved-body">
                        <tr><td colspan="8" style="text-align:center; padding:20px;">Loading saved leads...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- TAB 4: My Performance -->
        <div id="tab-performance" class="tab-pane" style="display: none;">
            <h2 style="font-size: 18px; font-weight: 800; color: var(--ols-navy); margin-bottom: 15px;">My Work Metrics & Conversion Rates</h2>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 15px; margin-bottom: 25px;">
                <div class="ols-carrier-card" style="padding: 18px;">
                    <div class="ols-meta-label">Total Outbound Calls</div>
                    <div style="font-size: 28px; font-weight: 800; color: #0a2540;" id="perf-calls">0</div>
                </div>
                <div class="ols-carrier-card" style="padding: 18px;">
                    <div class="ols-meta-label">Conversions / Signed</div>
                    <div style="font-size: 28px; font-weight: 800; color: #10b981;" id="perf-conversions">0</div>
                </div>
                <div class="ols-carrier-card" style="padding: 18px;">
                    <div class="ols-meta-label">Conversion Rate</div>
                    <div style="font-size: 28px; font-weight: 800; color: #0096c7;" id="perf-rate">0%</div>
                </div>
                <div class="ols-carrier-card" style="padding: 18px;">
                    <div class="ols-meta-label">Saved Pipeline Leads</div>
                    <div style="font-size: 28px; font-weight: 800; color: #f59e0b;" id="perf-leads">0</div>
                </div>
            </div>
        </div>

    </div>

    <!-- Application Config passed to JS -->
    <script>
        window.APP_CONFIG = {
            sessionId: <?= (int)$session['id'] ?>,
            rangeId: <?= (int)$range['id'] ?>,
            startMc: <?= (int)$range['start_mc'] ?>,
            endMc: <?= (int)$range['end_mc'] ?>,
            currentMc: <?= (int)$range['current_mc'] ?>,
            checkinTime: "<?= htmlspecialchars($session['checkin_time']) ?>",
            isBreak: <?= ($session['status'] === 'break') ? 'true' : 'false' ?>,
            voip: "<?= htmlspecialchars($voip) ?>"
        };
    </script>
    <script src="/assets/js/workspace.js"></script>
</body>
</html>

