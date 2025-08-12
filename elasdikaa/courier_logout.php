<?php
session_start();
session_destroy();
header('Location: courier_login.php');
exit;
?>