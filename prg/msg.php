<?php

define('CHARSET_ISO8859_1', 'ISO-8859-1');
define('CHARSET_UTF8', 'UTF-8');
define('CHARSET_HTML', 'HTML');

if (session_id() == '')
{
	if (isset($_REQUEST['SESSID']))
		session_id($_REQUEST['SESSID']);
	session_start();
}
@date_default_timezone_set('America/Sao_Paulo');

include 'class.DTemplate.php';

// Below is PHP's error handling. I recommend leaving the number below at '1' or '0', if you
// experiance problems try settings this to 8, or deleting the line.
error_reporting(E_ALL);	//E_PARSE

$config_file = isset($_SESSION['CONFIG_INC']) ? $_SESSION['CONFIG_INC'] : '';
if (empty($config_file))
{
	// Carregar os arquivos de configuracoes
//	for ($i = 0; $i < 2; $i++) <<==== DESATIVADO bloqueto.inc.php
	for ($i = 0; $i < 1; $i++)
	{
		$config_file = '';
		switch ($i)
		{
		case 0:
			if (is_file('config.inc.php'))
				$config_file = 'config.inc.php';
			else if (is_file('config.inc-dist.php'))
				$config_file = 'config.inc-dist.php';
			else
			{
				Mensagem('Erro na instalação', 'Sem arquivo de configuração!');
				exit(1);
			}
			break;
		case 1:
			if (is_file('bloqueto.inc.php'))
				$config_file = 'bloqueto.inc.php';
			break;
		}
		
		if (empty($config_file))
			continue;

//echo "<!-- config_file=$config_file\n";
		$fConf=fopen($config_file, 'r');
		while (!feof($fConf))
		{
			$Linha = trim(fgets($fConf, 1024));
//echo "$Linha";
			if (empty($Linha))
				continue;
			if ($Linha[0] == '$')
			{
				$pos1 = strpos($Linha, '=');
				$pos2 = strpos($Linha, ';');
				if ($pos1 !== FALSE && $pos2 !== FALSE)
				{
					$conf = '_CONF_'.substr($Linha,1,$pos1-1);
					$val = substr($Linha,$pos1+1, $pos2-$pos1); 
					eval("\$_SESSION['$conf']=$val"); 
//echo "_SESSION[$conf]=$val\n"; 
				}
			}
		}
		fclose($fConf);
		if ($i == 0)
			$_SESSION['CONFIG_INC'] = $config_file;
//echo "\n-->\n";
	}
}

//----------------------------------------------------------------
function Configuracao($field, $default=null, $showError=true)
{
//echo "<!-- Configuracao($field, $default) -->\n";
	$conf = '_CONF_'.$field;
	if (isset($_SESSION[$conf]))
	{
		$val = $_SESSION[$conf];
		if (!is_string($val) || !empty($val)) {
//echo "<!-- session[$conf]=$val -->\n";
			return $val;
		}
	}

	if (!empty($default))
	{
		// Atribui o valor default que foi passado
		$val = $default;
//echo "<!-- default=$val -->\n";
	}
	else if (substr($field,0,4) == 'DIR_')
	{
		// Atribui valor default do diretorio
		if ($field == 'DIR_DADOS')
			$val = 'dados';
		else if ($field == 'DIR_FOTOS')
			$val = '../Fotos';
		else if ($field == 'DIR_IMAGENS')
			$val = '../imagens';
		else if ($field == 'DIR_LANCAMENTOS')
			$val = '../Lanctos';
		else if ($field == 'DIR_ANEXOS')
			$val = '../Anexos';
		else if ($field == 'DIR_IRANUAL')
			$val = Configuracao('DIR_DADOS').'IRanual';
		else if ($field == 'DIR_MODELOS')
			$val = 'modelos';
		else if ($field == 'DIR_MODELOS_AREACLIENTE')
		{
			$val = Configuracao('DIR_MODELOS');
			if (is_dir($val.'area_cliente'))
				$val .= 'area_cliente';
		}
		else if ($field == 'DIR_MODELOS_PESQUISA')
		{
			$val = Configuracao('DIR_MODELOS');
			if (is_dir($val.'pesq_imoveis'))
				$val .= 'pesq_imoveis';
		}
		else if ($field == 'DIR_BOLETOS')
		{
			$val = Configuracao('SUBDIR_BOLETOS');
			if (empty($val))
				$val = 'bloquetos3';
			$val = Configuracao('DIR_DADOS').$val; 
		}
		else
			$val = '';
	}
	else
		$val = '';

	$val = trim($val);
	if (substr($field,0,4) == 'DIR_')
	{
		if (!empty($val))
		{
			$val = str_replace('//','/', $val.'/');
			if (!is_dir($val))
			{
				if ($showError)
				{
					Mensagem($field, 'Nao existe diretório '.$val);
					exit(1);
				}
				else
					$val = '';
			}
		}
		else if ($showError)
		{
			Mensagem($field, 'Parâmetro de configuração inexistente!');
			exit(1);
		}
	}

//echo "<!-- $field = $val -->\n";
	$_SESSION[$conf] = $val;
	return $val;
}

