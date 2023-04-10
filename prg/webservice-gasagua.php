<?php
include "msg.php"; 

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache');
header('Access-Control-Allow-Origin: *');

$DirGasAgua = Configuracao('DIR_DADOS').'/gasagua';
$bSetTimeLimit = TRUE;
$aCondominios = array();

//----------------------------------------------------------------------------------
function myErrorHandler($type, $info, $hFile, $row)
{
	global $bSetTimeLimit;
	$bSetTimeLimit = FALSE;
//echo '#SEM set_time_limit()\n';
}

//----------------------------------------------------------------------------
function Login()
{
	GLOBAL $DirGasAgua, $aCondominios;

	$Usr = Campo('usr');
	if (empty($Usr))
		return false;
	$Pwd = Campo('pwd');
	if (empty($Pwd))
		return false;

	$bRet = false;
//echo "$DirGasAgua/usuarios.csv\n";
	$hFile = @fopen("$DirGasAgua/usuarios.csv", 'r');
	if ($hFile !== FALSE) {
		while (!feof ($hFile)) {
			$aReg = fgetcsv($hFile, 1024, ';');
//print_r($aReg);
			if (empty($aReg))
				continue;
			$aux = $aReg[0];
			if ($aux == $Usr && $aReg[4] == $Pwd)
			{
				// Autenticou usuario entao coleta sua lista de condominios e blocos
				$bRet = true;
				$codCondom = $aReg[1];
				$aCondominios[$codCondom] = array(trim($aReg[2]));
				while (!feof ($hFile)) {
					$aReg = fgetcsv($hFile, 1024, ';');
//print_r($aReg);
					if (empty($aReg) || $aReg[0] != $Usr)
						break;
					if ($codCondom == $aReg[1]) {
						if (array_search($aReg[2], $aCondominios[$codCondom]) === FALSE)
							$aCondominios[$codCondom][] = trim($aReg[2]);
					} else {
						$codCondom = $aReg[1];
						$aCondominios[$codCondom] = array(trim($aReg[2]));
					}
				}
				break;
			}
			if ($aux > $Usr)
				// Lista ordenada entao nao vai achar mais.
				break;
		}
		fclose($hFile);
	}
//echo "\n===== Login:\n"; print_r($aCondominios);

	return $bRet;
}

//----------------------------------------------------------------------------
function ListaPlanilhas($codCondom)
{
	GLOBAL $DirGasAgua;
	
	$aPlanilhas = array();
	$dir = sprintf("%s/%05d", $DirGasAgua, $codCondom);
	if (is_dir($dir)) {
		$PlanilhasGeradas = ' ';
		$dtAtual = date('Ym', mktime(0,0,0, date('m'), 1, date('y')));
		$dtAnt = date('Ym', mktime(0,0,0, date('m')-1, 1, date('y')));
		$aDir = scandir($dir);
		rsort($aDir);
//print_r($aDir);

		foreach ($aDir as $Planilha)
		{
			if (preg_match('/ '.substr($Planilha,0,-11).' /', $PlanilhasGeradas))
				continue;

			$pattern = sprintf('/[GA]%05d.{1,3}-%s\.csv$/', $codCondom, $dtAtual);
			$bTem = preg_match($pattern, $Planilha);
			if (!$bTem)
			{
				$pattern = sprintf('/[GA]%05d.{1,3}-%s\.csv$/', $codCondom, $dtAnt);
				$bTem = preg_match($pattern, $Planilha);
			}

			if ($bTem)
			{
//echo "[[ ACHOU planilha $Planilha ]]\n";
				$arq = "$dir/$Planilha";
				$hFile = @fopen($arq, 'r');
				if ($hFile === FALSE)
					continue;
				$aReg = fgetcsv($hFile, 1024, ';');
				fclose($hFile);
				if (empty($aReg) || $aReg[11] != 'N')
					// Planilha finalizada/fechada.
					continue;

				// Vai retornar planilha no formato:
				// $aPlanilhas[Nome_Arquivo] = {Nome_Condominio, Nome_Bloco}
				$aPlanilhas[$Planilha] = array($aReg[3], $aReg[4]);
				$PlanilhasGeradas .= substr($Planilha,0,-11).' ';

			}
//else echo "[[ NAO serve a planilha $Planilha ]]\n";
		}
	}

//echo "\n===== ListaPlanilhas:\n"; print_r($aPlanilhas);

	return $aPlanilhas;
}

