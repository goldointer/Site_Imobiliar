<?php

session_start();
$val = $_REQUEST['ativar'];
$conf = '_CONF_PDF_DOC';
$_SESSION[$conf] = $val;

?>