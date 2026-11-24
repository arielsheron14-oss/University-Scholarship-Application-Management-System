<?php
require __DIR__.'/../config/database.php';
require __DIR__.'/../includes/functions.php';
require_role(['student']);
$studentId=(int)$_SESSION['user_id'];
$sid=(int)($_GET['id']??$_POST['scholarship_id']??0);
$q=$pdo->prepare("SELECT * FROM scholarships WHERE scholarship_id=? AND status='published' AND opening_date<=CURDATE() AND closing_date>=CURDATE()");$q->execute([$sid]);$scholarship=$q->fetch();
if(!$scholarship){http_response_code(404);exit('This scholarship is not currently open for applications.');}
$q=$pdo->prepare('SELECT u.full_name,u.email,u.phone,u.date_of_birth,u.address,sp.* FROM users u LEFT JOIN student_profiles sp ON sp.user_id=u.user_id WHERE u.user_id=?');$q->execute([$studentId]);$profile=$q->fetch() ?: [];
$q=$pdo->prepare('SELECT * FROM scholarship_document_requirements WHERE scholarship_id=? AND is_active=1 ORDER BY sort_order,requirement_id');$q->execute([$sid]);$requirements=$q->fetchAll();
$q=$pdo->prepare('SELECT * FROM scholarship_questions WHERE scholarship_id=? AND is_active=1 ORDER BY sort_order,question_id');$q->execute([$sid]);$questions=$q->fetchAll();
$q=$pdo->prepare('SELECT application_id FROM applications WHERE scholarship_id=? AND student_id=?');$q->execute([$sid,$studentId]);
if($q->fetchColumn()){flash('You have already applied for this scholarship.','error');redirect('my_applications.php');}
$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 verify_csrf();$statement=trim($_POST['applicant_statement']??'');if(empty($_POST['declaration']))$error='Please accept the declaration before submitting.';$answers=$_POST['answers']??[];$pendingFiles=[];$savedFiles=[];
 if($statement==='')$error='Please enter your applicant statement.';
 foreach($questions as $question){$value=trim((string)($answers[$question['question_id']]??''));if((int)$question['is_required']===1 && $value===''){$error='Please answer all required questions.';break;}if($question['field_type']==='number' && $value!=='' && !is_numeric($value)){$error='A numeric answer is required for '. $question['question_text'];break;}if($question['field_type']==='select' && $value!==''){$opts=json_decode($question['options_json']??'[]',true)?:[];if(!in_array($value,$opts,true)){$error='Please select a valid answer.';break;}}}
 if(!$error){foreach($requirements as $req){$file=$_FILES['documents']['tmp_name'][$req['requirement_id']]??'';$fileError=$_FILES['documents']['error'][$req['requirement_id']]??UPLOAD_ERR_NO_FILE;if($fileError===UPLOAD_ERR_NO_FILE){if((int)$req['is_required']===1){$error='Please upload the required document: '.$req['document_name'];break;}continue;}if($fileError!==UPLOAD_ERR_OK || !is_uploaded_file($file)){$error='A document could not be uploaded. Please try again.';break;}$original=$_FILES['documents']['name'][$req['requirement_id']]??'document';$size=(int)($_FILES['documents']['size'][$req['requirement_id']]??0);$ext=strtolower(pathinfo($original,PATHINFO_EXTENSION));$mime=(new finfo(FILEINFO_MIME_TYPE))->file($file);$allowed=array_map('trim',explode(',',strtolower($req['allowed_types'])));$map=['pdf'=>'application/pdf','jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png'];if(!in_array($ext,$allowed,true)||!isset($map[$ext])||$map[$ext]!==$mime){$error='Invalid file type for '.$req['document_name'].'. Allowed: '.$req['allowed_types'];break;}if($size<1||$size>(float)$req['max_size_mb']*1024*1024){$error=$req['document_name'].' must be smaller than '.e($req['max_size_mb']).' MB.';break;}$pendingFiles[]=['requirement'=>$req,'tmp'=>$file,'original'=>$original,'size'=>$size,'mime'=>$mime,'ext'=>$ext];}}
 if(!$error){$privateDir=__DIR__.'/../private_uploads';if(!is_dir($privateDir))mkdir($privateDir,0750,true);try{foreach($pendingFiles as $i=>$item){$stored=bin2hex(random_bytes(24)).'.'.$item['ext'];if(!move_uploaded_file($item['tmp'],$privateDir.'/'.$stored))throw new RuntimeException('Could not store uploaded documents.');$savedFiles[]=['item'=>$item,'stored'=>$stored];}
  $snapshot=['captured_at'=>date('c'),'student'=>['full_name'=>$profile['full_name']??'','email'=>$profile['email']??'','phone'=>$profile['phone']??'','date_of_birth'=>$profile['date_of_birth']??'','address'=>$profile['address']??'','student_number'=>$profile['student_number']??'','faculty'=>$profile['faculty']??'','degree_program'=>$profile['degree_program']??'','study_year'=>$profile['study_year']??'','current_gpa'=>$profile['current_gpa']??'','achievements'=>$profile['achievements']??'','household_income_range'=>$profile['household_income_range']??'','household_size'=>$profile['household_size']??'','dependents_count'=>$profile['dependents_count']??'','guardian_occupation'=>$profile['guardian_occupation']??'','employment_status'=>$profile['employment_status']??'','receives_financial_aid'=>$profile['receives_financial_aid']??'','financial_circumstances'=>$profile['financial_circumstances']??'']];
  $pdo->beginTransaction();$ins=$pdo->prepare('INSERT INTO applications(scholarship_id,student_id,applicant_statement,profile_snapshot) VALUES(?,?,?,?)');$ins->execute([$sid,$studentId,$statement,json_encode($snapshot,JSON_UNESCAPED_UNICODE)]);$applicationId=(int)$pdo->lastInsertId();
  $ans=$pdo->prepare('INSERT INTO application_answers(application_id,question_id,answer_text,question_text_snapshot) VALUES(?,?,?,?)');foreach($questions as $question){$value=trim((string)($answers[$question['question_id']]??''));if($value!=='')$ans->execute([$applicationId,$question['question_id'],$value,$question['question_text']]);}
  $doc=$pdo->prepare('INSERT INTO application_documents(application_id,requirement_id,original_name,stored_name,mime_type,file_size,document_name_snapshot) VALUES(?,?,?,?,?,?,?)');foreach($savedFiles as $saved){$it=$saved['item'];$doc->execute([$applicationId,$it['requirement']['requirement_id'],$it['original'],$saved['stored'],$it['mime'],$it['size'],$it['requirement']['document_name']]);}
  add_notification($pdo,$studentId,'Your application for '.$scholarship['title'].' has been submitted.');$pdo->commit();flash('Your scholarship application has been submitted successfully.');redirect('my_applications.php');
 }catch(Throwable $ex){if($pdo->inTransaction())$pdo->rollBack();foreach($savedFiles as $saved){@unlink($privateDir.'/'.$saved['stored']);}$error=$ex instanceof PDOException?'You may have already applied, or the application could not be saved.':'The application could not be saved. Please try again.';}}
}
$pageTitle='Apply for '.$scholarship['title'];require __DIR__.'/../includes/header.php';
$profileGroups=[ 'Personal information'=>['full_name'=>'Full name','email'=>'Email address','phone'=>'Phone number','date_of_birth'=>'Date of birth','address'=>'Permanent address'], 'Academic information'=>['student_number'=>'Student registration number','faculty'=>'Faculty','degree_program'=>'Degree programme','study_year'=>'Current study year','current_gpa'=>'Current GPA','achievements'=>'Academic achievements'], 'Financial background'=>['household_income_range'=>'Monthly household income','household_size'=>'Household size','dependents_count'=>'Financial dependents','guardian_occupation'=>'Guardian occupation','employment_status'=>'Employment status','receives_financial_aid'=>'Other financial assistance','financial_circumstances'=>'Additional financial circumstances'] ];
$totalSteps=4+($questions?1:0);
?>
<section class="application-page google-application">
  <div class="application-top-card">
    <p class="eyebrow">SCHOLARSHIP APPLICATION</p>
    <h1><?=e($scholarship['title'])?></h1>
    <p class="muted">Complete the sections below carefully. Your saved profile details are shown automatically, and scholarship-specific requirements are listed separately.</p>
    <div class="application-meta"><span><strong><?=count($requirements)?></strong> document requirements</span><span><strong><?=count($questions)?></strong> additional questions</span><span><strong><?=$totalSteps?></strong> sections</span></div>
    <div class="application-progress-track"><span style="width:100%"></span></div>
    <small class="muted">Please review all information before submitting.</small>
  </div>
  <?php if($error):?><div class="alert error" role="alert"><?=e($error)?></div><?php endif;?>
  <form method="post" enctype="multipart/form-data" class="form application-form google-form">
    <input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><input type="hidden" name="scholarship_id" value="<?=$sid?>">
    <section class="panel application-section google-section">
      <div class="google-section-heading"><span class="section-number">01</span><div><h2>Student profile information</h2><p>These details are automatically retrieved from your profile.</p></div></div>
      <div class="profile-source-note"><span class="note-dot"></span> Profile information · Read only <a href="../profile.php">Edit my profile</a></div>
      <?php $groupNo=0; foreach($profileGroups as $group=>$fields):$groupNo++;?><div class="profile-subgroup"><h3><?=e($group)?></h3><div class="application-profile-grid"><?php foreach($fields as $key=>$label):$value=$profile[$key]??'';if($key==='receives_financial_aid'&&$value!=='')$value=((int)$value===1?'Yes':'No');?><div class="profile-answer"><span><?=e($label)?></span><div class="readonly-value"><?=trim((string)$value)!==''?nl2br(e($value)):'Not provided in profile'?></div></div><?php endforeach;?></div></div><?php endforeach;?>
    </section>
    <section class="panel application-section google-section">
      <div class="google-section-heading"><span class="section-number">02</span><div><h2>Applicant statement</h2><p>Tell the scholarship committee why you are applying.</p></div></div>
      <div class="google-question"><label for="applicantStatement">Personal statement <span class="required-mark">Required</span></label><p class="question-help">Explain your goals and how this scholarship will support your education.</p><textarea id="applicantStatement" name="applicant_statement" rows="6" maxlength="5000" placeholder="Write your statement here..." required><?=e($_POST['applicant_statement']??'')?></textarea></div>
    </section>
    <?php if($questions):?><section class="panel application-section google-section">
      <div class="google-section-heading"><span class="section-number">03</span><div><h2>Additional scholarship questions</h2><p>Answer the questions set by the scholarship staff.</p></div></div>
      <?php foreach($questions as $question):$qid=(int)$question['question_id'];$value=$_POST['answers'][$qid]??'';$type=$question['field_type'];?><div class="google-question"><label for="question<?=$qid?>"><?=e($question['question_text'])?> <?php if($question['is_required']):?><span class="required-mark">Required</span><?php else:?><span class="optional-mark">Optional</span><?php endif;?></label><?php if($question['help_text']):?><p class="question-help"><?=e($question['help_text'])?></p><?php endif;?><?php if($type==='long_text'):?><textarea id="question<?=$qid?>" name="answers[<?=$qid?>]" rows="4" placeholder="Your answer" <?=$question['is_required']?'required':''?>><?=e($value)?></textarea><?php elseif($type==='select'):$opts=json_decode($question['options_json']??'[]',true)?:[];?><select id="question<?=$qid?>" name="answers[<?=$qid?>]" <?=$question['is_required']?'required':''?>><option value="">Choose an answer</option><?php foreach($opts as $opt):?><option value="<?=e($opt)?>" <?=($value===$opt?'selected':'')?>><?=e($opt)?></option><?php endforeach;?></select><?php else:?><input id="question<?=$qid?>" type="<?=e($type==='number'?'number':($type==='date'?'date':'text'))?>" name="answers[<?=$qid?>]" value="<?=e($value)?>" placeholder="Your answer" <?=$question['is_required']?'required':''?>><?php endif;?></div><?php endforeach;?>
    </section><?php endif;?>
    <section class="panel application-section google-section">
      <div class="google-section-heading"><span class="section-number"><?= $questions?'04':'03' ?></span><div><h2>Supporting documents</h2><p>Upload the documents requested by the scholarship team.</p></div></div>
      <?php if(!$requirements):?><div class="notice">No additional document requirements have been configured for this scholarship.</div><?php endif;?>
      <?php foreach($requirements as $req):?><div class="document-upload-card google-document"><div class="document-upload-heading"><div><h3><?=e($req['document_name'])?></h3><?php if($req['description']):?><p><?=e($req['description'])?></p><?php endif;?></div><span class="<?= $req['is_required']?'required-mark':'optional-mark' ?>"><?= $req['is_required']?'Required':'Optional' ?></span></div><small>Accepted formats: <?=e(strtoupper(str_replace(',',', ',$req['allowed_types'])))?> · Maximum <?=e($req['max_size_mb'])?> MB</small><label class="file-upload-control"><span>Choose file</span><input type="file" name="documents[<?=(int)$req['requirement_id']?>]" accept="<?=e(implode(',',array_map(fn($x)=>'.'.trim($x),explode(',',$req['allowed_types']))))?>" <?= $req['is_required']?'required':'' ?>><small>No file selected</small></label></div><?php endforeach;?>
    </section>
    <section class="panel application-section google-section">
      <div class="google-section-heading"><span class="section-number"><?= $questions?'05':'04' ?></span><div><h2>Declaration and submission</h2><p>Confirm that your application details are correct.</p></div></div>
      <label class="declaration-check"><input type="checkbox" name="declaration" value="1" required> <span>I confirm that the information and documents submitted are accurate and complete to the best of my knowledge.</span></label>
      <div class="application-submit-actions"><button type="submit">Submit application</button><a class="button secondary" href="../scholarships/view.php?id=<?=$sid?>">Cancel</a></div>
    </section>
  </form>
</section>
<script>
document.querySelectorAll('.file-upload-control input[type="file"]').forEach(input=>input.addEventListener('change',()=>{const label=input.closest('.file-upload-control');const text=label.querySelector('small');text.textContent=input.files.length?input.files[0].name:'No file selected';}));
</script>
<?php require __DIR__.'/../includes/footer.php'; ?>
