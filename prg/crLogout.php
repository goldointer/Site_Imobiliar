<?php
include "msg.php"; 

//---main-------------------------------------------------------------------------

SetSessao('usuario_id', "");
GetSessao('usuario', "");

$page = Campo('url');
if (empty($page))
{
	$page = GetSessao('login_url');
	if (empty($page))
		CampoObrigatorio('url');
}
session_write_close();

/* Redirect browser */
header("Location: " .$page);
?>