//----------------------------------------------------------------------------
function BuscarCondominios()
{
	GLOBAL $aCondominios;

	echo 'condominios_callback({"Error":false, "Items":[';
	$ultCond = '';
	$sep = '';

	foreach (array_keys($aCondominios) as $codCondom) {
		if ($codCondom == $ultCond)
			continue;
		$ultCond = $codCondom;
		$aPlanilhas = ListaPlanilhas($codCondom);
		if (empty($aPlanilhas))
			// Este condominio nao possui planilhas
			continue;
		foreach ($aPlanilhas as $value) {
			// Pega nome do condominio no primeiro elemento.
			printf('%s{"Id":%d,"Nome":"%s"}', $sep, $codCondom, ISO8859_1toUTF8($value[0]));
			$sep = ',';
			break;
		}
	}

	echo ']});';
}

//----------------------------------------------------------------------------
function BuscarBlocos()
{
	GLOBAL $aCondominios;

	$codCondom = Campo('condominio');
	if (empty($codCondom) || empty($aCondominios) || !isset($aCondominios[$codCondom])) {
		echo 'blocos_callback({"Error":true, "Status":-1});';
		return;
	}

	$aBlocos = $aCondominios[$codCondom];
//print_r($aBlocos);
	if (empty($aBlocos)) {
		echo 'blocos_callback({"Error":true, "Status":-1});';
		return;
	}

	echo 'blocos_callback({"Error":false,"Condominio":'.$codCondom.',"Items":[';
	$sep = '';

	$aPlanilhas = ListaPlanilhas($codCondom);
	if (!empty($aPlanilhas)) {
		reset($aBlocos);
		foreach ($aBlocos as $codBloco) {
			$nomeBloco = '';
			reset($aPlanilhas);
			foreach ($aPlanilhas as $Planilha => $value) {
//echo "\n$codBloco) $Planilha => "; print_r($value);
				$auxBloco = explode('-', $Planilha);
				$auxBloco = trim(substr($auxBloco[0],6,3));
				if ($codBloco == $auxBloco) {
					$nomeBloco = $value[1];
					break;
				}
			}
			if (!empty($nomeBloco)) {
				// Este bloco nao possui planilhas
				printf('%s{"Id":"%s","Nome":"%s"}', $sep, $codBloco, ISO8859_1toUTF8($nomeBloco));
				$sep = ',';
			}
		}
	}
	
	echo ']});';
}

//----------------------------------------------------------------------------
function BuscarArquivos()
{
	$codCondom = Campo('condominio');
	$codBloco = Campo('bloco');
	if (empty($codCondom) || empty($codBloco)) {
		echo 'arquivos_callback({"Error":true, "Status":-1});';
		return;
	}
	
	printf('arquivos_callback({"Error":false,"Condominio":%d,"Bloco":"%s","Items":[', $codCondom, $codBloco);
	$sep = '';

	$aPlanilhas = ListaPlanilhas($codCondom);
	if (!empty($aPlanilhas)) {
		reset($aPlanilhas);
		foreach ($aPlanilhas as $Planilha => $value) {
			$auxBloco = explode('-', $Planilha);
			$auxBloco = trim(substr($auxBloco[0],6,3));
			if ($codBloco == $auxBloco) {
				$nome = ($Planilha[0] == 'G') ? 'Gas' : 'Agua';
				$arq = substr($Planilha, 0, strlen($Planilha)-4);
				printf('%s{"Id":"%s","Nome":"%s","Status":0}', $sep, $arq, ISO8859_1toUTF8($nome));
				$sep = ',';
			}
		}
	}

	echo ']});';
}

