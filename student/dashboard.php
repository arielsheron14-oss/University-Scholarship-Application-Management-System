
<?php

require __DIR__.'/../config/database.php';
require __DIR__.'/../includes/functions.php';

require_role(['student']);

$id = (int)$_SESSION['user_id'];

$q = $pdo->prepare(
    'SELECT * FROM student_profiles WHERE user_id=?'
);

$q->execute([$id]);

$profile = $q->fetch();

$q = $pdo->prepare(
    'SELECT COUNT(*) FROM applications WHERE student_id=?'
);

$q->execute([$id]);

$count = $q->fetchColumn();

$q = $pdo->prepare(
    "SELECT s.*, c.category_name
    FROM scholarships s
    LEFT JOIN scholarship_categories c
    ON c.category_id=s.category_id
    WHERE s.status='published'
    AND s.closing_date>=CURDATE()
    ORDER BY s.closing_date
    LIMIT 5"
);

$scholarships = $q->fetchAll();

$pageTitle = 'Student Dashboard';

require __DIR__.'/../includes/header.php';

?>

<h1>Welcome, <?=e($_SESSION['full_name'])?></h1>

<div class="stats">

    <div class="stat">

        <strong><?= (int)$count ?></strong>

        <span>Applications submitted</span>

    </div>

    <div class="stat">

        <strong><?=e($profile['current_gpa'] ?? '0.00')?></strong>

        <span>Current GPA</span>

    </div>

</div>

<p>

    <a class="button" href="profile.php">
        Update my profile
    </a>

    <a class="button secondary" href="my_applications.php">
        Track applications
    </a>

</p>

<h2>Scholarships you may consider</h2>

<div class="cards">

    <?php foreach ($scholarships as $s):

        $match = (float)($profile['current_gpa'] ?? 0) >= (float)$s['min_gpa'];

    ?>

        <article class="card">

            <h3><?=e($s['title'])?></h3>

            <p>
                Minimum GPA: <?=e($s['min_gpa'])?>
            </p>

            <p class="<?=$match ? 'good' : 'warning'?>">

                <?=$match ? 'Possible GPA match' : 'Review GPA requirement'?>

            </p>

            <a href="../scholarships/view.php?id=<?=(int)$s['scholarship_id']?>">
                View
            </a>

        </article>

    <?php endforeach; ?>

</div>

<?php

require __DIR__.'/../includes/footer.php';

?>
