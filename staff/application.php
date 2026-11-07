<?php
require __DIR__.'/../config/database.php';require __DIR__.'/../includes/functions.php';require_role(['staff','admin']);
$allowed=['Submitted','Under Review','Shortlisted','Rejected','Awarded'];
if($_SERVER['REQUEST_METHOD']==='POST'){
 verify_csrf();$id=(int)$_POST['application_id'];$status=$_POST['status']??'';
 if(in_array($status,$allowed,true)){
  $q=$pdo->prepare('UPDATE applications SET status=? WHERE application_id=?');$q->execute([$status,$id]);
  $q=$pdo->prepare('SELECT student_id FROM applications WHERE application_id=?');$q->execute([$id]);$student=(int)$q->fetchColumn();
  if($student)add_notification($pdo,$student,"Your scholarship application status changed to: $status");
  flash('Application status updated.');
 }else flash('Invalid status.','error');
 redirect('applications.php');
}
$rows=$pdo->query('SELECT a.*,u.full_name,u.email,s.title FROM applications a JOIN users u ON u.user_id=a.student_id JOIN scholarships s ON s.scholarship_id=a.scholarship_id ORDER BY a.submitted_at DESC')->fetchAll();
$pageTitle='Review Applications';require __DIR__.'/../includes/header.php';
?>
<h1>Review applications</h1><div class="table-wrap"><table><tr><th>Student</th><th>Scholarship</th><th>Statement</th><th>Document</th><th>Status</th><th>Update</th></tr>
<?php foreach($rows as $r):?><tr><td><?=e($r['full_name'])?><br><small><?=e($r['email'])?></small></td><td><?=e($r['title'])?></td><td><?=e($r['applicant_statement'])?></td><td><?php if($r['document_path']):?><a href="../<?=e($r['document_path'])?>" target="_blank">Open PDF</a><?php else:?>None<?php endif;?></td><td><?=e($r['status'])?></td><td><form method="post"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><input type="hidden" name="application_id" value="<?=$r['application_id']?>"><select name="status"><?php foreach($allowed as $st):?><option <?=($st===$r['status']?'selected':'')?> value="<?=e($st)?>"><?=e($st)?></option><?php endforeach;?></select><button>Save</button></form></td></tr><?php endforeach;?>
</table></div>
<?php require __DIR__.'/../includes/footer.php'; ?>
