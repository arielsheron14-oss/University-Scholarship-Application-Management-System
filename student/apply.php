
<?php

// 1. Include required files
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/functions.php';

require_role(['student']);


// 2. Get scholarship ID
$sid = (int)($_GET['id'] ?? $_POST['scholarship_id'] ?? 0);


// 3. Get scholarship details
$q = $pdo->prepare("
    SELECT *
    FROM scholarships
    WHERE scholarship_id = ?
      AND status = 'published'
      AND opening_date <= CURDATE()
      AND closing_date >= CURDATE()
");

$q->execute([$sid]);

$s = $q->fetch(PDO::FETCH_ASSOC);

if (!$s) {
    http_response_code(404);
    exit('This scholarship is not currently open for applications.');
}


// 4. Get logged-in student ID
$userId = (int)$_SESSION['user_id'];


// 5. Get student profile information
$profileQuery = $pdo->prepare("
    SELECT
        u.full_name,
        u.email,
        u.phone,
        u.date_of_birth,
        u.address,
        sp.student_number,
        sp.faculty,
        sp.degree_program,
        sp.study_year,
        sp.current_gpa,
        sp.achievements,
        sp.household_income_range,
        sp.household_size,
        sp.dependents_count,
        sp.guardian_occupation,
        sp.employment_status,
        sp.receives_financial_aid,
        sp.financial_circumstances
    FROM users u
    LEFT JOIN student_profiles sp
        ON sp.user_id = u.user_id
    WHERE u.user_id = ?
");

$profileQuery->execute([$userId]);

$student = $profileQuery->fetch(PDO::FETCH_ASSOC) ?: [];


// 6. Get scholarship requirements
$reqQ = $pdo->prepare("
    SELECT
        requirement_id,
        requirement_name,
        description,
        is_required,
        allowed_file_types,
        max_file_size_mb
    FROM scholarship_requirements
    WHERE scholarship_id = ?
    ORDER BY sort_order, requirement_id
");

$reqQ->execute([$sid]);

$requirements = $reqQ->fetchAll(PDO::FETCH_ASSOC);


// 7. Initialize variables
$error = '';
$statement = trim($_POST['applicant_statement'] ?? '');


// 8. Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // CSRF verification
    verify_csrf();

    // Check declaration
    $confirmed = isset($_POST['declaration'])
        && $_POST['declaration'] === '1';

    // Validate applicant statement
    if ($statement === '') {
        $error = 'Please complete your applicant statement.';
    } elseif (!$confirmed) {
        $error = 'Please confirm the declaration before submitting.';
    }


    // 9. Validate required document uploads
    $uploads = [];

    if (!$error) {

        foreach ($requirements as $req) {

            $rid = (int)$req['requirement_id'];

            $file = $_FILES['requirement_files']['tmp_name'][$rid] ?? '';

            $err = $_FILES['requirement_files']['error'][$rid]
                ?? UPLOAD_ERR_NO_FILE;

            // Check required file
            if ($err === UPLOAD_ERR_NO_FILE) {

                if ((int)$req['is_required'] === 1) {
                    $error = 'Please upload: ' . $req['requirement_name'] . '.';
                    break;
                }

                continue;
            }

            // Check upload error
            if ($err !== UPLOAD_ERR_OK || !is_uploaded_file($file)) {
                $error = 'There was a problem uploading '
                    . $req['requirement_name'] . '.';
                break;
            }

            // Get file information
            $size = (int)($_FILES['requirement_files']['size'][$rid] ?? 0);

            $maxBytes = max(1, (int)$req['max_file_size_mb'])
                * 1024 * 1024;

            $original = $_FILES['requirement_files']['name'][$rid]
                ?? 'document';

            $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));

            $allowed = array_filter(
                array_map(
                    'trim',
                    explode(',', strtolower($req['allowed_file_types'] ?: 'pdf'))
                )
            );

            $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file);

            // Validate file extension and MIME type
            $validMime =
                ($ext === 'pdf' && $mime === 'application/pdf')
                || (in_array($ext, ['jpg', 'jpeg'], true) && $mime === 'image/jpeg')
                || ($ext === 'png' && $mime === 'image/png');

            if (
                !in_array($ext, $allowed, true)
                || !$validMime
                || $size > $maxBytes
            ) {

                $error = 'Invalid file for ' . $req['requirement_name']
                    . '. Allowed: ' . implode(', ', $allowed)
                    . '; maximum ' . (int)$req['max_file_size_mb'] . ' MB.';

                break;
            }

            // Store validated upload information
            $uploads[$rid] = [
                'tmp' => $file,
                'ext' => $ext,
                'name' => $original,
                'requirement' => $req
            ];
        }
    }


    // 10. Validate optional general supporting document
    $general = null;

    if (
        !$error
        && isset($_FILES['document'])
        && $_FILES['document']['error'] !== UPLOAD_ERR_NO_FILE
    ) {

        $f = $_FILES['document'];

        $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));

        $mime = (
            $f['error'] === UPLOAD_ERR_OK
            && is_uploaded_file($f['tmp_name'])
        )
            ? (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name'])
            : '';

        if (
            $f['error'] !== UPLOAD_ERR_OK
            || $f['size'] > 2 * 1024 * 1024
            || $ext !== 'pdf'
            || $mime !== 'application/pdf'
        ) {

            $error = 'The optional additional document must be a valid PDF smaller than 2 MB.';

        } else {

            $general = $f['tmp_name'];
        }
    }


    // 11. Save application if there are no errors
    if (!$error) {

        $saved = [];

        try {

            // Create upload directory
            $dir = __DIR__ . '/../uploads/application_documents';

            if (
                !is_dir($dir)
                && !mkdir($dir, 0750, true)
                && !is_dir($dir)
            ) {
                throw new RuntimeException(
                    'Upload directory could not be created.'
                );
            }


            // Start database transaction
            $pdo->beginTransaction();


            // Save student profile snapshot
            $snapshot = json_encode(
                $student,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            );


            // Insert application
            $insert = $pdo->prepare("
                INSERT INTO applications (
                    scholarship_id,
                    student_id,
                    applicant_statement,
                    document_path,
                    profile_snapshot
                )
                VALUES (?, ?, ?, ?, ?)
            ");

            $insert->execute([
                $sid,
                $userId,
                $statement,
                null,
                $snapshot
            ]);

            $applicationId = (int)$pdo->lastInsertId();


            // Prepare document insert query
            $docInsert = $pdo->prepare("
                INSERT INTO application_documents (
                    application_id,
                    requirement_id,
                    original_filename,
                    stored_path,
                    mime_type,
                    file_size
                )
                VALUES (?, ?, ?, ?, ?, ?)
            ");


            // 12. Save scholarship-specific documents
            foreach ($uploads as $rid => $upload) {

                $filename = bin2hex(random_bytes(18))
                    . '.' . $upload['ext'];

                $target = $dir . '/' . $filename;

                if (!move_uploaded_file($upload['tmp'], $target)) {
                    throw new RuntimeException(
                        'Could not save one of the supporting documents.'
                    );
                }

                $saved[] = $target;

                $relative = 'uploads/application_documents/' . $filename;

                $mime = (new finfo(FILEINFO_MIME_TYPE))->file($target);

                $docInsert->execute([
                    $applicationId,
                    $rid,
                    $upload['name'],
                    $relative,
                    $mime,
                    filesize($target)
                ]);
            }


            // 13. Save optional general document
            if ($general) {

                $filename = bin2hex(random_bytes(18)) . '.pdf';

                $target = $dir . '/' . $filename;

                if (!move_uploaded_file($general, $target)) {
                    throw new RuntimeException(
                        'Could not save the additional document.'
                    );
                }

                $saved[] = $target;

                $relative = 'uploads/application_documents/' . $filename;

                $mime = 'application/pdf';

                $docInsert->execute([
                    $applicationId,
                    null,
                    $_FILES['document']['name'],
                    $relative,
                    $mime,
                    filesize($target)
                ]);

                // Update application document path
                $pdo->prepare("
                    UPDATE applications
                    SET document_path = ?
                    WHERE application_id = ?
                ")->execute([
                    $relative,
                    $applicationId
                ]);
            }


            // 14. Add notification
            add_notification(
                $pdo,
                $userId,
                'Your application for ' . $s['title'] . ' has been submitted.'
            );


            // 15. Commit transaction
            $pdo->commit();

            flash('Your scholarship application has been submitted successfully.');

            redirect('my_applications.php');


        } catch (Throwable $ex) {

            // Rollback transaction
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            // Delete uploaded files if saving failed
            foreach ($saved as $filePath) {
                if (is_file($filePath)) {
                    @unlink($filePath);
                }
            }

            // Display error message
            $error = ($ex instanceof PDOException)
                ? 'You may have already applied for this scholarship, or your application could not be saved.'
                : $ex->getMessage();
        }
    }
}