//----------------------------------------------------------------------------
function BuscarUnidades()
{
	GLOBAL $DirGasAgua;
	
	$codCondom = Campo('condominio');
	$codBloco = Campo('bloco');
	$Planilha = Campo('arquivo');
	if (empty($codCondom) || empty($codBloco) || empty($Planilha)) {
		echo 'unidades_callback({"Error":true, "Status":-1});';
		return;
	}

	$auxBloco = explode('-', $Planilha);
	$auxBloco = trim(substr($auxBloco[0],6,3));
	if ($codBloco != $auxBloco) {
		echo 'unidades_callback({"Error":true, "Status":-2});';
		return;
	}

	$arq = sprintf("%s/%05d/%s.csv", $DirGasAgua, $codCondom, $Planilha);
	$hFile = @fopen($arq, 'r');
	if ($hFile === FALSE) {
		// Planilha inexistente
		echo 'unidades_callback({"Error":true, "Status":2});';
		return;
	}
	if (!is_writable($arq)) {
		// Planilha sem permissao de escrita.
		echo 'unidades_callback({"Error":true, "Status":-3});';
		return;
	}

	$aReg = fgetcsv($hFile, 1024, ';');
	if (empty($aReg) || $aReg[0] != 'CA' || $aReg[1] != $codCondom || $aReg[2] != $codBloco) {
		echo 'unidades_callback({"Error":true, "Status":-4});';
		return;
	}
	if ($aReg[11] != 'N') {
		// Planilha finalizada/fechada.
		echo 'unidades_callback({"Error":true, "Status":1});';
		return;
	}

	printf('unidades_callback({"Error":false,"Condominio":%d,"Bloco":"%s","Arquivo":"%s","Items":[',
								$codCondom, $codBloco, $Planilha);
	$sep = '';

	while (!feof ($hFile)) {
		$aReg = fgetcsv($hFile, 1024, ';');
		if (!empty($aReg) && $aReg[0] == 'LE') {
			printf('%s"%d;%s;%s;%s"', $sep, $aReg[1], ISO8859_1toUTF8(str_replace(';',',',$aReg[2])), $aReg[3], $aReg[4]);
			$sep = ',';
		}
	}
	fclose($hFile);

	echo ']});';
}

//----------------------------------------------------------------------------
function AtualizaUnidade()
{
	GLOBAL $DirGasAgua;
	
	$codCondom = Campo('condominio');
	$codBloco = Campo('bloco');
	$Planilha = Campo('arquivo');
	$codUnidade = Campo('unidade');
	$valorUnidade = Campo('valor');
	if (empty($codCondom) || empty($codBloco) || empty($Planilha) || 
	    empty($codUnidade) || empty($valorUnidade)) {
		echo 'atualiza_callback({"Error":true, "Status":-1});';
		return;
	}

	$auxBloco = explode('-', $Planilha);
	$auxBloco = trim(substr($auxBloco[0],6,3));
	if ($codBloco != $auxBloco) {
		echo 'atualiza_callback({"Error":true, "Status":-2});';
		return;
	}

	$arq = sprintf("%s/%05d/%s.csv", $DirGasAgua, $codCondom, $Planilha);
	$hFile = @fopen($arq, 'r');
	if ($hFile === FALSE) {
		// Planilha inexistente
		echo 'atualiza_callback({"Error":true, "Status":2});';
		return;
	}
	if (!is_writable($arq)) {
		// Planilha sem permissao de escrita.
		echo 'atualiza_callback({"Error":true, "Status":-3});';
		return;
	}

	// Ler o arquivo
	$aReg = fgetcsv($hFile, 1024, ';');
	if (empty($aReg) || $aReg[0] != 'CA' || $aReg[1] != $codCondom || $aReg[2] != $codBloco) {
		echo 'atualiza_callback({"Error":true, "Status":-4});';
		return;
	}
	if ($aReg[11] != 'N') {
		// Planilha finalizada/fechada.
		echo 'atualiza_callback({"Error":true, "Status":1});';
		return;
	}

	$bAchouUnidade = false;
	$aRegs = array($aReg);
	while (!feof ($hFile)) {
		$aReg = fgetcsv($hFile, 1024, ';');
		if (empty($aReg))
			continue;
		if ($aReg[0] == 'LE' && $aReg[1] == $codUnidade) {
			if (!is_numeric($valorUnidade) || $valorUnidade < $aReg[4]) {
				// Valor invalido
				echo 'atualiza_callback({"Error":true, "Status":4});';
				return;
			}
			$bAchouUnidade = true;
			$aReg[7] = $valorUnidade;
		}
		$aRegs[] = $aReg;
	}

	fclose($hFile);

	if (!$bAchouUnidade) {
		echo 'atualiza_callback({"Error":true, "Status":3});';
		return;
	}

	// Regravar o arquivo
	$hFile = @fopen($arq, 'w');
	if ($hFile === FALSE) {
		// Planilha nao pode ser gravada
		echo 'atualiza_callback({"Error":true, "Status":-6});';
		return;
	}

	$bErro = FALSE;
	foreach ($aRegs as $aReg) {
		if (empty($aReg))
			break;
		if (fputcsv($hFile, $aReg, ';') === FALSE) {
echo "ERRO: fputcsv "; print_r($aReg);
			$bErro = TRUE;
			break;
		}
	}

	fclose($hFile);

	if ($bErro)
		echo 'atualiza_callback({"Error":true, "Status":-5});';
	else
		echo 'atualiza_callback({"Error":false});';
}

