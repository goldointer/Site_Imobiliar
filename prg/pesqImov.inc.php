<?php

define ("_LOCACAO", "L");
define ("_VENDA", "V");

//-----------------------------------------------------------------------------------
function MontaCidades(&$model, $lv, $CidadeDefault)
{
/*
Registro de cidade:
cidade      40;
cod_cidade   6;
NewLine;
*/
	GLOBAL $DirDados;

	$iTamReg = 46;
	$iCont = 0;
	$aCidades = array();

	if ($lv == _VENDA)
		$file = @fopen($DirDados."cidadesvenda.txt", "r");
	else
		$file = @fopen($DirDados."cidades.txt", "r");

	if ($file !== false) {
		while (!feof($file)) {
			$sReg = fgets($file, 1024);
			if (empty($sReg) || strlen($sReg) < $iTamReg+1)
				break;
			$Cidade = trim(strtoupper(substr($sReg, 0, 40)));
			$CodCid = trim(substr($sReg, 40, 6));
			$UF = trim(substr($sReg, 46, 2));
			$aCidades[] = array($Cidade, $CodCid, $UF);
			$iCont++;
		}
		fclose($file);
	}

	$Cidades_JS = "";
	$Selected = "";
	$iIdx = 0;

	if ($iCont != 1 && Configuracao("EXIBE_TODAS_CIDADES") == "SIM")
	{
		if ($model->TelaUnica != 2) {
			if (empty($CidadeDefault)) {
				$sel = " selected ";
				$Selected = "var Default$lv=0; <!-- '$CidadeDefault' -->\n";
			}
			else
                                $sel = '';
			$model->assign('VALOR', "::");
			$model->assign('OPCAO', "TODAS");
			$model->assign('SELEC', $sel);
			$model->parse('.CIDADES'); 
		}
		$Cidades_JS .= "'TODAS|::',";
		$iIdx++;
	}

	for ($i = 0; $i < $iCont; $i++) {
		$sel = " ";
		$Cidade = $aCidades[$i][0];
		$CodCid = $aCidades[$i][1];
		$UF = $aCidades[$i][2];
		$val = "$CodCid:$Cidade:$UF";
		if (empty($Selected)) {
			if (empty($CidadeDefault) || $Cidade == $CidadeDefault) {
				$sel = " selected ";
				$Selected = "var Default$lv=$iIdx; <!-- '$CidadeDefault' -->\n";
			}
		}
		if ($model->TelaUnica != 2) {
			$model->assign('VALOR', $val);
			$model->assign('OPCAO', trim($Cidade));
			$model->assign('SELEC', $sel);
			$model->parse('.CIDADES'); 
		}
		$Cidades_JS .= "'$Cidade|$val',";
		$iIdx++;
	}

	if (empty($Selected))
		$Selected = "var Default$lv=0;\n";

	if (empty($Cidades_JS)) {
		if ($model->TelaUnica != 2) {
			$model->assign('SELEC', ' selected ');
			$model->assign('VALOR', '');
			$model->assign('OPCAO', 'Nenhuma cidade dispon&iacute;vel');
			$model->parse('.CIDADES'); 
		}
	} else
		$Cidades_JS = substr($Cidades_JS, 0, strlen($Cidades_JS)-1);

	$Cidades_JS = "var Cidades = [$Cidades_JS];\n".$Selected;

	return $Cidades_JS;
}

//-----------------------------------------------------------------------------------
function PesqCidade($lv, $nome)
{
	GLOBAL $DirDados;

//echo "<!-- PesqCidade($lv, $nome) -->\n";

	if ($nome == '' && Configuracao("EXIBE_TODAS_CIDADES") == "SIM")
		return array('', '', '');

	$iTamReg = 46;
	$iCont = 0;

	if ($lv == _VENDA)
		$file = @fopen($DirDados."cidadesvenda.txt", "r");
	else
		$file = @fopen($DirDados."cidades.txt", "r");
	if ($file === false)
		return false;

	for(;;)
	{
		$sReg = fgets($file, 1024);
		if (empty($sReg) || strlen($sReg) < $iTamReg+1)
			break;
		$Cidade = trim(strtoupper(substr($sReg, 0, 40)));

		if (empty($nome) || strcmp($Cidade, $nome) == 0)
		{
			$CodCid = trim(substr($sReg, 40, 6));
			$UF = trim(substr($sReg, 46, 2));
			break;
		}
	}
	fclose($file);

	return array($CodCid, $Cidade, $UF);
}

