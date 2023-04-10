<?php
include "msg.php";

header("Content-type: text/plain");

$geraCmdFTP = false;
$bSetTimeLimit = true;
$contDel = 0;

$TipoLancto = Campo("tipo");
if ($TipoLancto == 'L')
	$DirImagens = Configuracao("DIR_LANCAMENTOS_LOC");
else
$DirImagens = Configuracao("DIR_LANCAMENTOS");

//----------------------------------------------------------------------------------
function myErrorHandler($type, $info, $file, $row)
{
	global $bSetTimeLimit;
	$bSetTimeLimit = false;
}

//----------------------------------------------------------------------------------
function limpaLanctos($dir, $subdir)
{
	global $DirImagens, $bSetTimeLimit, $geraCmdFTP, $contDel;

//echo "limpaLanctos($dir, $subdir);\n";

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
			$cont += limpaLanctos($file, $subdir.'/'.$dirEntry.'/');
			continue;
		}
		if (!is_file($file))
			continue;

		if ((++$cont % 100) == 0)
			if ($bSetTimeLimit) set_time_limit(60);

		if ($contDel > 500)
			break;
	}

	closedir($fh);

	if ($cont == 0)
	{
		if ($dir != $DirImagens)
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

/* Prepara chamada do FTP. */
echo "pwd\n";
flush();

/* Remove diretorios de lancamentos vazios. */
$fh = opendir($DirImagens);
if ($fh === false)
{
	echo "#OK 0\n";
	return;
}
while (false !== ($dirEntry = readdir($fh)))
{
	if ($dirEntry == "." || $dirEntry == "..")
		continue;

	$dir = $DirImagens.$dirEntry;
	if (is_dir($dir))
		limpaLanctos($dir, $dirEntry);

	if ($contDel > 500)
		break;
}

closedir($fh);

/* Finaliza chamada do FTP. */
echo "#OK $contDel\n";
?>
