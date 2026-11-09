<?php
if (session_status() === PHP_SESSION_NONE) session_start();

function e($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
function is_logged_in(): bool {
    return isset($_SESSION['user_id']);
}
function require_login(): void {
    if (!is_logged_in()) {
        header('Location: /University_Scholarship_System/login.php');
        exit;
    }
}
function require_role(array $roles): void {
    require_login();
    if (!in_array($_SESSION['role'] ?? '', $roles, true)) {
        http_response_code(403);
        exit('Access denied.');
    }
}
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf_token'];
}
function verify_csrf(): void {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? '';
        if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
            http_response_code(400);
            exit('Invalid request token. Please go back and try again.');
        }
    }
}
function flash(string $message, string $type='success'): void {
    $_SESSION['flash'] = ['message'=>$message, 'type'=>$type];
}
function redirect(string $path): void {
    header("Location: $path");
    exit;
}
function add_notification(PDO $pdo, int $userId, string $message): void {
    $stmt = $pdo->prepare('INSERT INTO notifications(user_id,message) VALUES(?,?)');
    $stmt->execute([$userId, $message]);
}