//---------------------------------------------------------------------------------
function MontaBairros(&$model, $lv, $CodCidade)
{
/*
registro de bairro {
cod_bairro  5;
bairro     60;
cod_cidade  6;
NewLine;
}
*/
	GLOBAL $DirDados;
	$iTamReg = 71;

//echo "<!-- MontaBairros($lv, $CodCidade)\n";

	if ($lv == _VENDA)
		$Arq = 'bairros_venda.txt';
	else if ($lv == _LOCACAO)
		$Arq = 'bairros_loc.txt';
	else
		$Arq = 'bairros.txt';
	$file = @fopen($DirDados.$Arq, 'r');

//echo "Arq='$DirDados$Arq'\n";

	$contBairro = 0;
	$Bairros = array();
	if ($file !== false)
	{
		while(!feof($file))
		{
			$sReg = fgets($file, 1024);
			if (empty($sReg) || strlen($sReg) < $iTamReg+1)
				break;
			$CodB = trim(substr($sReg, 0, 5));
			$Bairro = trim(substr($sReg, 5, 60));
			$CodCid = trim(substr($sReg, 65, 6));
			if (!isset($Bairros[$CodCid]))
				$Bairros[$CodCid] = array();
			$Bairros[$CodCid][] = $Bairro."|".$CodB;
//echo "[$CodCid]=$CodB|$Bairro\n";

			if ($CodCid == $CodCidade)
			{
				$contBairro++;
				if ($model->TelaUnica != 2) {
					$model->assign('BVALOR', $CodB);
					$model->assign('BOPCAO', $Bairro);
					$model->assign('SELEC', '');
					$model->parse('.BAIRROS'); 
				}
			}
		}
		fclose($file);
	}

//print_r($Bairros);
	$Cidades_JS = 'var Cidades = [';
	$Bairros_JS = 'var Bairros = [';

	if ($contBairro == 0 && $model->TelaUnica != 2) {
		$model->assign('BVALOR', ' ');
		$model->assign('BOPCAO', ' ');
		$model->assign('SELEC', ' selected ');
		$model->parse('.BAIRROS'); 
	}

	$lastCid = "";
	$aux = "";
	foreach($Bairros AS $CodCid => $Lista)
	{
		$Cidades_JS .= "$CodCid,";
		if ($lastCid != '')
			$Bairros_JS .= '['.substr($aux, 0, strlen($aux)-1)."],\n";
		$lastCid = $CodCid;
		$aux = "'- QUALQUER BAIRRO -|- QUALQUER BAIRRO -',";

		foreach($Lista AS $Bairro)
			$aux .= "'". str_replace("'", "\\'", $Bairro)."',";
	}
	$Bairros_JS .= "[".substr($aux, 0, strlen($aux)-1)."]";
	if (!empty($lastCid))
		$Cidades_JS = substr($Cidades_JS, 0, strlen($Cidades_JS)-1);

	$Bairros_JS .= "];\n";
	$Cidades_JS .= "];\n";

//print_r($Cidades_JS);
//print_r($Bairros_JS);
//echo "-->\n";
	return array($Cidades_JS, $Bairros_JS);
}

