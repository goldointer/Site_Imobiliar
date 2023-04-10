<?php
include "msg.php";

header("Content-type: text/plain");

$bSetTimeLimit = true;
$contArqs = 0;
$DirDados = Configuracao("DIR_DADOS");

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
function CatalogoDocs($dir, $subdir)
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
		if (!is_file($file))
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
set_error_handler("myErrorHandler");
set_time_limit(60);
restore_error_handler();

CatalogoDocs($DirDados, "");

echo "#OK $contArqs\n";

if (is_file("limpaDados.php"))
	echo "#LIMPADADOS\n";
?>
