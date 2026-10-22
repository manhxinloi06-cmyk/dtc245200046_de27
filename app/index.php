<?php
declare(strict_types=1);
session_set_cookie_params([
    'httponly' => true, 'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    'samesite' => 'Lax'
]);
session_start();
require __DIR__ . '/config.php';

try {
    ensure_demo_admin();
    ensure_sample_classes();
} catch (Throwable $ex) {
    error_log('Database initialization check failed: ' . $ex->getMessage());
    http_response_code(503);
    exit('Không kết nối được cơ sở dữ liệu. Kiểm tra container db và cấu hình .env.');
}

$page = $_GET['page'] ?? 'dashboard';
$error = '';
$success = '';
$selectedClass = trim((string)($_GET['class'] ?? $_POST['selected_class'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'login') {
        $username = trim((string)($_POST['username'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $stmt = db()->prepare('SELECT id, username, password_hash, role FROM users WHERE username = ?');
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        if ($user && password_verify($password, $user['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['user'] = ['id' => (int)$user['id'], 'username' => $user['username'], 'role' => $user['role']];
            header('Location: index.php');
            exit;
        }
        $error = 'Tên đăng nhập hoặc mật khẩu không đúng.';
        $page = 'login';
    } elseif ($action === 'logout') {
        $_SESSION = [];
        session_destroy();
        header('Location: index.php?page=login');
        exit;
    } else {
        require_login();
        if ($action === 'checkin' || $action === 'checkout') {
            $personId = filter_input(INPUT_POST, 'person_id', FILTER_VALIDATE_INT);
            if (!$personId) {
                $error = 'Vui lòng chọn sinh viên hợp lệ.';
            } else {
                $stmt = db()->prepare('SELECT id FROM people WHERE id = ? AND active = 1');
                $stmt->execute([$personId]);
                if (!$stmt->fetch()) {
                    $error = 'Không tìm thấy sinh viên đang hoạt động.';
                } elseif ($action === 'checkin') {
                    $stmt = db()->prepare('INSERT INTO attendance(person_id, attendance_date, check_in) VALUES(?, CURDATE(), NOW()) ON DUPLICATE KEY UPDATE check_in = IF(check_in IS NULL, NOW(), check_in)');
                    $stmt->execute([$personId]);
                    $success = 'Đã ghi nhận sinh viên vào lớp (check-in).';
                } else {
                    $stmt = db()->prepare('UPDATE attendance SET check_out = NOW() WHERE person_id = ? AND attendance_date = CURDATE() AND check_in IS NOT NULL AND check_out IS NULL');
                    $stmt->execute([$personId]);
                    $success = $stmt->rowCount() ? 'Đã ghi nhận sinh viên ra về (check-out).' : 'Chưa có lượt điểm danh vào lớp hoặc đã điểm danh ra về.';
                }
            }
            $page = 'dashboard';
        } elseif ($action === 'change_password') {
            $old = (string)($_POST['old_password'] ?? '');
            $new = (string)($_POST['new_password'] ?? '');
            $stmt = db()->prepare('SELECT password_hash FROM users WHERE id = ?');
            $stmt->execute([$_SESSION['user']['id']]);
            $hash = $stmt->fetchColumn();
            if (!$hash || !password_verify($old, (string)$hash)) {
                $error = 'Mật khẩu hiện tại không đúng.';
            } elseif (strlen($new) < 12 || !preg_match('/[A-Z]/', $new) || !preg_match('/[a-z]/', $new) || !preg_match('/[0-9]/', $new) || !preg_match('/[^A-Za-z0-9]/', $new)) {
                $error = 'Mật khẩu mới cần tối thiểu 12 ký tự, gồm chữ hoa, chữ thường, số và ký tự đặc biệt.';
            } else {
                $stmt = db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
                $stmt->execute([password_hash($new, PASSWORD_DEFAULT), $_SESSION['user']['id']]);
                $success = 'Đổi mật khẩu thành công.';
            }
            $page = 'account';
        }
    }
}

if (empty($_SESSION['user']) && $page !== 'login') {
    header('Location: index.php?page=login');
    exit;
}
$today = date('Y-m-d');
$people = [];
$classList = ['CNTT K23A', 'CNTT K23B', 'CNTT K23C'];
$history = [];
$stats = ['people' => 0, 'checked' => 0, 'incomplete' => 0];
if (!empty($_SESSION['user'])) {
    try {
        $dbClasses = db()->query('SELECT DISTINCT department FROM people WHERE active = 1 ORDER BY department')->fetchAll(PDO::FETCH_COLUMN);
        if (!empty($dbClasses)) {
            $classList = $dbClasses;
        }
    } catch (Throwable $e) {}

    $peopleStmt = db()->prepare('
        SELECT p.id, p.code, p.full_name, p.department, a.check_in, a.check_out 
        FROM people p 
        LEFT JOIN attendance a ON a.person_id = p.id AND a.attendance_date = ? 
        WHERE p.active = 1 
        ORDER BY p.code ASC
    ');
    $peopleStmt->execute([$today]);
    $people = $peopleStmt->fetchAll();

    $stmt = db()->prepare('SELECT COUNT(*) FROM attendance WHERE attendance_date = ?');
    $stmt->execute([$today]); $stats['checked'] = (int)$stmt->fetchColumn();
    $stats['people'] = count($people);
    $stmt = db()->prepare('SELECT COUNT(*) FROM attendance WHERE attendance_date = ? AND check_in IS NOT NULL AND check_out IS NULL');
    $stmt->execute([$today]); $stats['incomplete'] = (int)$stmt->fetchColumn();
    $history = db()->query('SELECT p.code, p.full_name, p.department, a.attendance_date, a.check_in, a.check_out FROM attendance a JOIN people p ON p.id=a.person_id ORDER BY a.attendance_date DESC, a.check_in DESC LIMIT 100')->fetchAll();
}
function active(string $current, string $item): string { return $current === $item ? 'active' : ''; }
?>
<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Điểm danh Sinh viên | Student Attendance</title>
<link rel="stylesheet" href="style.css?v=<?=filemtime(__DIR__ . '/style.css')?>">
<style>
.panel-heading{padding:16px 22px;display:flex;align-items:center;justify-content:space-between;gap:16px;border-bottom:1px solid var(--line);background:#fff}
.panel-heading-title{min-width:0}
.panel-heading-title h2{font-size:16px;font-weight:700;color:var(--ink);margin:0}
.panel-heading-title p{font-size:12px;color:var(--muted);margin-top:3px}
.panel-heading-controls{display:flex;align-items:center;gap:10px;flex-shrink:0;flex-wrap:nowrap}
.class-picker-box{display:inline-flex;align-items:center;gap:8px;background:#f8fafc;border:1px solid #d5dde7;border-radius:9px;padding:4px 10px 4px 12px;height:38px;box-sizing:border-box;transition:all .2s ease}
.class-picker-box:hover{background:#fff;border-color:#cbd5e1}
.class-picker-box:focus-within{background:#fff;border-color:#3768ed;box-shadow:0 0 0 3px rgba(55,104,237,.14)}
.class-picker-lbl{display:inline-flex;align-items:center;gap:6px;font-size:12px;font-weight:700;color:#4b586e;cursor:pointer;white-space:nowrap;user-select:none}
.class-picker-lbl .picker-icon{color:#3768ed;flex-shrink:0}
.select-wrapper{position:relative;display:inline-flex;align-items:center}
.class-select-styled{appearance:none;-webkit-appearance:none;-moz-appearance:none;border:1px solid #d0d7e2!important;background:#fff!important;color:#1a2536!important;font-family:inherit!important;font-size:13px!important;font-weight:700!important;border-radius:6px!important;padding:4px 26px 4px 10px!important;outline:none!important;cursor:pointer!important;line-height:1.4!important;transition:all .15s ease!important}
.class-select-styled:hover{border-color:#3768ed!important}
.class-select-styled:focus{border-color:#3768ed!important;box-shadow:0 0 0 2px rgba(55,104,237,.2)!important}
.class-select-styled option{font-weight:600;color:#1e293b;background:#fff;padding:8px}
.select-arrow{position:absolute;right:8px;top:50%;transform:translateY(-50%);font-size:10px;color:#64748b;pointer-events:none;user-select:none}
.badge-count{display:inline-flex!important;align-items:center!important;height:38px!important;box-sizing:border-box!important;font-size:11px!important;font-weight:700!important;color:#2563eb!important;background:#eff6ff!important;border:1px solid #bfdbfe!important;padding:0 12px!important;border-radius:8px!important;white-space:nowrap!important;letter-spacing:.2px!important}
.live-dot-chip{display:inline-flex!important;align-items:center!important;gap:6px!important;height:38px!important;box-sizing:border-box!important;font-size:11px!important;font-weight:700!important;color:#15803d!important;background:#f0fdf4!important;border:1px solid #bbf7d0!important;padding:0 12px!important;border-radius:8px!important;white-space:nowrap!important}
.live-dot-chip .dot{display:inline-block;width:7px;height:7px;border-radius:50%;background:#22c55e;box-shadow:0 0 0 2px rgba(34,197,94,.2)}
.class-pill{display:inline-block!important;font-size:11px!important;font-weight:700!important;color:#334155!important;background:#f1f5f9!important;border:1px solid #e2e8f0!important;padding:3px 8px!important;border-radius:6px!important}
.record-status.idle{background:#f1f3f6!important;color:#7d8a9e!important}
.btn-checkout-active{background:#fff5e4!important;border-color:#f6d194!important;color:#b77912!important;font-weight:700!important}
.btn-checkout-active:hover{background:#ffebc8!important}
@media(max-width:768px){.panel-heading{flex-direction:column;align-items:flex-start;gap:12px}.panel-heading-controls{width:100%;flex-wrap:wrap;justify-content:flex-start}}
</style>
</head>
<body>
<?php if ($page === 'login' && empty($_SESSION['user'])): ?>
<main class="login-wrap">
  <section class="login-card">
    <div class="brand-mark">🎓</div><p class="eyebrow">STUDENT ATTENDANCE SYSTEM</p>
    <h1>Đăng nhập hệ thống</h1><p class="muted">Hệ thống quản lý điểm danh sinh viên</p>
    <?php if ($error): ?><div class="alert error"><?=e($error)?></div><?php endif; ?>
    <form method="post">
      <input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="login">
      <label>Tên đăng nhập<input name="username" autocomplete="username" required autofocus></label>
      <label>Mật khẩu<input type="password" name="password" autocomplete="current-password" required></label>
      <button class="btn primary full" type="submit">Đăng nhập</button>
    </form>
  </section>
</main>
<?php else: ?>
<div class="app-shell">
<aside class="sidebar">
  <div class="brand"><span class="brand-mark small">🎓</span><span><b>SinhViên<span class="accent">Attend</span></b><small>ĐIỂM DANH SINH VIÊN</small></span></div>
  <div class="nav-label">MENU CHÍNH</div>
  <a class="<?=active($page,'dashboard')?>" href="index.php"><span>▦</span> Tổng quan</a>
  <a class="<?=active($page,'history')?>" href="index.php?page=history"><span>◷</span> Lịch sử điểm danh</a>
  <a class="<?=active($page,'account')?>" href="index.php?page=account"><span>⚙</span> Tài khoản</a>
  <div class="sidebar-bottom"><div class="avatar"><?=e(strtoupper(substr($_SESSION['user']['username'],0,1)))?></div><div><b><?=e($_SESSION['user']['username'])?></b><small><?=e($_SESSION['user']['role'])?></small></div>
  <form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="logout"><button class="logout" title="Đăng xuất">↗</button></form></div>
</aside>
<main class="main">
  <header class="topbar"><div><span class="muted">Hệ thống /</span> <b><?= $page==='history'?'Lịch sử điểm danh sinh viên':($page==='account'?'Tài khoản':'Tổng quan điểm danh') ?></b></div><div class="date-chip">◷ <?=e(date('d/m/Y'))?></div></header>
  <section class="content">
    <?php if ($error): ?><div class="alert error"><?=e($error)?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert success"><?=e($success)?></div><?php endif; ?>
    <?php if ($page === 'dashboard'): ?>
      <div class="page-heading"><div><p class="eyebrow">DASHBOARD</p><h1>Tổng quan điểm danh sinh viên</h1><p class="muted">Theo dõi tình trạng chuyên cần và có mặt của sinh viên trong ngày.</p></div><span class="status-pill"><i></i> Hệ thống hoạt động</span></div>
      <div class="stats-grid">
        <article class="stat-card"><div class="stat-top"><span>Tổng số sinh viên</span><span class="stat-icon blue">🎓</span></div><strong><?=$stats['people']?></strong><small>Sinh viên trong danh sách</small></article>
        <article class="stat-card"><div class="stat-top"><span>Có mặt hôm nay</span><span class="stat-icon green">✓</span></div><strong><?=$stats['checked']?></strong><small>Trên <?=$stats['people']?> sinh viên</small></article>
        <article class="stat-card"><div class="stat-top"><span>Chưa điểm danh ra</span><span class="stat-icon amber">◷</span></div><strong><?=$stats['incomplete']?></strong><small>Đang trong lớp / Chưa về</small></article>
      </div>
      <div class="panel">
        <div class="panel-heading">
          <div class="panel-heading-title">
            <h2>Điểm danh sinh viên</h2>
            <p class="muted">Ghi nhận giờ vào lớp và giờ ra về của sinh viên.</p>
          </div>
          <div class="panel-heading-controls">
            <div class="class-picker-box">
              <label for="classFilterSelect" class="class-picker-lbl">
                <svg class="picker-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1-2.5-2.5Z"/><path d="M6 6h10"/><path d="M6 10h10"/></svg>
                <span>Lớp học:</span>
              </label>
              <div class="select-wrapper">
                <select id="classFilterSelect" class="class-select-styled" onchange="filterClass(this.value)">
                  <option value="">-- Tất cả các lớp --</option>
                  <?php foreach ($classList as $cls): ?>
                    <option value="<?=e($cls)?>" <?=$selectedClass === $cls ? 'selected' : ''?>>
                      <?=e($cls)?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <span class="select-arrow">▾</span>
              </div>
            </div>
            <?php 
              $initialMatchingCount = 0; 
              foreach ($people as $p) { 
                if ($selectedClass === '' || $p['department'] === $selectedClass) $initialMatchingCount++; 
              } 
            ?>
            <span class="badge-count" id="classCountBadge"><?=$initialMatchingCount?> sinh viên</span>
            <span class="live-dot-chip"><i class="dot"></i> Hôm nay</span>
          </div>
        </div>
        <div class="table-wrap">
          <table id="attendanceTable">
            <thead>
              <tr>
                <th>Mã SV</th>
                <th>Họ và tên</th>
                <th>Lớp học</th>
                <th>Trạng thái hôm nay</th>
                <th>Thao tác</th>
              </tr>
            </thead>
            <tbody>
            <?php 
            $initialVisibleCount = 0;
            foreach ($people as $p): 
                $isVisible = ($selectedClass === '' || $p['department'] === $selectedClass);
                if ($isVisible) $initialVisibleCount++;
                $hasCheckin = !empty($p['check_in']);
                $hasCheckout = !empty($p['check_out']);
            ?>
              <tr class="student-row" data-class="<?=e($p['department'])?>" <?= !$isVisible ? 'style="display:none;"' : '' ?>>
                <td><span class="code-tag"><?=e($p['code'])?></span></td>
                <td><b><?=e($p['full_name'])?></b></td>
                <td><span class="class-pill"><?=e($p['department'])?></span></td>
                <td>
                  <?php if ($hasCheckout): ?>
                    <span class="record-status done">✓ Đã về (<?=date('H:i', strtotime($p['check_out']))?>)</span>
                  <?php elseif ($hasCheckin): ?>
                    <span class="record-status pending">● Trong lớp (<?=date('H:i', strtotime($p['check_in']))?>)</span>
                  <?php else: ?>
                    <span class="record-status idle">Chưa điểm danh</span>
                  <?php endif; ?>
                </td>
                <td>
                  <div class="actions">
                    <form method="post">
                      <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
                      <input type="hidden" name="action" value="checkin">
                      <input type="hidden" name="person_id" value="<?=$p['id']?>">
                      <input type="hidden" name="selected_class" class="hidden-selected-class" value="<?=e($selectedClass)?>">
                      <button class="btn small primary" type="submit" title="Ghi nhận giờ vào lớp">Vào lớp</button>
                    </form>
                    <form method="post">
                      <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
                      <input type="hidden" name="action" value="checkout">
                      <input type="hidden" name="person_id" value="<?=$p['id']?>">
                      <input type="hidden" name="selected_class" class="hidden-selected-class" value="<?=e($selectedClass)?>">
                      <button class="btn small outline <?= ($hasCheckin && !$hasCheckout) ? 'btn-checkout-active' : '' ?>" type="submit" title="Ghi nhận giờ ra về">Ra về</button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
            <tr id="emptyClassRow" <?= $initialVisibleCount > 0 ? 'style="display:none;"' : '' ?>>
              <td colspan="5" class="empty">Không có sinh viên nào trong lớp này.</td>
            </tr>
            </tbody>
          </table>
        </div>
      </div>
      <div class="panel"><div class="panel-heading"><div><h2>Hoạt động gần đây</h2><p class="muted">10 bản ghi mới nhất</p></div><a class="text-link" href="index.php?page=history">Xem tất cả →</a></div><?php render_history(array_slice($history,0,10)); ?></div>
    <?php elseif ($page === 'history'): ?>
      <div class="page-heading"><div><p class="eyebrow">ATTENDANCE RECORDS</p><h1>Lịch sử điểm danh sinh viên</h1><p class="muted">Danh sách tối đa 100 lượt điểm danh gần nhất của sinh viên.</p></div></div>
      <div class="panel"><div class="panel-heading"><h2>Bản ghi điểm danh</h2><span class="code-tag"><?=count($history)?> lượt</span></div><?php render_history($history); ?></div>
    <?php elseif ($page === 'account'): ?>
      <div class="page-heading"><div><p class="eyebrow">SECURITY</p><h1>Tài khoản</h1><p class="muted">Cập nhật mật khẩu đăng nhập.</p></div></div>
      <div class="panel account-panel"><h2>Đổi mật khẩu</h2><p class="muted">Mật khẩu tối thiểu 12 ký tự, gồm chữ hoa, chữ thường, số và ký tự đặc biệt.</p><form method="post" class="account-form"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="change_password"><label>Mật khẩu hiện tại<input type="password" name="old_password" required></label><label>Mật khẩu mới<input type="password" name="new_password" minlength="12" required></label><button class="btn primary" type="submit">Cập nhật mật khẩu</button></form></div>
    <?php endif; ?>
    <footer>Hệ thống Quản lý Điểm danh Sinh viên <span>•</span> Đề tài 27</footer>
  </section>
</main>
</div>
<?php endif; ?>
<script>
function filterClass(selectedClass) {
  const rows = document.querySelectorAll('.student-row');
  let visibleCount = 0;
  rows.forEach(row => {
    const rowClass = row.getAttribute('data-class');
    if (!selectedClass || rowClass === selectedClass) {
      row.style.display = '';
      visibleCount++;
    } else {
      row.style.display = 'none';
    }
  });

  const emptyRow = document.getElementById('emptyClassRow');
  if (emptyRow) {
    emptyRow.style.display = visibleCount === 0 ? '' : 'none';
  }

  const badge = document.getElementById('classCountBadge');
  if (badge) {
    badge.textContent = visibleCount + ' sinh viên';
  }

  document.querySelectorAll('.hidden-selected-class').forEach(input => {
    input.value = selectedClass;
  });

  const url = new URL(window.location.href);
  if (selectedClass) {
    url.searchParams.set('class', selectedClass);
  } else {
    url.searchParams.delete('class');
  }
  window.history.replaceState({}, '', url.toString());
}

document.addEventListener('DOMContentLoaded', () => {
  const select = document.getElementById('classFilterSelect');
  if (select && select.value) {
    filterClass(select.value);
  }
});
</script>
</body></html>
<?php
function render_history(array $rows): void {
    echo '<div class="table-wrap"><table><thead><tr><th>Mã SV</th><th>Họ và tên</th><th>Lớp học</th><th>Ngày</th><th>Giờ vào</th><th>Giờ ra</th><th>Trạng thái</th></tr></thead><tbody>';
    if (!$rows) echo '<tr><td colspan="7" class="empty">Chưa có dữ liệu điểm danh sinh viên.</td></tr>';
    foreach ($rows as $r) {
        $done = !empty($r['check_out']);
        echo '<tr><td><span class="code-tag">'.e($r['code']).'</span></td><td><b>'.e($r['full_name']).'</b></td><td>'.e($r['department']).'</td><td>'.e($r['attendance_date']).'</td><td>'.e($r['check_in'] ? date('H:i:s', strtotime($r['check_in'])) : '—').'</td><td>'.e($r['check_out'] ? date('H:i:s', strtotime($r['check_out'])) : '—').'</td><td><span class="record-status '.($done?'done':'pending').'">'.($done?'Đã về':'Đang trong lớp').'</span></td></tr>';
    }
    echo '</tbody></table></div>';
}
?>
