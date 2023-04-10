<?php
include 'msg.php';

$DirDados = Configuracao('DIR_DADOS');
$bSetTimeLimit = true;
$fileChmod = 0666;
$dirChmod = 0777;

//----------------------------------------------------------------------------------
function myErrorHandler($type, $info, $file, $row)
{
	global $bSetTimeLimit;
	$bSetTimeLimit = false;
//echo "#SEM set_time_limit()\n";
}

//---- main ------------------------------------------------------------------------
set_error_handler("myErrorHandler");
set_time_limit(60);
restore_error_handler();

ini_set("safe_mode", "0");

header("Content-type: text/plain");
$arqIn = Campo('arq');
if (empty($arqIn))
{
	echo "\nArquivo nao especificado!\n";
	exit(1);
}

$gz = Campo('gz');
$gz = !empty($gz);
echo "#Arquivo: '$arqIn' (".($gz?'':'un')."compressed)\n";

$aux = Campo('fchmod');
if (!empty($aux))
{
	$fileChmod = intval($aux, 8);
	printf("#fileChmod=%o\n",$fileChmod);
	$aux = Campo('dchmod');
	if (empty($aux))
	{
		echo "\nFaltou 'dchmod'!\n";
		exit(1);
	}
	$dirChmod = intval($aux, 8);
	printf("#dirChmod=%o\n",$dirChmod);
}
else 
{
	$aux = Campo('dchmod');
	if(!empty($aux))
	{
		echo "\nFaltou 'fchmod'!\n";
		exit(1);
	}
}

$fOut = false;
$stat = @stat($arqIn);
if ($stat === false)
{
	echo "\nArquivo $arqIn nao existe!\n";
	exit(1);
}
if ($gz === false)
	$fIn = fopen($arqIn, "r");
else
	$fIn = gzopen($arqIn, "r");
if ($fIn === false)
{
	echo "\nArquivo $arqIn nao abriu!\n";
	exit(1);
}

chdir($DirDados);
chdir('..');

for ($cont=0;;)
{
	if ($gz === false)
		$sReg = fgets($fIn, 16384);
	else
		$sReg = gzgets($fIn, 16384);
	if (empty($sReg))
	{
		echo "\nArquivo $arqIn corrompido!\n";
		exit(1);
	}
//echo "REG=$sReg";
//echo "(".ord($sReg)." ".ord(substr($sReg,1))." ".ord(substr($sReg,2))." ".ord(substr($sReg,3)).")\n";

	if (ord($sReg) == 2 && ord(substr($sReg,1)) == 3 && 
		ord(substr($sReg,2)) == 4 && ord(substr($sReg,3)) == 5)
	{
		if ($fOut !== false)
		{
			fclose($fOut);
			@chmod($arqOut, $fileChmod);
			$fOut = false;
		}
		$arqOut = substr($sReg, 4, strlen($sReg)-5);
		if ($arqOut{0} == '#' && ord(substr($arqOut,1)) == 2 && ord(substr($arqOut,2)) == 3 && 
			ord(substr($arqOut,3)) == 4 && ord(substr($arqOut,4)) == 5)
		{
			// Marca de Fim de Arquivo
			break;
		}
		$dir = dirname($arqOut);
		if (!empty($dir) && !file_exists($dir))
		{
//echo "NEW DIR=$dir\n";
			$adir = explode('/',$dir);
			foreach($adir as $val)
			{
				$subdir .= $val.'/';
				if (file_exists($subdir))
					continue;
				if (@mkdir($subdir) === false)
				{
					echo "\n#SEM PERMISSAO para criar dir '$subdir'\n";
					exit;
				}
				@chmod($dir, $dirChmod);
			}
		}
//echo "OUT=$arqOut\n";
		$fOut = fopen($arqOut, "w");
		if ($fOut === false)
		{
			echo "\n#SEM PERMISSAO para criar arq '$arqOut'\n";
			exit;
		}

		if ((++$cont % 100) == 0)
			if ($bSetTimeLimit) set_time_limit(60);

		continue;
	}

	if ($fOut === false)
	{
		echo "\nNao criou arquivo de saida $arqOut!\n";
		exit(1);
	}

	if (fputs($fOut, $sReg) === false)
	{
		echo "\nNao gravou no arquivo de saida $arqOut!\n";
		exit(1);
	}
}

if ($fOut !== false)
{
	fclose($fOut);
	@chmod($arqOut, $fileChmod);
}

if ($gz === false)
	fclose($fIn);
else
	gzclose($fIn);

echo "\n#OK $cont\n";

if (is_file("limpaAnexos.php"))
	echo "#LIMPAANEXOS\n";
if (is_file("limpaBoletos.php"))
	echo "#LIMPABOLETOS\n";
if (is_file("limpaDados.php"))
	echo "#LIMPADADOS\n";
if (is_file("limpaIRanual.php"))
	echo "LIMPAIRANUAL\n";
if (is_file("limpaLanctos.php"))
	echo "#LIMPALANCTOS\n";
?>
