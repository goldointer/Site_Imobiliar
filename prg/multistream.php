<?php
/* 
 Script de suporte ao acesso por HTTP da classe MultiStream e que deve ser instalado no web server.
 TESTES:
	http://www.padraoimoveisrs.com.br/prg/multistream.php?oper=tstftp&file_name=xxx&user=ct00065816-001&pass=gotenixi&ftpdir=/htdocs/prg&httpdir=/prg
*/

error_reporting(E_ALL);	//E_PARSE

$Browser_Debug = false;	// Habilita/desabilita depuracao pelo browser
$Ftp_Enabled = true;	// Habilita/desabilita uso de FTP local

$Ftp_handle = false;
$Ftp_user_name = "";
$Ftp_user_pass = "";
$Ftp_dir = "";
$Http_dir = "";
$Http_dir_base = "";
$Mensagems = "";
$DirList_Tipos = array(
	0140000=>'S',
	0120000=>'L',
	0100000=>'F',
	0060000=>'B',
	0040000=>'D',
	0020000=>'V',
	0010000=>'P');

//----------------------------------------------------------------------------------
function AddMsg($msg)
{
	global $Mensagems;

	$Mensagems .= $msg;
}
//----------------------------------------------------------------------------------
function ShowHeader()
{
	global $Browser_Debug;

	if (!headers_sent())
		if (empty($Browser_Debug))
			header("Content-Type: text/plain");
		else
			header("Content-Type: text/html");
}
//----------------------------------------------------------------------------------
function ShowMsg()
{
	global $Browser_Debug, $Mensagems;

	if (empty($Browser_Debug))
	{
		$Mensagems = str_replace('<br>', '', $Mensagems);
		$Mensagems = str_replace('<hr>', '', $Mensagems);
		$Mensagems = str_replace('<BR>', '', $Mensagems);
		$Mensagems = str_replace('<HR>', '', $Mensagems);
		echo "\n";
	}
	else
		echo "\n<hr>\n";

	echo $Mensagems."\n";

	//echo("_REQUEST ");print_r($_REQUEST);
	//echo("_SERVER ");print_r($_SERVER);
}
//----------------------------------------------------------------------------------
function myErrorHandler($type, $info, $file, $row)
{
	AddMsg($info."<br>\n");
}
//---------------------------------------------------------
function Fatal($msg, $status='400 Requisicao invalida')
{
	global $Browser_Debug, $Mensagems;

	header('HTTP/1.0 '.$status);
	ShowHeader();
	if (empty($Browser_Debug))
	{
		echo $msg."\n";
		ShowMsg();
		//echo("_REQUEST ");print_r($_REQUEST);
		//echo("_SERVER ");print_r($_SERVER);
	}
	else
	{
		echo $msg."<br>\n";
		if (!empty($Mensagems))
		{
			AddMsg($msg."<br>\n");
			ShowMsg();
		}
		if(function_exists('ftp_connect'))
			echo "ATENCAO: COM suporte de FTP!\n";
		else
			echo "ATENCAO: SEM suporte de FTP!\n";
		phpinfo();
	}
	exit;
}
//---------------------------------------------------------
// Normaliza o caminho de diretorio
function NormalizeDir($path, $relative=false)
{
	if (empty($path))
		$newpath = '';
	else if ($path == '/')
		$newpath = '/';
	else if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
		$newpath = preg_replace('/(\/)+/','\\',$path); //only back-slash
	} else {
		$newpath = preg_replace('/(\/){2,}|(\\\){1,}/','/',$path); //only forward-slash
		$dirs = explode('/', $newpath);

		for ($i = 0; $i < count($dirs); $i++)
		{
			$elem = $dirs[$i];
			if (empty($elem) || $elem == '.') {
				for ($j = $i; $j < count($dirs)-1; $j++)
					$dirs[$j] = $dirs[$j+1];
				array_pop($dirs);
				$i--;
			} else if ($elem == '..') {
				if ($i == 0)
				{
					if (empty($relative))
						Fatal("ERRO: caminho inv '$path'\n");
					break;
				}
				for ($j = $i-1; $j < count($dirs)-2; $j++)
					$dirs[$j] = $dirs[$j+2];
				array_pop($dirs);
				array_pop($dirs);
				$i -= 2;
			}
		}

		$newpath = ($newpath{0} == '/') ? '/' : '';
		foreach ($dirs as $elem)
			$newpath .= $elem.'/';
		$newpath = substr($newpath,0,strlen($newpath)-1);
	}

