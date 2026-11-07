<?php
require __DIR__.'/../config/database.php';require __DIR__.'/../includes/functions.php';require_role(['admin']);
if($_SERVER['REQUEST_METHOD']==='POST'){verify_csrf();$uid=(int)$_POST['user_id'];$role=$_POST['role']??'';if(in_array($role,['student','staff','admin'],true)&&$uid!==(int)$_SESSION['user_id']){$q=$pdo->prepare('UPDATE users SET role=? WHERE user_id=?');$q->execute([$role,$uid]);flash('User role updated.');}redirect('users.php');}
$users=$pdo->query('SELECT user_id,full_name,email,role,created_at FROM users ORDER BY created_at DESC')->fetchAll();
$pageTitle='Manage Users';require __DIR__.'/../includes/header.php';
?>
<h1>Manage user roles</h1><div class="table-wrap"><table><tr><th>Name</th><th>Email</th><th>Role</th><th>Change role</th></tr>
<?php foreach($users as $u):?><tr><td><?=e($u['full_name'])?></td><td><?=e($u['email'])?></td><td><?=e($u['role'])?></td><td><?php if($u['user_id']!==(int)$_SESSION['user_id']):?><form method="post"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><input type="hidden" name="user_id" value="<?=$u['user_id']?>"><select name="role"><option value="student">Student</option><option value="staff" <?=($u['role']==='staff'?'selected':'')?>>Staff</option><option value="admin" <?=($u['role']==='admin'?'selected':'')?>>Admin</option></select><button>Update</button></form><?php else:?>Current account<?php endif;?></td></tr><?php endforeach;?>
</table></div>
<?php require __DIR__.'/../includes/footer.php'; ?>
