<?php
include "msg.php";

header("Content-type: text/plain");

$contDel = 0;
$geraCmdFTP = false;
$bSetTimeLimit = true;
$DirAnexos = Configuracao("DIR_ANEXOS");

//----------------------------------------------------------------------------------
function myErrorHandler($type, $info, $file, $row)
{
	global $bSetTimeLimit;
	$bSetTimeLimit = false;
}

//----------------------------------------------------------------------------------
function LimpaAnexos($subdir)
{
	global $DirAnexos, $bSetTimeLimit, $geraCmdFTP, $contDel;

#echo "#LimpaAnexos($subdir);\n";

	if ($contDel > 600)
		return 0;

	if ($bSetTimeLimit) set_time_limit(60);
	$fh = @opendir($subdir);
	if ($fh === false)
		return 0;

	$cont = 0;
	while (false !== ($dirEntry = readdir($fh)))
	{
		if ($dirEntry{0} == '.')
			continue;
		$file = $subdir.'/'.$dirEntry;
		if (is_dir($file))
		{
			$cont += LimpaAnexos($file);
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
		if ($subdir != '.')
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

$subdir = Campo('targetdir');
if (empty($subdir))
	$subdir = "Condom";
$DirAnexos .= '/'.$subdir;

/* Prepara chamada do FTP. */
echo "pwd\n";
flush();

/* Remove diretorios de anexos vazios. */
chdir($DirAnexos);
echo '#CWD: '.getcwd()."\n";
$fh = @opendir('.');
while (false !== ($dirEntry = readdir($fh)))
{
	if ($dirEntry == "." || $dirEntry == "..")
		continue;

	if (is_dir($dirEntry))
		LimpaAnexos($dirEntry);

	if ($contDel > 500)
		break;
}

closedir($fh);

/* Finaliza chamada do FTP. */
echo "#OK $contDel\n";
?>