//AddMsg("NormalizeDir($path) => '$newpath'<br>\n");

	return $newpath;
}
//---------------------------------------------------------
function HttpInit()
{
	global $_SERVER, $Http_dir_base, $Http_dir;

	if (isset($_REQUEST['httpdir']))
		$Http_dir = $_REQUEST['httpdir'];
//AddMsg("Http_dir=$Http_dir<br>\n");

	$Http_dir_base = dirname($_SERVER['SCRIPT_FILENAME']);
	$localdir = HttpPath('.');
	if (!is_dir($localdir) && isset($_SERVER['PHPRC']))
	{
		// Forma alternativa de se obter diretorio base quando o PHP nao consegue acessar diretorio fisico.
		$localdir = $_SERVER['PHPRC'].basename($_SERVER['DOCUMENT_ROOT']).dirname($_SERVER['SCRIPT_NAME']);
		if (is_dir($localdir))
			$Http_dir_base = $localdir;
	}
//AddMsg("Http_dir_base=$Http_dir_base<br>\n");
}
//---------------------------------------------------------
// Monta o caminho completo para HTTP
function HttpPath($path)
{
	global $Http_dir_base, $Http_dir;

	$localpath = NormalizeDir($Http_dir_base.'/'.$Http_dir.'/'.$path);
//AddMsg("HttpPath($path) => $localpath<br>\n");

	return $localpath;
}
//---------------------------------------------------------
// Prepara FTP
function FtpInit()
{
	global $Ftp_Enabled, $Ftp_dir, $Ftp_user_name, $Ftp_user_pass;

	if (empty($Ftp_Enabled))
		return false;

	if (!empty($Ftp_user_name))
		return true;  // ja' inicializou

	if (isset($_REQUEST['user']))
		$Ftp_user_name = $_REQUEST['user'];

	if (isset($_REQUEST['pass']))
		$Ftp_user_pass = $_REQUEST['pass'];

	if (isset($_REQUEST['ftpdir']))
		$Ftp_dir = NormalizeDir($_REQUEST['ftpdir']);
//AddMsg("Ftp_dir=$Ftp_dir<br>\n");

	if (empty($Ftp_user_name) || empty($Ftp_user_pass))
		$Ftp_Enabled = false;
//AddMsg("Ftp_Enabled=$Ftp_Enabled<br>\n");

	return true;
}
//---------------------------------------------------------
// // Monta o caminho completo para FTP
function FtpPath($path)
{
	global $Ftp_dir;

	if (empty($Ftp_dir) && empty($path))
		$localpath = '';
	else
		$localpath = NormalizeDir('/'.$Ftp_dir.'/'.$path);
//AddMsg("FtpPath($path) => $localpath<br>\n");

	return $localpath;
}
//---------------------------------------------------------
// Conecta via FTP
function FtpConnect()
{
	global $Ftp_handle, $Ftp_user_name, $Ftp_user_pass;

	if ($Ftp_handle !== false)
		return true;	// ja' conectado

	if (!function_exists('ftp_connect') || !FtpInit())
		return false;

	// set up basic connection
	$Ftp_handle = ftp_connect("localhost");
	if ($Ftp_handle === false)
		Fatal("ERRO: FTP connection has failed\n");

	// login with username and password
	//AddMsg("ftp_login($Ftp_user_name, $Ftp_user_pass)<br>\n");
	$login_result = ftp_login($Ftp_handle, $Ftp_user_name, $Ftp_user_pass);
	if ($login_result === false)
		Fatal("ERRO: FTP connection has failed attempted to login for user '$Ftp_user_name'\n");

	return true;
}
//---------------------------------------------------------
// Envia arquivo por FTP
function FtpPut($local_file, $remote_file)
{
	global $Ftp_handle;

	if (!FtpConnect())
		return false;

//AddMsg("FtpPut($local_file, $remote_file)<br>\n");
	$remote_file = FtpPath($remote_file);
	if (ftp_put($Ftp_handle, $remote_file, $local_file, FTP_BINARY) === false)
		Fatal("ERRO: FTP upload has failed: '$local_file'->'$remote_file'\n");

	return true;
}
//---------------------------------------------------------
// Remove arquivo por FTP
function FtpDelete($file)
{
	global $Ftp_handle;

	if (!FtpConnect())
		return false;

//AddMsg("FtpDelete($file)<br>\n");
	$remote_file = FtpPath($file);
	if (ftp_delete($Ftp_handle, $remote_file) === false)
		Fatal("ERRO: Nao apagou o arquivo: '$remote_file'\n");

	return true;
}
//---------------------------------------------------------
// Renomeia arquivo por FTP
function FtpRename($file, $newname)
{
	global $Ftp_handle;

	if (!FtpConnect())
		return false;

//AddMsg("FtpRename($file,$newname)<br>\n");
	$remote_file = FtpPath($file);
	$new_remote_file = FtpPath($newname);
	if (ftp_rename($Ftp_handle, $remote_file, $new_remote_file) === false)
		Fatal("ERRO: Nao renomeou o arquivo: '$remote_file'\n");

	return true;
}
//---------------------------------------------------------
// Altera permissoes por FTP
function FtpChMod($path, $modo)
{
	global $Ftp_handle;

	if (!FtpConnect() || !function_exists('ftp_chmod'))
		return false;

//AddMsg("FtpChMod($path, $modo)<br>\n");
	$path = FtpPath($path);
	if (ftp_chmod($Ftp_handle, $modo, $path) === false)
		Fatal("ERRO: Nao alterou permissoes de '$path'\n");

	return true;
}
//---------------------------------------------------------
// Cria por FTP todos subdiretorios que compoe o caminho
function FtpMkPath($newdir)
{
	global $Ftp_handle;

//AddMsg("FtpMkPath($newdir)<br>\n");
	if (!FtpConnect())
		return false;

	$newdir = FtpPath($newdir);
	if (empty($newdir))
		return true;

//AddMsg(" Verificando '$newdir'<br>\n");
	$parts = explode('/',$newdir);
	foreach($parts as $part)
	{
		if (empty($part) || @ftp_chdir($Ftp_handle, $part))
			continue;
//AddMsg(" Criando '$part':<br>\n");
		if (@ftp_mkdir($Ftp_handle, $part) === false ||
			!@ftp_chmod($Ftp_handle, 0777, $part) ||
			!@ftp_chdir($Ftp_handle, $part)
		   )
			Fatal("ERRO: Nao criou diretorio '$newdir'\n");

	}
	return true;
}
//---------------------------------------------------------
// Desconecta  FTP
function FtpClose()
{
	global $Ftp_handle;

	if ($Ftp_handle !== false)
	{
		ftp_close($Ftp_handle);
		$Ftp_handle = false;
	}
}
//---------------------------------------------------------
// Cria todos subdiretorios que compoe o caminho
function MkPath($newdir)
{
	global $Http_dir, $Http_dir_base;

//AddMsg("MkPath($newdir)<br>\n");
	if (!@file_exists($Http_dir_base))
		return false;
	if (@chdir($Http_dir_base) === false)
		return false;

	if (!empty($Http_dir))
	{
		if ($Http_dir{0} == '/')
			$newdir = NormalizeDir(substr($Http_dir,1).'/'.$newdir);
		else
			$newdir = NormalizeDir($Http_dir.'/'.$newdir);
	}
	else if (empty($newdir))
		return true;

//AddMsg(" Verificando '$newdir'<br>\n");
	if (@file_exists($newdir))
		return true;

	$parts = explode('/',$newdir);
	foreach($parts as $part){
//AddMsg(" Criando '$part':<br>\n");
		if (empty($part) || @chdir($part))
			continue;
		if (@mkdir($part) === false)
			return false;
		if (@chmod($part, 0777) === false)
			return false;
		if (@chdir($part) === false)
			return false;
	}
	return true;
}
//---------------------------------------------------------
// Percorre diretorio gerando lista de arquivos
function DirList($localdir, $subdir, $filtro, $fullinfo, $maxdepth)
{
	global $DirList_Tipos;

//echo "-> DirList('$localdir', '$subdir', '$filtro', $fullinfo, $maxdepth)\n";
	if ($subdir == '.')
		$subdir = '';
	else
		$subdir .= '/';
	  
	foreach (scandir("$localdir/$subdir") as $dirEntry)
	{
		if ($dirEntry == "." || $dirEntry == "..")
			continue;

		if (!empty($filtro) && !fnmatch($filtro, $dirEntry))
			continue;

		$file = $subdir.$dirEntry;

		if ($fullinfo)
		{	// Monta no formato:
			//	"file_name * st_size * st_atime * st_mtime * st_ctime * type * permission * str_mtime"
			//	type = Char giving information about the file's mode:
			//			F (File) | D (Directoty) | L (Link) | V (deVice) |
			//			P (fifo ou named Pipe) | S (Socket) | B (Block) | U (Unknown)
			$ss = lstat($localdir.'/'.$file);
			$mode=$ss['mode'];
			$tipo=decoct($mode & 0170000); // File Encoding Bit
			$tipo=(array_key_exists(octdec($tipo),$DirList_Tipos))?$DirList_Tipos[octdec($tipo)]:'U';
			$info = $file.'*'.$ss['size'].'*'.$ss['atime'].'*'.$ss['mtime'].'*'.$ss['ctime'].'*'.$tipo.'*'. 
				(($mode&0x0100)?'r':'-').(($mode&0x0080)?'w':'-').
				(($mode&0x0040)?(($mode&0x0800)?'s':'x'):(($mode&0x0800)?'S':'-')).
				(($mode&0x0020)?'r':'-').(($mode&0x0010)?'w':'-').
				(($mode&0x0008)?(($mode&0x0400)?'s':'x'):(($mode&0x0400)?'S':'-')).
				(($mode&0x0004)?'r':'-').(($mode&0x0002)?'w':'-').
				(($mode&0x0001)?(($mode&0x0200)?'t':'x'):(($mode&0x0200)?'T':'-')).'*'.
				date('Y-m-d-H-i-s',$ss['mtime']);
			echo "$info\n";
		}
		else if (is_file($localdir.'/'.$file))
			echo "$file\n";

		if ($maxdepth != 1  && is_dir($localdir.'/'.$file))
			DirList($localdir, $file, $filtro, $fullinfo, $maxdepth-1);
	}
}

