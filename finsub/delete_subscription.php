<?php
include 'config.php';

$id = $_GET['id'];
mysqli_query($conn, "DELETE FROM subscriptions WHERE id = $id");

header("Location: index.php");
exit;
?>
