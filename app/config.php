<?php
declare(strict_types=1);
date_default_timezone_set(getenv('APP_TIMEZONE') ?: 'Asia/Ho_Chi_Minh');

function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;
    $host = getenv('DB_HOST') ?: 'db';
    $name = getenv('DB_NAME') ?: 'attendance_db';
    $user = getenv('DB_USER') ?: 'attendance_app';
    $pass = getenv('DB_PASSWORD') ?: '';
    $dsn = "mysql:host={$host};dbname={$name};charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $pdo;
}
function e(?string $value): string {
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function verify_csrf(): void {
    $token = $_POST['csrf'] ?? '';
    if (!is_string($token) || !hash_equals($_SESSION['csrf'] ?? '', $token)) {
        http_response_code(400);
        exit('Yêu cầu không hợp lệ (CSRF). Hãy tải lại trang và thử lại.');
    }
}
function ensure_demo_admin(): void {
    $count = (int) db()->query("SELECT COUNT(*) FROM users")->fetchColumn();
    if ($count === 0) {
        $hash = password_hash('123456', PASSWORD_DEFAULT);
        $stmt = db()->prepare("INSERT INTO users(username,password_hash,role) VALUES('admin', ?, 'admin')");
        $stmt->execute([$hash]);
        error_log('Attendance demo admin account created.');
    }
}
function ensure_sample_classes(): void {
    $classes = [
        ['SV001', 'Nguyễn Minh Anh', 'CNTT K23A'],
        ['SV002', 'Trần Hoàng Nam', 'CNTT K23A'],
        ['SV003', 'Lê Thu Hà', 'CNTT K23A'],
        ['SV004', 'Phạm Đức Long', 'CNTT K23A'],
        ['SV005', 'Hoàng Tuấn Kiệt', 'CNTT K23A'],
        ['SV006', 'Vũ Thảo My', 'CNTT K23B'],
        ['SV007', 'Đặng Quang Huy', 'CNTT K23B'],
        ['SV008', 'Bùi Ngọc Ánh', 'CNTT K23B'],
        ['SV009', 'Đỗ Gia Bảo', 'CNTT K23B'],
        ['SV010', 'Ngô Phương Linh', 'CNTT K23B'],
        ['SV011', 'Phan Văn Đạt', 'CNTT K23C'],
        ['SV012', 'Dương Thị Mai', 'CNTT K23C'],
        ['SV013', 'Hồ Tiến Dũng', 'CNTT K23C'],
        ['SV014', 'Lý Bảo Trâm', 'CNTT K23C'],
        ['SV015', 'Trịnh Công Minh', 'CNTT K23C'],
    ];

    $checkStmt = db()->query("SELECT COUNT(*) FROM people WHERE active = 1 AND department = 'CNTT K23A'");
    $k23aCount = (int) $checkStmt->fetchColumn();

    if ($k23aCount !== 5) {
        $stmt = db()->prepare("INSERT INTO people (code, full_name, department, active) 
                               VALUES (?, ?, ?, 1) 
                               ON DUPLICATE KEY UPDATE full_name = VALUES(full_name), department = VALUES(department), active = 1");
        foreach ($classes as $student) {
            $stmt->execute($student);
        }
        db()->exec("UPDATE people SET department = 'CNTT K23A', active = 1 WHERE code IN ('SV001','SV002','SV003','SV004','SV005')");
        db()->exec("UPDATE people SET department = 'CNTT K23B', active = 1 WHERE code IN ('SV006','SV007','SV008','SV009','SV010')");
        db()->exec("UPDATE people SET department = 'CNTT K23C', active = 1 WHERE code IN ('SV011','SV012','SV013','SV014','SV015')");
        db()->exec("UPDATE people SET active = 0 WHERE code NOT IN ('SV001','SV002','SV003','SV004','SV005','SV006','SV007','SV008','SV009','SV010','SV011','SV012','SV013','SV014','SV015')");
    }
}
function require_login(): void {
    if (empty($_SESSION['user'])) {
        header('Location: index.php?page=login');
        exit;
    }
}