//---------------------------------------------------------
function GetContentType($ext)
{
	$Mimes = array(
	"3dm,x-world/x-3dmf",
	"3dmf,x-world/x-3dmf",
	"a,application/octet-stream",
	"aab,application/x-authorware-bin",
	"aam,application/x-authorware-map",
	"aas,application/x-authorware-seg",
	"abc,text/vnd'abc",
	"acgi,text/html",
	"afl,video/animaflex",
	"ai,application/postscript",
	"aif,audio/aiff",
	"aifc,audio/aiff",
	"aiff,audio/aiff",
	"aim,application/x-aim",
	"aip,text/x-audiosoft-intra",
	"ani,application/x-navi-animation",
	"aos,application/x-nokia-9000-communicator-add-on-software",
	"aps,application/mime",
	"arc,application/octet-stream",
	"arj,application/octet-stream",
	"art,image/x-jg",
	"asf,video/x-ms-asf",
	"asm,text/x-asm",
	"asp,text/asp",
	"asx,application/x-mplayer2",
	"au,audio/basic",
	"avi,video/avi",
	"avs,video/avs-video",
	"bcpio,application/x-bcpio",
	"bin,application/octet-stream",
	"bm,image/bmp",
	"bmp,image/bmp",
	"boo,application/book",
	"book,application/book",
	"boz,application/x-bzip2",
	"bsh,application/x-bsh",
	"bz,application/x-bzip",
	"bz2,application/x-bzip2",
	"c,text/plain",
	"c++,text/plain",
	"cat,application/vnd'ms-pki'seccat",
	"cc,text/plain",
	"ccad,application/clariscad",
	"cco,application/x-cocoa",
	"cdf,application/cdf",
	"cer,application/x-x509-ca-cert",
	"cha,application/x-chat",
	"chat,application/x-chat",
	"class,application/java",
	"com,application/octet-stream",
	"conf,text/plain",
	"cpio,application/x-cpio",
	"cpp,text/x-c",
	"cpt,application/x-cpt",
	"crl,application/pkix-crl",
	"crt,application/pkix-cert",
	"csh,text/x-script'csh",
	"css,text/css",
	"cxx,text/plain",
	"dcr,application/x-director",
	"deepv,application/x-deepv",
	"def,text/plain",
	"der,application/x-x509-ca-cert",
	"dif,video/x-dv",
	"dir,application/x-director",
	"dl,video/dl",
	"doc,application/msword",
	"dot,application/msword",
	"dp,application/commonground",
	"drw,application/drafting",
	"dump,application/octet-stream",
	"dv,video/x-dv",
	"dvi,application/x-dvi",
	"dwg,image/x-dwg",
	"dxf,image/x-dwg",
	"dxr,application/x-director",
	"el,text/x-script'elisp",
	"elc,application/x-elc",
	"env,application/x-envoy",
	"eps,application/postscript",
	"es,application/x-esrehber",
	"etx,text/x-setext",
	"evy,application/envoy",
	"exe,application/octet-stream",
	"f,text/plain",
	"f,text/x-fortran",
	"f77,text/x-fortran",
	"f90,text/plain",
	"fdf,application/vnd'fdf",
	"fif,image/fif",
	"fli,video/fli",
	"flo,image/florian",
	"flx,text/vnd'fmi'flexstor",
	"fmf,video/x-atomic3d-feature",
	"for,text/plain",
	"fpx,image/vnd'fpx",
	"frl,application/freeloader",
	"funk,audio/make",
	"g,text/plain",
	"g3,image/g3fax",
	"gif,image/gif",
	"gl,video/gl",
	"gsd,audio/x-gsm",
	"gsm,audio/x-gsm",
	"gsp,application/x-gsp",
	"gss,application/x-gss",
	"gtar,application/x-gtar",
	"gz,application/x-compressed",
	"h,text/plain",
	"hdf,application/x-hdf",
	"help,application/x-helpfile",
	"hgl,application/vnd'hp-hpgl",
	"hh,text/plain",
	"hlb,text/x-script",
	"hlp,application/hlp",
	"hlp,application/x-helpfile",
	"hpg,application/vnd'hp-hpgl",
	"hpgl,application/vnd'hp-hpgl",
	"hqx,application/binhex",
	"hta,application/hta",
	"htc,text/x-component",
	"htm,text/html",
	"html,text/html",
	"htmls,text/html",
	"htt,text/webviewhtml",
	"htx,text/html",
	"ice,x-conference/x-cooltalk",
	"ico,image/x-icon",
	"idc,text/plain",
	"ief,image/ief",
	"iefs,image/ief",
	"iges,application/iges",
	"igs,application/iges",
	"ima,application/x-ima",
	"inf,application/inf",
	"ip,application/x-ip2",
	"isu,video/x-isvideo",
	"it,audio/it",
	"iv,application/x-inventor",
	"ivr,i-world/i-vrml",
	"ivy,application/x-livescreen",
	"jam,audio/x-jam",
	"jav,text/plain",
	"java,text/plain",
	"jcm,application/x-java-commerce",
	"jfif,image/jpeg",
	"jfif-tbnl,image/jpeg",
	"jpe,image/jpeg",
	"jpeg,image/jpeg",
	"jpg,image/jpeg",
	"jps,image/x-jps",
	"js,application/javascript",
	"js,text/javascript",
	"jut,image/jutvision",
	"kar,audio/midi",
	"ksh,application/x-ksh",
	"la,audio/nspaudio",
	"lam,audio/x-liveaudio",
	"latex,application/x-latex",
	"lha,application/octet-stream",
	"lhx,application/octet-stream",
	"list,text/plain",
	"lma,audio/nspaudio",
	"log,text/plain",
	"lsp,application/x-lisp",
	"lst,text/plain",
	"lsx,text/x-la-asf",
	"ltx,application/x-latex",
	"lzh,application/octet-stream",
	"lzx,application/octet-stream",
	"m,text/plain",
	"m1v,video/mpeg",
	"m2a,audio/mpeg",
	"m2v,video/mpeg",
	"m3u,audio/x-mpequrl",
	"man,application/x-troff-man",
	"map,application/x-navimap",
	"mar,text/plain",
	"mbd,application/mbedlet",
	"mc$,application/x-magic-cap-package-1'0",
	"mcd,application/mcad",
	"mcf,text/mcf",
	"mcp,application/netmc",
	"me,application/x-troff-me",
	"mht,message/rfc822",
	"mhtml,message/rfc822",
	"mid,audio/midi",
	"midi,audio/midi",
	"mif,application/x-mif",
	"mime,www/mime",
	"mjf,audio/x-vnd'audioexplosion'mjuicemediafile",
	"mjpg,video/x-motion-jpeg",
	"mm,application/base64",
	"mme,application/base64",
	"mod,audio/mod",
	"moov,video/quicktime",
	"mov,video/quicktime",
	"movie,video/x-sgi-movie",
	"mp2,audio/mpeg",
	"mp3,audio/mpeg3",
	"mpa,video/mpeg",
	"mpc,application/x-project",
	"mpe,video/mpeg",
	"mpeg,video/mpeg",
	"mpg,video/mpeg",
	"mpga,audio/mpeg",
	"mpp,application/vnd'ms-project",
	"mpt,application/x-project",
	"mpv,application/x-project",
	"mpx,application/x-project",
	"mrc,application/marc",
	"ms,application/x-troff-ms",
	"mv,video/x-sgi-movie",
	"my,audio/make",
	"mzz,application/x-vnd'audioexplosion'mzz",
	"nap,image/naplps",
	"naplps,image/naplps",
	"nc,application/x-netcdf",
	"ncm,application/vnd'nokia'configuration-message",
	"nif,image/x-niff",
	"niff,image/x-niff",
	"nix,application/x-mix-transfer",
	"nsc,application/x-conference",
	"nvd,application/x-navidoc",
	"o,application/octet-stream",
	"oda,application/oda",
	"omc,application/x-omc",
	"omcd,application/x-omcdatamaker",
	"omcr,application/x-omcregerator",
	"p,text/x-pascal",
	"p10,application/pkcs10",
	"p12,application/pkcs-12",
	"p7a,application/x-pkcs7-signature",
	"p7c,application/pkcs7-mime",
	"p7m,application/pkcs7-mime",
	"p7r,application/x-pkcs7-certreqresp",
	"p7s,application/pkcs7-signature",
	"part,application/pro_eng",
	"pas,text/pascal",
	"pbm,image/x-portable-bitmap",
	"pcl,application/x-pcl",
	"pct,image/x-pict",
	"pcx,image/x-pcx",
	"pdb,chemical/x-pdb",
	"pdf,application/pdf",
	"pfunk,audio/make",
	"pgm,image/x-portable-greymap",
	"pic,image/pict",
	"pict,image/pict",
	"pkg,application/x-newton-compatible-pkg",
	"pko,application/vnd'ms-pki'pko",
	"pl,text/plain",
	"plx,application/x-pixclscript",
	"pm,image/x-xpixmap",
	"pm4,application/x-pagemaker",
	"pm5,application/x-pagemaker",
	"png,image/png",
	"pnm,image/x-portable-anymap",
	"pot,application/mspowerpoint",
	"pov,model/x-pov",
	"ppa,application/vnd'ms-powerpoint",
	"ppm,image/x-portable-pixmap",
	"pps,application/mspowerpoint",
	"ppt,application/mspowerpoint",
	"ppt,application/powerpoint",
	"ppz,application/mspowerpoint",
	"pre,application/x-freelance",
	"prt,application/pro_eng",
	"ps,application/postscript",
	"psd,application/octet-stream",
	"pvu,paleovu/x-pv",
	"pwz,application/vnd'ms-powerpoint",
	"py,text/x-script'phyton",
	"pyc,application/x-bytecode'python",
	"qcp,audio/vnd'qcelp",
	"qd3,x-world/x-3dmf",
	"qd3d,x-world/x-3dmf",
	"qif,image/x-quicktime",
	"qt,video/quicktime",
	"qtc,video/x-qtc",
	"qti,image/x-quicktime",
	"qtif,image/x-quicktime",
	"ra,audio/x-realaudio",
	"ram,audio/x-pn-realaudio",
	"ras,image/cmu-raster",
	"rast,image/cmu-raster",
	"rexx,text/x-script'rexx",
	"rf,image/vnd'rn-realflash",
	"rgb,image/x-rgb",
	"rm,audio/x-pn-realaudio",
	"rmi,audio/mid",
	"rmm,audio/x-pn-realaudio",
	"rmp,audio/x-pn-realaudio",
	"rng,application/ringing-tones",
	"rnx,application/vnd'rn-realplayer",
	"roff,application/x-troff",
	"rp,image/vnd'rn-realpix",
	"rpm,audio/x-pn-realaudio-plugin",
	"rt,text/richtext",
	"rtf,text/richtext",
	"rtx,text/richtext",
	"rv,video/vnd'rn-realvideo",
	"s,text/x-asm",
	"s3m,audio/s3m",
	"saveme,application/octet-stream",
	"sbk,application/x-tbook",
	"scm,video/x-scm",
	"sdml,text/plain",
	"sdp,application/sdp",
	"sdr,application/sounder",
	"sea,application/sea",
	"set,application/set",
	"sgm,text/sgml",
	"sgml,text/sgml",
	"sh,application/x-bsh",
	"shar,application/x-bsh",
	"shtml,text/html",
	"sid,audio/x-psid",
	"sit,application/x-sit",
	"skd,application/x-koan",
	"skm,application/x-koan",
	"skp,application/x-koan",
	"skt,application/x-koan",
	"sl,application/x-seelogo",
	"smi,application/smil",
	"smil,application/smil",
	"snd,audio/basic",
	"sol,application/solids",
	"spc,text/x-speech",
	"spl,application/futuresplash",
	"spr,application/x-sprite",
	"sprite,application/x-sprite",
	"src,application/x-wais-source",
	"ssi,text/x-server-parsed-html",
	"ssm,application/streamingmedia",
	"sst,application/vnd'ms-pki'certstore",
	"step,application/step",
	"stl,application/sla",
	"stp,application/step",
	"sv4cpio,application/x-sv4cpio",
	"sv4crc,application/x-sv4crc",
	"svf,image/x-dwg",
	"svr,application/x-world",
	"swf,application/x-shockwave-flash",
	"t,application/x-troff",
	"talk,text/x-speech",
	"tar,application/x-tar",
	"tbk,application/toolbook",
	"tcl,application/x-tcl",
	"tcsh,text/x-script'tcsh",
	"tex,application/x-tex",
	"texi,application/x-texinfo",
	"texinfo,application/x-texinfo",
	"text,text/plain",
	"tgz,application/x-compressed",
	"tif,image/tiff",
	"tiff,image/tiff",
	"tr,application/x-troff",
	"tsi,audio/tsp-audio",
	"tsp,audio/tsplayer",
	"tsv,text/tab-separated-values",
	"turbot,image/florian",
	"txt,text/plain",
	"uil,text/x-uil",
	"uni,text/uri-list",
	"unis,text/uri-list",
	"unv,application/i-deas",
	"uri,text/uri-list",
	"uris,text/uri-list",
	"ustar,multipart/x-ustar",
	"uu,text/x-uuencode",
	"uue,text/x-uuencode",
	"vcd,application/x-cdlink",
	"vcs,text/x-vcalendar",
	"vda,application/vda",
	"vdo,video/vdo",
	"vew,application/groupwise",
	"viv,video/vivo",
	"viv,video/vnd'vivo",
	"vivo,video/vivo",
	"vmd,application/vocaltec-media-desc",
	"vmf,application/vocaltec-media-file",
	"voc,audio/voc",
	"vos,video/vosaic",
	"vox,audio/voxware",
	"vqe,audio/x-twinvq-plugin",
	"vqf,audio/x-twinvq",
	"vql,audio/x-twinvq-plugin",
	"vrml,application/x-vrml",
	"vrt,x-world/x-vrt",
	"vsd,application/x-visio",
	"vst,application/x-visio",
	"vsw,application/x-visio",
	"w60,application/wordperfect6'0",
	"w61,application/wordperfect6'1",
	"w6w,application/msword",
	"wav,audio/wav",
	"wb1,application/x-qpro",
	"wbmp,image/vnd'wap'wbmp",
	"web,application/vnd'xara",
	"wiz,application/msword",
	"wk1,application/x-123",
	"wmf,windows/metafile",
	"wml,text/vnd'wap'wml",
	"wmlc,application/vnd'wap'wmlc",
	"wmls,text/vnd'wap'wmlscript",
	"wmlsc,application/vnd'wap'wmlscriptc",
	"word,application/msword",
	"wp,application/wordperfect",
	"wp5,application/wordperfect",
	"wp6,application/wordperfect",
	"wpd,application/wordperfect",
	"wq1,application/x-lotus",
	"wri,application/mswrite",
	"wrl,application/x-world",
	"wrl,model/vrml",
	"wrz,model/vrml",
	"wsc,text/scriplet",
	"wsrc,application/x-wais-source",
	"wtk,application/x-wintalk",
	"xbm,image/xbm",
	"xdr,video/x-amt-demorun",
	"xgz,xgl/drawing",
	"xif,image/vnd'xiff",
	"xl,application/excel",
	"xla,application/excel",
	"xlb,application/excel",
	"xlc,application/excel",
	"xld,application/excel",
	"xlk,application/excel",
	"xll,application/excel",
	"xlm,application/excel",
	"xls,application/excel",
	"xlt,application/excel",
	"xlv,application/excel",
	"xlw,application/excel",
	"xm,audio/xm",
	"xml,text/xml",
	"xmz,xgl/movie",
	"xpix,application/x-vnd'ls-xpix",
	"xpm,image/xpm",
	"x-png,image/png",
	"xsr,video/x-amt-showrun",
	"xwd,image/x-xwd",
	"xyz,chemical/x-pdb",
	"z,application/x-compressed",
	"zip,application/x-compressed",
	"zip,application/zip",
	"zoo,application/octet-stream",
	"zsh,text/x-script'zsh"
);

//AddMsg("GetContentType($ext)<br>\n");
	foreach ($Mimes as $mime)
	{
		$val = explode(',', $mime);
		$cmp = strcmp($ext, $val[0]);
		if ($cmp == 0)
			return $val[1];
		if ($cmp < 0)
			break;
	}
	
	// Nao identificou
	return 'application/octet-stream';
}

