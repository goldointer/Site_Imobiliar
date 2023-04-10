<?php 

error_reporting(E_ALL);

if (!isset($_REQUEST["sessao"])) {
	session_start();
	phpinfo();
	print_r($_SESSION);
	exit();
}

header('Content-Type: text/html; charset=ISO-8859-1');

function detectUTF8($string)
{
	return preg_match('%(?:
		[\xC2-\xDF][\x80-\xBF]				# non-overlong 2-byte
		|\xE0[\xA0-\xBF][\x80-\xBF]			# excluding overlongs
		|[\xE1-\xEC\xEE\xEF][\x80-\xBF]{2}	# straight 3-byte
		|\xED[\x80-\x9F][\x80-\xBF]			# excluding surrogates
		|\xF0[\x90-\xBF][\x80-\xBF]{2}		# planes 1-3
		|[\xF1-\xF3][\x80-\xBF]{3}			# planes 4-15
		|\xF4[\x80-\x8F][\x80-\xBF]{2}		# plane 16
		)+%xs', $string);
}

function ReplaceCallbackUTF8($matches)
{
	return chr(ord($matches[1])<<6&0xC0|ord($matches[2])&0x3F);
}

$encoding = '';
if (isset($_REQUEST['valor']))
{
	$valor=$_REQUEST['valor'];
	if (detectUTF8($valor)) {
		$encoding = '(era UTF-8)';
		$valor = preg_replace_callback('/([\xC2\xC3])([\x80-\xBF])/', "ReplaceCallbackUTF8" , $valor);
	}
}

if ($_REQUEST['sessao'] == 'get') {
	session_start(); 
	$sessao = $_SESSION['valor'];
	if($sessao == $valor)
		echo "<b>SUCESSO</b><br>\nA sess&atilde;o guardou corretamente o valor [ $valor ] $encoding";
	else 
		echo "<b>ERRO</b><br>\nFoi gravado o valor [ $valor ] e a sess&atilde;o devolveu o valor [ $sessao ]";
	exit();
} else if ($_REQUEST['sessao'] == 'set') {
	session_start(); 
	$_SESSION['valor'] = $valor ;
	echo "Voc&ecirc; gravou na sess&atilde;o o valor [ $valor ] $encoding<br>\n";
	echo "Clique no link para ler e conferir o valor na sess&atilde;o:\n
	<a href=\"test.php?sessao=get&valor=$valor\">Ler valor da sess&atilde;o</a>";
	exit();
} else if ($_REQUEST['sessao'] != '') {
	echo 'Digite .../test.php?sessao';
	exit();
}
?>

<html>
<head>
<title>Teste de sess&atilde;o</title>
<meta http-equiv="Content-Type" content="text/html;charset=ISO-8859-1" >
</head>

<body>
<h1>Teste de sess&atilde;o</h1>
<form method=post>
Valor: 
<input name=sessao value=set type=hidden>
<input name=valor type=text>
<input type=submit value="Gravar valor na sess&atilde;o">
</form>
</body>
</html>
