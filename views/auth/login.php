<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In - Orient Logistics Carrier Intelligence</title>
    <link rel="stylesheet" href="/assets/css/app.css">
    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background: radial-gradient(circle at top, #0d3866 0%, #06192c 100%);
            margin: 0;
            padding: 20px;
        }
        .login-card {
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
            width: 100%;
            max-width: 420px;
            padding: 40px 36px;
        }
        .login-logo {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            margin-bottom: 24px;
        }
        .logo-box {
            width: 44px;
            height: 44px;
            background: linear-gradient(135deg, #00d4ff 0%, #0077b6 100%);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            box-shadow: 0 4px 12px rgba(0, 212, 255, 0.4);
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-label {
            display: block;
            font-size: 13px;
            font-weight: 700;
            color: #334155;
            margin-bottom: 6px;
        }
        .form-control {
            width: 100%;
            height: 44px;
            padding: 0 14px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 14px;
            background: #f8fafc;
            color: #0f172a;
            outline: none;
            transition: all 0.2s;
        }
        .form-control:focus {
            border-color: #0096c7;
            background: #ffffff;
            box-shadow: 0 0 0 3px rgba(0, 150, 199, 0.15);
        }
        .btn-submit {
            width: 100%;
            height: 44px;
            background: linear-gradient(135deg, #0a2540 0%, #16365c 100%);
            color: #ffffff;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            margin-top: 10px;
            transition: opacity 0.2s;
        }
        .btn-submit:hover {
            opacity: 0.95;
        }
        .login-alert {
            background: #fee2e2;
            color: #991b1b;
            padding: 10px 14px;
            border-radius: 8px;
            font-size: 13px;
            margin-bottom: 20px;
            display: none;
        }
        .credentials-pill {
            margin-top: 24px;
            background: #f1f5f9;
            border-radius: 8px;
            padding: 12px;
            font-size: 12px;
            color: #475569;
        }
    </style>
</head>
<body>

<div class="login-card">
    <div class="login-logo">
        <div class="logo-box">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="1" y="3" width="15" height="13"></rect><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon><circle cx="5.5" cy="18.5" r="2.5"></circle><circle cx="18.5" cy="18.5" r="2.5"></circle></svg>
        </div>
        <div style="font-size: 20px; font-weight: 800; color: #0a2540;">Orient Logistics</div>
    </div>

    <h2 style="font-size: 18px; font-weight: 800; color: #0f172a; margin-bottom: 6px; text-align: center;">Carrier Intelligence Portal</h2>
    <p style="font-size: 13px; color: #64748b; text-align: center; margin-bottom: 24px;">Sign in to access your calling rig workspace</p>

    <div class="login-alert" id="login-error"></div>

    <form id="login-form">
        <div class="form-group">
            <label class="form-label" for="username">Username or Email</label>
            <input type="text" id="username" name="username" class="form-control" placeholder="e.g. admin or carrier1" required autofocus>
        </div>
        <div class="form-group">
            <label class="form-label" for="password">Password</label>
            <input type="password" id="password" name="password" class="form-control" placeholder="••••••••" required>
        </div>
        <button type="submit" class="btn-submit" id="btn-login">Sign In</button>
    </form>

    <div class="credentials-pill">
        <div style="font-weight: 700; margin-bottom: 4px; color: #0f172a;">Quick Access Credentials:</div>
        <div>👑 <strong>Admin:</strong> admin / admin123</div>
        <div>📞 <strong>Carrier / Dispatcher:</strong> carrier1 / carrier123</div>
    </div>
</div>

<script>
document.getElementById('login-form').addEventListener('submit', function(e) {
    e.preventDefault();
    var errEl = document.getElementById('login-error');
    var btn = document.getElementById('btn-login');
    errEl.style.display = 'none';
    btn.disabled = true;
    btn.innerText = 'Signing in...';

    var formData = new FormData(this);

    fetch('/login', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
    })
    .then(function(res) { return res.json(); })
    .then(function(data) {
        if (data.success && data.redirect) {
            window.location.href = data.redirect;
        } else {
            errEl.innerText = data.message || 'Login failed. Please check credentials.';
            errEl.style.display = 'block';
            btn.disabled = false;
            btn.innerText = 'Sign In';
        }
    })
    .catch(function() {
        errEl.innerText = 'Network error. Please try again.';
        errEl.style.display = 'block';
        btn.disabled = false;
        btn.innerText = 'Sign In';
    });
});
</script>

</body>
</html>

