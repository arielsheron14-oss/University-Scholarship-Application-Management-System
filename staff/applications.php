
<?php

require __DIR__.'/../config/database.php';
require __DIR__.'/../includes/functions.php';

require_role(['staff','admin']);

$allowed = [
    'Submitted',
    'Under Review',
    'Shortlisted',
    'Rejected',
    'Awarded'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verify_csrf();

    $id = (int)$_POST['application_id'];
    $status = $_POST['status'] ?? '';

    if (in_array($status, $allowed, true)) {

        $q = $pdo->prepare(
            'UPDATE applications SET status=? WHERE application_id=?'
        );

        $q->execute([$status, $id]);

        $q = $pdo->prepare(
            'SELECT student_id FROM applications WHERE application_id=?'
        );

        $q->execute([$id]);

        $student = (int)$q->fetchColumn();

        if ($student) {

            add_notification(
                $pdo,
                $student,
                "Your scholarship application status changed to: $status"
            );

        }

        flash('Application status updated.');

    } else {

        flash('Invalid status.', 'error');

    }

    redirect('applications.php');

}

$rows = $pdo->query(
    'SELECT
        a.*,
        u.full_name,
        u.email,
        s.title,
        sp.household_income_range,
        sp.household_size,
        sp.dependents_count,
        sp.guardian_occupation,
        sp.employment_status,
        sp.receives_financial_aid,
        sp.financial_circumstances
    FROM applications a
    JOIN users u ON u.user_id = a.student_id
    JOIN scholarships s ON s.scholarship_id = a.scholarship_id
    LEFT JOIN student_profiles sp ON sp.user_id = a.student_id
    ORDER BY a.submitted_at DESC'
)->fetchAll();

$docQ = $pdo->prepare(
    'SELECT
        ad.*,
        sr.requirement_name
    FROM application_documents ad
    LEFT JOIN scholarship_requirements sr
    ON sr.requirement_id = ad.requirement_id
    WHERE ad.application_id=?
    ORDER BY ad.document_id'
);

$pageTitle = 'Review Applications';

require __DIR__.'/../includes/header.php';

?>

<h1>Review applications</h1>

<div class="table-wrap">

    <table>

        <tr>
            <th>Student</th>
            <th>Scholarship</th>
            <th>Statement</th>
            <th>Financial background</th>
            <th>Document</th>
            <th>Status</th>
            <th>Update</th>
        </tr>

        <?php foreach ($rows as $r): ?>

            <tr>

                <td>

                    <?=e($r['full_name'])?>

                    <br>

                    <small><?=e($r['email'])?></small>

                </td>

                <td>
                    <?=e($r['title'])?>
                </td>

                <td>
                    <?=e($r['applicant_statement'])?>
                </td>

                <td>

                    <strong>Monthly household income:</strong>

                    <?=e($r['household_income_range'] ?: 'Not provided')?>

                    <br>

                    <strong>Household size:</strong>

                    <?=e($r['household_size'] ?? 'Not provided')?>

                    <br>

                    <strong>Dependents:</strong>

                    <?=e($r['dependents_count'] ?? 'Not provided')?>

                    <br>

                    <strong>Guardian occupation:</strong>

                    <?=e($r['guardian_occupation'] ?: 'Not provided')?>

                    <br>

                    <strong>Employment:</strong>

                    <?=e($r['employment_status'] ?: 'Not provided')?>

                    <br>

                    <strong>Other assistance:</strong>

                    <?=(
                        $r['receives_financial_aid'] === null
                        ? 'Not provided'
                        : ($r['receives_financial_aid'] ? 'Yes' : 'No')
                    )?>

                    <br>

                    <strong>Additional circumstances:</strong>

                    <?=e($r['financial_circumstances'] ?: 'Not provided')?>

                </td>

                <td>

                    <?php

                    $docQ->execute([
                        (int)$r['application_id']
                    ]);

                    $docs = $docQ->fetchAll(PDO::FETCH_ASSOC);

                    ?>

                    <?php if ($docs): ?>

                        <?php foreach ($docs as $doc): ?>

                            <div class="review-document">

                                <strong>
                                    <?=e($doc['requirement_name'] ?: 'Additional document')?>
                                </strong>

                                <br>

                                <a href="download_document.php?id=<?= (int)$doc['document_id']?>">

                                    <?=e($doc['original_filename'])?>

                                </a>

                            </div>

                        <?php endforeach; ?>

                    <?php elseif ($r['document_path']): ?>

                        <a
                            href="../<?=e($r['document_path'])?>"
                            target="_blank"
                            rel="noopener"
                        >
                            Open PDF
                        </a>

                    <?php else: ?>

                        None

                    <?php endif; ?>

                </td>

                <td>
                    <?=e($r['status'])?>
                </td>

                <td>

                    <form method="post">

                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?=e(csrf_token())?>"
                        >

                        <input
                            type="hidden"
                            name="application_id"
                            value="<?=$r['application_id']?>"
                        >

                        <select name="status">

                            <?php foreach ($allowed as $st): ?>

                                <option
                                    <?=($st === $r['status'] ? 'selected' : '')?>
                                    value="<?=e($st)?>"
                                >
                                    <?=e($st)?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                        <button>Save</button>

                    </form>

                </td>

            </tr>

        <?php endforeach; ?>

    </table>

</div>

<?php

require __DIR__.'/../includes/footer.php';

?>