//---- Main -----------------------------------------------
set_error_handler("myErrorHandler");

if (isset($_SERVER['PATH_INFO']))
	$path_info = $_SERVER['PATH_INFO'];
if (!empty($path_info))
{
	$param = explode('/', $path_info);
	$cont = count($param);
	if ($cont <= 1)
		Fatal('Acesso restrito!');
	$oper = $param[1];
	if ($cont == 2)
	{
		if ($oper != "ping")
			Fatal('Acesso restrito!');
	}
	else if ($oper == "load")
		$_REQUEST['file_name'] = '/'.implode('/',array_slice($param,2));
	else if ($oper == "dirlist")
		$_REQUEST['dir'] = '/'.implode('/',array_slice($param,2));
	else if ($oper == "dirlistfull")
	{
		$oper = "dirlist";
		$_REQUEST['fullinfo'] = 'SIM';
		$_REQUEST['dir'] = '/'.implode('/',array_slice($param,2));
	}
	else if ($cont > 3)
	{
		if ($oper == "depthdirlist")
		{
			$oper = "dirlist";
			$_REQUEST['dir'] = '/'.implode('/',array_slice($param,3));
		}
		else if ($oper == "depthdirlistfull")
		{
			$oper = "dirlist";
			$_REQUEST['fullinfo'] = 'SIM';
			$_REQUEST['dir'] = '/'.implode('/',array_slice($param,3));
		}
		else
			Fatal('Acesso restrito!');
		if (!ctype_digit( $param[2]))
			Fatal('Acesso restrito!');
		 $_REQUEST['maxdepth'] = $param[2];
	}
	else
		Fatal('Acesso restrito!');
}
else if (isset($_REQUEST['oper']))
	$oper = $_REQUEST['oper'];
