<?php
require __DIR__.'/../config/database.php';require __DIR__.'/../includes/functions.php';require_role(['staff','admin']);$id=(int)($_GET['id']??$_POST['application_id']??0);$allowed=['Submitted','Under Review','Shortlisted','Rejected','Awarded'];
if($_SERVER['REQUEST_METHOD']==='POST'){verify_csrf();$action=$_POST['action']??'';if($action==='status'){$status=$_POST['status']??'';if(in_array($status,$allowed,true)){$q=$pdo->prepare('SELECT status,student_id FROM applications WHERE application_id=?');$q->execute([$id]);$current=$q->fetch();$oldStatus=$current['status']??null;$student=(int)($current['student_id']??0);$q=$pdo->prepare('UPDATE applications SET status=? WHERE application_id=?');$q->execute([$status,$id]);if($oldStatus!==$status){$h=$pdo->prepare('INSERT INTO application_status_history(application_id,old_status,new_status,changed_by) VALUES(?,?,?,?)');$h->execute([$id,$oldStatus,$status,(int)$_SESSION['user_id']]);}if($student)add_notification($pdo,$student,'Your scholarship application status changed to: '.$status);flash('Application status updated.');}else flash('Invalid status.','error');}elseif($action==='document'){$docId=(int)($_POST['document_id']??0);$docStatus=$_POST['document_status']??'Pending';$note=trim($_POST['reviewer_note']??'');if(in_array($docStatus,['Pending','Verified','Rejected'],true)){$q=$pdo->prepare('UPDATE application_documents SET review_status=?,reviewer_note=?,reviewed_by=?,reviewed_at=NOW() WHERE document_id=? AND application_id=?');$q->execute([$docStatus,$note,(int)$_SESSION['user_id'],$docId,$id]);flash('Document review saved.');}}redirect('application_review.php?id='.$id);}
$q=$pdo->prepare('SELECT a.*,u.full_name,u.email,u.phone,s.title FROM applications a JOIN users u ON u.user_id=a.student_id JOIN scholarships s ON s.scholarship_id=a.scholarship_id WHERE a.application_id=?');$q->execute([$id]);$app=$q->fetch();if(!$app){http_response_code(404);exit('Application not found.');}$snapshot=json_decode($app['profile_snapshot']??'',true)?:[];$student=$snapshot['student']??[];
$q=$pdo->prepare('SELECT ad.*,COALESCE(ad.document_name_snapshot,dr.document_name) AS document_name FROM application_documents ad JOIN scholarship_document_requirements dr ON dr.requirement_id=ad.requirement_id WHERE ad.application_id=? ORDER BY ad.document_id');$q->execute([$id]);$docs=$q->fetchAll();
$q=$pdo->prepare('SELECT aa.answer_text,COALESCE(aa.question_text_snapshot,sq.question_text) AS question_text FROM application_answers aa JOIN scholarship_questions sq ON sq.question_id=aa.question_id WHERE aa.application_id=? ORDER BY sq.sort_order,sq.question_id');$q->execute([$id]);$answers=$q->fetchAll();
$q=$pdo->prepare('SELECT h.*,u.full_name AS reviewer_name FROM application_status_history h JOIN users u ON u.user_id=h.changed_by WHERE h.application_id=? ORDER BY h.changed_at DESC');$q->execute([$id]);$history=$q->fetchAll();
$pageTitle='Application review';require __DIR__.'/../includes/header.php';
?>
<section class="review-hero">
  <div class="review-hero-main">
    <a class="review-back-link" href="applications.php">← Back to applications</a>
    <p class="eyebrow">SCHOLARSHIP APPLICATION · REVIEW</p>
    <h1><?=e($app['full_name'])?></h1>
    <p class="review-hero-subtitle"><?=e($app['title'])?></p>
    <div class="review-hero-meta"><span><?=e($app['email'])?></span><span>Submitted <?=e($app['submitted_at'])?></span></div>
  </div>
  <div class="review-current-status"><span>Current application status</span><strong class="review-status-badge status-<?=strtolower(str_replace(' ','-',e($app['status'])))?>"><?=e($app['status'])?></strong></div>
