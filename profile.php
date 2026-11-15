<?php
require __DIR__.'/config/database.php';
require __DIR__.'/includes/functions.php';
require_login();

$id = (int)$_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'student';
$stmt = $pdo->prepare('SELECT u.*, sp.student_number, sp.faculty, sp.degree_program, sp.study_year, sp.current_gpa, sp.achievements, sp.household_income_range, sp.household_size, sp.dependents_count, sp.guardian_occupation, sp.employment_status, sp.receives_financial_aid, sp.financial_circumstances FROM users u LEFT JOIN student_profiles sp ON sp.user_id=u.user_id WHERE u.user_id=?');
$stmt->execute([$id]);
$profile = $stmt->fetch();
if (!$profile) { http_response_code(404); exit('User profile not found.'); }
$error = '';
$validIncomeRanges = [
    '',
    'Below LKR 25,000',
    'LKR 25,000–49,999',
    'LKR 50,000–99,999',
    'LKR 100,000–149,999',
    'LKR 150,000–249,999',
    'LKR 250,000 or above',
    'Prefer not to say'
];

$validEmployment = [
    '',
    'Not employed',
    'Employed full-time',
    'Employed part-time',
    'Self-employed',
    'Casual / Temporary employment',
    'Unemployed and seeking employment',
    'Retired',
    'Unable to work',
    'Prefer not to say'
];
$incomeRange = $profile['household_income_range'] ?? '';
$householdSizeRaw = $profile['household_size'] ?? '';
$dependentsRaw = $profile['dependents_count'] ?? '';
$guardianOccupation = $profile['guardian_occupation'] ?? '';
$employmentStatus = $profile['employment_status'] ?? '';
$financialAidRaw = isset($profile['receives_financial_aid']) && $profile['receives_financial_aid'] !== null ? (string)(int)$profile['receives_financial_aid'] : '';
$financialCircumstances = $profile['financial_circumstances'] ?? '';
$uploadDir = __DIR__.'/uploads/profile_pictures';
if (!is_dir($uploadDir)) { mkdir($uploadDir, 0755, true); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name = trim($_POST['full_name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $phone = trim($_POST['phone'] ?? '');
    $dob = trim($_POST['date_of_birth'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $studentNo = trim($_POST['student_number'] ?? '');
    $faculty = trim($_POST['faculty'] ?? '');
    $degree = trim($_POST['degree_program'] ?? '');
    $studyYearRaw = trim($_POST['study_year'] ?? '');
    $gpaRaw = trim($_POST['current_gpa'] ?? '0');
    $achievements = trim($_POST['achievements'] ?? '');
    $incomeRange = trim($_POST['household_income_range'] ?? '');
    $householdSizeRaw = trim($_POST['household_size'] ?? '');
    $dependentsRaw = trim($_POST['dependents_count'] ?? '');
    $guardianOccupation = trim($_POST['guardian_occupation'] ?? '');
    $employmentStatus = trim($_POST['employment_status'] ?? '');
    $financialAidRaw = $_POST['receives_financial_aid'] ?? '';
    $financialCircumstances = trim($_POST['financial_circumstances'] ?? '');
    $financialAidRaw = (string)$financialAidRaw;
    $validIncomeRanges = ['', 'Below LKR 25,000', 'LKR 25,000–49,999', 'LKR 50,000–99,999', 'LKR 100,000–149,999', 'LKR 150,000–249,999', 'LKR 250,000 or above', 'Prefer not to say'];
    $validEmployment = ['', 'Employed full-time', 'Employed part-time', 'Self-employed', 'Unemployed', 'Retired', 'Not applicable'];
    $removePhoto = isset($_POST['remove_picture']);
    $newPhoto = null;

    if ($name === '' || mb_strlen($name) > 120) {
        $error = 'Please enter a name (maximum 120 characters).';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($phone) > 30) {
        $error = 'Phone number must be 30 characters or fewer.';
    } elseif ($dob !== '' && (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob) || $dob > date('Y-m-d'))) {
        $error = 'Please enter a valid date of birth that is not in the future.';
    } elseif ($role === 'student' && $studyYearRaw !== '' && (!ctype_digit($studyYearRaw) || (int)$studyYearRaw < 1 || (int)$studyYearRaw > 8)) {
        $error = 'Study year must be between 1 and 8.';
    } elseif ($role === 'student' && (!is_numeric($gpaRaw) || (float)$gpaRaw < 0 || (float)$gpaRaw > 4)) {
        $error = 'GPA must be between 0.00 and 4.00.';
    } elseif ($role === 'student' && !in_array($incomeRange, $validIncomeRanges, true)) {
        $error = 'Please select a valid household income range.';
    } elseif ($role === 'student' && !in_array($employmentStatus, $validEmployment, true)) {
        $error = 'Please select a valid employment status.';
    } elseif ($role === 'student' && (($householdSizeRaw !== '' && (!ctype_digit($householdSizeRaw) || (int)$householdSizeRaw < 1 || (int)$householdSizeRaw > 30)) || ($dependentsRaw !== '' && (!ctype_digit($dependentsRaw) || (int)$dependentsRaw < 0 || (int)$dependentsRaw > 30)))) {
        $error = 'Household size must be 1–30 and dependents must be 0–30.';
    } elseif ($role === 'student' && !in_array($financialAidRaw, ['', '1', '0'], true)) {
        $error = 'Please select a valid financial assistance option.';
    } elseif ($role === 'student' && (mb_strlen($guardianOccupation) > 150 || mb_strlen($financialCircumstances) > 2000)) {
        $error = 'Financial background details are too long.';
    }

    if ($error === '' && isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] !== UPLOAD_ERR_NO_FILE) {
        $file = $_FILES['profile_picture'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $error = 'The picture could not be uploaded. Please try again.';
        } elseif ($file['size'] > 2 * 1024 * 1024) {
            $error = 'Profile picture must be 2 MB or smaller.';
        } else {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($file['tmp_name']);
            $extensions = ['image/jpeg'=>'jpg', 'image/png'=>'png', 'image/webp'=>'webp'];
            if (!isset($extensions[$mime]) || @getimagesize($file['tmp_name']) === false) {
                $error = 'Please upload a valid JPG, PNG or WebP image.';
            } else {
                $newPhoto = 'profile_pictures/'.$id.'_'.bin2hex(random_bytes(12)).'.'.$extensions[$mime];
                if (!move_uploaded_file($file['tmp_name'], __DIR__.'/uploads/'.$newPhoto)) {
                    $newPhoto = null;
                    $error = 'Could not save the profile picture. Check folder permissions.';
                }
            }
        }
    }

    if ($error === '') {
        try {
            $oldPhoto = $profile['profile_picture'];
            $photo = $newPhoto ?: ($removePhoto ? null : $oldPhoto);
            $stmt = $pdo->prepare('UPDATE users SET full_name=?, email=?, phone=?, date_of_birth=?, address=?, profile_picture=? WHERE user_id=?');
            $stmt->execute([$name, $email, $phone, $dob !== '' ? $dob : null, $address, $photo, $id]);

            if ($role === 'student') {
                $stmt = $pdo->prepare('INSERT INTO student_profiles (user_id, student_number, faculty, degree_program, study_year, current_gpa, achievements, household_income_range, household_size, dependents_count, guardian_occupation, employment_status, receives_financial_aid, financial_circumstances) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE student_number=VALUES(student_number), faculty=VALUES(faculty), degree_program=VALUES(degree_program), study_year=VALUES(study_year), current_gpa=VALUES(current_gpa), achievements=VALUES(achievements), household_income_range=VALUES(household_income_range), household_size=VALUES(household_size), dependents_count=VALUES(dependents_count), guardian_occupation=VALUES(guardian_occupation), employment_status=VALUES(employment_status), receives_financial_aid=VALUES(receives_financial_aid), financial_circumstances=VALUES(financial_circumstances)');
                $stmt->execute([$id, $studentNo, $faculty, $degree, $studyYearRaw === '' ? null : (int)$studyYearRaw, (float)$gpaRaw, $achievements, $incomeRange !== '' ? $incomeRange : null, $householdSizeRaw === '' ? null : (int)$householdSizeRaw, $dependentsRaw === '' ? null : (int)$dependentsRaw, $guardianOccupation, $employmentStatus !== '' ? $employmentStatus : null, $financialAidRaw === '' ? null : (int)$financialAidRaw, $financialCircumstances]);
            }
            $_SESSION['full_name'] = $name;
            $_SESSION['profile_picture'] = $photo ?? '';
            if (($newPhoto || $removePhoto) && $oldPhoto && strpos($oldPhoto, 'profile_pictures/') === 0) {
                $oldPath = __DIR__.'/uploads/'.$oldPhoto;
                if (is_file($oldPath)) @unlink($oldPath);
            }
            flash('Your profile has been updated successfully.');
            redirect('profile.php');
        } catch (PDOException $ex) {
            if ($newPhoto && is_file(__DIR__.'/uploads/'.$newPhoto)) @unlink(__DIR__.'/uploads/'.$newPhoto);
            $error = $ex->getCode() === '23000' ? 'That email address is already being used by another account.' : 'Could not update your profile. Please try again.';
        }
    }
    if ($newPhoto && $error !== '' && is_file(__DIR__.'/uploads/'.$newPhoto)) @unlink(__DIR__.'/uploads/'.$newPhoto);
}

$pageTitle = 'My Profile';
require __DIR__.'/includes/header.php';
$photoUrl = !empty($profile['profile_picture']) ? '/University_Scholarship_System/uploads/'.e($profile['profile_picture']) : '';
$initial = strtoupper(substr(trim($profile['full_name']), 0, 1));
?>
<section class="profile-page profile-layout">
  <header class="profile-heading">
    <div><p class="eyebrow">ACCOUNT SETTINGS</p><h1>My Profile</h1><p class="muted">Keep your personal, academic and financial information up to date.</p></div>
  </header>
  <?php if ($error): ?><div class="alert error" role="alert"><?=e($error)?></div><?php endif; ?>
  <form method="post" enctype="multipart/form-data" class="profile-form profile-editor">
    <input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>">
    <aside class="profile-sidebar">
      <section class="panel identity-card">
        <div class="identity-avatar">
          <?php if ($photoUrl): ?><img class="profile-avatar" src="<?=$photoUrl?>" alt="Profile picture">
          <?php else: ?><div class="profile-avatar avatar-placeholder"><?=e($initial)?></div><?php endif; ?>
        </div>
        <h2><?=e($profile['full_name'])?></h2>
        <p><?=e(ucfirst($role))?></p>
        <span class="identity-email"><?=e($profile['email'])?></span>
        <div class="identity-divider"></div>
        <h3>Profile picture</h3>
        <p class="muted picture-help">Use a clear JPG, PNG or WebP image. Maximum 2 MB.</p>
        <button type="button" class="file-picker" id="chooseProfilePhoto">Choose / adjust photo</button>
        <input class="profile-file-input" type="file" id="profilePictureInput" name="profile_picture" accept="image/jpeg,image/png,image/webp">
        <p class="photo-dimensions">Square profile photo · 512 × 512 px</p>
        <?php if ($photoUrl): ?><label class="remove-picture"><input type="checkbox" name="remove_picture" value="1"> Remove current picture</label><?php endif; ?>
      </section>
      <nav class="profile-nav" aria-label="Profile sections">
        <a href="#personal-details"><span>01</span> Personal details</a>
        <?php if ($role === 'student'): ?><a href="#academic-details"><span>02</span> Academic details</a><a href="#financial-details"><span>03</span> Financial background</a><?php endif; ?>
      </nav>
    </aside>

    <div class="profile-content">
      <section class="panel profile-section" id="personal-details">
        <div class="section-heading"><div class="section-icon">01</div><div><h2>Personal details</h2><p class="muted">Your basic contact and personal information.</p></div></div>
        <div class="profile-grid">
          <label>Full name<input name="full_name" maxlength="120" value="<?=e($profile['full_name'])?>" required></label>
          <label>Email address<input type="email" name="email" maxlength="150" value="<?=e($profile['email'])?>" required></label>
          <label>Phone number<input type="tel" name="phone" maxlength="30" value="<?=e($profile['phone'] ?? '')?>" placeholder="e.g. +94 77 123 4567"></label>
          <label>Date of birth<input type="date" name="date_of_birth" max="<?=date('Y-m-d')?>" value="<?=e($profile['date_of_birth'] ?? '')?>"></label>
          <label class="full-width">Residential address<textarea name="address" rows="2" maxlength="1000" placeholder="Enter your residential address"><?=e($profile['address'] ?? '')?></textarea></label>
        </div>
      </section>
      <?php if ($role === 'student'): ?>
      <section class="panel profile-section" id="academic-details">
        <div class="section-heading"><div class="section-icon">02</div><div><h2>Academic details</h2><p class="muted">Information used to assess scholarship eligibility.</p></div></div>
        <div class="profile-grid">
          <label>Student number<input name="student_number" maxlength="30" value="<?=e($profile['student_number'] ?? '')?>" placeholder="Enter student number"></label>
          <label>Faculty<input name="faculty" maxlength="120" value="<?=e($profile['faculty'] ?? '')?>" placeholder="Enter faculty"></label>
          <label>Degree programme<input name="degree_program" maxlength="150" value="<?=e($profile['degree_program'] ?? '')?>" placeholder="Enter degree programme"></label>
          <label>Study year<input type="number" min="1" max="8" name="study_year" value="<?=e($profile['study_year'] ?? '')?>" placeholder="e.g. 2"></label>
          <label>Current GPA <span class="field-hint">Scale: 0.00–4.00</span><input type="number" step="0.01" min="0" max="4" name="current_gpa" value="<?=e($profile['current_gpa'] ?? '0.00')?>" placeholder="e.g. 3.50"></label>
          <label class="full-width">Achievements<textarea name="achievements" rows="3" placeholder="Academic awards, extracurricular activities and achievements"><?=e($profile['achievements'] ?? '')?></textarea></label>
        </div>
      </section>
      <section class="panel profile-section" id="financial-details">
        <div class="section-heading"><div class="section-icon">03</div><div><h2>Financial background</h2><p class="muted">Optional information to support need-based scholarship assessment.</p></div></div>
        <div class="privacy-note"><strong>Your information is private.</strong> Only authorized scholarship staff and administrators should use these details for application review. You may leave fields blank or choose “Prefer not to say”.</div>
        <div class="profile-grid">
          <label>Monthly household income
            <select name="household_income_range"><option value="">Select an income range</option><?php foreach ($validIncomeRanges as $range): if ($range === '') continue; ?><option value="<?=e($range)?>" <?=($incomeRange === $range ? 'selected' : '')?>><?=e($range)?></option><?php endforeach; ?></select>
          </label>
          <label>People in household<input type="number" name="household_size" min="1" max="30" value="<?=e($householdSizeRaw)?>" placeholder="e.g. 5"></label>
          <label>Financial dependents<input type="number" name="dependents_count" min="0" max="30" value="<?=e($dependentsRaw)?>" placeholder="e.g. 2"></label>
          <label>Parent / guardian occupation<input name="guardian_occupation" maxlength="150" value="<?=e($guardianOccupation)?>" placeholder="Optional"></label>
          <label>Employment status
            <select name="employment_status"><option value="">Select status</option><?php foreach ($validEmployment as $status): if ($status === '') continue; ?><option value="<?=e($status)?>" <?=($employmentStatus === $status ? 'selected' : '')?>><?=e($status)?></option><?php endforeach; ?></select>
          </label>
          <label>Receiving financial assistance?
            <select name="receives_financial_aid"><option value="" <?=($financialAidRaw === '' ? 'selected' : '')?>>Prefer not to say</option><option value="1" <?=($financialAidRaw === '1' ? 'selected' : '')?>>Yes</option><option value="0" <?=($financialAidRaw === '0' ? 'selected' : '')?>>No</option></select>
          </label>
          <label class="full-width">Additional financial circumstances<textarea name="financial_circumstances" rows="3" maxlength="2000" placeholder="Optional: briefly describe circumstances that may affect your ability to fund your studies."><?=e($financialCircumstances)?></textarea></label>
        </div>
      </section>
      <?php endif; ?>
      <div class="profile-actions"><button type="submit" id="saveProfileButton">Save changes</button><a class="button secondary" href="<?= $role === 'student' ? '/University_Scholarship_System/student/dashboard.php' : '/University_Scholarship_System/staff/dashboard.php' ?>">Cancel</a></div>
    </div>

    <div class="photo-crop-modal" id="photoCropModal" hidden aria-modal="true" role="dialog" aria-labelledby="cropTitle">
      <div class="photo-crop-dialog">
        <div class="crop-header"><div><h2 id="cropTitle">Adjust profile photo</h2><p>Drag the image to position it. Zoom to fit your face inside the circle.</p></div><button type="button" class="crop-close" id="cancelPhotoCrop" aria-label="Close">×</button></div>
        <div class="crop-stage-wrap"><div class="crop-stage" id="cropStage"><img id="cropSourceImage" alt="Photo crop preview" draggable="false"><div class="crop-guide"></div></div></div>
        <label class="zoom-control">Zoom <input type="range" id="photoZoom" min="1" max="3" step="0.01" value="1"></label>
        <div class="crop-actions"><button type="button" class="button secondary" id="cancelCropButton">Cancel</button><button type="button" id="applyPhotoCrop">Use this photo</button></div>
        <p class="crop-error" id="cropError" role="alert"></p>
      </div>
    </div>
  </form>
</section>
<script>
(() => {
  const input = document.getElementById('profilePictureInput');
  const choose = document.getElementById('chooseProfilePhoto');
  const modal = document.getElementById('photoCropModal');
  const stage = document.getElementById('cropStage');
  const image = document.getElementById('cropSourceImage');
  const zoom = document.getElementById('photoZoom');
  const apply = document.getElementById('applyPhotoCrop');
  const error = document.getElementById('cropError');
  const form = document.querySelector('.profile-editor');
  const save = document.getElementById('saveProfileButton');
  const avatar = document.querySelector('.identity-avatar');
  let objectUrl = null, baseW = 0, baseH = 0, drawW = 0, drawH = 0, x = 0, y = 0, dragging = false, startX = 0, startY = 0, startLeft = 0, startTop = 0, croppedFile = null, busy = false;
  let stageSize = 320;
  const clamp = (v, min, max) => Math.min(max, Math.max(min, v));
  function layoutImage(center = false) {
    const factor = Number(zoom.value);
    drawW = baseW * factor; drawH = baseH * factor;
    if (center) { x = (stageSize - drawW) / 2; y = (stageSize - drawH) / 2; }
    x = clamp(x, stageSize - drawW, 0); y = clamp(y, stageSize - drawH, 0);
    image.style.width = drawW + 'px'; image.style.height = drawH + 'px';
    image.style.left = x + 'px'; image.style.top = y + 'px';
  }
  function closeCrop() { modal.hidden = true; if (objectUrl) URL.revokeObjectURL(objectUrl); objectUrl = null; }
  function openCrop(file) {
    error.textContent = '';
    if (!file || !['image/jpeg','image/png','image/webp'].includes(file.type)) { error.textContent = 'Choose a JPG, PNG or WebP image.'; return; }
    if (file.size > 2 * 1024 * 1024) { error.textContent = 'Choose an image smaller than 2 MB.'; return; }
    if (objectUrl) URL.revokeObjectURL(objectUrl);
    objectUrl = URL.createObjectURL(file);
    image.onload = () => {
      modal.hidden = false;
      requestAnimationFrame(() => {
        stageSize = stage.clientWidth || 320;
        const scale = Math.max(stageSize / image.naturalWidth, stageSize / image.naturalHeight);
        baseW = image.naturalWidth * scale; baseH = image.naturalHeight * scale;
        zoom.value = '1'; layoutImage(true);
      });
    };
    image.onerror = () => { error.textContent = 'This image could not be opened. Try another photo.'; };
    image.src = objectUrl;
  }
  choose.addEventListener('click', () => input.click());
  input.addEventListener('change', () => { if (input.files && input.files[0]) openCrop(input.files[0]); });
  zoom.addEventListener('input', () => layoutImage(true));
  stage.addEventListener('pointerdown', e => { dragging = true; stage.setPointerCapture(e.pointerId); startX = e.clientX; startY = e.clientY; startLeft = x; startTop = y; });
  stage.addEventListener('pointermove', e => { if (!dragging) return; x = startLeft + e.clientX - startX; y = startTop + e.clientY - startY; layoutImage(false); });
  stage.addEventListener('pointerup', () => { dragging = false; });
  stage.addEventListener('pointercancel', () => { dragging = false; });
  function cancel() { closeCrop(); input.value = ''; }
  document.getElementById('cancelPhotoCrop').addEventListener('click', cancel);
  document.getElementById('cancelCropButton').addEventListener('click', cancel);
  apply.addEventListener('click', () => {
    if (!image.naturalWidth || busy) return;
    const canvas = document.createElement('canvas'); canvas.width = 512; canvas.height = 512;
    const ctx = canvas.getContext('2d');
    const factor = 512 / stageSize;
    ctx.drawImage(image, x * factor, y * factor, drawW * factor, drawH * factor);
    canvas.toBlob(blob => {
      if (!blob) { error.textContent = 'Could not crop this photo. Please try another image.'; return; }
      croppedFile = new File([blob], 'profile-photo.jpg', {type:'image/jpeg', lastModified:Date.now()});
      try { const transfer = new DataTransfer(); transfer.items.add(croppedFile); input.files = transfer.files; }
      catch (e) { error.textContent = 'Your browser cannot prepare the cropped image. Please use a recent browser.'; return; }
      const previewUrl = URL.createObjectURL(blob);
      const existing = avatar.querySelector('img.profile-avatar');
      if (existing) existing.src = previewUrl;
      else { avatar.innerHTML = ''; const img = document.createElement('img'); img.className = 'profile-avatar'; img.alt = 'New profile picture preview'; img.src = previewUrl; avatar.appendChild(img); }
      const remove = document.querySelector('input[name="remove_picture"]'); if (remove) remove.checked = false;
      closeCrop();
    }, 'image/jpeg', 0.9);
  });
  form.addEventListener('submit', e => {
    if (busy) { e.preventDefault(); return; }
    if (modal.hidden) { save.disabled = true; save.textContent = 'Saving…'; }
  });
})();
</script>
<?php require __DIR__.'/includes/footer.php'; ?>
