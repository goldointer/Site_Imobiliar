<?php
include 'msg.php';

header('Content-type: text/plain');

$simular = false;
$DirBoletos = Configuracao('DIR_BOLETOS');
$MesesNoSiteBoleto = Configuracao('MESES_NO_SITE_BOLETOS', Configuracao('MESES_NO_SITE', 2));
$MesesNoSiteBoletoLoc = Configuracao('MESES_NO_SITE_BOLETOS_LOC', $MesesNoSiteBoleto) + 2;
$MesesNoSiteBoletoCond = Configuracao('MESES_NO_SITE_BOLETOS_COND', $MesesNoSiteBoleto) + 2;
$MesesNoSiteBoleto += 2;
$geraCmdFTP = false;
$bSetTimeLimit = true;
$maxDel = Configuracao('LIMPEZA_MAX_BOLETOS', 1000);
if ($geraCmdFTP) $maxDel /= 10;
$contDel = 0;

//----------------------------------------------------------------------------------
function myErrorHandler($type, $info, $file, $row)
{
	global $bSetTimeLimit;
//	$bSetTimeLimit = false;
}
//--------------------------------------------------------------------------------
//	Obtem o campo do arquivo de boleto
//--------------------------------------------------------------------------------
function GetFields($file, $aFields)
{
	$aValues = array();
	if (($handle = fopen ($file, 'r')) === false)
		return $aValues;

	while (!empty($aFields) && !feof($handle))
	{
		$buffer = fgets($handle, 1024);
		if (empty($buffer))
			break;

		$pos = strpos($buffer, '=');
		if (empty($pos))
			continue;

		$field = substr($buffer, 0, $pos);
		$idx = array_search($field, $aFields);
		if ($idx === false)
			continue;
		$val = trim(substr($buffer, $pos+1));
		$aValues[$field] = $val;
		unset($aFields[$idx]);
	}

	foreach($aFields as $field)
		// Campo inexistente no arquivo.
		$aValues[$field] = '';

	fclose($handle);
	return $aValues;
}
//--------------------------------------------------------------------------------
//	Verifica se o boleto esta vencido pelo campo "IsDataVenc" do arquivo.
//--------------------------------------------------------------------------------
function BoletoAntigoOuCancelado($arqBoleto, $dataHoraAgora, $dias, $meses)
{
	global $simular;
//echo "#BoletoAntigoOuCancelado($arqBoleto, $dataHoraAgora, $dias, $meses)\n";

	$aValues = GetFields($arqBoleto, array('IsDataVenc','IsDataPagamento','IsDebConta','IsCodBarra1'));

	// Se for quitado entao deve permanecer.
	if (!empty($aValues['IsDataPagamento']))
		return false;
	if (empty($aValues['IsCodBarra1']) && stristr($aValues['IsDebConta'], 'Quitado') !== false)
		return false;

	$vencido = true;
	$IsDataVenc = $aValues['IsDataVenc'];
	if (!empty($IsDataVenc))
	{
		// Tem informacao de vencimento.
		if ($IsDataVenc == '01/01/1900')
		{
			// Foi cancelado.
			if ($simular)
				print(sprintf("#Vencimento: %s (CANCELADO)       | REMOVER arquivo: %s \n", $IsDataVenc, $arqBoleto));
		}
		else
		{
			list ($dia, $mes, $ano) = explode('/', $IsDataVenc);
			//echo " V $IsDataVenc $dia,$mes,$ano";
			if (checkdate($mes,$dia,$ano))
			{
				// Data de vencto esta' no formato certo.
				$dia += $dias;
				$mes += $meses;
				while ($mes > 12) { $mes -= 12; $ano++; }
				$validade = mktime(0,0,0,$mes,$dia,$ano);
				//echo " [$dia,$mes,$ano=".date('d/m/Y',$validade)."]";
				if ($validade > $dataHoraAgora)
				{
					// Este arquivo deve permanecer.
					if ($simular)
						print(sprintf("#Vencimento: %s (ate' %02d/%02d/%04d) | MANTER  arquivo: %s \n", $IsDataVenc, $dia, $mes, $ano, $arqBoleto));
					$vencido = false;
				}
				else
				{
					if ($simular)
						print(sprintf("#Vencimento: %s (ate' %02d/%02d/%04d) | REMOVER arquivo: %s \n", $IsDataVenc, $dia, $mes, $ano, $arqBoleto));
				}
			}
			//else echo "# [DATA INVALIDA]";
			//echo "\n";
		}
	}
	return $vencido;
}
//----------------------------------------------------------------------------------
function limpaBoletos($dir, $dias, $todosextras, $nivel=0)
{
	global $bSetTimeLimit, $geraCmdFTP, $contDel, $maxDel, $simular;
	global $MesesNoSiteBoletoLoc, $MesesNoSiteBoletoCond;

//echo "##limpaBoletos($dir,$dias,$todosextras,$nivel)\n";

	/* Ajusta periodo a limpar */
	if (empty($dias))
	{
		$dias = 0;
//echo "\n#MesesNoSiteBoletoLoc=$MesesNoSiteBoletoLoc; MesesNoSiteBoletoCond=$MesesNoSiteBoletoCond\n";
	}
	else
	{
//echo "Dias no site=$dias\n";
		$meses = 0;
	}
	$agora = time();
	$extra_maxfuturo = strtotime('+2 months');
//echo "#Agora=".date('d/m/Y',$agora)."\n";
//echo '#Max.Futuro='.date('d/m/Y',$extra_maxfuturo)."\n";

	/* Remove todos os DOCs desejados do diretorio. */
	if (!is_dir($dir))
		return 0;

	$fh = opendir($dir);
	if ($fh === false)
		return 0;

	$cont = 0;
	if ($bSetTimeLimit) set_time_limit(60);

	while (false !== ($dirEntry = readdir($fh)))
	{
		if ($contDel >= $maxDel)
			break;

		if ($dirEntry == '.' || $dirEntry == '..')
			continue;

		if ((++$cont % 100) == 0)
			if ($bSetTimeLimit) set_time_limit(60);

		if (strstr($dirEntry, '.sempre.txt') == '.sempre.txt')
			// Nome especial que indica ser sempre valido
			continue;

		$file = $dir.$dirEntry;
		if (is_dir($file))
		{
			if (limpaBoletos($file.'/',$dias,$todosextras,$nivel+1) == 0)
				$cont--;
			continue;
		}

		if (strstr($dirEntry, '.txt') != '.txt')
			continue;

		$stat = @stat($file);
		if ($stat === false || $stat['size'] <= 0)
			continue;

//echo "\n#$file -> ";
		// Vai limpar tudo se dias for negativo.
		if ($dias >= 0)
		{
			if ($dias == 0)
			{
				if (substr($file, 0, 1) == "L")
					$meses = intval($MesesNoSiteBoletoLoc);
				else
					$meses = intval($MesesNoSiteBoletoCond);
			}

			$competencia = strstr($dirEntry, 'E');
			if (empty($competencia))
			{
				// Limpar apenas DOCs normais que forem mais antigos que os dias especificados.
				$competencia = strstr($dirEntry, 'N');
				if (!empty($competencia))
				{
					// Tem multiplos DOCs entao limpar olhando a competencia no nome.
					$ano = substr($competencia, 1, 4);
					$mes = substr($competencia, 5, 2);
					$dia = 28;
//echo " M $competencia $dia,$mes,$ano";
					if (checkdate($mes,$dia,$ano))
					{
						// Nome do arquivo esta' no formato de multiplos DOCs.
						$sDataVenc = mktime(0,0,0,$mes,$dia,$ano);
						$dia += $dias;
						$mes += $meses;
						while ($mes > 12) { $mes -= 12; $ano++; }
						$validade = mktime(0,0,0,$mes,$dia,$ano);
//echo " [$dia,$mes,$ano=".date('d/m/Y',$validade)."]";
						if ($validade > $agora)
							// Este arquivo esta' dentro da competencia.
							$competencia = '';
					}
				}

				if (empty($competencia))
				{
					if (!BoletoAntigoOuCancelado($file, $agora, $dias, $meses))
						continue; // Este arquivo deve permanecer.
					else
					{
						if ($simular)
							continue; // Nao remove arquivo, apenas para testes
					}
				}
			}
			else if (!$todosextras)
			{
				// Limpar apenas DOCs extras que forem mais antigos que os dias especificados
				// ou cujo vencimento seja mais de 2 meses adiante.
				$ano = substr($competencia, 1, 4);
				$mes = substr($competencia, 5, 2);
				$dia = substr($competencia, 7, 2);
//echo " E $competencia $dia,$mes,$ano";
				if (checkdate($mes,$dia,$ano))
				{
					// Nome do arquivo esta' no formato de DOC extra.
					$sDataVenc = mktime(0,0,0,$mes,$dia,$ano);
					$dia += $dias;
					$mes += $meses;
					while ($mes > 12) { $mes -= 12; $ano++; }
					$validade = mktime(0,0,0,$mes,$dia,$ano);
//echo " [$dia,$mes,$ano=".date('d/m/Y',$validade)."]\n";
					if ($validade > $agora && $sDataVenc < $extra_maxfuturo)
						// Este arquivo deve permanecer.
						$competencia ='';

					if (empty($competencia))
					{
						if (!BoletoAntigoOuCancelado($file, $agora, $dias, $meses))
							continue; // Este arquivo deve permanecer.
						else
						{
							if ($simular)
								continue; // Nao remove arquivo, apenas para testes
						}
					}
				}
//else echo " [DATA INVALIDA]";
//echo "\n";
			}
		}

		// Apagar este arquivo.
		$contDel++;
		$cont--;
		if (!empty($geraCmdFTP) || @unlink($file) === false)
		{
			echo "del $file\n";
			flush();
			if (empty($geraCmdFTP))
			{
				$geraCmdFTP = true;
				$maxDel /= 10;
			}
		}
//else echo "# $file\n";
	}

	closedir($fh);
	if ($cont == 0 && $nivel > 0)
	{
		// Apagar diretorio vazio
		$contDel++;
		if (!empty($geraCmdFTP) || @rmdir($dir) === false)
		{
			echo "rmdir $dir\n";
			flush();
			if (empty($geraCmdFTP))
			{
				$geraCmdFTP = true;
				$maxDel /= 10;
			}
		}
	}

	return $cont;
}

