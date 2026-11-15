
<?php

// 1. Connect Database and Functions
require __DIR__.'/../config/database.php';
require __DIR__.'/../includes/functions.php';

// 2. Get Scholarship ID
$id = (int)($_GET['id'] ?? 0);

// 3. SQL Query
$q = $pdo->prepare(
    "SELECT s.*, c.category_name
     FROM scholarships s
     LEFT JOIN scholarship_categories c
     ON c.category_id = s.category_id
     WHERE s.scholarship_id = ?
     AND s.status = 'published'"
);

// 4. Execute Query
$q->execute([$id]);


$s = $q->fetch();

// 6. Check Scholarship Exists
if (!$s) {
    http_response_code(404);
    exit('Scholarship not found.');
}

// 7. Page Title and Header
$pageTitle = $s['title'];

require __DIR__.'/../includes/header.php';

?>

<!-- 8. Scholarship Title -->
<h1><?= e($s['title']) ?></h1>

<!-- 9. Scholarship Description -->
<p>
    <?= nl2br(e($s['description'])) ?>
</p>

<!-- 10. Provider Name -->
<p>
    <strong>Provider:</strong>
    <?= e($s['provider_name']) ?>
</p>

<!-- 11. Scholarship Benefits -->
<p>
    <strong>Benefits:</strong>
    <?= nl2br(e($s['benefits'])) ?>
</p>

<!-- 12. Scholarship Category -->
<p>
    <strong>Category:</strong>
    <?= e($s['category_name'] ?? 'Other') ?>
</p>

<!-- 13. Minimum GPA -->
<p>
    <strong>Minimum GPA:</strong>
    <?= e($s['min_gpa']) ?>
</p>

<!-- 14. Required Documents -->
<p>
    <strong>Required documents:</strong>
    <?= nl2br(e($s['required_documents'])) ?>
</p>

<!-- 15. Application Opening and Closing Dates -->
<p>
    <strong>Apply between:</strong>
    <?= e($s['opening_date']) ?>
    and
    <?= e($s['closing_date']) ?>
</p>

<!-- 16. Apply Button / Login Button -->
<?php if (
    is_logged_in() &&
    $_SESSION['role'] === 'student' &&
    $s['closing_date'] >= date('Y-m-d')
): ?>

    <a class="button" href="../student/apply.php?id=<?= $id ?>">
        Apply now
    </a>

<?php elseif (!is_logged_in()): ?>

    <a class="button" href="../login.php">
        Login to apply
    </a>

<?php endif; ?>

<!-- 17. Footer -->
<?php require __DIR__.'/../includes/footer.php'; ?>

