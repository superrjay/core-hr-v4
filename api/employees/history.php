<?php
require_once __DIR__.'/../bootstrap.php';
if (!in_array(current_user()['role_name'], ['ADMIN','HR'], true)) api_response(['error'=>'HR authorization required.'],403);
$id=(int)($_GET['id']??0);$q=db()->prepare('SELECT h.*,u.username FROM employment_histories h LEFT JOIN users u ON u.id=h.performed_by WHERE h.employee_id=? ORDER BY h.effective_date DESC,h.id DESC');$q->execute([$id]);api_response(['data'=>$q->fetchAll()]);