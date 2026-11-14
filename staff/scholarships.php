<?php
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/functions.php';

require_role(['staff', 'admin']);

$sql = "SELECT * FROM scholarship_categories";
$cats = $pdo->query($sql)->fetchAll();

$pageTitle = 'Manage Scholarships';

require __DIR__ . '/../includes/header.php';
?>

<h1>Scholarship Manager</h1>

<?php
require __DIR__ . '/../includes/footer.php';
?>
