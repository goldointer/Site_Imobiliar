<?php
include "msg.php";

header("Content-type: text/plain");

$contArqs = 0;
$bSetTimeLimit = true;
$DirDados = Configuracao('DIR_DADOS'); 
$SubDirBoletos = Configuracao('SUBDIR_BOLETOS');
if (empty($SubDirBoletos))
	$SubDirBoletos = 'bloquetos3';

//----------------------------------------------------------------------------------
function myErrorHandler($type, $info, $file, $row)
{
	global $bSetTimeLimit;
	$bSetTimeLimit = false;
//echo "#SEM set_time_limit()\n";
}

//----------------------------------------------------------------------------------
/*
	Monta catalogo de todos os DOCs do diretorio.
*/
function CatalogoDocs($dir, $subdir, $ext)
{
	global $bSetTimeLimit, $contArqs;

//echo "CatalogoDocs($dir, $subdir);\n";
	$cont = 0;
	if ($bSetTimeLimit) set_time_limit(60);
	$fh = opendir($dir);
	if ($fh === false)
		return 0;

	while (false !== ($dirEntry = readdir($fh)))
	{
		if ($dirEntry{0} == '.')
			continue;

		$file = $dir."/".$dirEntry;
		if (is_dir($file)) {
			if (is_numeric($dirEntry))
				CatalogoDocs($file, $subdir.$dirEntry."/", $ext);
			continue;
		}
		if (!is_file($file))
			continue;
		if (!empty($ext) && strstr($dirEntry, $ext) != $ext)
			continue;

		$infos = stat($file);
		$size = $infos[7];
		$mtime = $infos[9];
		echo "File: $subdir$dirEntry\nSize: $size\n";
		echo 'Date: '.$mtime.' ('.gmdate('Y-m-d H:i:s', $mtime).")\n\n";
		$contArqs++;

		if (++$cont > 100)
		{
			$cont = 0;
			if ($bSetTimeLimit) set_time_limit(60);
		}
	}

	closedir($fh);
}

//---- main ------------------------------------------------------------------------
set_error_handler('myErrorHandler');
set_time_limit(60);
restore_error_handler();

$subdir = Campo('subdir');
if (empty($subdir))
	$subdir = $DirDados.$SubDirBoletos;
echo "# $subdir\n\n";

if (is_dir($subdir)) {
	$ext = Campo('ext');
	if ($ext == false)
		$ext = ".txt";
	else if ($ext == '*')
		$ext = '';

	CatalogoDocs($subdir, '', $ext);
}

echo "#OK $contArqs\n";

if (is_file("limpaBoletos.php"))
	echo "#LIMPABOLETOS\n";
?>
