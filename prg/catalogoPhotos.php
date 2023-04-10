<?php
include "msg.php";

header("Content-type: text/plain");

$contArqs = 0;
$bSetTimeLimit = true;
$DirImagens = Configuracao("DIR_FOTOS");

//----------------------------------------------------------------------------------
function myErrorHandler($type, $info, $file, $row)
{
	global $bSetTimeLimit;
	$bSetTimeLimit = false;
//echo "#SEM set_time_limit()\n";
}

//----------------------------------------------------------------------------------
/*
	Monta catalogo de todos as fotos do diretorio.
*/
function CatalogoPhotos($dir, $subdir)
{
	global $bSetTimeLimit, $contArqs;

//echo "CatalogoPhotos($dir, $subdir);\n";
	$cont = 0;
	if ($bSetTimeLimit) set_time_limit(60);
	$fh = opendir($dir);

	while (false !== ($dirEntry = readdir($fh)))
	{
		if ($dirEntry{0} == '.')
			continue;
		if (strstr(strtolower($dirEntry), ".jpg") != ".jpg" && 
                        strstr(strtolower($dirEntry), ".url") != ".url")
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

echo "#CATALOG: PHOTOS\n\n";

CatalogoPhotos($DirImagens, "");

$fh = opendir($DirImagens);

while (false !== ($dirEntry = readdir($fh)))
{
	if ($dirEntry == "." || $dirEntry == "..")
		continue;
	if (!is_numeric($dirEntry))
		continue;

	$dir = $DirImagens.$dirEntry;
	if (is_dir($dir))
		CatalogoPhotos($dir, $dirEntry."/");
}

closedir($fh);

echo "#OK $contArqs\n";
?>
