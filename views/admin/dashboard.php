<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard & Dispatcher Tracking - Orient Logistics</title>
    <link rel="stylesheet" href="/assets/css/app.css">
    <style>
        .admin-nav {
            display: flex;
            gap: 6px;
            background: #ffffff;
            border: 1px solid var(--ols-border);
            padding: 6px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .admin-nav-btn {
            background: transparent;
            border: none;
            padding: 8px 16px;
            font-size: 13px;
            font-weight: 700;
            border-radius: 6px;
            cursor: pointer;
            color: var(--ols-slate-600);
        }
        .admin-nav-btn.active {
            background: #0a2540;
            color: #ffffff;
        }
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
            gap: 15px;
            margin-bottom: 24px;
        }
        .kpi-card {
            background: #ffffff;
            border: 1px solid var(--ols-border);
            border-radius: 12px;
            padding: 18px 20px;
            box-shadow: var(--ols-shadow-sm);
        }
        .kpi-title {
            font-size: 11px;
            font-weight: 700;
            color: var(--ols-slate-500);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .kpi-val {
            font-size: 26px;
            font-weight: 800;
            color: #0a2540;
            margin-top: 4px;
        }
        .form-section {
            background: #ffffff;
            border: 1px solid var(--ols-border);
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: var(--ols-shadow-sm);
        }
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 16px;
        }
        .form-label {
            display: block;
            font-size: 12px;
            font-weight: 700;
            color: #334155;
            margin-bottom: 4px;
        }
        .form-input {
            width: 100%;
            height: 38px;
            border: 1px solid var(--ols-border);
            border-radius: 6px;
            padding: 0 12px;
            font-size: 13px;
            background: #f8fafc;
            color: #0f172a;
            outline: none;
        }
        .form-input:focus {
            border-color: #0096c7;
            background: #fff;
        }
        .btn-action {
            background: #0a2540;
            color: #ffffff;
            border: none;
            border-radius: 6px;
            padding: 9px 18px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            transition: opacity 0.2s;
        }
        .btn-action:hover {
            opacity: 0.9;
        }
        .btn-test {
            background: #10b981;
            color: #ffffff;
        }
    </style>
