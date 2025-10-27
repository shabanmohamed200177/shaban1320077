<?php
include 'db_connect.php';
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) { die('معرف غير صالح'); }
$stmt = $conn->prepare("SELECT * FROM couriers WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $id);
$stmt->execute();
$res = $stmt->get_result();
$qry = $res ? $res->fetch_array(MYSQLI_ASSOC) : null;
$stmt->close();
if(!$qry){ die('المندوب غير موجود'); }
foreach($qry as $k => $v){ $$k = $v; }
include 'new_courier.php';
?>