# Orient Logistics - Standalone Carrier Intelligence & Calling Rig

This application is built in **pure PHP 8.2+ (MVC architecture)** and custom **HTML5 / CSS3 / JavaScript** with **zero WordPress overhead**, connected to MariaDB / MySQL database `carrier-lookup`.

---

## 🚀 Key Improvements Over the WordPress Plugin

1. **Ultra Fast & Clean Execution:**
   - Lightweight MVC architecture (`Core\App`, `Core\Router`, `Core\Database`, `Core\FMCSAEngine`, `Core\Request`, `Core\Response`, `Core\Session`).
   - Removed all heavy WordPress core dependencies, hooks, and database overhead.
   - Response latency dropped to sub-50ms for local queries and concurrent FMCSA multi-cURL lookups.

2. **Strict Two-Tier Role-Based Access Control:**
   - **`admin`**: Exclusive access to the `/admin` portal. Carrier dispatchers attempting to access `/admin` are instantly blocked and redirected to `/carrier`.
   - **`carrier`**: Access strictly bounded to the `/carrier` workspace and calling rig. All backend administrative API keys, proxies, and user controls are completely inaccessible to carriers.

3. **Admin Settings & API Management (Admin Area Only):**
   - FMCSA WebKey configuration.
   - Datacenter US-based Proxy routing (HTTP, HTTPS, SOCKS5) to bypass FMCSA 403 Forbidden errors.
   - VOIP Dialer configuration (RingCentral, 8x8 Work, or Native `tel:`).
   - Real-time connection testing button (`⚡ Test FMCSA API Connection`).

4. **Dynamic Per-User MC Range Allocation:**
   - Admin can create carrier accounts and assign dedicated start MC, end MC, and initial pointer positions (e.g., `MC 1740000 - 1755000` with custom territory labels).
   - Admin can reassign or adjust any user's active range at any time.

5. **Granular Per-User Audit & Activity Tracking:**
   - Tracks searches, target lookups, DOT/MC references, and user IP addresses.
   - Real-time **click-to-copy audits** (who copied which carrier's phone, email, or address, and when).
   - Dispatcher shift attendance with Shift Elapsed Timer, Break In / Break Out tracking, and Check Out actions.
   - Comprehensive call logging with outcomes, callback reminders, and notes.

---

## 🔑 Default Sign-In Credentials

| Role | Username | Password | Default Area |
| :--- | :--- | :--- | :--- |
| **Admin** | `admin` | `admin123` | `http://localhost:8000/admin` |
| **Carrier / Dispatcher** | `carrier1` | `carrier123` | `http://localhost:8000/carrier` |

---

## 📁 Directory Structure

```text
D:\Abdul Moeez\orientlogisticssolution.com\carrier-look-up\
├── app/
│   ├── Controllers/
│   │   ├── AdminController.php
│   │   ├── AuthController.php
│   │   └── CarrierController.php
├── config/
│   └── app.php
├── core/
│   ├── App.php
│   ├── Controller.php
│   ├── Database.php
│   ├── FMCSAEngine.php
│   ├── Request.php
│   ├── Response.php
│   ├── Router.php
│   └── Session.php
├── public/
│   ├── assets/
│   │   ├── css/app.css
│   │   └── js/
│   │       ├── admin.js
│   │       └── workspace.js
│   ├── index.php
│   └── .htaccess
├── routes/
│   └── web.php
├── views/
│   ├── admin/dashboard.php
│   ├── auth/login.php
│   └── carrier/dashboard.php
├── storage/
│   └── logs/
└── .env
```

---

## 🏃 Running the Application

In a terminal:
```bash
cd "D:\Abdul Moeez\orientlogisticssolution.com\carrier-look-up"
php -S 127.0.0.1:8000 -t public
```
Then visit `http://127.0.0.1:8000` in your web browser.

