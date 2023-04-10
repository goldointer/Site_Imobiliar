<?php
include "msg.php";

header('Content-type: text/plain');

$contArqs = 0;
$bRmdirOK = true;
$bSetTimeLimit = true;
$DirImagens = Configuracao('DIR_LANCAMENTOS');

//----------------------------------------------------------------------------------
function myErrorHandler($type, $info, $file, $row)
{
	global $bSetTimeLimit;
	$bSetTimeLimit = false;
//echo "#SEM set_time_limit()\n";
}

//----------------------------------------------------------------------------------
/*
	Monta catalogo de todos as imagens de lancamentos do diretorio.
*/
function CatalogoLanctos($dir, $subdir)
{
	global $bSetTimeLimit, $contArqs, $bRmdirOK;

//echo "CatalogoLanctos($dir, $subdir);\n";
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
			if (is_numeric($dirEntry))
				CatalogoLanctos($file, $subdir.$dirEntry.'/');
			continue;
		}
		if (!is_file($file))
			continue;
		if (strstr(strtolower($dirEntry), '.jpg') != '.jpg')
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
		else
			echo "#RMDIR: $dir\n\n";
	}
}

//---- main ------------------------------------------------------------------------
set_error_handler("myErrorHandler");
set_time_limit(60);
restore_error_handler();

echo "#CATALOG: LANCTOS\n\n";

$subdir = Campo('subdir');

CatalogoLanctos($DirImagens.$subdir, '');

echo "#OK $contArqs\n";

if (is_file("limpaLanctos.php"))
	echo "#LIMPALANCTOS\n";
?>
