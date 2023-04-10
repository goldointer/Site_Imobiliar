<?php
include "msg.php";

header("Content-type: text/plain");

$geraCmdFTP = false;
$bSetTimeLimit = true;
$contChmod = 0;
$ContOk = 0;
$DirDados = Configuracao("DIR_DADOS");
$fileChmod = 0666;
$dirChmod = 0777;
$bRecursivo = false;

//----------------------------------------------------------------------------------
function myErrorHandler($type, $info, $file, $row)
{
	global $bSetTimeLimit;
	$bSetTimeLimit = false;
}

//----------------------------------------------------------------------------------
/*
	Muda permissao de todos os arquivos do diretorio.
*/
function PermissaoArqs($dir)
{
	global $geraCmdFTP, $bSetTimeLimit, $contChmod, $ContOk, $fileChmod, $dirChmod, $bRecursivo;

//echo "#PermissaoArqs($dir)\n";

	if ($bSetTimeLimit) set_time_limit(60);

	if ($dir == '.')
	{
		$fh = opendir($dir);
		if ($fh === false)
			return;
		$dir = '';
	}
	else
	{
		$stat = @stat($dir);
		if (empty($stat))
		{
			printf("chmod %o \"%s\"\n",$dirChmod,$dir);
			$contChmod++;
			return;
		}
		if (($stat['mode'] & 040000) != 040000)
			return; // nao e' diretorio
		if (($stat['mode'] & 0777) != $dirChmod)
		{
//printf("#%s (%o)\n",$dir,$stat['mode']);
			if (!empty($geraCmdFTP) || @chmod($dir, $dirChmod) === false)
			{
				printf("chmod %o \"%s\"\n",$dirChmod,$dir);
				$contChmod++;
				$geraCmdFTP = true;
			}
			else $ContOk++;
		}
		$fh = opendir($dir);
		if ($fh === false)
			return;
		$dir .= '/';
	}

	$contDir = 0;

	while (false !== ($dirEntry = readdir($fh)))
	{
		if ($dirEntry{0} == '.')
			continue;

		if (++$contDir > 100)
		{
			$contDir = 0;
			if ($bSetTimeLimit) set_time_limit(60);
		}

		$file = $dir.$dirEntry;
		$stat = @stat($file);
		if (empty($stat))
		{
			printf("chmod %o \"%s\"\n",$dirChmod,$file);
			$contChmod++;
			continue;
		}

		if (($stat['mode'] & 040000) == 040000)
		{
			if ($bRecursivo)
				PermissaoArqs($file); // e' diretorio
			continue;
		}
		
		if (($stat['mode'] & 0100000) != 0100000)
			continue; // nao e' arquivo

//printf("#%s (%o)\n",$file,$stat['mode']);
		if (($stat['mode'] & 0777) != $fileChmod)
		{
			if (!empty($geraCmdFTP) || @chmod($file, $fileChmod) === false)
			{
				printf("chmod %o \"%s\"\n",$fileChmod,$file);
				$contChmod++;
				$geraCmdFTP = true;
			}
			else $ContOk++;
		}
	}

	closedir($fh);
}

//---- main ------------------------------------------------------------------------
set_error_handler("myErrorHandler");
set_time_limit(60);
restore_error_handler();

if (Campo('ftp') !== false)
	$geraCmdFTP = true;

$aux = Campo('dchmod');
if (!empty($aux))
	$dirChmod = intval($aux, 8);
printf("#dirChmod=%o\n",$dirChmod);

$aux = Campo('fchmod');
if (!empty($aux))
	$fileChmod = intval($aux, 8);
printf("#fileChmod=%o\n",$fileChmod);

$aux = Campo('recursivo');
if ($aux == 'sim')
	$bRecursivo = true;

$dir = Campo('dir');
if (empty($dir))
{
	// Vai acessar todo diretorio default dos dados
	$last = strlen($DirDados) - 1;
	if ($DirDados{$last} == '/')
		$DirDados = substr($DirDados, 0, $last);

}
else if ($dir{0} == '/')
{
	// Vai acessar o diretorio absoluto passado
	if (@chdir($dir) === false)
	{
		$dir = $_SERVER["DOCUMENT_ROOT"].'/'.$dir;
		if (@chdir($dir) === false)
		{
			echo "\nDiretorio invalido: $dir\n";
			exit(1);
		}
	}
	$DirDados = ".";
}
else if ($dir{0} == '@')
{
	// Vai acessar o diretorio de configuracao
	$conf = substr($dir, 1);
	if ($conf == 'DIR_PRG')
	{
		$DirDados = '.';
		$bRecursivo = false;
	}
	else
	{
		$DirDados = Configuracao(substr($dir, 1),'',false);
		if (empty($DirDados))
		{
			echo "\nDiretorio de configuracao invalido: $dir\n";
			exit(1);
		}
		$last = strlen($DirDados) - 1;
		if ($DirDados{$last} == '/')
			$DirDados = substr($DirDados, 0, $last);

		if (@is_dir($DirDados) === false)
		{
			echo "\nDiretorio invalido: $DirDados\n";
			exit(1);
		}
	}
}
else
{
	// Vai acessar o diretorio relativo passado
	$last = strlen($DirDados) - 1;
	if ($DirDados{$last} != '/')
		$DirDados .= '/';
	$DirDados .= $dir;
	$stat = stat($DirDados);
	if (($stat['mode'] & 040000) != 040000)
	{
		echo "\nDiretorio invalido: $DirDados\n";
		exit(1);
	}
}

$subdir = Campo('subdir');
if (!empty($subdir))
	$DirDados .= "/$subdir";
echo "#Dir='$dir' ; #Subdir='$subdir' ; DirDados='$DirDados'\n";

/*
 *Prepara chamada do ftp.
 */
echo "pwd\n";

/*
 *Pesquisa todos os arquivos do diretorio.
 */
PermissaoArqs($DirDados);

/*
 *Finaliza chamada do ftp.
 */
echo "#OK $contChmod\n";
if ($ContOk > 0)
echo "#REALIZADOS $ContOk\n";
if (is_file("limpaDados.php"))
	echo "#LIMPADADOS\n";
if (is_file("limpaBoletos.php"))
	echo "#LIMPABOLETOS\n";
if (is_file("limpaIRanual.php"))
	echo "#LIMPAIRANUAL\n";
?>
