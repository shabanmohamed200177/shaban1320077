<?php
include 'db_connect.php';
$area_id = intval($_POST['area_id']);
$conn->query("DELETE FROM courier_areas WHERE area_id=$area_id");
if(!empty($_POST['couriers'])){
    foreach($_POST['couriers'] as $cid){
        $conn->query("INSERT INTO courier_areas (courier_id, area_id) VALUES (".intval($cid).", $area_id)");
    }
}
echo 1;
?>