//----------------------------------------------------------------
function NormalizePath($path)
{
	$path = str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $path);
	$parts = array_filter(explode(DIRECTORY_SEPARATOR, $path), 'strlen');
	$absolutes = array();
	foreach ($parts as $part) {
		if ('.' == $part) continue;
		if ('..' == $part) array_pop($absolutes);
		else $absolutes[] = $part;
	}
	return implode(DIRECTORY_SEPARATOR, $absolutes);
}

//----------------------------------------------------------------
function GetFullUrl($path)
{
	$path = NormalizePath(dirname($_SERVER["SCRIPT_NAME"]).'/'.$path);
	if (isset($_SERVER["SCRIPT_URI"]))
	{
		$protocolo = explode(':',$_SERVER["SCRIPT_URI"]);
		$protocolo = $protocolo[0];
	}
	else
		$protocolo = 'http';

	return $protocolo.'://'.NormalizePath($_SERVER["SERVER_NAME"].'/'.$path);
}

//----------------------------------------------------------------
function HTMLtoISO8859_1($str_html_entities)
{
	return html_entity_decode($str_html_entities, ENT_COMPAT, CHARSET_ISO8859_1);
}

//----------------------------------------------------------------
function HTMLtoModel($str_html_entities)
{
	global $ModelCharset;

	if ($ModelCharset == CHARSET_HTML)
		return $str_html_entities;

	$str_model = html_entity_decode($str_html_entities, ENT_COMPAT, $ModelCharset);
	$str_model = str_replace("<br>", "   \x0a", $str_model);
	return $str_model;	
}

//----------------------------------------------------------------
function ISO8859_1toModel($str_iso8859_1)
{
	global $ModelCharset;

	if ($ModelCharset == CHARSET_HTML)
		return htmlentities($str_iso8859_1, ENT_COMPAT, CHARSET_ISO8859_1, false);

	if ($ModelCharset == CHARSET_UTF8)
		return ISO8859_1toUTF8($str_iso8859_1);
	
	return $str_iso8859_1;
}

//----------------------------------------------------------------
function ISO8859_1toASCII($string)
{
	if (function_exists('iconv'))
		return iconv('ISO-8859-1', 'ASCII//TRANSLIT', $string);

	$aConv = array(
	'\'', '\'', chr(0x93), chr(0x94), chr(0x95), chr(0x96), chr(0x97), chr(0x98),
	chr(0x99), chr(0x9a), chr(0x9b), chr(0x9c), chr(0x9d), chr(0x9e), chr(0x9f), chr(0xa0),
	'i', chr(0xa2), chr(0xa3), chr(0xa4), chr(0xa5),  '|', chr(0xa7),  '"',
	chr(0xa9),  '.',  '<', chr(0xac),  '-', chr(0xae),  '-',  '.',
	chr(0xb1), chr(0xb2), chr(0xb3), '\'',  'u', chr(0xb6), '\'',  '.',
	chr(0xb9),  '.',  '>', chr(0xbc), chr(0xbd), chr(0xbe),  '?',  'A',
	'A',  'A',  'A',  'A',  'A',  'A',  'C',  'E',
	'E',  'E',  'E',  'I',  'I',  'I',  'I',  'D',
	'N',  'O',  'O',  'O',  'O',  'O',  '*',  'O',
	'U',  'U',  'U',  'U',  'Y',  'P',  'B',  'a',
	'a',  'a',  'a',  'a',  'a',  'a',  'c',  'e',
	'e',  'e',  'e',  'i',  'i',  'i',  'i',  'o',
	'n',  'o',  'o',  'o',  'o',  'o',  '/',  'o',
	'u',  'u',  'u',  'u',  'y',  'p',  'y'
	);

	for ($i = strlen($string)-1; $i >= 0; $i--)
	{
		$c = ord($string[$i]);
		if ($c >= 0x91)
			$string[$i] = $aConv[$c-0x91];
	}
	return $string;
}

