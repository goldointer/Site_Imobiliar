<?php
include "msg.php";

header('Content-type: text/plain');

$contArqs = 0;
$bRmdirOK = true;
$bSetTimeLimit = true;
$DirAnexos = Configuracao('DIR_ANEXOS');

//----------------------------------------------------------------------------------
function myErrorHandler($type, $info, $file, $row)
{
	global $bSetTimeLimit;
	$bSetTimeLimit = false;
//echo "#SEM set_time_limit()\n";
}

//----------------------------------------------------------------------------------
/*
	Monta catalogo de todos os anexos do diretorio.
*/
function CatalogoAnexos($dir, $subdir)
{
	global $bSetTimeLimit, $contArqs, $bRmdirOK;

//echo "CatalogoAnexos($dir, $subdir);\n";
	$cont = 0;
	if ($bSetTimeLimit) set_time_limit(60);
	$fh = opendir($dir);
	if ($fh === false)
		return 0;

	while (false !== ($dirEntry = readdir($fh)))
	{
		if ($dirEntry{0} == '.')
			continue;
		$file = $dir.'/'.$dirEntry;
		if (is_dir($file))
		{
			CatalogoAnexos($file, $subdir.$dirEntry.'/');
			continue;
		}
		if (!is_file($file))
			continue;

		$infos = stat($file);
		$size = $infos[7];
		$mtime = $infos[9];
		echo "File: $subdir$dirEntry\nSize: $size\n";
		echo 'Date: '.$mtime.' ('.gmdate('Y-m-d H:i:s', $mtime).")\n\n";
		$contArqs++;

		if ((++$cont % 100) == 0)
			if ($bSetTimeLimit) set_time_limit(60);
	}

	closedir($fh);

	if ($cont == 0 && $bRmdirOK) {
		// Apagar diretorio vazio
		if (@rmdir($dir) === false)
			$bRmdirOK = false;
	}
}

//---- main ------------------------------------------------------------------------
set_error_handler("myErrorHandler");
set_time_limit(60);
restore_error_handler();

echo "#CATALOG: ANEXOS\n\n";

CatalogoAnexos($DirAnexos, '');

echo "#OK $contArqs\n";

if (is_file("limpaAnexos.php"))
	echo "#LIMPAANEXOS\n";
?>