else
	Fatal("ERRO: falta operacao\n");

HttpInit();

if ($oper == "ping")
{
	$Resp = "OK: MultiStream pronto\n";
}
else if ($oper == "tstftp")
{
	$Browser_Debug = true;
	if (!isset($_REQUEST['file_name']))
		Fatal("ERRO: falta parametro 'file_name'\n");
	FtpInit();

	$srcfile = $_REQUEST['file_name'];
	$localfile= HttpPath($srcfile);
	$ftpfile = NormalizeDir(dirname($srcfile).'/tstftp.tmp');
AddMsg('getcwd()='.getcwd()."<br>\n");
AddMsg("srcfile=$srcfile<br>\n");
AddMsg("localfile=$localfile<br>\n");
AddMsg("ftpfile=".FtpPath($ftpfile)."<br>\n");

	$sSrc = @file_get_contents($localfile);
	if ($sSrc === false)
		Fatal("ERRO: Nao abriu o arquivo origem: '$srcfile'\n");

	if (!FtpPut($localfile, $ftpfile))
		Fatal("ERRO: nao gravou '$uploadfile' -> '$file'\n");

	$sDest = @file_get_contents(HttpPath($ftpfile));
	if ($sDest === false)
		Fatal("ERRO: Nao abriu o arquivo depois de gravado por FTP em '$ftpfile'\n");
	if ($sDest != $sSrc)
		Fatal("ERRO: Nao gravou corretamente no arquivo: '$ftpfile'\n");
	FtpDelete($ftpfile);

	echo "OK: gravou por FTP, comparou '$srcfile'&'$ftpfile' e apagou por FTP\n";
	ShowMsg();
	exit;
}
else if ($oper == "tst")
{
	$Browser_Debug = true;
	if (!isset($_REQUEST['file_name']))
		Fatal("ERRO: falta parametro 'file_name'\n");

	$destfile = HttpPath($_REQUEST['file_name']);

	$fOut = @fopen($destfile, "w");
	if ($fOut === false)
		Fatal("ERRO: Sem permissao para criar arquivo: '$destfile'\n");

	$sOut = "TESTE DE GRAVACAO\n".date('r')."\n";
	if (fputs($fOut, $sOut) === false)
		Fatal("ERRO: Nao gravou no arquivo de saida: '$destfile'\n");
	fclose($fOut);

	$sIn = @file_get_contents($destfile);
	if ($sIn === false)
		Fatal("ERRO: Nao abriu o arquivo depois de gravado: '$destfile'\n");
	if ($sIn != $sOut)
		Fatal("ERRO: Nao gravou no arquivo: '$destfile'\n");
	if (unlink($destfile) === false)
		Fatal("ERRO: Nao apagou o arquivo: '$destfile'\n");
	$Resp = "OK: gravou e leu '$destfile'\n";
}
else if ($oper == "save")
{
	if (!isset($_REQUEST['file_name']))
		Fatal("ERRO: falta parametro 'file_name'\n");

	if (!isset($_FILES['file_content']))
		Fatal("ERRO: falta parametro 'file_content'\n");

	$file = $_REQUEST['file_name'];
	$destdir = NormalizeDir(dirname($file));
	if (!MkPath($destdir))
		if (!FtpMkPath($destdir))
			Fatal("ERRO: nao criou diretorio '$destdir'\n", '401 Nao autorizado');

	$uploadfile = $_FILES['file_content']['name'];
	$uploadtmp = $_FILES['file_content']['tmp_name'];
	$httpfile = HttpPath($file);
	if (!@move_uploaded_file($uploadtmp, $httpfile))
		if (!FtpPut($uploadtmp, $file))
			Fatal("ERRO: nao gravou '$uploadfile' -> '$file'\n", '401 Nao autorizado');

	if (isset($_REQUEST['file_chmod']))
		$chmod = $_REQUEST['file_chmod'];
	else
		$chmod = 0666;
	if (@chmod($httpfile, $chmod) === false)
		FtpChMod($file, $chmod);

	if (isset($_REQUEST['file_mtime']))
		@touch($httpfile, $_REQUEST['file_mtime']);

	$Resp = "OK: gravado '$uploadfile' -> '$httpfile'\n";
}
else if ($oper == "del")
{
	ShowHeader();
	if (!isset($_REQUEST['file_name']))
		Fatal("ERRO: falta parametro 'file_name'\n");

	$delfile = $_REQUEST['file_name'];
	$localfile = HttpPath($delfile);
	if (!file_exists($localfile))
			Fatal("ERRO: nao existe '$delfile'\n", '404 Nao encontrado');
	if (!@unlink($localfile))
		if (!@FtpDelete($delfile))
			Fatal("ERRO: nao removeu '$delfile'\n", '401 Nao autorizado');
	$Resp = "OK: removido '$delfile'\n";
}
else if ($oper == "load")
{
	if (!isset($_REQUEST['file_name']))
		Fatal("ERRO: falta parametro 'file_name'\n");
	$file = $_REQUEST['file_name'];

	$localfile = HttpPath($file);
	if (!file_exists($localfile))
		Fatal("ERRO: inexiste '$file' ($localfile)\n", '404 Nao encontrado');

	if (isset($_REQUEST['text']))
		$content_type = 'text/'.$_REQUEST['text'];
	else
	{
		if (isset($_REQUEST['content_type']))
			$content_type = $_REQUEST['content_type'];
		if (empty($content_type))
		{
			$path_parts = pathinfo($file);
			$content_type = GetContentType($path_parts['extension']);
		}
	}

	if (empty($content_type))
	{
		header('Content-Description: File Transfer');
		header('Content-Type: application/octet-stream');
		header("Content-Disposition: attachment; filename=\"".basename($file)."\";");
		header('Content-Transfer-Encoding: binary');
		header('Expires: 0');
		header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
		header('Pragma: public');
	}
	else
		header('Content-Type: '.$content_type);

	$total_size = filesize($localfile);
	$start_pos = isset($_REQUEST['start_pos']) ? $_REQUEST['start_pos'] : 0;
	$max_size = isset($_REQUEST['max_size']) ? $_REQUEST['max_size'] : 0;

	if ($total_size == 0 || ($start_pos == 0 && $max_size == 0))
	{
		header('Content-Length: ' . $total_size);
		@readfile($localfile);
	}
	else
	{
		if ($max_size == 0 || $start_pos+$max_size > $total_size)
			$max_size = $total_size - $start_pos;

		header('Content-Length: ' . $max_size);
		$fp = fopen($localfile, 'rb');
		fseek($fp, $start_pos);
		while ($max_size > 0 && !feof($fp))
		{
		    //reset time limit for big files
		    set_time_limit(0);
			$block_size = min($max_size, 8192);
		    print(fread($fp, $block_size));
			$max_size -= $block_size;
		}

		fclose($fp);
	}
	exit;
}
else if ($oper == "ren")
{
	ShowHeader();
	if (!isset($_REQUEST['file_name']))
		Fatal("ERRO: falta parametro 'file_name'\n");
	if (!isset($_REQUEST['new_name']))
		Fatal("ERRO: falta parametro 'new_name'\n");

	$renfile = $_REQUEST['file_name'];
	$localfile = HttpPath($renfile);
	if (!file_exists($localfile))
		Fatal("ERRO: inexiste '$renfile' ($localfile)\n", '404 Nao encontrado');

	$newfile = $_REQUEST['new_name'];
	$newlocalfile = HttpPath($newfile);
//	if (file_exists($newlocalfile))
//		Fatal("ERRO: ja' existe '$newfile' ($newlocalfile)\n");

	if (!@rename($localfile, $newlocalfile))
		if (!@FtpRename($renfile, $newfile))
			Fatal("ERRO: nao renomeou '$renfile'\n", '401 Nao autorizado');
	$Resp = "OK: renomeado '$renfile' para '$newfile'\n";
}
else if ($oper == "dirlist")
{
	if (!isset($_REQUEST['dir']))
		Fatal("ERRO: falta parametro 'dir'\n");

	$dir = $_REQUEST['dir'];
	if ($dir[0] == '@')
	{	// Trata-se de referencia indireta para dir. de configuracao
		$conf = substr($dir,1);
		if ($conf == 'DIR_PRG')
			$dir = '.';
		else
		{
			if (!is_file('msg.php')) {
				header("HTTP/1.0 404 Nao encontrado");
				echo("ERRO: nao ha' ambiente para parametro 'dir' com referencia\n");
				exit(1);
			}
			include 'msg.php';
			$dir = Configuracao($conf,'',false);
			if (empty($dir)) {
				header("HTTP/1.0 404 Nao encontrado");
				echo("ERRO: parametro 'dir' com referencia inexistente (@$conf)\n");
				exit(1);
			}
		}
	}
	$localdir = HttpPath($dir);
	if (isset($_REQUEST['fullinfo']) && strtoupper($_REQUEST['fullinfo']) == "SIM")
		$fullinfo = true;
	else
		$fullinfo = false;
	if (isset($_REQUEST['filter']))
		$filtro = $_REQUEST['filter'];
	else
		$filtro = false;

	if (isset($_REQUEST['maxdepth']))
	{
		$maxdepth = $_REQUEST['maxdepth'];
		if (strlen(trim($maxdepth)) == 0)
			$maxdepth = 1;
	}
	else
		$maxdepth = 1;

	if (!is_dir($localdir))
		Fatal("ERRO: '$dir' nao existe ($localdir)\n", '404 Nao encontrado');

	ShowHeader();
	echo "OK: dirlist '$dir' ($localdir)\n";
	DirList($localdir, '.', $filtro, $fullinfo, $maxdepth);
	exit;
}
else if ($oper == "fileinfo")
{
	if (!isset($_REQUEST['file_name']))
		Fatal("ERRO: falta parametro 'file_name'\n");
	$file = $_REQUEST['file_name'];

	$localfile = HttpPath($file);
	if (!is_file($localfile))
		Fatal("ERRO: inexiste '$file' ($localfile)\n", '404 Nao encontrado');

	// Monta no formato:
	//	"file_name * st_size * st_atime * st_mtime * st_ctime * type * permission * str_mtime"
	//	type = Char giving information about the file's mode:
	//			F (File) | D (Directoty) | L (Link) | V (deVice) |
	//			P (fifo ou named Pipe) | S (Socket) | B (Block) | U (Unknown)
	$ss = lstat($localfile);
	$mode=$ss['mode'];
	$tipo=decoct($mode & 0170000); // File Encoding Bit
	$tipo=(array_key_exists(octdec($tipo),$DirList_Tipos))?$DirList_Tipos[octdec($tipo)]:'U';
	$info = $file.'*'.$ss['size'].'*'.$ss['atime'].'*'.$ss['mtime'].'*'.$ss['ctime'].'*'.$tipo.'*'. 
		(($mode&0x0100)?'r':'-').(($mode&0x0080)?'w':'-').
		(($mode&0x0040)?(($mode&0x0800)?'s':'x'):(($mode&0x0800)?'S':'-')).
		(($mode&0x0020)?'r':'-').(($mode&0x0010)?'w':'-').
		(($mode&0x0008)?(($mode&0x0400)?'s':'x'):(($mode&0x0400)?'S':'-')).
		(($mode&0x0004)?'r':'-').(($mode&0x0002)?'w':'-').
		(($mode&0x0001)?(($mode&0x0200)?'t':'x'):(($mode&0x0200)?'T':'-')).'*'.
		date('Y-m-d-H-i-s',$ss['mtime']);

	ShowHeader();
	echo "OK: fileinfo '$file' ($localfile)\n";
	echo "$info\n";
	exit;
}
else
	Fatal("ERRO: unknown operation: '$oper'\n", '501 Nao implementado');

ShowHeader();
echo $Resp;
ShowMsg();
?>