//----------------------------------------------------------------
function ReplaceCallbackUTF8toISO8859_1($matches)
{
	return chr(ord($matches[1])<<6&0xC0|ord($matches[2])&0x3F);
}

//----------------------------------------------------------------
function UTF8toISO8859_1($str_utf8)
{
	if (function_exists('utf8_decode'))
		return utf8_decode($str_utf8);

	if (function_exists('iconv'))
		return iconv('UTF-8', 'ISO-8859-1', $str_utf8);

	return preg_replace_callback('/([\xC2\xC3])([\x80-\xBF])/', "ReplaceCallbackUTF8toISO8859_1", $str_utf8);
}

//----------------------------------------------------------------
function ReplaceCallbackISO8859_1toUTF8($matches)
{
	return chr(0xC0|ord($matches[1])>>6).chr(0x80|ord($matches[1])&0x3F);
}

//----------------------------------------------------------------
function ISO8859_1toUTF8($str_iso8859_1)
{
	if (function_exists('utf8_encode'))
		return utf8_encode($str_iso8859_1);

	if (function_exists('iconv'))
		return iconv('ISO-8859-1', 'UTF-8', $str_iso8859_1);

	return preg_replace_callback('/([\x80-\xFF])/', "ReplaceCallbackISO8859_1toUTF8", $str_iso8859_1);
}

