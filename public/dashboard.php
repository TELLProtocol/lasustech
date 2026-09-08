<?php

/* Database file */
require_once('config/dbPlay.php');
/* Session Manager file */
require_once('config/sessionManager.php');

/* Initialize session manager */
use SessionManager\SessionManager;

$ses = new SessionManager(1800);

/* Start Session */
$ses->Start();


/**
 * Lecturer auth
 * ─────────────
 * This page expects a lecturer to already be signed in (via the login flow) with the tutor's identity stored in $ses.
 */

/* Check if session has expired */
if ($ses->isExpired()) {
	/* Session expired, user needs to login again */
    header('Location: /login.php');
    exit;
}

/* Check if session is authenticated */
if ($ses->get('authenticated') !== true) {
	/* Session authentication check failed, user needs to login again */
    header('Location: /login.php');
    exit;
}

/* Verify the client fingerprint */
$ses->bindToClient();


/*
 * Retrieve authenticated user information
 * from the session.
 */
 
/* Get lecturer ID from authenticated session */
$tutorId = $ses->get('usrID');
/* Get Lecturer email address from authenticated session */
$tutorMail = $ses->get('usrEmaile');

if (!$tutorId || !$tutorMail) {
	/* User information is empty. Destroy session and user must signin again */
	$session->destroy();
	header('Location: /login.php');
    exit;
}




/*  */
/*  */

