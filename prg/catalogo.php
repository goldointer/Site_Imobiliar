<?php
include "msg.php";

header('Content-type: text/plain');

$contArqs = 0;
$bRmdirOK = true;
$bRecursivo = false;
$bSetTimeLimit = true;

if(!function_exists('fnmatch')) {

    function fnmatch($pattern, $string) {
        return true;
    }

}

//----------------------------------------------------------------------------------
function myErrorHandler($type, $info, $file, $row)
{
	global $bSetTimeLimit;
	$bSetTimeLimit = false;
//echo "#SEM set_time_limit()\n";
}

//----------------------------------------------------------------------------------
function DirList($subdir)
{
	global $DirBase, $bSetTimeLimit, $contArqs;

#echo "#DirList();\n";
	$cont = 0;
	if ($bSetTimeLimit) set_time_limit(60);
	$fh = @opendir($subdir);
	if ($fh === false)
		return 0;

	while (false !== ($siteEntry = readdir($fh)))
	{
		if ($siteEntry{0} == '.')
			continue;
		if (!is_dir($siteEntry))
			continue;
		echo "Dir: $siteEntry\n";
		$contArqs++;

		if ((++$cont % 100) == 0)
			if ($bSetTimeLimit) set_time_limit(60);
	}
	closedir($fh);

	return $cont;
}
//----------------------------------------------------------------------------------
function Catalogo($subDir, $match, $nivel=0)
{
	global $DirBase, $bSetTimeLimit, $contArqs, $bRecursivo, $bRmdirOK;

#echo "#Catalogo('$subDir', '$match');\n";
	$cont = 0;
	if ($bSetTimeLimit) set_time_limit(60);
	$fh = @opendir($subDir);
	if ($fh === false)
		return 0;

	while (false !== ($siteEntry = readdir($fh)))
	{
		if ($siteEntry{0} == '.')
			continue;
		if ($subDir == '.')
			$file = $siteEntry;
		else
			$file = $subDir.'/'.$siteEntry;
		if (is_dir($file))
		{
			if ($bRecursivo)
				$cont += Catalogo($file, $match, $nivel+1);
			continue;
		}
		if (!is_file($file))
			continue;
			
		if (!empty($match))
		{
			$naocasou = true;
			foreach ($match as $mask)
			{
				if (fnmatch($mask, $siteEntry))
				{
					$naocasou = false;
					break;
				}
			}
			if ($naocasou)
				continue;
		}
		
		$infos = stat($file);
		$size = $infos[7];
		$mtime = $infos[9];
		echo "File: $file\nSize: $size\n";
		echo 'Date: '.$mtime.' ('.gmdate('Y-m-d H:i:s', $mtime).")\n\n";
		$contArqs++;

		if ((++$cont % 100) == 0)
			if ($bSetTimeLimit) set_time_limit(60);
	}

	closedir($fh);

	if ($cont == 0 && $bRmdirOK)
	{
		if ($nivel > 0)
			// Apagar subdiretorio vazio
			if (@rmdir($subDir) === false)
				$bRmdirOK = false;
	}
	
	return $cont;
}

//---- main ------------------------------------------------------------------------
set_error_handler("myErrorHandler");
set_time_limit(60);
restore_error_handler();

echo "#ORIGEM: catalogo.php\n";

$dir = Campo('dir');
if (empty($dir))
	$dir = Configuracao('DIR_DADOS');
else
{
	if ($dir[0] == '@')
	{	// Trata-se de referencia indireta para dir. de configuracao
		$conf = substr($dir,1);
		if ($conf == 'DIR_PRG')
			$dir = '.';
		else
		{
			$dir = Configuracao($conf, '', false);
			if (empty($dir))
			{
				echo("ERRO: parametro 'dir' com referencia inexistente (@$conf)\n");
				exit(1);
			}
		}
	}
}

$DirBase = $dir;
echo '#DIR: '.NormalizePath(dirname($_SERVER["SCRIPT_NAME"]).'/'.$dir)."\n";
$subdir = Campo('targetdir');
if (!empty($subdir))
{
	$dir .= '/'.$subdir;
	echo "#TARGETDIR: $subdir\n";
}
$segmdir = Campo('segmentdir');
if (empty($segmdir))
{
	$segmdir = '.';
	$nivel = 0;
}
else
{
	echo "#SEGMENTDIR: $segmdir\n";
	$nivel = 1;
}
if (is_dir($dir)) {
	$aux = Campo('recursivo');
	if ($aux == 'sim')
		$bRecursivo = true;

	echo '#RECURSIVO='.($bRecursivo?'sim':'nao')."\n";
	$ext = Campo('ext');
	$match = Campo('match');
	if (empty($match))
	{
		if (!empty($ext))
		{
			echo "#EXT: $ext\n";
			if (strstr($ext, '.') === false)
				$match = array(0 => "*.$ext");
			else
				$match = array(0 => "*$ext");
		}
	}
	else
	{
		if (!empty($ext))
		{
			echo("ERRO: parametros 'ext' e 'match' sao excludentes!\n");
			exit(1);
		}
		echo "#MATCH: $match\n";
		$match = explode('|', $match);
	}

	chdir($dir);
	echo '#CWD: '.getcwd()."\n";
	echo "\n";

	$oper = Campo('oper');
	if ($oper == 'dirlist')
		DirList($segmdir);
	else
		Catalogo($segmdir, $match, $nivel);
}

echo "#OK $contArqs\n";

if (is_file("limpaAnexos.php"))
	echo "#LIMPAANEXOS\n";
if (is_file("limpaBoletos.php"))
	echo "#LIMPABOLETOS\n";
if (is_file("limpaDados.php"))
	echo "#LIMPADADOS\n";
if (is_file("limpaIRanual.php"))
	echo "#LIMPAIRANUAL\n";
if (is_file("limpaLanctos.php"))
	echo "#LIMPALANCTOS\n";
?>
