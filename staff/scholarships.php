<?php
require __DIR__.'/../config/database.php';require __DIR__.'/../includes/functions.php';require_role(['staff','admin']);
$cats=$pdo->query('SELECT * FROM scholarship_categories ORDER BY category_name')->fetchAll();
if($_SERVER['REQUEST_METHOD']==='POST'){
 verify_csrf();$id=(int)($_POST['scholarship_id']??0);$title=trim($_POST['title']??'');$desc=trim($_POST['description']??'');$min=filter_var($_POST['min_gpa']??null,FILTER_VALIDATE_FLOAT);
 $open=$_POST['opening_date']??'';$close=$_POST['closing_date']??'';$status=$_POST['status']??'draft';
 if($title===''||$desc===''||$min===false||$min<0||$min>4||$open===''||$close<$open||!in_array($status,['draft','published','closed','archived'],true)){flash('Please check the required answers and application dates.','error');redirect('scholarships.php');}
 $data=[(int)($_POST['category_id']?:0)?:null,$title,$desc,trim($_POST['provider_name']),trim($_POST['benefits']),$min,trim($_POST['required_documents']),$open,$close,$status];
 if($id){$data[]=$id;$q=$pdo->prepare('UPDATE scholarships SET category_id=?,title=?,description=?,provider_name=?,benefits=?,min_gpa=?,required_documents=?,opening_date=?,closing_date=?,status=? WHERE scholarship_id=?');$q->execute($data);}
 else{$data[]=(int)$_SESSION['user_id'];$q=$pdo->prepare('INSERT INTO scholarships(category_id,title,description,provider_name,benefits,min_gpa,required_documents,opening_date,closing_date,status,created_by) VALUES(?,?,?,?,?,?,?,?,?,?,?)');$q->execute($data);}
 flash('Scholarship details saved successfully.');redirect('scholarships.php');
}
if(isset($_GET['delete'])){$q=$pdo->prepare("UPDATE scholarships SET status='archived' WHERE scholarship_id=?");$q->execute([(int)$_GET['delete']]);flash('Scholarship archived.');redirect('scholarships.php');}
$rows=$pdo->query('SELECT s.*,c.category_name FROM scholarships s LEFT JOIN scholarship_categories c ON c.category_id=s.category_id ORDER BY s.created_at DESC')->fetchAll();
$pageTitle='Manage Scholarships';require __DIR__.'/../includes/header.php';
?>
<section class="staff-intro">
 <p class="eyebrow">STAFF WORKSPACE</p><h1>Scholarship manager</h1>
 <p class="muted">Add a scholarship by answering a few simple questions. The preview turns your answers into a clear description students can read.</p>
</section>
<details class="panel scholarship-builder" open>
 <summary><span class="step-number">1</span> Add scholarship details</summary>
 <div class="builder-grid">
  <form method="post" class="form builder-form" id="scholarshipForm">
   <input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>">
   <div class="question"><label>What is the name of this scholarship?<input name="title" id="title" placeholder="e.g. University Academic Excellence Scholarship" required maxlength="180"></label></div>
   <div class="question"><label>Who provides this scholarship?<input name="provider_name" id="provider" placeholder="University / organization name"></label></div>
   <div class="question"><label>Which category does it belong to?<select name="category_id" id="category"><option value="">Choose a category</option><?php foreach($cats as $c):?><option value="<?=$c['category_id']?>"><?=e($c['category_name'])?></option><?php endforeach;?></select></label></div>
   <div class="question"><label>What is this scholarship for? Explain it in simple words.<textarea name="description" id="description" required placeholder="Describe who this scholarship is intended for and why it is offered."></textarea></label></div>
   <div class="question"><label>What benefits will selected students receive?<textarea name="benefits" id="benefits" placeholder="e.g. Financial support, book allowance or tuition assistance"></textarea></label></div>
   <div class="question"><label>What is the minimum GPA required?<input type="number" name="min_gpa" id="minGpa" step="0.01" min="0" max="4" value="0" required></label><small>Enter a value between 0.00 and 4.00.</small></div>
   <div class="question"><label>Which documents must students submit?<textarea name="required_documents" id="documents" placeholder="e.g. Academic transcript, student ID copy, recommendation letter"></textarea></label></div>
   <div class="date-grid">
    <div class="question"><label>When does the application open?<input type="date" name="opening_date" id="openingDate" required></label></div>
    <div class="question"><label>What is the closing date?<input type="date" name="closing_date" id="closingDate" required></label></div>
   </div>
   <div class="question"><label>Should students be able to see this scholarship?<select name="status"><option value="draft">Save as draft (not visible)</option><option value="published">Publish (visible to students)</option><option value="closed">Closed</option></select></label></div>
   <button type="submit">Save scholarship</button>
  </form>
  <aside class="preview-card">
   <p class="eyebrow">STUDENT VIEW PREVIEW</p><h2 id="previewTitle">Scholarship title</h2>
   <p class="preview-provider" id="previewProvider">Provider name</p>
   <p id="previewDescription">Your scholarship description will appear here in clear, readable form.</p>
   <div class="preview-line"><strong>Benefits</strong><span id="previewBenefits">Not specified yet</span></div>
   <div class="preview-line"><strong>Minimum GPA</strong><span id="previewGpa">0.00</span></div>
   <div class="preview-line"><strong>Required documents</strong><span id="previewDocuments">Not specified yet</span></div>
   <div class="preview-line"><strong>Application period</strong><span id="previewDates">Opening date – Closing date</span></div>
   <p class="preview-hint">This is a preview only. The scholarship will be saved when you select “Save scholarship”.</p>
  </aside>
 </div>
</details>
<section class="existing-scholarships">
 <h2>Existing scholarships</h2><p class="muted">Review the scholarships already added to the system.</p>
 <div class="table-wrap"><table><tr><th>Title</th><th>Dates</th><th>Status</th><th>Action</th></tr>
 <?php foreach($rows as $r):?><tr><td><?=e($r['title'])?></td><td><?=e($r['opening_date'])?> – <?=e($r['closing_date'])?></td><td><span class="status-pill"><?=e($r['status'])?></span></td><td><a onclick="return confirm('Archive this scholarship?')" href="?delete=<?=$r['scholarship_id']?>">Archive</a></td></tr><?php endforeach;?>
 </table></div>
</section>
<script>
(() => {
 const pairs = {
  title:'previewTitle', provider:'previewProvider', description:'previewDescription',
  benefits:'previewBenefits', minGpa:'previewGpa', documents:'previewDocuments'
 };
 Object.entries(pairs).forEach(([inputId, outputId]) => {
  const input=document.getElementById(inputId), output=document.getElementById(outputId);
  const update=()=>{const value=input.value.trim(); output.textContent=value || (inputId==='title'?'Scholarship title':inputId==='provider'?'Provider name':inputId==='description'?'Your scholarship description will appear here in clear, readable form':'Not specified yet');};
  input.addEventListener('input',update); input.addEventListener('change',update);
 });
 const opening=document.getElementById('openingDate'), closing=document.getElementById('closingDate'), dates=document.getElementById('previewDates');
 const updateDates=()=>dates.textContent=(opening.value||'Opening date')+' – '+(closing.value||'Closing date');
 opening.addEventListener('change',updateDates); closing.addEventListener('change',updateDates);
 document.getElementById('scholarshipForm').addEventListener('submit', e=>{if(opening.value && closing.value && closing.value<opening.value){e.preventDefault();alert('The closing date must be on or after the opening date.');}});
})();
</script>
<?php require __DIR__.'/../includes/footer.php'; ?>
