<?php
require __DIR__.'/../config/database.php';require __DIR__.'/../includes/functions.php';require_role(['staff','admin']);
$total=(int)$pdo->query('SELECT COUNT(*) FROM scholarships')->fetchColumn();
$apps=(int)$pdo->query('SELECT COUNT(*) FROM applications')->fetchColumn();
$pageTitle='Staff Dashboard';require __DIR__.'/../includes/header.php';
?>
<h1>Staff dashboard</h1><div class="stats"><div class="stat"><strong><?=$total?></strong><span>Scholarships</span></div><div class="stat"><strong><?=$apps?></strong><span>Applications</span></div></div>
<p><a class="button" href="scholarships.php">Manage scholarships</a> <a class="button secondary" href="applications.php">Review applications</a></p>
<?php if($_SESSION['role']==='admin'):?><a href="users.php">Manage user roles</a><?php endif;?>
<?php require __DIR__.'/../includes/footer.php'; ?>