//----------------------------------------------------------------------------
function FechaArquivo()
{
	GLOBAL $DirGasAgua;
	
	$codCondom = Campo('condominio');
	$codBloco = Campo('bloco');
	$Planilha = Campo('arquivo');
	if (empty($codCondom) || empty($codBloco) || empty($Planilha)) {
		echo 'fechar_callback({"Error":true, "Status":-1});';
		return;
	}

	$auxBloco = explode('-', $Planilha);
	$auxBloco = trim(substr($auxBloco[0],6,3));
	if ($codBloco != $auxBloco) {
		echo 'fechar_callback({"Error":true, "Status":-1});';
		return;
	}

	$arq = sprintf("%s/%05d/%s.csv", $DirGasAgua, $codCondom, $Planilha);
	$hFile = @fopen($arq, 'r+');
	if ($hFile === FALSE) {
		// Planilha inexistente.
		echo 'fechar_callback({"Error":true, "Status":2});';
		return;
	}

	$sReg = fgets($hFile, 1024);
	$pos = strrpos($sReg, ';');
	if (empty($sReg) || substr($sReg,0,3) != 'CA;' || $pos === FALSE) {
		echo 'fechar_callback({"Error":true, "Status":-1});';
		return;
	}

	if ($sReg[++$pos] != 'N') {
		// Planilha finalizada/fechada.
		echo 'fechar_callback({"Error":true, "Status":1});';
		return;
	}

	if (!is_writable($arq) || fseek($hFile, $pos) != 0 || fwrite($hFile, 'S', 1) === FALSE)
		echo 'fechar_callback({"Error":true, "Status":-1});';
	else
		echo 'fechar_callback({"Error":false});';

	fclose($hFile);
}