//----------------------------------------------------------------------------------
function MontaTipos(&$model, $lv, $ResCom)
{
/*
registro de tipos {
cod         4;
descr      30;
com_res     1;
tem_dormit  1;
tem_garagem 1;
NewLine;
}
*/
	GLOBAL $DirDados;

//echo "<!-- MontaTipos(..., $lv, $ResCom)\n";
	$iTamReg = 36;
	$iCont = 0;
	$TiposR_JS = "var TiposR = [";
	$TiposC_JS = "var TiposC = [";
	$bPesqGaragem = (Configuracao("PESQUISA_GARAGEM") == "SIM");

	if (Configuracao("EXIBE_TODOS_TIPOS") == "SIM")
	{
		$Aux = ' ';
		$Descr = '- QUALQUER TIPO -';
		$model->assign('TVALOR', $Aux);
		$model->assign('TOPCAO', $Descr);
		$model->assign('SELEC', ' selected ');
		$model->parse('.TIPOS'); 
		$TiposR_JS .= "'$Descr|$Aux',";
		$TiposC_JS .= "'$Descr|$Aux',";
		$iSelected = 1;
		$iCont = 1;
	}
	else
	{
		$iSelected = 0;
		$iCont = 0;
	}

	if ($lv == _VENDA)
		$Arq = "tipo_venda.txt";
	else if ($lv == _LOCACAO)
		$Arq = "tipo_loc.txt";
	else
		$Arq = "tipo_imov.txt";

//echo "Arq='$DirDados$Arq'\n";

	$file = @fopen($DirDados.$Arq, "r");
	if ($file === false)
		return "";

	for (;;$iCont++)
	{
		$sReg = fgets($file, 1024);
		if (empty($sReg) || strlen($sReg) < $iTamReg+1)
			break;

//echo "$sReg\n";
		$TComRes = substr($sReg, 34, 1);
		$Cod = trim(substr($sReg, 0, 4));
		$Descr = trim(substr($sReg, 4, 30));
		$Dormit = substr($sReg, 35, 1);
		$Garagem = substr($sReg, 36, 1);
		if ($Dormit == "S")
			$Descr = $Descr." -->";
		$Val = $TComRes.$Cod;
		if ($bPesqGaragem && $Garagem == 'S')
			$Val .= 'G';

		if ($TComRes == "R")
			$TiposR_JS .= "'". str_replace("'", "\\'", $Descr."|".$Val)."',";
		else
			$TiposC_JS .= "'". str_replace("'", "\\'", $Descr."|".$Val)."',";

		if ($ResCom == '*' || $TComRes == $ResCom)
		{
			$model->assign('TVALOR', $Val);
			$model->assign('TOPCAO', $Descr);
			if ( $iSelected == 0)
			{
				$model->assign('SELEC', " selected ");
				$iSelected = 1;
			}
			else
				$model->assign('SELEC', "");
			$model->parse('.TIPOS');
		}
	}

	fclose($file);

	if ($iCont == 0)
	{
		$model->assign('TVALOR', ' ');
		$model->assign('TOPCAO', ' ');
		$model->assign('SELEC', ' selected ');
		$model->parse('.TIPOS'); 
	}
	else
	{
		$TiposR_JS = substr($TiposR_JS, 0, strlen($TiposR_JS)-1);
		$TiposC_JS = substr($TiposC_JS, 0, strlen($TiposC_JS)-1);
	}
//echo "-->\n";

	return $TiposR_JS."];\n".$TiposC_JS."];\n";
}

//----------------------------------------------------------------------------------
function MontaCaracs(&$model)
{
/*
registro de caracteristicas {
cod        4;
descr     25;
pq         1;
desc_unid 10;
NewLine;
}
*/
	// Apaga campo nas telas antigas
	$model->assign('COD', '');
	$model->assign('DESCR', '');
	$model->parse('.CARAC'); 
}

//--------------------------------------------------------------------------------
function BuscaSituacao($Situacao)
{
	GLOBAL $DirDados;
	static $handle = false;

	if ($handle === false)
	{
		$file = $DirDados.'obscomerc/situacaoimo.txt';
		if (!file_exists($file))
			return $Situacao;
		$stat = stat($file);
		if ($stat === false || $stat['size'] <= 0 || ($handle=fopen($file, 'r')) === false)
			return $Situacao;
	}
	else
		rewind($handle);

	while (!feof ($handle))
	{
		$buffer = fgets($handle, 4098);
		if (empty($buffer))
			break;

		if (substr($buffer,0,3) == $Situacao)
		{
			$Situacao = substr($buffer,3);
			break;
		}
	}

	if (!empty($Situacao))
		$Situacao = '<span class="situacao_imovel">'.$Situacao.'</span>';

	return $Situacao;
}

?>