//---- main ------------------------------------------------------------------------------
set_error_handler('myErrorHandler');
set_time_limit(60);
restore_error_handler();

if (substr($DirBoletos, 0, 2) == './')
	$DirBoletos = substr($DirBoletos, 2);
echo "#$DirBoletos\n";
echo "#MesesNoSiteBoletoLoc=$MesesNoSiteBoletoLoc\n";
echo "#MesesNoSiteBoletoCond=$MesesNoSiteBoletoCond\n";

//limpaBoletos($DirBoletos.'5663',0,false);
//limpaBoletos($DirBoletos.'6615',60,false);
//echo "#OK $contDel\n";
//exit;

$val = Campo('simular');
if (!empty($val))
	$simular = true;

$dias = Campo('limpartudo');
if (!empty($dias))
	$dias = -1;
else
{
	$todosextras = Campo('limpartodosextras');
	$dias = Campo('dias');
	if (!empty($dias))
	{
		$dias = intval($dias);
		if ($dias <= 0)
		{
			echo "#ERRO no parametro dias\n";
			exit;
		}
	}
}
$todosextras = !empty($todosextras);

/* Prepara chamada do FTP. */
echo "pwd\n";
flush();

/* Pesquisa todos os usuarios online. */
limpaBoletos($DirBoletos,$dias,$todosextras);
echo "#DEL $contDel\n";

/* Limpa diretorios obsoletos */
$dirbase = dirname($DirBoletos);
foreach (array('bloquetos','bloquetos0','bloquetos1','bloquetos2') as $dir)
{
	if ($contDel >= $maxDel)
		break;

	$dir = $dirbase.'/'.$dir.'/';
	if (!is_dir($dir) || $dir == $DirBoletos)
		continue;
	echo "#$dir\n";
	limpaBoletos($dir,-1,true);

	// Apagar diretorio vazio
	$contDel++;
	if (!empty($geraCmdFTP) || @rmdir($dir) === false)
	{
		echo "rmdir $dir\n";
		flush();
		if (empty($geraCmdFTP))
		{
			$geraCmdFTP = true;
			$maxDel /= 10;
		}
	}
}

/* Finaliza chamada do FTP. */
echo "#OK $contDel\n";
?>
