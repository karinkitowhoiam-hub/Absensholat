<?php
session_start();
session_destroy();

// Redirect ke halaman index.php (Naik satu folder ..)
header("Location: ../index.php");
?>