</head>
<body>

    <!-- Header Navigation -->
    <header class="ols-app-header">
        <div class="ols-brand">
            <div class="ols-brand-logo">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="1" y="3" width="15" height="13"></rect><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon><circle cx="5.5" cy="18.5" r="2.5"></circle><circle cx="18.5" cy="18.5" r="2.5"></circle></svg>
            </div>
            <span>Orient Logistics</span>
            <span class="ols-brand-tag" style="background:#ef4444; color:#fff;">Admin Control</span>
        </div>

        <div class="ols-header-actions">
            <a href="/carrier" class="ols-btn-action" style="background: rgba(0,212,255,0.2); border-color: #00d4ff; color: #00d4ff;">Switch to Carrier Rig ↗</a>
            <span style="font-size: 13px; font-weight: 600; color: rgba(255,255,255,0.9); padding: 0 8px;">
                👑 <?= htmlspecialchars($admin['name'] ?? $admin['username']) ?>
            </span>
            <a href="/logout" class="ols-btn-action" style="background: rgba(239, 68, 68, 0.2); border-color: rgba(239,68,68,0.4); color: #fca5a5;">Sign Out</a>
        </div>
    </header>

    <div class="ols-workspace-container">
        
        <!-- Navigation Menu -->
        <div class="admin-nav">
            <button type="button" class="admin-nav-btn active" data-tab="users">Carrier Dispatchers & MC Ranges</button>
            <button type="button" class="admin-nav-btn" data-tab="logs">User Activity Logs</button>
            <button type="button" class="admin-nav-btn" data-tab="sessions">Attendance & Sessions</button>
            <button type="button" class="admin-nav-btn" data-tab="settings">API & Proxy Settings (Admin Only)</button>
        </div>

        <!-- KPI Metrics Row -->
        <div class="kpi-grid">
            <div class="kpi-card">
                <div class="kpi-title">Active Dispatchers Today</div>
                <div class="kpi-val" style="color:#059669;"><?= $activeSessionsToday ?> <span style="font-size:14px; font-weight:500; color:#64748b;">/ <?= $totalUsers ?> carriers</span></div>
            </div>
            <div class="kpi-card">
                <div class="kpi-title">Outbound Calls Today</div>
                <div class="kpi-val" style="color:#0096c7;"><?= $totalCallsToday ?></div>
            </div>
            <div class="kpi-card">
                <div class="kpi-title">FMCSA Searches Today</div>
                <div class="kpi-val"><?= $totalSearchesToday ?></div>
            </div>
            <div class="kpi-card">
                <div class="kpi-title">Click-to-Copy Actions</div>
                <div class="kpi-val" style="color:#f59e0b;"><?= $totalCopiesToday ?></div>
            </div>
        </div>

        <!-- SECTION 1: Users & MC Range Allocation -->
        <div id="adm-tab-users" class="admin-tab-pane">
            
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
                <h2 style="font-size: 18px; font-weight: 800; color: var(--ols-navy);">Carrier Dispatchers & Allocated MC Ranges</h2>
                <button type="button" class="btn-action" onclick="toggleAddCarrierModal()">+ Add New Dispatcher</button>
            </div>

            <!-- Dispatcher Table -->
            <div class="ols-table-responsive" style="margin-bottom: 25px;">
                <table class="ols-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Dispatcher Name</th>
                            <th>Username / Email</th>
                            <th>Live Status</th>
                            <th>Assigned MC Range</th>
                            <th>Current MC Position</th>
                            <th>Searches</th>
                            <th>Calls</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($carriers as $c): ?>
                        <tr>
                            <td>#<?= $c['id'] ?></td>
                            <td><strong><?= htmlspecialchars($c['name']) ?></strong></td>
                            <td><?= htmlspecialchars($c['username']) ?><br><span style="font-size:11px; color:#64748b;"><?= htmlspecialchars($c['email']) ?></span></td>
                            <td>
                                <?php if ($c['live_status'] === 'active'): ?>
                                    <span class="ols-badge authorized">Checked In</span>
                                <?php elseif ($c['live_status'] === 'break'): ?>
                                    <span class="ols-badge" style="background:#fef3c7; color:#b45309;">On Break</span>
                                <?php else: ?>
                                    <span class="ols-badge" style="background:#f1f5f9; color:#64748b;">Offline</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong>MC <?= htmlspecialchars($c['start_mc'] ?? 1739800) ?> – <?= htmlspecialchars($c['end_mc'] ?? 1750799) ?></strong><br>
                                <span style="font-size:11px; color:#64748b;"><?= htmlspecialchars($c['range_label'] ?? 'Sequential') ?></span>
                            </td>
                            <td>
                                <span style="font-weight:700; color:#0096c7;">MC <?= htmlspecialchars($c['current_mc'] ?? 1739800) ?></span>
                            </td>
                            <td><?= (int)($c['search_count'] ?? 0) ?></td>
                            <td><?= (int)($c['calls_count'] ?? 0) ?></td>
                            <td>
                                <div style="display:flex; gap:6px;">
                                    <button type="button" class="ols-btn-clock" onclick="openEditRangeModal(<?= $c['id'] ?>, '<?= htmlspecialchars($c['name']) ?>', <?= $c['start_mc'] ?? 1739800 ?>, <?= $c['end_mc'] ?? 1750799 ?>, <?= $c['current_mc'] ?? 1739800 ?>, '<?= htmlspecialchars($c['range_label'] ?? '') ?>')">Edit Range</button>
                                    <button type="button" class="ols-btn-clock" onclick="filterLogsByUser(<?= $c['id'] ?>)">View Logs</button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Modal / Form: Add New Dispatcher -->
            <div id="modal-add-carrier" class="form-section" style="display:none; border:2px solid #00d4ff;">
                <h3 style="font-size: 16px; font-weight: 800; color: #0a2540; margin-bottom: 15px;">Add New Carrier Dispatcher & Set MC Range</h3>
                <form id="form-create-carrier">
                    <div class="form-grid">
                        <div>
                            <label class="form-label">Full Name</label>
                            <input type="text" name="name" class="form-input" placeholder="e.g. John Doe" required>
                        </div>
                        <div>
                            <label class="form-label">Username</label>
                            <input type="text" name="username" class="form-input" placeholder="e.g. carrier2" required>
                        </div>
                        <div>
                            <label class="form-label">Email Address</label>
                            <input type="email" name="email" class="form-input" placeholder="carrier2@orientlogistics.com" required>
                        </div>
                        <div>
                            <label class="form-label">Password</label>
                            <input type="password" name="password" class="form-input" placeholder="••••••••" required>
                        </div>
                        <div>
                            <label class="form-label">Start MC Number</label>
                            <input type="number" name="start_mc" class="form-input" value="1750800" required>
                        </div>
                        <div>
                            <label class="form-label">End MC Number</label>
                            <input type="number" name="end_mc" class="form-input" value="1760000" required>
                        </div>
                        <div>
                            <label class="form-label">Initial Starting MC</label>
                            <input type="number" name="current_mc" class="form-input" value="1750800" required>
                        </div>
                        <div>
                            <label class="form-label">Range Label / Territory</label>
                            <input type="text" name="range_label" class="form-input" value="Midwest Territory">
                        </div>
                    </div>
                    <div style="margin-top: 18px; display:flex; gap:10px;">
                        <button type="submit" class="btn-action">Create Dispatcher</button>
                        <button type="button" class="btn-action" style="background:#cbd5e1; color:#0f172a;" onclick="toggleAddCarrierModal()">Cancel</button>
                    </div>
                </form>
            </div>

            <!-- Modal / Form: Edit MC Range -->
            <div id="modal-edit-range" class="form-section" style="display:none; border:2px solid #0096c7;">
                <h3 style="font-size: 16px; font-weight: 800; color: #0a2540; margin-bottom: 15px;">Modify MC Range for <span id="lbl-edit-user"></span></h3>
                <form id="form-update-range">
                    <input type="hidden" name="user_id" id="edit-user-id">
                    <div class="form-grid">
                        <div>
                            <label class="form-label">Start MC Number</label>
                            <input type="number" name="start_mc" id="edit-start-mc" class="form-input" required>
                        </div>
                        <div>
                            <label class="form-label">End MC Number</label>
                            <input type="number" name="end_mc" id="edit-end-mc" class="form-input" required>
                        </div>
                        <div>
                            <label class="form-label">Current MC Pointer</label>
                            <input type="number" name="current_mc" id="edit-current-mc" class="form-input" required>
                        </div>
                        <div>
                            <label class="form-label">Range Label / Territory</label>
                            <input type="text" name="range_label" id="edit-range-label" class="form-input">
                        </div>
                    </div>
                    <div style="margin-top: 18px; display:flex; gap:10px;">
                        <button type="submit" class="btn-action">Save MC Range</button>
                        <button type="button" class="btn-action" style="background:#cbd5e1; color:#0f172a;" onclick="closeEditRangeModal()">Cancel</button>
                    </div>
                </form>
            </div>

        </div>

        <!-- SECTION 2: User Activity Logs (Per-user detailed tracking) -->
        <div id="adm-tab-logs" class="admin-tab-pane" style="display: none;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; flex-wrap: wrap; gap: 10px;">
                <h2 style="font-size: 18px; font-weight: 800; color: var(--ols-navy);">Granular User Activity & Click-to-Copy Audit</h2>
                
                <div style="display: flex; gap: 10px;">
                    <select id="log-filter-user" class="form-input" style="width: 200px;">
                        <option value="0">All Dispatchers</option>
                        <?php foreach ($carriers as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?> (<?= htmlspecialchars($c['username']) ?>)</option>
                        <?php endforeach; ?>
                    </select>

                    <select id="log-filter-action" class="form-input" style="width: 150px;">
                        <option value="">All Actions</option>
                        <option value="search">Searches</option>
                        <option value="copy">Copies</option>
                    </select>

                    <button type="button" class="btn-action" onclick="fetchAdminLogs()">Apply Filter</button>
                </div>
            </div>

            <div class="ols-table-responsive">
                <table class="ols-table">
                    <thead>
                        <tr>
                            <th>Timestamp</th>
                            <th>Dispatcher</th>
                            <th>Action Type</th>
                            <th>Target / Field</th>
                            <th>Query / Copied Detail</th>
                            <th>Carrier Affected</th>
                            <th>IP Address</th>
                        </tr>
                    </thead>
                    <tbody id="tbl-admin-logs-body">
                        <tr><td colspan="7" style="text-align:center; padding:20px;">Select a filter and click Apply Filter or View Logs</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- SECTION 3: Shift Attendance & Sessions -->
        <div id="adm-tab-sessions" class="admin-tab-pane" style="display: none;">
            <h2 style="font-size: 18px; font-weight: 800; color: var(--ols-navy); margin-bottom: 15px;">Dispatcher Shift Attendance & Real-Time Clock Logs</h2>
            <div class="ols-table-responsive">
                <table class="ols-table">
                    <thead>
                        <tr>
                            <th>Session ID</th>
                            <th>Dispatcher</th>
                            <th>Check-in Time</th>
                            <th>Check-out Time</th>
                            <th>Break Duration</th>
                            <th>Status</th>
                            <th>MCs Covered</th>
                            <th>Calls Placed</th>
                            <th>Conversions</th>
                            <th>IP Address</th>
                        </tr>
                    </thead>
                    <tbody id="tbl-admin-sessions-body">
                        <tr><td colspan="10" style="text-align:center; padding:20px;">Loading sessions...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- SECTION 4: API & Proxy Settings (ADMIN ONLY) -->
        <div id="adm-tab-settings" class="admin-tab-pane" style="display: none;">
            <div class="form-section">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                    <div>
                        <h2 style="font-size: 18px; font-weight: 800; color: var(--ols-navy); margin-bottom: 4px;">FMCSA API & Datacenter Proxy Configuration</h2>
                        <p style="font-size: 13px; color: var(--ols-slate-500); margin:0;">Configure registered FMCSA credentials and US-based proxy parameters.</p>
                    </div>
                    <button type="button" class="btn-action btn-test" id="btn-test-fmcsa" onclick="testFmcsaApi()">⚡ Test FMCSA API Connection</button>
                </div>

                <div id="test-result-box" style="display: none; padding: 12px 16px; border-radius: 8px; margin-bottom: 18px; font-size: 13px; font-weight: 600;"></div>

                <form id="form-admin-settings">
                    <h3 style="font-size: 14px; font-weight: 800; color: var(--ols-slate-700); border-bottom: 1px solid var(--ols-border); padding-bottom: 6px; margin-bottom: 16px;">1. FMCSA API WebKey</h3>
                    <div class="form-grid" style="margin-bottom: 24px;">
                        <div>
                            <label class="form-label">FMCSA WebKey</label>
                            <input type="text" name="fmcsa_webkey" id="fmcsa-webkey" class="form-input" value="<?= htmlspecialchars($settings['fmcsa_webkey'] ?? '') ?>" style="font-family:monospace;">
                        </div>
                        <div>
                            <label class="form-label">Cache Duration (Hours)</label>
                            <input type="number" name="cache_hours" class="form-input" value="<?= htmlspecialchars($settings['cache_hours'] ?? '24') ?>">
                        </div>
                        <div>
                            <label class="form-label">Default VOIP Engine</label>
                            <select name="default_voip" class="form-input">
                                <option value="ringcentral" <?= (($settings['default_voip'] ?? '') === 'ringcentral') ? 'selected' : '' ?>>RingCentral</option>
                                <option value="8x8" <?= (($settings['default_voip'] ?? '') === '8x8') ? 'selected' : '' ?>>8x8 Work</option>
                                <option value="tel" <?= (($settings['default_voip'] ?? '') === 'tel') ? 'selected' : '' ?>>Native tel: protocol (MicroSIP / ZoIPer)</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Display SAFER Snapshot Link</label>
                            <select name="show_safer_link" class="form-input">
                                <option value="1" <?= (($settings['show_safer_link'] ?? '1') === '1') ? 'selected' : '' ?>>Yes, Show Link</option>
                                <option value="0" <?= (($settings['show_safer_link'] ?? '') === '0') ? 'selected' : '' ?>>No, Hide Link</option>
                            </select>
                        </div>
                    </div>

                    <h3 style="font-size: 14px; font-weight: 800; color: var(--ols-slate-700); border-bottom: 1px solid var(--ols-border); padding-bottom: 6px; margin-bottom: 16px;">2. Datacenter Proxy Settings (Bypass 403 Forbidden)</h3>
                    <div class="form-grid" style="margin-bottom: 24px;">
                        <div>
                            <label class="form-label">Enable Proxy</label>
                            <select name="proxy_enabled" class="form-input">
                                <option value="0" <?= (($settings['proxy_enabled'] ?? '0') === '0') ? 'selected' : '' ?>>Disabled (Direct Connection)</option>
                                <option value="1" <?= (($settings['proxy_enabled'] ?? '') === '1') ? 'selected' : '' ?>>Enabled</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Proxy Protocol</label>
                            <select name="proxy_type" class="form-input">
                                <option value="http" <?= (($settings['proxy_type'] ?? '') === 'http') ? 'selected' : '' ?>>HTTP</option>
                                <option value="https" <?= (($settings['proxy_type'] ?? '') === 'https') ? 'selected' : '' ?>>HTTPS</option>
                                <option value="socks5" <?= (($settings['proxy_type'] ?? '') === 'socks5') ? 'selected' : '' ?>>SOCKS5</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Proxy Host / IP</label>
                            <input type="text" name="proxy_host" class="form-input" value="<?= htmlspecialchars($settings['proxy_host'] ?? '') ?>" placeholder="e.g. 192.168.1.1 or proxy.us.net">
                        </div>
                        <div>
                            <label class="form-label">Proxy Port</label>
                            <input type="text" name="proxy_port" class="form-input" value="<?= htmlspecialchars($settings['proxy_port'] ?? '') ?>" placeholder="e.g. 8080 or 1080">
                        </div>
                        <div>
                            <label class="form-label">Proxy Username (Optional)</label>
                            <input type="text" name="proxy_user" class="form-input" value="<?= htmlspecialchars($settings['proxy_user'] ?? '') ?>">
                        </div>
                        <div>
                            <label class="form-label">Proxy Password (Optional)</label>
                            <input type="password" name="proxy_pass" class="form-input" value="<?= htmlspecialchars($settings['proxy_pass'] ?? '') ?>">
                        </div>
                    </div>

                    <button type="submit" class="btn-action">Save Admin Settings</button>
                </form>
            </div>
        </div>

    </div>

    <script src="/assets/js/admin.js"></script>
</body>
</html>

