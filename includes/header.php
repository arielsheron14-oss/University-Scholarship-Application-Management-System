<?php
$pageTitle = $pageTitle ?? 'University Scholarship System';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?></title>
<link rel="stylesheet" href="/University_Scholarship_System/assets/css/style.css">
<script defer src="/University_Scholarship_System/assets/js/app.js"></script>
</head>
<body>
<header class="topbar">
  <a class="brand" href="/University_Scholarship_System/index.php">Scholarship Portal</a>
  <nav aria-label="Main navigation">
    <a href="/University_Scholarship_System/scholarships/index.php">Scholarships</a>
    <?php if (is_logged_in()): ?>
      <?php if (($_SESSION['role'] ?? '') === 'student'): ?>
        <a href="/University_Scholarship_System/student/dashboard.php">Dashboard</a>
        <a href="/University_Scholarship_System/student/my_applications.php">My Applications</a>
      <?php else: ?>
        <a href="/University_Scholarship_System/staff/dashboard.php">Staff Dashboard</a>
      <?php endif; ?>
      <?php
if (!array_key_exists('profile_picture', $_SESSION)) {
    try {
        $avatarQuery = $pdo->prepare('SELECT profile_picture FROM users WHERE user_id = ?');
        $avatarQuery->execute([(int)$_SESSION['user_id']]);
        $_SESSION['profile_picture'] = $avatarQuery->fetchColumn() ?: '';
    } catch (Throwable $e) {
        $_SESSION['profile_picture'] = '';
    }
}
$navAvatar = $_SESSION['profile_picture'] ?? '';
$navInitial = strtoupper(substr(trim($_SESSION['full_name'] ?? 'U'), 0, 1));
?>
<details class="account-menu">
    <summary class="account-trigger" aria-label="Open profile menu">
        <?php if ($navAvatar): ?>
            <img class="nav-avatar" src="/University_Scholarship_System/uploads/<?=e($navAvatar)?>" alt="">
        <?php else: ?>
            <span class="nav-avatar nav-avatar-placeholder"><?=e($navInitial)?></span>
        <?php endif; ?>
        <span class="account-caret" aria-hidden="true">▾</span>
    </summary>
    <div class="account-dropdown">
        <div class="account-dropdown-user">
            <strong><?=e($_SESSION['full_name'] ?? 'User')?></strong>
            <small><?=e(ucfirst($_SESSION['role'] ?? 'user'))?> account</small>
        </div>
        <a href="/University_Scholarship_System/profile.php">View / Edit Profile</a>
        <a href="/University_Scholarship_System/logout.php">Logout</a>
    </div>
</details>
    <?php else: ?>
      <a href="/University_Scholarship_System/login.php">Login</a>
      <a href="/University_Scholarship_System/register.php">Register</a>
    <?php endif; ?>
  </nav>
</header>
<main class="container">
<?php if (!empty($_SESSION['flash'])): $f=$_SESSION['flash']; unset($_SESSION['flash']); ?>
  <div class="alert <?= e($f['type']) ?>"><?= e($f['message']) ?></div>
<?php endif; ?>
