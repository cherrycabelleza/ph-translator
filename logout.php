<?php

session_start();
session_destroy();

header("Location: PLT_MainPage.php");
exit();

?>