/* DESATIVADO =========================================>>>
//----------------------------------------------------------------------------
function BuscarCondominios()
{
	if (empty($aCondominios)) {
		echo 'condominios_callback({"Error":true, "Status":-1});';
		return;
	}

	$hFile = @fopen($DirGasAgua.'/condominios.csv', 'r');
	if ($hFile === FALSE) {
		echo 'condominios_callback({"Error":true, "Status":-1});';
		return;
	}
	$aReg = fgetcsv($hFile, 1024, ';');

	echo 'condominios_callback({"Error":false, "Items":[';
	$sep = '';

	foreach (array_keys($aCondominios) as $codCondom) {
		$dir = sprintf("%s/%05d", $DirGasAgua, $codCondom);
		if (empty(ListaPlanilhas($codCondom)))
			// Este condominio nao possui planilhas
			continue;

		$nomeCondom = '';
		while (!empty($aReg)) {
			$auxCondom = $aReg[1];
			if ($auxCondom == $codCondom) {
				$nomeCondom = $aReg[2];
				break;
			}
			if ($auxCondom > $codCondom)
				// Lista ordenada entao nao vai achar mais.
				break;
			$aReg = fgetcsv($hFile, 1024, ';');
		}
		printf('%s{"Id":%d,"Nome":"%s"}', $sep, $codCondom, $nomeCondom);
		$sep = ',';
	}

	echo ']});';
	fclose($hFile);
}
//----------------------------------------------------------------------------
function BuscarBlocos()
{
	$codCondom = Campo('condominio');
	if (empty($codCondom) || empty($aCondominios) || !isset($aCondominios[$codCondom])) {
		echo 'blocos_callback({"Error":true, "Status":-1});';
		return;
	}

	$aBlocos = $aCondominios[$codCondom];
	if (empty($aBlocos)) {
		echo 'blocos_callback({"Error":true, "Status":-1});';
		return;
	}

	$hFile = @fopen($DirGasAgua.'/blocos.csv', 'r');
	if ($hFile === FALSE) {
		echo 'blocos_callback({"Error":true, "Status":-1});';
		return;
	}
	$aReg = fgetcsv($hFile, 1024, ';');

	echo 'blocos_callback({"Error":false,"Condominio":'.$codCondom.',"Items":[';
	$sep = '';

	$aPlanilhas = ListaPlanilhas($codCondom);
	if (!empty($aPlanilhas)) {
		foreach ($aBlocos as $codBloco) {
			if (empty(ListaPlanilhas($codCondom, $codBloco)))
				// Este condominio nao possui planilhas
				continue;

			$nomeBloco = '';
			while (!empty($aReg)) {
				$auxCondom = $aReg[1];
				if ($auxCondom == $codCondom) {
					$auxBloco = trim($aReg[2]);
					if ($auxBloco == $codBloco) {
						$arq = sprintf("%s/%05d", $DirGasAgua, $codCondom);
						if (is_dir($dir) {
							$nomeBloco = $aReg[3];
							break;
						}
					}
					if ($auxBloco > $codBloco)
						// Lista ordenada entao nao vai achar mais.
						break;
				}
				if ($auxCondom > $codCondom)
					// Lista ordenada entao nao vai achar mais.
					break;
				$aReg = fgetcsv($hFile, 1024, ';');
			}
			printf('%s{"Id":%d,"Nome":"%s"}', $sep, $codCondom, $nomeBloco);
			$sep = ',';
		}
	}
	
	echo ']});';
	fclose($hFile);
}
<<< DESATIVADO =======================================================*/

//---main---------------------------------------------------------------------
set_error_handler('myErrorHandler');
set_time_limit(60);
restore_error_handler();

$Oper = Campo('mode');

if ($Oper == 'login')
{
	if (Login())
		echo 'login_callback({"Error":false});';
	else
		echo 'login_callback({"Error":true});';
}
else if ($Oper == 'condominios')
{
	if (Login())
		BuscarCondominios();
	else
		echo 'condominios_callback({"Error":true, "Status":0});';
}
else if ($Oper == 'blocos')
{
	if (Login())
		BuscarBlocos();
	else
		echo 'blocos_callback({"Error":true, "Status":0});';
}
else if ($Oper == 'arquivos')
{
	if (Login())
		BuscarArquivos();
	else
		echo 'arquivos_callback({"Error":true, "Status":0});';
}
else if ($Oper == 'unidades')
{
	if (Login())
		BuscarUnidades();
	else
		echo 'unidades_callback({"Error":true, "Status":0});';
}
else if ($Oper == 'atualiza')
{
	if (Login())
		AtualizaUnidade();
	else
		echo 'atualiza_callback({"Error":true, "Status":0});';
}
else if ($Oper == 'fechar')
{
	if (Login())
		FechaArquivo();
	else
		echo 'fechar_callback({"Error":true, "Status":0});';
}
else
	echo 'login_callback({"Error":true});';

?>