//----------------------------------------------------------------
function UTF8detect($string)
{
	if (!is_string($string))
		return false;

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

//----------------------------------------------------------------
function Campo($field)
{
	if (isset($_REQUEST[$field]))
		$val = $_REQUEST[$field];
	else
	{
		if (isset($_SERVER['REQUEST_METHOD']))
		{
			if ($_SERVER['REQUEST_METHOD'] == 'GET')
				$val = isset($_GET[$field]) ? $_GET[$field] : false;
			else if ($_SERVER['REQUEST_METHOD'] == 'POST')
				$val = isset($_POST[$field]) ? $_POST[$field] : false;
			else
				$val = false;
		}
		else
			$val = false;
	}

	// No caso do navegador enviar em UTF-8 entao converte para ISO-8859-1.
	if (UTF8detect($val))
		$val = UTF8toISO8859_1($val);

//echo "<!-- $field = $val -->\n";
	return $val;
}

//----------------------------------------------------------------
function CampoObrigatorio($field)
{
	$val = Campo($field);

	if ($val !== false)
		return $val;

	// Campo obrigatorio nao encontrado.
	header('Status: 406 Not Acceptable');

	Mensagem("Campo '".$field."' inexistente", "O campo obrigatório '".$field."' foi removido ou renomeado no formulário. Ele deve ser recolocado na página modelo (shtml ou sxml) conforme especificado na página original!");
	
	exit;
}

//----------------------------------------------------------------
function GetSessao($field)
{
	if (isset($_SESSION))
		$val = isset($_SESSION[$field]) ? $_SESSION[$field] : false;

	return $val;
}

//----------------------------------------------------------------
function SetSessao($field, $val)
{
	if (isset($_SESSION))
		$_SESSION[$field] = $val;
}

//----------------------------------------------------------------
function Mensagem($tit, $msg='', $dica='')
{
	global $UsingXmlModel;

	// Deve receber strings em ISO-8859-1 .
	$DirModelos = Configuracao('DIR_MODELOS', '.');
	if ($DirModelos == '.' || !is_dir($DirModelos))
	{
		// Se nao achou a pagina modelo entao monta uma pagina de emergencia.
		$charset = ($ModelCharset == CHARSET_HTML) ? CHARSET_ISO8859_1 : $ModelCharset;
		if (GetSessao('XML_MODE') === true)
		{
			header('Content-Type: application/xml; charset='.$charset);
			printf(
"<?xml version=\"1.0\" encoding=\"%s\" ?>
<sessao>
 <resultado>
  <situacao>ERRO</situacao>
  <conteudo>MENSAGEM</conteudo>
 </resultado>
 <mensagem>
  <titulo><![CDATA[%s]]></titulo>
  <texto><![CDATA[%s]]></texto>
 </mensagem>
</sessao>\n", $charset, $tit, $msg);
		}
		else
		{
			header('Content-Type: text/html; charset='.$charset);
			printf(
"<HTML>
<HEAD>
<TITLE>406 Not Acceptable</TITLE>
 <meta http-equiv=\"Content-Type\" content=\"text/html;charset=%s\" >
</HEAD>
<BODY>
 <BR><BR><H2><font color=\"#FF0000\">%s</font></H2>
 <H3>%s</H3>
</BODY>
</HTML>\n", $charset, $tit, $msg);
		}
	}

	// Tem a pagina modelo entao vai preenche-la.
	$model = new DTemplate($DirModelos);
	$model-> define_templates ( array ( 'msg' => Modelo($DirModelos, 'msg', $UsingXmlModel) ) );

	if (empty($msg))
	{
		$msg = $tit;
		$tit = 'AVISO';
	}
	$model->assign('TITULO', ISO8859_1toModel($tit));
	$model->assign('MSG', ISO8859_1toModel($msg));
	$model->assign('DICA', ISO8859_1toModel($dica));
	$model->assign('ORIGEM_MSG', GetSessao('ORIGEM_MSG'));

	$sPathSite = substr($_SERVER['SCRIPT_FILENAME'], strlen($_SERVER['DOCUMENT_ROOT']), strlen($_SERVER['SCRIPT_FILENAME']));
	if (isset($_SERVER["SCRIPT_URI"]))
	{
		$protocolo = explode(':',$_SERVER["SCRIPT_URI"]);
		$protocolo = $protocolo[0];
	}
	else
		$protocolo = 'http';
	$sHttpHost = $protocolo.'://'.$_SERVER['HTTP_HOST'].'/'.substr($sPathSite, 0, strpos($sPathSite, '/')).'/';
//echo "Host=$sHttpHost<br>\n";
	$model->assign('HTTP_HOST', $sHttpHost);

	$model->parse('msg');
	$model->DPrint('msg');
//phpinfo();
}

//----------------------------------------------------------------
function Modelo($DirModelos, $Modelo, $XmlMode=false)
{
	global $UsingXmlModel, $ModelCharset;

	$ModelCharset = GetSessao('MODEL_CHARSET');

	if ($XmlMode)
		$UsingXmlModel = true;
	else {
		$ponto = strrpos($Modelo, ".");
		$Ext = substr($Modelo, $ponto);
		if ($Ext == '.shtml') {
			$XmlMode = false;
			$Modelo = substr($Modelo, 0, $ponto);
		} 
		elseif ($Ext == '.sxml') {
			$XmlMode = true;
			$Modelo = substr($Modelo, 0, $ponto);
		} 
		else
			$XmlMode = GetSessao('XML_MODE');

		if ($XmlMode && is_file($DirModelos.'/'.$Modelo.'.sxml'))
			$UsingXmlModel = true;
		else
			$UsingXmlModel = false;
	}
	SetSessao('USING_XML_MODEL', $UsingXmlModel);
	
	if ($UsingXmlModel) {
		$Type = 'application/xml';
		$Ext = '.sxml';
		if (empty($ModelCharset))
			// Se nao foi especificado, por padrao os XMLs devem utilizar ISO8859-1.
			$ModelCharset = CHARSET_ISO8859_1;
	}
	else 
	{
		$Type = 'text/html';
		$Ext = '.shtml';
		if (empty($ModelCharset))
			// Se nao foi especificado, por padrao as paginas devem utilizar "HTML entities".
			$ModelCharset = CHARSET_HTML;
	}

	if (!headers_sent())
	{
		$ContentType = $Type.'; charset='.($ModelCharset == CHARSET_HTML ? CHARSET_ISO8859_1 : $ModelCharset);
		header('Content-Type: '.$ContentType);
	}
	return $Modelo.$Ext;
}

//----------------------------------------------------------------
function Submeter($script, $campos)
{
	global $ModelCharset;
	
	$charset = ($ModelCharset == CHARSET_HTML) ? CHARSET_ISO8859_1 : $ModelCharset;
	header('Content-Type: text/html; charset='.$charset);

	print('<html><head><meta http-equiv="Content-Type" content="text/html;charset='.$charset.'" ></head>'.
		'<body onload="javascript:document.frm.submit()">'.
		'<form action="'.$script.'" method="post" name="frm">'.
		'<input type="hidden" name="timestamp" value="'.time().'">');

	foreach($campos as $name => $value) {
		print('<input type="hidden" name="'.$name.'" value="'.$value.'">');
	}

	print('</form></body></html>');
	exit;
}

//--------------------- INICIALIZACOES GERAIS --------------------
$UsingXmlModel = false;
$ModelCharset = CHARSET_HTML;
?>