$initials = 'L';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>LASUSTECH e-Attendance — Lecturer Portal</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,400&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet" />
  <script src="./dist/js/qrcode@1_5_1/qrcode.min.js"></script>
  <style>
    *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

    :root {
      --bg:        #f5f3ef;
      --surface:   #ffffff;
      --surface2:  #f0ede8;
      --border:    #e2ddd6;
      --accent:    #c2410c;      /* burnt orange — authority, action */
      --accent2:   #15803d;      /* forest green — success */
      --accent3:   #b45309;      /* amber — warning / QR */
      --danger:    #dc2626;
      --text:      #1c1917;
      --muted:     #78716c;
      --radius:    10px;
      --font:      'DM Sans', sans-serif;
      --mono:      'DM Mono', monospace;
      --shadow:    0 1px 3px rgba(0,0,0,.08), 0 4px 12px rgba(0,0,0,.05);
      --shadow-lg: 0 8px 32px rgba(0,0,0,.12);
    }

    body {
      font-family: var(--font);
      background: var(--bg);
      color: var(--text);
      min-height: 100vh;
      display: flex;
      flex-direction: column;
    }

    /* ── Scrollbar ── */
    ::-webkit-scrollbar { width: 6px; height: 6px; }
    ::-webkit-scrollbar-track { background: var(--surface2); }
    ::-webkit-scrollbar-thumb { background: var(--border); border-radius: 3px; }

    /* ════════════════════════════════════
       HEADER
    ════════════════════════════════════ */
    header {
      background: var(--surface);
      border-bottom: 1px solid var(--border);
      position: sticky; top: 0; z-index: 200;
      box-shadow: var(--shadow);
    }
    .header-inner {
      max-width: 1380px; margin: 0 auto;
      padding: 0 28px;
      display: flex; align-items: center; justify-content: space-between;
      height: 58px;
    }
    .logo { display: flex; align-items: center; gap: 10px; text-decoration: none; }
    .logo-mark {
      width: 32px; height: 32px; border-radius: 7px;
      background: var(--accent); display: grid; place-items: center;
      color: #fff; font-size: 15px;
    }
    .logo-text { font-size: 16px; font-weight: 700; color: var(--text); letter-spacing: -.3px; }
    .logo-text em { font-style: normal; color: var(--accent); }

    .header-right { display: flex; align-items: center; gap: 14px; }

    .tutor-pill {
      display: flex; align-items: center; gap: 10px;
      background: var(--surface2); border: 1px solid var(--border);
      border-radius: 30px; padding: 5px 14px 5px 6px;
    }
    .avatar {
      width: 30px; height: 30px; border-radius: 50%;
      background: var(--accent); display: grid; place-items: center;
      font-size: 11px; font-weight: 700; color: #fff; letter-spacing: .5px;
    }
    .tutor-name { font-size: 14px; font-weight: 600; }
    .tutor-id   { font-size: 11px; color: var(--muted); font-family: var(--mono); }

    /* ════════════════════════════════════
       LAYOUT
    ════════════════════════════════════ */
    .app-body {
      display: grid;
      grid-template-columns: 230px 1fr;
      max-width: 1380px; margin: 0 auto; width: 100%;
      padding: 28px 28px; gap: 28px; flex: 1;
    }

    /* ── Sidebar ── */
    .sidebar {
      background: var(--surface); border: 1px solid var(--border);
      border-radius: var(--radius); padding: 10px 0;
      height: fit-content; position: sticky; top: 86px;
      box-shadow: var(--shadow);
    }
    .sidebar-group { padding: 6px 0; }
    .sidebar-label {
      font-size: 10px; font-weight: 700; letter-spacing: 1.2px;
      text-transform: uppercase; color: var(--muted);
      padding: 4px 16px 8px;
    }
    .nav-item {
      display: flex; align-items: center; gap: 11px;
      padding: 9px 16px; cursor: pointer;
      border-left: 3px solid transparent;
      transition: all .18s; color: var(--muted);
      font-size: 14px; font-weight: 500; text-decoration: none;
    }
    .nav-item:hover { color: var(--text); background: var(--surface2); }
    .nav-item.active { color: var(--accent); border-left-color: var(--accent); background: #fff4ed; }
    .nav-item i { width: 15px; text-align: center; font-size: 13px; }
    .sidebar-divider { border: none; border-top: 1px solid var(--border); margin: 4px 0; }

    /* ── Main ── */
    main { min-width: 0; }
    .page { display: none; }
    .page.active { display: block; animation: fadein .22s ease; }
    @keyframes fadein { from{opacity:0;transform:translateY(5px)} to{opacity:1;transform:none} }

    .page-header {
      display: flex; align-items: flex-start; justify-content: space-between;
      margin-bottom: 24px; gap: 16px; flex-wrap: wrap;
    }
    .page-title { font-size: 22px; font-weight: 700; }
    .page-title span { color: var(--accent); }
    .page-sub { font-size: 13px; color: var(--muted); margin-top: 3px; }

    /* ════════════════════════════════════
       STAT CARDS
    ════════════════════════════════════ */
    .stats-row { display: grid; grid-template-columns: repeat(auto-fill, minmax(190px, 1fr)); gap: 16px; margin-bottom: 26px; }
    .stat-card {
      background: var(--surface); border: 1px solid var(--border);
      border-radius: var(--radius); padding: 18px 20px;
      box-shadow: var(--shadow); position: relative; overflow: hidden;
      transition: box-shadow .2s, transform .2s;
    }
    .stat-card:hover { box-shadow: var(--shadow-lg); transform: translateY(-2px); }
    .stat-card::before {
      content:''; position:absolute; top:0; left:0; right:0; height:3px;
      background: var(--card-accent, var(--accent));
    }
    .stat-label { font-size: 12px; color: var(--muted); font-weight: 500; margin-bottom: 8px; }
    .stat-value { font-size: 28px; font-weight: 700; line-height: 1; }
    .stat-sub   { font-size: 12px; color: var(--muted); margin-top: 5px; }
    .stat-icon  { position: absolute; right: 16px; top: 50%; transform: translateY(-50%); font-size: 28px; opacity: .1; }

    /* ════════════════════════════════════
       TABLES
    ════════════════════════════════════ */
    .table-wrap {
      background: var(--surface); border: 1px solid var(--border);
      border-radius: var(--radius); overflow: hidden;
      margin-bottom: 24px; box-shadow: var(--shadow);
    }
    .table-head {
      padding: 14px 20px; border-bottom: 1px solid var(--border);
      display: flex; align-items: center; justify-content: space-between;
      background: var(--surface2);
    }
    .table-head h3 { font-size: 14px; font-weight: 700; }
    .table-head-right { display: flex; gap: 8px; align-items: center; }
    table { width: 100%; border-collapse: collapse; }
    thead { background: var(--surface2); }
    th {
      padding: 10px 16px; text-align: left; font-size: 11px;
      font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: .6px;
      border-bottom: 1px solid var(--border);
    }
    td { padding: 12px 16px; font-size: 13.5px; border-top: 1px solid var(--border); }
    tbody tr:hover { background: var(--surface2); }
    code { font-family: var(--mono); font-size: 11.5px; color: var(--muted); background: var(--surface2); padding: 2px 6px; border-radius: 4px; }

    /* ════════════════════════════════════
       BADGES
    ════════════════════════════════════ */
    .badge {
      display: inline-flex; align-items: center; gap: 4px;
      padding: 3px 9px; border-radius: 20px; font-size: 11px; font-weight: 700; letter-spacing: .2px;
    }
    .badge-green  { background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }
    .badge-orange { background: #fff7ed; color: #c2410c; border: 1px solid #fed7aa; }
    .badge-amber  { background: #fffbeb; color: #b45309; border: 1px solid #fde68a; }
    .badge-gray   { background: var(--surface2); color: var(--muted); border: 1px solid var(--border); }
    .badge-red    { background: #fee2e2; color: #dc2626; border: 1px solid #fecaca; }

    /* ════════════════════════════════════
       BUTTONS
    ════════════════════════════════════ */
    .btn {
      display: inline-flex; align-items: center; justify-content: center;
      gap: 7px; padding: 9px 18px; border-radius: 8px;
      font-family: var(--font); font-size: 13.5px; font-weight: 600;
      cursor: pointer; border: none; transition: all .18s; white-space: nowrap;
    }
    .btn-primary  { background: var(--accent); color: #fff; }
    .btn-primary:hover  { background: #9a3412; }
    .btn-success  { background: var(--accent2); color: #fff; }
    .btn-success:hover  { background: #166534; }
    .btn-amber    { background: var(--accent3); color: #fff; }
    .btn-amber:hover    { background: #92400e; }
    .btn-outline  { background: transparent; color: var(--text); border: 1px solid var(--border); }
    .btn-outline:hover  { background: var(--surface2); }
    .btn-outline.is-active { background: var(--accent); color: #fff; border-color: var(--accent); }
    .btn-danger   { background: var(--danger); color: #fff; }
    .btn-danger:hover   { background: #b91c1c; }
    .btn-sm { padding: 5px 12px; font-size: 12px; border-radius: 6px; }

    /* ════════════════════════════════════
       FORMS
    ════════════════════════════════════ */
    .card {
      background: var(--surface); border: 1px solid var(--border);
      border-radius: var(--radius); padding: 22px;
      box-shadow: var(--shadow);
    }
    .card-title { font-size: 15px; font-weight: 700; margin-bottom: 4px; }
    .card-sub   { font-size: 13px; color: var(--muted); margin-bottom: 18px; }

    .form-group { display: flex; flex-direction: column; gap: 5px; margin-bottom: 14px; }
    .form-group label { font-size: 12px; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: .5px; }
    .form-group input, .form-group select {
      background: var(--surface2); border: 1.5px solid var(--border);
      border-radius: 8px; padding: 9px 13px;
      color: var(--text); font-family: var(--font); font-size: 14px; outline: none;
      transition: border-color .18s;
    }
    .form-group input:focus, .form-group select:focus { border-color: var(--accent); background: #fff; }
    .form-group input::placeholder { color: #c4bdb7; }
    .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }

    /* ════════════════════════════════════
       QR CODE
    ════════════════════════════════════ */
    .qr-panel {
      display: flex; flex-direction: column; align-items: center;
      background: var(--surface); border: 1px solid var(--border);
      border-radius: var(--radius); padding: 28px;
      box-shadow: var(--shadow); text-align: center;
    }
    #qr-canvas { margin: 20px auto; border-radius: 8px; overflow: hidden; display: none; }
    .qr-placeholder-box {
      width: 180px; height: 180px;
      border: 2px dashed var(--border); border-radius: 10px;
      display: flex; flex-direction: column; align-items: center; justify-content: center;
      gap: 10px; color: var(--muted); margin: 20px auto; font-size: 13px;
    }
    .qr-placeholder-box i { font-size: 42px; color: var(--border); }
    .qr-url-box {
      background: var(--surface2); border: 1px solid var(--border);
      border-radius: 8px; padding: 10px 14px;
      font-family: var(--mono); font-size: 11px; color: var(--muted);
      word-break: break-all; text-align: left; margin-top: 14px; width: 100%;
      max-height: 80px; overflow-y: auto; display: none;
    }
    .qr-expiry { font-size: 12px; color: var(--muted); margin-top: 10px; }
    .qr-expiry.active { color: var(--accent2); font-weight: 600; }
    .qr-expiry.expired { color: var(--danger); font-weight: 600; }

    /* countdown ring */
    .countdown-ring { position: relative; width: 56px; height: 56px; margin: 0 auto 8px; }
    .countdown-ring svg { transform: rotate(-90deg); }
    .countdown-ring .track { fill: none; stroke: var(--surface2); stroke-width: 4; }
    .countdown-ring .fill  { fill: none; stroke: var(--accent2); stroke-width: 4; stroke-linecap: round;
      stroke-dasharray: 151; stroke-dashoffset: 0; transition: stroke-dashoffset 1s linear, stroke .5s; }
    .countdown-ring .label { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center;
      font-size: 13px; font-weight: 700; font-family: var(--mono); }

    /* ════════════════════════════════════
       PROGRESS
    ════════════════════════════════════ */
    .progress-track { background: var(--surface2); border-radius: 4px; height: 7px; overflow: hidden; }
    .progress-fill  { height: 100%; border-radius: 4px; transition: width .5s; }

    /* ════════════════════════════════════
       MODAL
    ════════════════════════════════════ */
    .modal-overlay {
      position: fixed; inset: 0; background: rgba(0,0,0,.35);
      display: none; place-items: center; z-index: 500; padding: 20px;
    }
    .modal-overlay.open { display: grid; animation: fadein .2s; }
    .modal {
      background: var(--surface); border: 1px solid var(--border);
      border-radius: 14px; padding: 28px; max-width: 520px; width: 100%;
      box-shadow: var(--shadow-lg); position: relative;
    }
    .modal-close {
      position: absolute; top: 16px; right: 16px;
      background: var(--surface2); border: none; width: 30px; height: 30px;
      border-radius: 50%; cursor: pointer; font-size: 14px; color: var(--muted);
      display: grid; place-items: center; transition: background .15s;
    }
    .modal-close:hover { background: var(--border); }
    .modal-title { font-size: 18px; font-weight: 700; margin-bottom: 6px; }
    .modal-sub   { font-size: 13px; color: var(--muted); margin-bottom: 20px; }

    /* ════════════════════════════════════
       TOAST
    ════════════════════════════════════ */
    #toast {
      position: fixed; bottom: 24px; right: 24px; z-index: 9999;
      background: var(--surface); border: 1px solid var(--border);
      border-radius: 10px; padding: 13px 18px;
      display: flex; align-items: center; gap: 11px; max-width: 360px;
      box-shadow: var(--shadow-lg);
      transform: translateY(20px); opacity: 0; pointer-events: none; transition: all .28s;
    }
    #toast.show { transform: none; opacity: 1; pointer-events: auto; }
    #toast.success .ti { color: var(--accent2); }
    #toast.error   .ti { color: var(--danger); }
    #toast.info    .ti { color: var(--accent3); }
    #toast .tm { font-size: 13.5px; line-height: 1.45; }

    /* ════════════════════════════════════
       EMPTY STATE
    ════════════════════════════════════ */
    .empty-state { text-align: center; padding: 44px 20px; color: var(--muted); }
    .empty-state i { font-size: 36px; margin-bottom: 10px; display: block; opacity: .4; }
    .empty-state p { font-size: 14px; }

    /* ════════════════════════════════════
       RESPONSIVE
    ════════════════════════════════════ */
    @media(max-width:960px) {
      .app-body { grid-template-columns: 1fr; }
      .sidebar  { display: none; }
    }
    @media(max-width:640px) {
      .stats-row { grid-template-columns: 1fr 1fr; }
      .form-row  { grid-template-columns: 1fr; }
      .header-inner { padding: 0 16px; }
    }
  </style>
</head>
<body>

<!-- ════════════ HEADER ════════════ -->
<header>
  <div class="header-inner">
    <a class="logo" href="#">
      <div class="logo-mark"><i class="fas fa-clipboard-check"></i></div>
      <div class="logo-text">LASUSTECH <em>Attend</em></div>
    </a>
    <div class="header-right">
      <div class="tutor-pill">
        <div class="avatar"><?php echo htmlspecialchars($initials); ?></div>
        <div>
          <div class="tutor-name"><?php echo htmlspecialchars($tutorMail); ?></div>
          <div class="tutor-id">ID: <?php echo htmlspecialchars($tutorId); ?></div>
        </div>
      </div>
    </div>
  </div>
</header>

<!-- ════════════ BODY ════════════ -->
<div class="app-body">

  <!-- ── Sidebar ── -->
  <aside class="sidebar">
    <div class="sidebar-group">
      <div class="sidebar-label">Overview</div>
      <a class="nav-item active" data-page="dashboard"><i class="fas fa-gauge-high"></i> Dashboard</a>
    </div>

    <hr class="sidebar-divider" />

    <div class="sidebar-group">
      <div class="sidebar-label">Sessions</div>
      <a class="nav-item" data-page="create-session"><i class="fas fa-qrcode"></i> Create Session</a>
    </div>

    <hr class="sidebar-divider" />

    <div class="sidebar-group">
      <div class="sidebar-label">Attendance</div>
      <a class="nav-item" data-page="attendance"><i class="fas fa-list-check"></i> Attendance Viewer</a>
    </div>
  </aside>

  <!-- ── Main ── -->
  <main>

    <!-- ══════════════════════════════
         PAGE: DASHBOARD
    ══════════════════════════════ -->
    <div class="page active" id="page-dashboard">
      <div class="page-header">
        <div>
          <div class="page-title">Lecturer <span>Dashboard</span></div>
          <div class="page-sub">Welcome back, <strong><?php echo htmlspecialchars($tutorMail); ?></strong> · Tutor ID <span style="font-family:var(--mono)"><?php echo htmlspecialchars($tutorId); ?></span></div>
        </div>
        <button class="btn btn-primary" onclick="navigate('create-session')">
          <i class="fas fa-qrcode"></i> New Session
        </button>
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;align-items:start">
        <div class="card">
          <div class="card-title">Create a session</div>
          <div class="card-sub">Generate a QR code students scan to sign attendance for any course.</div>
          <button class="btn btn-primary" style="width:100%" onclick="navigate('create-session')">
            <i class="fas fa-qrcode"></i> Create Session
          </button>
        </div>

        <div class="card">
          <div class="card-title">Jump to a course</div>
          <div class="card-sub">Open the attendance viewer straight to a course's sessions and records.</div>
          <div class="form-group" style="margin-bottom:12px">
            <label>Course code</label>
            <input type="text" id="dash-course-jump" placeholder="e.g. CSC301" />
          </div>
          <button class="btn btn-outline" style="width:100%" onclick="jumpToCourse()">
            <i class="fas fa-arrow-right"></i> View Attendance
          </button>
        </div>
      </div>

      <div class="table-wrap" style="margin-top:24px">
        <div class="table-head"><h3>Sessions created this visit</h3></div>
        <table>
          <thead><tr><th>Course</th><th>Session ID</th><th>Created At</th><th></th></tr></thead>
          <tbody id="dash-recent-tbody">
            <tr><td colspan="4"><div class="empty-state" style="padding:20px"><i class="fas fa-clock-rotate-left"></i><p>Sessions you create will show up here for this visit.</p></div></td></tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ══════════════════════════════
         PAGE: CREATE SESSION + QR
    ══════════════════════════════ -->
    <div class="page" id="page-create-session">
      <div class="page-header">
        <div>
          <div class="page-title">Create <span>Session</span></div>
          <div class="page-sub">Generate a QR link students scan to sign attendance</div>
        </div>
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;align-items:start">

        <!-- Form -->
        <div>
          <div class="card" style="margin-bottom:18px">
            <div class="card-title">Session details</div>
            <div class="card-sub">Choose a course and set a unique session ID · creating as <strong><?php echo htmlspecialchars($tutorMail); ?></strong></div>

            <div class="form-group">
              <label>Year &amp; Semester</label>
              <select id="cs-year-semester">
                <option value="">— select year &amp; semester —</option>
                <option value="year_one:first_semester">Year 1 — First Semester</option>
                <option value="year_one:second_semester">Year 1 — Second Semester</option>
                <option value="year_two:first_semester">Year 2 — First Semester</option>
                <option value="year_two:second_semester">Year 2 — Second Semester</option>
                <option value="year_three:first_semester">Year 3 — First Semester</option>
                <option value="year_three:second_semester">Year 3 — Second Semester</option>
                <option value="year_four:first_semester">Year 4 — First Semester</option>
                <option value="year_four:second_semester">Year 4 — Second Semester</option>
              </select>
            </div>
            <div class="form-group">
              <label>Course</label>
              <select id="cs-course-select" disabled>
                <option value="">— select year &amp; semester first —</option>
              </select>
            </div>
            <div class="form-group">
              <label>Session ID</label>
              <input type="text" id="session-id-input" placeholder="e.g. 4A4A23Z" maxlength="30"/>
            </div>

            <div style="display:flex;gap:10px">
              <button class="btn btn-amber" style="flex:1" onclick="autoSessionId()">
                <i class="fas fa-dice"></i> Auto-generate ID
              </button>
              <button class="btn btn-primary" style="flex:1" onclick="createSession()">
                <i class="fas fa-calendar-plus"></i> Create Session
              </button>
            </div>
          </div>

          <!-- Existing sessions for chosen course -->
          <div class="table-wrap" style="margin-bottom:0">
            <div class="table-head"><h3 id="sessions-list-title">Sessions for this course</h3></div>
            <table>
              <thead><tr><th>Session ID</th><th>Created At</th><th>Sign-ins</th><th>Status</th></tr></thead>
              <tbody id="sessions-tbody">
                <tr><td colspan="4"><div class="empty-state" style="padding:20px"><i class="fas fa-calendar-xmark"></i><p>Select a course to see its sessions.</p></div></td></tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- QR Panel -->
        <div class="qr-panel">
          <div class="card-title">QR Code</div>
          <div class="card-sub" style="margin-bottom:0">Students scan this to sign attendance</div>

          <div class="qr-placeholder-box" id="qr-placeholder">
            <i class="fas fa-qrcode"></i>
            <span>QR appears after creating a session</span>
          </div>
          <div id="qr-canvas"></div>

          <!-- Countdown -->
          <div id="countdown-wrap" style="display:none;margin-top:8px">
            <div class="countdown-ring">
              <svg viewBox="0 0 54 54" width="54" height="54">
                <circle class="track" cx="27" cy="27" r="24"/>
                <circle class="fill"  cx="27" cy="27" r="24" id="cring"/>
              </svg>
              <div class="label" id="clabel">30:00</div>
            </div>
            <div class="qr-expiry active" id="expiry-text">Valid for a while</div>
          </div>
          <div id="expired-notice" style="display:none;margin-top:8px;font-size:13px;color:var(--danger);font-weight:600">
            <i class="fas fa-clock"></i> Session expired — create a new one
          </div>

          <div class="qr-url-box" id="qr-url-box"></div>

          <div style="display:flex;gap:10px;margin-top:16px;width:100%">
            <button class="btn btn-outline" style="flex:1" id="copy-url-btn" onclick="copyURL()" disabled>
              <i class="fas fa-copy"></i> Copy URL
            </button>
            <button class="btn btn-outline" style="flex:1" id="print-qr-btn" onclick="printQR()" disabled>
              <i class="fas fa-print"></i> Print QR
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- ══════════════════════════════
         PAGE: ATTENDANCE VIEWER
    ══════════════════════════════ -->
    <div class="page" id="page-attendance">
      <div class="page-header">
        <div>
          <div class="page-title">Attendance <span>Viewer</span></div>
          <div class="page-sub">Past and present attendance, by session, by course, or both</div>
        </div>
        <div style="display:flex;gap:8px" id="av-view-toggle">
          <button class="btn btn-outline btn-sm is-active" data-view="both" onclick="setAttendanceView('both')">Combined</button>
          <button class="btn btn-outline btn-sm" data-view="course" onclick="setAttendanceView('course')">By Course</button>
          <button class="btn btn-outline btn-sm" data-view="session" onclick="setAttendanceView('session')">By Session</button>
        </div>
      </div>

      <div class="card" style="margin-bottom:20px">
        <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end">
          <div class="form-group" style="margin-bottom:0;flex:1;min-width:180px">
            <label>Course code</label>
            <input type="text" id="av-course-input" placeholder="e.g. CSC301" />
          </div>
          <div class="form-group" style="margin-bottom:0;flex:1;min-width:180px">
            <label>Session</label>
            <select id="av-session-select">
              <option value="">— load a course first —</option>
            </select>
          </div>
          <button class="btn btn-primary" onclick="loadAttendance()"><i class="fas fa-rotate"></i> Load</button>
        </div>
      </div>

      <!-- Course-wide block -->
      <div id="av-course-block" style="display:none">
        <div class="stats-row" id="av-course-stats"></div>
        <div class="table-wrap">
          <div class="table-head">
            <h3>Per-Student Attendance</h3>
            <span id="av-course-label" style="font-size:12px;color:var(--muted)"></span>
          </div>
          <table>
            <thead><tr><th>Reg. No.</th><th>Sessions Attended</th><th>Out of</th><th>Rate</th><th>Status</th></tr></thead>
            <tbody id="av-course-tbody">
              <tr><td colspan="5"><div class="empty-state"><i class="fas fa-chart-bar"></i><p>Load a course to see per-student attendance.</p></div></td></tr>
            </tbody>
          </table>
        </div>
        <div class="table-wrap">
          <div class="table-head"><h3>Sessions</h3></div>
          <table>
            <thead><tr><th>Session ID</th><th>Created At</th><th>Sign-ins</th><th></th></tr></thead>
            <tbody id="av-sessions-tbody"></tbody>
          </table>
        </div>
      </div>

      <!-- Session-specific block -->
      <div id="av-session-block" style="display:none">
        <div class="table-wrap">
          <div class="table-head">
            <h3>Attendees</h3>
            <span id="av-session-count" style="font-size:12px;color:var(--muted)"></span>
          </div>
          <table>
            <thead><tr><th>#</th><th>Reg. No.</th><th>Signed At</th></tr></thead>
            <tbody id="av-session-tbody">
              <tr><td colspan="3"><div class="empty-state"><i class="fas fa-users"></i><p>Select a session to load its roster.</p></div></td></tr>
            </tbody>
          </table>
        </div>
      </div>

      <div class="empty-state" id="av-empty"><i class="fas fa-list-check"></i><p>Enter a course code above and click Load.</p></div>
    </div>

  </main>
</div>

<!-- ═══════ MODAL: SESSION DETAILS (reserved for future use) ═══════ -->
<div class="modal-overlay" id="session-modal" onclick="closeModal(event)">
  <div class="modal">
    <button class="modal-close" onclick="closeModalById('session-modal')"><i class="fas fa-xmark"></i></button>
    <div class="modal-title" id="modal-title">Session Details</div>
    <div class="modal-sub"  id="modal-sub"></div>
    <div id="modal-body"></div>
  </div>
</div>

<!-- ═══════ TOAST ═══════ -->
<div id="toast">
  <i class="fas fa-circle-check ti"></i>
  <div class="tm" id="toast-msg"></div>
</div>

<script>
// ═══════════════════════════════════════════════════════════════
//  CONFIG
// ═══════════════════════════════════════════════════════════════

// Same-origin PHP endpoints, matching your backend.
const API_BASE = '/api';

// TODO: point this at your student-facing sign-in / attendance page.
const QR_BASE_URL = '/attend.php';

const QR_VALIDITY_WINDOW = 12 * 60;   // seconds — how long a QR stays valid

// Lecturer identity comes from the PHP session (see top of this file),
// not from the browser — nothing here is user-editable.
const LECTURER = {
  name: <?php echo json_encode($tutorMail); ?>,
  tutor_id: <?php echo json_encode($tutorId); ?>,
  avatar: <?php echo json_encode($initials); ?>
};

let activeQR       = null;
let countdownTimer = null;
let attendanceView = 'both';
let avSessions      = [];   // normalized sessions for the currently loaded course
let recentSessions   = [];  // sessions created during this visit — not persisted

// ═══════════════════════════════════════════════════════════════
//  HELPERS
// ═══════════════════════════════════════════════════════════════

function ts() { return Math.floor(Date.now() / 1000); }

function fmtTs(unix) {
  return new Date(unix * 1000).toLocaleString('en-NG', {
    year:'numeric', month:'short', day:'numeric', hour:'2-digit', minute:'2-digit'
  });
}

async function api(url, options = {}) {
  const res = await fetch(`${API_BASE}${url}`, {
    headers: { 'Content-Type': 'application/json' },
    credentials: 'same-origin',   // send the PHP session cookie
    ...options
  });
  let body = null;
  try { body = await res.json(); } catch (_) { /* non-JSON response */ }
  if (!res.ok) {
    throw new Error(body?.error || `Request failed (${res.status})`);
  }
  return body;
}

// ═══════════════════════════════════════════════════════════════
//  API RESPONSE SHAPE — adjust these if your JSON keys differ
// ═══════════════════════════════════════════════════════════════
// summary.php's exact field names weren't pinned down, so these
// normalizers accept a few likely variants. If your real response
// uses different keys, it's easiest to just extend the ?? chains
// below rather than touch the rendering code further down.

function extractSessions(summaryResponse) {
  if (Array.isArray(summaryResponse)) return summaryResponse;
  return summaryResponse?.sessions || summaryResponse?.data || [];
}

function normSession(s) {
  return {
    id:         s.session_id ?? s.attendance_id ?? s.attendanceID ?? s.id ?? '',
    tutor_id:   s.tutor_id ?? s.tutorID ?? '',
    created_at: s.created_at ?? s.createdAt ?? s.timestamp ?? null,
    signins:    s.signins ?? s.signin_count ?? s.count ?? (Array.isArray(s.attendees) ? s.attendees.length : null),
    attendees:  s.attendees ?? s.records ?? null
  };
}

function normAttendee(a) {
  return {
    reg_no:    a.reg_no ?? a.reg_number ?? a.matric_no ?? a.regNo ?? '',
    signed_at: a.signed_at ?? a.signedAt ?? a.timestamp ?? a.created_at ?? null
  };
}

async function fetchSessionAttendance(sessionId) {
  try {
    const records = await api(`/session/attendance.php?session_id=${encodeURIComponent(sessionId)}&attendance_id=${encodeURIComponent(sessionId)}`);
    const list = Array.isArray(records) ? records : (records?.attendees || records?.records || []);
    return list.map(normAttendee);
  } catch (e) {
    return [];
  }
}

// ═══════════════════════════════════════════════════════════════
//  COURSES DATA
// ═══════════════════════════════════════════════════════════════

const COURSES_DATA = {
  "year_one": {
    "first_semester": [
      { "code": "MTH101", "title": "Elementary Mathematics I" },
      { "code": "COS101", "title": "Introduction to Computing Sciences" },
      { "code": "PHY101", "title": "General Physics I" },
      { "code": "PHY107", "title": "General Physics Practical I" },
      { "code": "GST111", "title": "Communication in English" },
      { "code": "STA111", "title": "Descriptive Statistics" },
      { "code": "LASUSTECH-LIB101", "title": "Use of Library, Study Skills and ICT" },
      { "code": "LASUSTECH-CSC103", "title": "Internet Technology" },
      { "code": "LASUSTECH-YOR101", "title": "Communication in Yoruba 1" },
      { "code": "CHM101", "title": "General Chemistry I" },
      { "code": "CHM107", "title": "General Chemistry Practical I" },
      { "code": "BIO101", "title": "General Biology I" },
      { "code": "BIO107", "title": "General Biology Practical I" }
    ],
    "second_semester": [
      { "code": "MTH102", "title": "Elementary Mathematics II (Calculus)" },
      { "code": "COS102", "title": "Problem Solving" },
      { "code": "PHY102", "title": "General Physics II" },
      { "code": "PHY108", "title": "General Physics Practical II" },
      { "code": "GST112", "title": "Nigerian Peoples and Culture" },
      { "code": "LASUSTECH-CSC106", "title": "Website Design and Management" },
      { "code": "LASUSTECH-FRE102", "title": "French Language for Science Student" },
      { "code": "LASUSTECH-YOR102", "title": "Communication in Yoruba 2" },
      { "code": "CHM102", "title": "General Chemistry II" },
      { "code": "CHM108", "title": "General Chemistry Practical II" },
      { "code": "FRS102", "title": "Introductory Forensic Science" },
      { "code": "STA112", "title": "Probability I" }
    ]
  },
  "year_two": {
    "first_semester": [
      { "code": "COS201", "title": "Computer Programming I" },
      { "code": "CSC203", "title": "Discrete Structures" },
      { "code": "MTH201", "title": "Mathematical Method I" },
      { "code": "ENT211", "title": "Entrepreneurship and Innovation" },
      { "code": "IFT211", "title": "Digital Logic Design" },
      { "code": "SEN201", "title": "Introduction to Software Engineering" },
      { "code": "CYB201", "title": "Introduction to Cybersecurity and Strategy" },
      { "code": "ICT201", "title": "Introduction to Information and Communication Technology" },
      { "code": "LASUSTECH-AGR215", "title": "General Agricultural Practice" }
    ],
    "second_semester": [
      { "code": "GST212", "title": "Philosophy, Logic & Human Existence" },
      { "code": "COS202", "title": "Computer Programming II" },
      { "code": "MTH202", "title": "Elementary Differential Equations" },
      { "code": "IFT212", "title": "Computer Architecture and Organisation" },
      { "code": "INS204", "title": "Systems Analysis and Design" },
      { "code": "PHY202", "title": "Electric Circuits and Electronics" },
      { "code": "CSC299", "title": "SIWES I" },
      { "code": "LASUSTECH-GET230", "title": "General Workshop Practice" },
      { "code": "LASUSTECH-CSC206", "title": "Web Server Administration" }
    ]
  },
  "year_three": {
    "first_semester": [
      { "code": "CSC301", "title": "Data Structures" },
      { "code": "CSC309", "title": "Artificial Intelligence" },
      { "code": "CSC399", "title": "SIWES II" },
      { "code": "ICT305", "title": "Data Communication System and Networks" },
      { "code": "LASUSTECH-CSC307", "title": "Cloud Computing" },
      { "code": "LASUSTECH-CSC311", "title": "Information Storage Management" },
      { "code": "SEN301", "title": "Object-Oriented Analysis and Design" },
      { "code": "ICT301", "title": "Satellite Communication" },
      { "code": "CYB301", "title": "Cryptography Techniques, Algorithms and Algorithms" }
    ],
    "second_semester": [
      { "code": "GST312", "title": "Peace and Conflict Resolution" },
      { "code": "ENT312", "title": "Venture Creation" },
      { "code": "CSC308", "title": "Operating Systems" },
      { "code": "CSC322", "title": "Computer Science Innovation and New Technologies" },
      { "code": "DTS304", "title": "Data Management I" },
      { "code": "LASUSTECH-CSC304", "title": "Computer System Security" },
      { "code": "LASUSTECH-CSC302", "title": "Survey of Programming Languages" },
      { "code": "LASUSTECH-CSC310", "title": "Machine Learning" }
    ]
  },
  "year_four": {
    "first_semester": [
      { "code": "CSC401", "title": "Algorithms and Complexity Analysis" },
      { "code": "COS409", "title": "Research Methodology and Technical Report Writing" },
      { "code": "INS401", "title": "Project Management" },
      { "code": "CSC497", "title": "Final Year Project I" },
      { "code": "LASUSTECH-CSC405", "title": "Operating System Engineering" },
      { "code": "LASUSTECH-CSC403", "title": "Blockchain and its Application" },
      { "code": "LASUSTECH-CSC435", "title": "Computer Graphics and Visualization" },
      { "code": "LASUSTECH-CSC437", "title": "Data Mining and Big Data Analytics" }
    ],
    "second_semester": [
      { "code": "CSC402", "title": "Ethics and Legal Issues in Computer Science" },
      { "code": "CSC498", "title": "Final Year Project II" },
      { "code": "ICT418", "title": "Design & Installation of Electrical & ICT Equipment" },
      { "code": "IFT442", "title": "Wireless Communications and Networking" },
      { "code": "LASUSTECH-CSC442", "title": "Mobile Application Development" },
      { "code": "LASUSTECH-CSC422", "title": "Human-Computer Interaction" },
      { "code": "LASUSTECH-CSC454", "title": "Semantic Web Computing" },
      { "code": "LASUSTECH-CSC406", "title": "Fault Tolerance Computing" },
      { "code": "LASUSTECH-CSC408", "title": "Game Design" }
    ]
  }
};

function bindCourseCascade(prefix, onSelected) {
  const ysSel     = document.getElementById(`${prefix}-year-semester`);
  const courseSel = document.getElementById(`${prefix}-course-select`);
  if (!ysSel || !courseSel) return;

  ysSel.addEventListener('change', () => {
    const value = ysSel.value;
    courseSel.innerHTML = '<option value="">— select a course —</option>';
    courseSel.disabled  = !value;
    if (!value) { onSelected?.(''); return; }

    const [year, semester] = value.split(':');
    const courses = COURSES_DATA?.[year]?.[semester] || [];
    courses.forEach(c => {
      const opt = document.createElement('option');
      opt.value       = c.code;
      opt.textContent = `${c.code} — ${c.title}`;
      courseSel.appendChild(opt);
    });
  });

  courseSel.addEventListener('change', () => onSelected?.(courseSel.value));
}

// ═══════════════════════════════════════════════════════════════
//  UI: DASHBOARD
// ═══════════════════════════════════════════════════════════════

function jumpToCourse() {
  const course = document.getElementById('dash-course-jump').value.trim().toUpperCase();
  if (!course) { toast('Enter a course code.', 'error'); return; }
  navigate('attendance');
  document.getElementById('av-course-input').value = course;
  loadAttendance();
}

function renderRecentSessions() {
  const tbody = document.getElementById('dash-recent-tbody');
  if (!tbody) return;
  if (!recentSessions.length) {
    tbody.innerHTML = `<tr><td colspan="4"><div class="empty-state" style="padding:20px"><i class="fas fa-clock-rotate-left"></i><p>Sessions you create will show up here for this visit.</p></div></td></tr>`;
    return;
  }
  tbody.innerHTML = recentSessions.map(s => `<tr>
    <td><strong>${s.course}</strong></td>
    <td><code>${s.session_id}</code></td>
    <td>${fmtTs(s.created_at)}</td>
    <td><button class="btn btn-outline btn-sm" onclick="viewRecentSession('${s.course}','${s.session_id}')">View</button></td>
  </tr>`).join('');
}

function viewRecentSession(course, sessionId) {
  navigate('attendance');
  document.getElementById('av-course-input').value = course;
  loadAttendance().then(() => {
    document.getElementById('av-session-select').value = sessionId;
    setAttendanceView('session');
    onAvSessionChange();
  });
}

// ═══════════════════════════════════════════════════════════════
//  UI: CREATE SESSION + QR
// ═══════════════════════════════════════════════════════════════

function autoSessionId() {
  const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
  const id    = [...Array(7)].map(() => chars[Math.random() * chars.length | 0]).join('');
  document.getElementById('session-id-input').value = id;
}

async function createSession() {
  const course = document.getElementById('cs-course-select').value;
  if (!course) { toast('Select a course first.', 'error'); return; }

  const session_id = document.getElementById('session-id-input').value.trim().toUpperCase();
  if (!session_id) { toast('Enter or generate a session ID.', 'error'); return; }

  try {
    const data = await api('/session/create.php', {
      method: 'POST',
      body: JSON.stringify({
        course_code:   course,
        session_id:    session_id,
        attendance_id: session_id,  // sent alongside session_id in case your endpoint expects this key instead
        tutor_id:      LECTURER.tutor_id
      })
    });
    if (data?.error) { toast(data.error, 'error'); return; }

    const validity = ts();
    const url = `${QR_BASE_URL}?course_code=${course}&session_id=${session_id}&tutor_id=${LECTURER.tutor_id}&nonce=${validity}`;
    activeQR = { url, expiresAt: validity + QR_VALIDITY_WINDOW, sessionId: session_id };

    generateQR(url);
    startCountdown(validity);
    recentSessions.unshift({ course, session_id, created_at: validity });
    renderRecentSessions();
    await renderSessionsForCourse(course);
    toast(`Session '${session_id}' created for ${course}.`, 'success');
  } catch (e) {
    toast(e.message, 'error');
  }
}

function generateQR(text) {
  const container   = document.getElementById('qr-canvas');
  const placeholder  = document.getElementById('qr-placeholder');

  const urlBox = document.getElementById('qr-url-box');
  urlBox.textContent   = text;
  urlBox.style.display = 'block';
  document.getElementById('copy-url-btn').disabled = false;
  document.getElementById('print-qr-btn').disabled = false;

  container.innerHTML = '';

  try {
    QRCode.toCanvas(text, {
      width: 256,
      margin: 2,
      color: { dark: '#000000', light: '#FFFFFF' }
    }, function (err, canvas) {
      if (err) {
        console.error('Error generating QR code:', err);
        toast('Failed to generate QR code.', 'error');
        return;
      }
      container.appendChild(canvas);
      placeholder.style.display = 'none';
      container.style.display   = 'block';
    });
  } catch (error) {
    console.error('Error generating QR code:', error);
    toast('QR library error: ' + error.message, 'error');
  }
}

function startCountdown(validity) {
  clearInterval(countdownTimer);
  const total  = QR_VALIDITY_WINDOW;
  const cring  = document.getElementById('cring');
  const clabel = document.getElementById('clabel');
  const circum = 2 * Math.PI * 24;

  document.getElementById('countdown-wrap').style.display = 'block';
  document.getElementById('expired-notice').style.display = 'none';
  document.getElementById('expiry-text').textContent = `Valid for ${Math.round(total / 60)} minutes`;
  document.getElementById('expiry-text').className   = 'qr-expiry active';

  function tick() {
    const elapsed = ts() - validity;
    const left    = Math.max(0, total - elapsed);
    const mins    = String(Math.floor(left / 60)).padStart(2, '0');
    const secs    = String(left % 60).padStart(2, '0');
    clabel.textContent = `${mins}:${secs}`;
    const pct = left / total;
    cring.style.strokeDashoffset = circum * (1 - pct);
    cring.style.stroke = left > 300 ? 'var(--accent2)' : left > 60 ? 'var(--accent3)' : 'var(--danger)';
    if (left === 0) {
      clearInterval(countdownTimer);
      document.getElementById('countdown-wrap').style.display = 'none';
      document.getElementById('expired-notice').style.display = 'block';
    }
  }
  tick();
  countdownTimer = setInterval(tick, 1000);
}

function copyURL() {
  if (!activeQR) return;
  navigator.clipboard.writeText(activeQR.url).then(() => toast('URL copied to clipboard.', 'success'));
}

function printQR() { window.print(); }

async function renderSessionsForCourse(course) {
  const tbody = document.getElementById('sessions-tbody');
  const title = document.getElementById('sessions-list-title');
  title.textContent = course ? `Sessions — ${course}` : 'Sessions for this course';

  if (!course) { clearSessionsList(); return; }

  try {
    const summary  = await api(`/summary.php?course_code=${encodeURIComponent(course)}`);
    const sessions = extractSessions(summary).map(normSession);

    if (!sessions.length) {
      tbody.innerHTML = `<tr><td colspan="4"><div class="empty-state" style="padding:20px"><i class="fas fa-calendar-xmark"></i><p>No sessions yet.</p></div></td></tr>`;
      return;
    }
    const nowTs = ts();
    tbody.innerHTML = [...sessions].reverse().map(s => {
      const active = s.created_at ? (nowTs - s.created_at <= QR_VALIDITY_WINDOW) : false;
      return `<tr>
        <td><code>${s.id}</code></td>
        <td>${s.created_at ? fmtTs(s.created_at) : '—'}</td>
        <td>${s.signins ?? '—'}</td>
        <td>${active
          ? `<span class="badge badge-green"><i class="fas fa-circle" style="font-size:7px"></i> Active</span>`
          : `<span class="badge badge-gray">Expired</span>`}</td>
      </tr>`;
    }).join('');
  } catch (e) {
    toast(e.message, 'error');
  }
}

function clearSessionsList() {
  document.getElementById('sessions-tbody').innerHTML =
    `<tr><td colspan="4"><div class="empty-state" style="padding:20px"><i class="fas fa-calendar-xmark"></i><p>Select a course to see its sessions.</p></div></td></tr>`;
}

// ═══════════════════════════════════════════════════════════════
//  UI: ATTENDANCE VIEWER
// ═══════════════════════════════════════════════════════════════

async function loadAttendance() {
  const course = document.getElementById('av-course-input').value.trim().toUpperCase();
  if (!course) { toast('Enter a course code.', 'error'); return; }

  document.getElementById('av-empty').style.display = 'none';

  try {
    const summary = await api(`/summary.php?course_code=${encodeURIComponent(course)}`);
    avSessions = extractSessions(summary).map(normSession);

    const sel = document.getElementById('av-session-select');
    const prevSelected = sel.value;
    sel.innerHTML = '<option value="">— select session —</option>';
    avSessions.forEach(s => {
      const opt = document.createElement('option');
      opt.value       = s.id;
      opt.textContent = s.created_at ? `${s.id} — ${fmtTs(s.created_at)}` : s.id;
      sel.appendChild(opt);
    });
    if (prevSelected && avSessions.some(s => s.id === prevSelected)) sel.value = prevSelected;

    await renderCourseBlock(course, avSessions);
    document.getElementById('av-course-block').style.display = 'block';
    applyAttendanceView();
    if (sel.value) await onAvSessionChange();
  } catch (e) {
    toast(e.message, 'error');
  }
}

async function renderCourseBlock(course, sessions) {
  document.getElementById('av-course-label').textContent = `${course} — ${sessions.length} session${sessions.length !== 1 ? 's' : ''}`;

  const stbody = document.getElementById('av-sessions-tbody');
  if (!sessions.length) {
    stbody.innerHTML = `<tr><td colspan="4"><div class="empty-state" style="padding:20px"><i class="fas fa-calendar-xmark"></i><p>No sessions yet.</p></div></td></tr>`;
  } else {
    stbody.innerHTML = [...sessions].reverse().map(s => `<tr>
      <td><code>${s.id}</code></td>
      <td>${s.created_at ? fmtTs(s.created_at) : '—'}</td>
      <td>${s.signins ?? '—'}</td>
      <td><button class="btn btn-outline btn-sm" onclick="jumpToSession('${s.id}')">View</button></td>
    </tr>`).join('');
  }

  const statsEl = document.getElementById('av-course-stats');
  const tbody   = document.getElementById('av-course-tbody');

  if (!sessions.length) {
    statsEl.innerHTML = '';
    tbody.innerHTML = `<tr><td colspan="5"><div class="empty-state"><i class="fas fa-user-slash"></i><p>No sign-ins recorded yet.</p></div></td></tr>`;
    return;
  }

  // Aggregate per-student totals. Uses attendees embedded in the summary
  // response when present, otherwise falls back to fetching each
  // session's roster in parallel — works either way summary.php is shaped.
  const attendeeLists = await Promise.all(sessions.map(s =>
    s.attendees ? Promise.resolve(s.attendees.map(normAttendee)) : fetchSessionAttendance(s.id)
  ));

  const counts = {};
  let totalSignins = 0;
  attendeeLists.forEach(list => {
    list.forEach(a => {
      if (!a.reg_no) return;
      counts[a.reg_no] = (counts[a.reg_no] || 0) + 1;
      totalSignins++;
    });
  });
  const entries = Object.entries(counts);

  statsEl.innerHTML = `
    <div class="stat-card" style="--card-accent:var(--accent)"><div class="stat-label">Total Sessions</div><div class="stat-value">${sessions.length}</div><i class="fas fa-calendar stat-icon"></i></div>
    <div class="stat-card" style="--card-accent:var(--accent2)"><div class="stat-label">Unique Students</div><div class="stat-value">${entries.length}</div><i class="fas fa-users stat-icon"></i></div>
    <div class="stat-card" style="--card-accent:#2563eb"><div class="stat-label">Total Sign-ins</div><div class="stat-value">${totalSignins}</div><i class="fas fa-signature stat-icon"></i></div>
  `;

  if (!entries.length) {
    tbody.innerHTML = `<tr><td colspan="5"><div class="empty-state"><i class="fas fa-user-slash"></i><p>No sign-ins recorded yet.</p></div></td></tr>`;
  } else {
    tbody.innerHTML = entries.sort((a, b) => b[1] - a[1]).map(([reg_no, count]) => {
      const pct   = sessions.length ? Math.round((count / sessions.length) * 100) : 0;
      const color = pct >= 75 ? 'var(--accent2)' : pct >= 50 ? 'var(--accent3)' : 'var(--danger)';
      return `<tr>
        <td><strong>${reg_no}</strong></td>
        <td>${count}</td>
        <td>${sessions.length}</td>
        <td>
          <div style="display:flex;align-items:center;gap:8px">
            <div class="progress-track" style="width:90px">
              <div class="progress-fill" style="width:${pct}%;background:${color}"></div>
            </div>
            <span style="font-weight:700;font-size:13px">${pct}%</span>
          </div>
        </td>
        <td>${pct >= 75
          ? '<span class="badge badge-green">On Track</span>'
          : pct >= 50
          ? '<span class="badge badge-amber">At Risk</span>'
          : '<span class="badge badge-red">Below Min</span>'}</td>
      </tr>`;
    }).join('');
  }
}

async function onAvSessionChange() {
  const sid   = document.getElementById('av-session-select').value;
  const tbody = document.getElementById('av-session-tbody');
  const countEl = document.getElementById('av-session-count');

  if (!sid) {
    tbody.innerHTML = `<tr><td colspan="3"><div class="empty-state"><i class="fas fa-users"></i><p>Select a session to load its roster.</p></div></td></tr>`;
    countEl.textContent = '';
    applyAttendanceView();
    return;
  }

  tbody.innerHTML = `<tr><td colspan="3"><div class="empty-state" style="padding:20px"><i class="fas fa-spinner fa-spin"></i><p>Loading…</p></div></td></tr>`;
  applyAttendanceView();

  const records = await fetchSessionAttendance(sid);
  countEl.textContent = `${records.length} student${records.length !== 1 ? 's' : ''}`;
  if (!records.length) {
    tbody.innerHTML = `<tr><td colspan="3"><div class="empty-state"><i class="fas fa-user-slash"></i><p>No sign-ins for this session yet.</p></div></td></tr>`;
    return;
  }
  tbody.innerHTML = records.map((r, i) => `<tr>
    <td>${i + 1}</td>
    <td><strong>${r.reg_no}</strong></td>
    <td>${r.signed_at ? fmtTs(r.signed_at) : '—'}</td>
  </tr>`).join('');
}

function jumpToSession(sessionId) {
  document.getElementById('av-session-select').value = sessionId;
  setAttendanceView('session');
  onAvSessionChange();
}

function setAttendanceView(mode) {
  attendanceView = mode;
  document.querySelectorAll('#av-view-toggle button').forEach(b => {
    b.classList.toggle('is-active', b.dataset.view === mode);
  });
  applyAttendanceView();
}

function applyAttendanceView() {
  const courseBlock  = document.getElementById('av-course-block');
  const sessionBlock = document.getElementById('av-session-block');
  const hasCourseData = courseBlock.innerHTML.trim() !== '';
  const sid = document.getElementById('av-session-select').value;

  courseBlock.style.display  = (hasCourseData && (attendanceView === 'course' || attendanceView === 'both')) ? 'block' : 'none';
  sessionBlock.style.display = (sid && (attendanceView === 'session' || attendanceView === 'both')) ? 'block' : 'none';
}

// ═══════════════════════════════════════════════════════════════
//  NAVIGATION
// ═══════════════════════════════════════════════════════════════

function navigate(pageId) {
  document.querySelectorAll('.nav-item').forEach(i => i.classList.remove('active'));
  document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
  const navEl  = document.querySelector(`.nav-item[data-page="${pageId}"]`);
  const pageEl = document.getElementById(`page-${pageId}`);
  if (navEl)  navEl.classList.add('active');
  if (pageEl) pageEl.classList.add('active');
}

document.querySelectorAll('.nav-item').forEach(item => {
  item.addEventListener('click', e => {
    e.preventDefault();
    navigate(item.dataset.page);
  });
});

// ═══════════════════════════════════════════════════════════════
//  MODAL (reserved — not currently opened anywhere)
// ═══════════════════════════════════════════════════════════════

function closeModal(e) {
  if (e.target === e.currentTarget) e.currentTarget.classList.remove('open');
}
function closeModalById(id) {
  document.getElementById(id).classList.remove('open');
}

// ═══════════════════════════════════════════════════════════════
//  TOAST
// ═══════════════════════════════════════════════════════════════

function toast(msg, type = 'info') {
  const el    = document.getElementById('toast');
  const msgEl = document.getElementById('toast-msg');
  const icons = { success:'fa-circle-check', error:'fa-circle-xmark', info:'fa-circle-info' };
  el.querySelector('i').className = `fas ${icons[type] || icons.info} ti`;
  el.className = `show ${type}`;
  msgEl.textContent = msg;
  clearTimeout(el._t);
  el._t = setTimeout(() => el.classList.remove('show'), 4000);
}

// ── Init ────────────────────────────────────────────────────────
bindCourseCascade('cs', code => {
  renderSessionsForCourse(code);
});
document.getElementById('av-session-select').addEventListener('change', onAvSessionChange);
</script>
</body>
</html>
