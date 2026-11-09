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
  <nav>
    <a href="/University_Scholarship_System/scholarships/index.php">Scholarships</a>
    <?php if (is_logged_in()): ?>
      <?php if (($_SESSION['role'] ?? '') === 'student'): ?>
        <a href="/University_Scholarship_System/student/dashboard.php">Dashboard</a>
        <a href="/University_Scholarship_System/student/my_applications.php">My Applications</a>
      <?php else: ?>
        <a href="/University_Scholarship_System/staff/dashboard.php">Staff Dashboard</a>
      <?php endif; ?>
      <span class="user"><?= e($_SESSION['full_name']) ?></span>
      <a href="/University_Scholarship_System/logout.php">Logout</a>
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