// 16. Helper functions
function display_value($value): string {

    return ($value !== null && $value !== '')
        ? e($value)
        : 'Not provided';
}


function profile_display(string $key, array $student): string {

    if (
        $key === 'receives_financial_aid'
        && array_key_exists($key, $student)
        && $student[$key] !== null
    ) {

        return ((int)$student[$key] === 1) ? 'Yes' : 'No';
    }

    return display_value($student[$key] ?? null);
}


// 17. Page title and header
$pageTitle = 'Scholarship Application';

require __DIR__ . '/../includes/header.php';

?>

<!-- 18. Application page -->
<div class="application-page">

    <div class="application-heading">

        <p class="application-eyebrow">SCHOLARSHIP APPLICATION</p>

        <h1><?= e($s['title']) ?></h1>

        <p>
            Your personal, academic and financial information is securely
            taken from your student profile. Review it below; edit your
            profile if anything is incorrect.
        </p>

        <div class="application-meta">

            <span>
                Deadline:
                <strong>
                    <?= e(date('d M Y', strtotime($s['closing_date']))) ?>
                </strong>
            </span>

            <span>
                Only the statement and scholarship-specific documents
                need to be completed here.
            </span>

        </div>

    </div>


    <!-- Display error message -->
    <?php if ($error): ?>

        <div class="alert error" role="alert">
            <?= e($error) ?>
        </div>

    <?php endif; ?>


    <!-- Application progress -->
    <div class="application-progress">

        <a href="#profile-summary">
            <span>1</span> Profile summary
        </a>

        <a href="#application-response">
            <span>2</span> Statement
        </a>

        <a href="#required-uploads">
            <span>3</span> Documents
        </a>

        <a href="#declaration">
            <span>4</span> Submit
        </a>

    </div>


    <!-- Application form -->
    <form method="post" enctype="multipart/form-data" class="application-form">

        <input
            type="hidden"
            name="csrf_token"
            value="<?= e(csrf_token()) ?>"
        >

        <input
            type="hidden"
            name="scholarship_id"
            value="<?= e($sid) ?>"
        >


        <!-- 19. Student profile summary -->
        <section class="application-section" id="profile-summary">

            <div class="application-section-title">

                <span class="application-number">01</span>

                <div>
                    <h2>Your profile information</h2>

                    <p>
                        Automatically connected to your student profile
                        — no duplicate data entry.
                    </p>
                </div>

            </div>


            <div class="application-fields">

                <?php
                foreach ([
                    'Full name' => 'full_name',
                    'Student registration number' => 'student_number',
                    'Email address' => 'email',
                    'Phone number' => 'phone',
                    'Date of birth' => 'date_of_birth',
                    'Faculty' => 'faculty',
                    'Degree programme' => 'degree_program',
                    'Study year' => 'study_year',
                    'Current GPA' => 'current_gpa',
                    'Monthly household income' => 'household_income_range',
                    'Household size' => 'household_size',
                    'Financial dependents' => 'dependents_count',
                    'Parent / guardian occupation' => 'guardian_occupation',
                    'Employment status' => 'employment_status',
                    'Receiving other financial aid' => 'receives_financial_aid'
                ] as $label => $key):
                ?>

                    <label>

                        <?= e($label) ?>

                        <span class="readonly-field">
                            <?= profile_display($key, $student) ?>
                        </span>

                    </label>

                <?php endforeach; ?>


                <label class="field-wide">

                    Residential address

                    <span class="readonly-field readonly-multiline">
                        <?= display_value($student['address'] ?? '') ?>
                    </span>

                </label>


                <label class="field-wide">

                    Academic achievements

                    <span class="readonly-field readonly-multiline">
                        <?= display_value($student['achievements'] ?? '') ?>
                    </span>

                </label>


                <label class="field-wide">

                    Additional financial circumstances

                    <span class="readonly-field readonly-multiline">
                        <?= display_value($student['financial_circumstances'] ?? '') ?>
                    </span>

                </label>

            </div>


            <p class="application-hint">

                Need to update these details?

                <a href="/University_Scholarship_System/profile.php">
                    Go to your profile
                </a>.

                The application will use the latest saved profile details
                when submitted.

            </p>

        </section>


        <!-- 20. Applicant statement -->
        <section class="application-section" id="application-response">

            <div class="application-section-title">

                <span class="application-number">02</span>

                <div>
                    <h2>Applicant statement</h2>

                    <p>
                        Write a statement tailored to this scholarship.
                    </p>
                </div>

            </div>


            <label class="application-label" for="applicant_statement">

                Why should you be considered for this scholarship?

                <b>*</b>

                <small>
                    Describe your goals, relevant achievements and how
                    this scholarship would support your education.
                </small>

            </label>


            <textarea
                id="applicant_statement"
                name="applicant_statement"
                rows="8"
                maxlength="5000"
                required
                placeholder="Write your application statement here..."
            ><?= e($statement) ?></textarea>

        </section>


        <!-- 21. Required document uploads -->
        <section class="application-section" id="required-uploads">

            <div class="application-section-title">

                <span class="application-number">03</span>

                <div>
                    <h2>Scholarship-specific documents</h2>

                    <p>
                        Upload only the evidence requested for this scholarship.
                    </p>
                </div>

            </div>


            <?php if ($requirements): ?>

                <?php foreach ($requirements as $req): ?>

                    <div class="dynamic-document">

                        <label
                            class="application-label"
                            for="req-<?= $req['requirement_id'] ?>"
                        >

                            <?= e($req['requirement_name']) ?>

                            <?php if ((int)$req['is_required'] === 1): ?>

                                <b>*</b>

                            <?php else: ?>

                                <small>(Optional)</small>

                            <?php endif; ?>


                            <small>

                                <?= e(
                                    $req['description']
                                    ?: 'Upload clear supporting evidence.'
                                ) ?>

                                ·

                                <?= e(
                                    strtoupper(
                                        str_replace(
                                            ',',
                                            ', ',
                                            $req['allowed_file_types'] ?: 'pdf'
                                        )
                                    )
                                ) ?>

                                · Max <?= (int)$req['max_file_size_mb'] ?> MB

                            </small>

                        </label>


                        <input
                            id="req-<?= $req['requirement_id'] ?>"
                            type="file"
                            name="requirement_files[<?= $req['requirement_id'] ?>]"
                            accept=".pdf,.jpg,.jpeg,.png"
                            <?= (
                                (int)$req['is_required'] === 1
                                ? 'required'
                                : ''
                            ) ?>
                        >

                    </div>

                <?php endforeach; ?>

            <?php else: ?>

                <div class="required-documents">

                    <strong>
                        No additional documents have been specified.
                    </strong>

                    <p>
                        This scholarship does not currently require
                        any scholarship-specific uploads.
                    </p>

                </div>

            <?php endif; ?>


            <!-- Optional general supporting document -->
            <div class="application-upload">

                <label
                    class="application-label"
                    for="supporting-document"
                >

                    Additional supporting document

                    <small>
                        Optional · PDF only · Maximum 2 MB
                    </small>

                </label>


                <input
                    id="supporting-document"
                    type="file"
                    name="document"
                    accept=".pdf,application/pdf"
                >

            </div>

        </section>


        <!-- 22. Declaration -->
        <section class="application-section" id="declaration">

            <div class="application-section-title">

                <span class="application-number">04</span>

                <div>
                    <h2>Declaration and submission</h2>

                    <p>
                        Review your information before submitting.
                    </p>
                </div>

            </div>


            <label class="application-declaration">

                <input
                    type="checkbox"
                    name="declaration"
                    value="1"
                    required
                >

                <span>

                    I confirm that my profile information and the details
                    in this application are accurate and complete to the
                    best of my knowledge.

                    <b>*</b>

                </span>

            </label>

        </section>


        <!-- 23. Submit application -->
        <div class="application-submit">

            <a
                class="button secondary"
                href="view.php?id=<?= e($sid) ?>"
            >
                Back to scholarship
            </a>


            <button type="submit">
                Submit scholarship application
            </button>

        </div>

    </form>

</div>


<?php

// 24. Include footer
require __DIR__ . '/../includes/footer.php';

?>
