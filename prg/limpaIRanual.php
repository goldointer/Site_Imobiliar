<?php
include "msg.php";

header("Content-type: text/plain");

//error_reporting(4);	//E_PARSE

$geraCmdFTP = false;
$bSetTimeLimit = true;
$contDel = 0;
$DirIRanual = Configuracao("DIR_IRANUAL");

//----------------------------------------------------------------------------------
function myErrorHandler($type, $info, $file, $row)
{
	global $bSetTimeLimit;
	$bSetTimeLimit = false;
}

//----------------------------------------------------------------------------------
function limpaIRanual($dir, $subdir, $ano)
{
	global $DirIRanual, $bSetTimeLimit, $geraCmdFTP, $contDel;

//echo "limpaIRanual($dir, $subdir, $ano);\n";

	if ($contDel > 500)
		return 0;

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
		if (is_dir($file))
		{
			$cont += limpaIRanual($file, $subdir.'/'.$dirEntry.'/', $ano);
			continue;
		}
		if (!is_file($file))
			continue;

		if ((++$cont % 100) == 0)
			if ($bSetTimeLimit) set_time_limit(60);

		$aux = explode("_", $file);
		if ($aux[1] == $ano)
		{
			// Apagar este arquivo
			$contDel++;
			if (!empty($geraCmdFTP) || @unlink($file) === false)
			{
				echo "del $file\n";
				flush();
				$geraCmdFTP = true;
			}
		}

		if ($contDel > 500)
			break;
	}

	closedir($fh);

	if ($cont == 0)
	{
		if ($dir != $DirIRanual)
		{
			// Apagar diretorio vazio
			$contDel++;
			if (!empty($geraCmdFTP) || @rmdir($dir) === false)
			{
				echo "rmdir $subdir\n";
				flush();
				$geraCmdFTP = true;
			}
		}
	}

	return $cont;
}

//---- main ------------------------------------------------------------------------------
set_error_handler("myErrorHandler");
set_time_limit(60);
restore_error_handler();

$ano = CampoObrigatorio('limparano').'.csv';

/* Prepara chamada do FTP. */
echo "#DIR: $DirIRanual\n";
echo "pwd\n";
flush();

/* Remove diretorios de lancamentos vazios. */
$fh = opendir($DirIRanual);
while (false !== ($dirEntry = readdir($fh)))
{
	if ($dirEntry == "." || $dirEntry == "..")
		continue;

	$dir = $DirIRanual.$dirEntry;
	if (is_dir($dir))
		limpaIRanual($dir, $dirEntry, $ano);

	if ($contDel > 500)
		break;
}

closedir($fh);

/* Finaliza chamada do FTP. */
echo "#OK $contDel\n";
?>