</section>
<div class="review-grid review-layout">
  <section class="panel review-panel review-profile-panel">
    <div class="review-section-heading"><div><p class="eyebrow">APPLICANT DETAILS</p><h2>Student profile</h2><p class="muted">A snapshot of the information saved when this application was submitted.</p></div><span class="review-heading-icon">01</span></div>
    <?php $groups=['Personal information'=>['full_name'=>'Full name','email'=>'Email','phone'=>'Phone','date_of_birth'=>'Date of birth','address'=>'Address'],'Academic information'=>['student_number'=>'Registration number','faculty'=>'Faculty','degree_program'=>'Degree programme','study_year'=>'Study year','current_gpa'=>'GPA','achievements'=>'Achievements'],'Financial background'=>['household_income_range'=>'Household income','household_size'=>'Household size','dependents_count'=>'Dependents','guardian_occupation'=>'Guardian occupation','employment_status'=>'Employment status','receives_financial_aid'=>'Other financial aid','financial_circumstances'=>'Financial circumstances']];$groupNo=0;foreach($groups as $group=>$fields):$groupNo++;?>
      <div class="review-profile-group"><h3><span><?=sprintf('%02d',$groupNo)?></span><?=e($group)?></h3><dl class="review-details"><?php foreach($fields as $key=>$label):$value=$student[$key]??'';if($key==='receives_financial_aid'&&$value!=='')$value=((int)$value?'Yes':'No');?><div><dt><?=e($label)?></dt><dd><?=trim((string)$value)!==''?nl2br(e($value)):'Not provided'?></dd></div><?php endforeach;?></dl></div>
    <?php endforeach;?>
  </section>
  <div class="review-side">
    <section class="panel review-panel"><div class="review-section-heading"><div><p class="eyebrow">PERSONAL STATEMENT</p><h2>Applicant statement</h2></div><span class="review-heading-icon">02</span></div><div class="review-statement"><?=trim((string)$app['applicant_statement'])!==''?nl2br(e($app['applicant_statement'])):'No statement provided.'?></div></section>
    <section class="panel review-panel"><div class="review-section-heading"><div><p class="eyebrow">ADDITIONAL INFORMATION</p><h2>Application answers</h2></div><span class="review-heading-icon">03</span></div><?php if(!$answers):?><p class="review-empty">No additional questions were configured for this scholarship.</p><?php endif;?><?php foreach($answers as $answer):?><div class="answer-item"><strong><?=e($answer['question_text'])?></strong><p><?=nl2br(e($answer['answer_text']))?></p></div><?php endforeach;?></section>
    <section class="panel review-panel review-documents-panel"><div class="review-section-heading"><div><p class="eyebrow">DOCUMENT CHECKLIST</p><h2>Supporting documents</h2><p class="muted">Open each file and update its verification status.</p></div><span class="review-heading-icon">04</span></div>
      <?php if(!$docs):?><p class="review-empty">No supporting documents were uploaded.</p><?php endif;?>
      <?php foreach($docs as $doc):$docStatus=$doc['review_status']??'Pending';?>
        <article class="document-review-card">
          <div class="document-review-top"><div class="document-file-icon">PDF</div><div class="document-file-info"><strong><?=e($doc['document_name'])?></strong><span><?=e($doc['original_name'])?></span><a href="document.php?id=<?=(int)$doc['document_id']?>" target="_blank" rel="noopener">View document securely ↗</a></div><span class="doc-state-chip doc-state-<?=strtolower($docStatus)?>"><?=e($docStatus)?></span></div>
          <?php if($doc['reviewer_note']):?><p class="saved-review-note"><strong>Previous note:</strong> <?=e($doc['reviewer_note'])?></p><?php endif;?>
          <form method="post" class="document-review-form"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><input type="hidden" name="application_id" value="<?=$id?>"><input type="hidden" name="document_id" value="<?=$doc['document_id']?>"><input type="hidden" name="action" value="document">
            <fieldset class="document-status-picker"><legend>Verification status</legend><div class="document-status-options">
              <?php foreach(['Pending'=>'Pending','Verified'=>'Verified','Rejected'=>'Rejected'] as $value=>$label):?><label class="document-status-option option-<?=strtolower($value)?>"><input type="radio" name="document_status" value="<?=e($value)?>" <?=$docStatus===$value?'checked':''?>><span><?=e($label)?></span></label><?php endforeach;?>
            </div></fieldset>
            <label class="review-note-label">Review note <input name="reviewer_note" placeholder="Add a note for this document (optional)" value="<?=e($doc['reviewer_note'])?>"></label>
            <div class="document-review-actions"><button type="submit">Save document review</button></div>
          </form>
        </article>
      <?php endforeach;?>
    </section>
    <section class="panel review-panel"><div class="review-section-heading"><div><p class="eyebrow">AUDIT TRAIL</p><h2>Decision history</h2></div><span class="review-heading-icon">05</span></div><?php if(!$history):?><p class="review-empty">No status changes have been recorded yet.</p><?php endif;?><?php foreach($history as $h):?><div class="review-history-row"><span class="review-history-dot"></span><div><strong><?=e($h['old_status']??'New application')?> <span>→</span> <?=e($h['new_status'])?></strong><small><?=e($h['reviewer_name'])?> · <?=e($h['changed_at'])?></small></div></div><?php endforeach;?></section>
    <section class="panel review-panel review-decision-panel"><div class="review-section-heading"><div><p class="eyebrow">FINAL STEP</p><h2>Application decision</h2><p class="muted">Update the overall application progress.</p></div><span class="review-heading-icon">06</span></div><form method="post" class="form review-decision-form"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><input type="hidden" name="application_id" value="<?=$id?>"><input type="hidden" name="action" value="status"><label>Application status<select name="status"><?php foreach($allowed as $st):?><option value="<?=e($st)?>" <?=$st===$app['status']?'selected':''?>><?=e($st)?></option><?php endforeach;?></select></label><button type="submit">Update application status</button></form></section>
  </div>
</div>
<?php require __DIR__.'/../includes/footer.php'; ?>
