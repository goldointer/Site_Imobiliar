<?php
include 'msg.php';
include 'fotos.inc.php';

header('Content-Type: text/html; charset=ISO-8859-1');

define ('_LOCA', 'L');
define ('_VENDA', 'V');

$DirDados = Configuracao('DIR_DADOS');
$DirFotos = Configuracao('DIR_FOTOS');
$DirModelos = Configuracao('DIR_MODELOS_PESQUISA');
$DirImagens = Configuracao('DIR_IMAGENS');

//--------------------------------------------------------------------------------
function TrataFaixa($Val)
{
	$Val = str_replace('.', '' , $Val);
	$iPos = strpos($Val, ',');
	if ($iPos !== false)
		$Val = substr($Val, 0, $iPos);
	return $Val;
}

//--------------------------------------------------------------------------------
function NumberFormat($iNumber, $iDecimals, $sDec_point, $sThousands_sep)
{
	$iNumber = str_replace('.', '' , $iNumber);
	$iNumber = str_replace(',', '.' , $iNumber);
	return number_format($iNumber, $iDecimals, ',', '.');
}

//---------------------------------------------------------------------------------
function PesqCodBairro($lv, $CodBairro)
{
	GLOBAL $DirDados;
	$iTamReg = 71;

	if ($lv == _VENDA)
		$file = @fopen($DirDados."bairros_venda.txt", "r");
	else
		$file = @fopen($DirDados."bairros_loc.txt", "r");
	if ($file === false)
		$file = @fopen($DirDados."bairros.txt", "r");
	$CodBairro = intval($CodBairro);

	if ($file !== false)
	{
		while(!feof($file))
		{
			$sReg = fgets($file, 1024);
			if (empty($sReg) || strlen($sReg) < $iTamReg+1)
				break;
			$CodB = intval(trim(substr($sReg, 0, 5)));

			if ($CodB == $CodBairro)
			{
				$Bairro = trim(substr($sReg, 5, 60));
				$CodCid = trim(substr($sReg, 65, 6));
				break;
			}
		}
		fclose($file);
	}

	return array($CodBairro, $Bairro, $CodCid);
}

//----------------------------------------------------------------------------------
function PesqCaracs($carac, $chvcaract)
{
/*
registro caracteristicas do imovel{
chave      8;
qtde       6;
compl     10;
cod        4;
descr     25;
pq         1;
desc_unid 10;
tipo_carac 3;
NewLine;
}
*/
	GLOBAL $DirDados;
	static $lchvpos, $lastchv = -1;

	$ret = 0;
	$iTamReg = 64;
	$chvcaract = intval($chvcaract);

	$file = @fopen($DirDados.'caract_imo.txt', 'r');
	if ($file !== false)
	{
		if ($lastchv != $chvcaract)
		{
			$lastchv = $chvcaract;
			fseek ($file, 0, SEEK_SET);
			$lchvpos = -1;
			$lpos = 0;
		}
		else
			fseek ($file, $lchvpos, SEEK_SET);

		for(;;)
		{
			$sReg = fgets($file, 1024);
			if (empty($sReg) || strlen($sReg) < $iTamReg+1)
				break;
			$Chave = intval(trim(substr($sReg, 0, 8)));
			if ($Chave != $chvcaract)
			{
				if ($lchvpos == -1)
					continue;
				else
					break;
			}
			if ($lchvpos == -1)
			{
				$lpos = fseek($file, 0, SEEK_CUR);
				$lchvpos = $lpos;
			}
	//		if (trim(substr($sReg, 24, 4)).';' == trim($carac))
			$car = sprintf('%04d;', (int)substr($sReg, 24, 4));
			if (strpos($carac, $car) !== false)
			{
				$ret = 1;
				break;
			}
		}
		fclose($file);
	}

	return $ret;
}

//----------------------------------------------------------------------------------
/*
registro de tipos {
cod         4;
descr      30;
ResCom     1;
tem_dormit  1;
NewLine;
}
*/
function PesqTipo($ResCom, $Cod)
{
	GLOBAL $DirDados;

	$iTamReg = 36; //Tamanho do registro do arquivo tipo_imov.txt

	$file = fopen($DirDados.'tipo_imov.txt', 'r');
	if ($file === FALSE)
		return 0;

//echo "<!-- $ResCom/$Cod? ";
	$iSelected = 0;
	fseek ($file, 0);
	for(;;)
	{
		$sReg = fgets($file, 1024);
		if (empty($sReg) || strlen($sReg) < $iTamReg+1)
			break;
		$AuxTipo = substr($sReg, 34, 1);
		$AuxCod = intval(trim(substr($sReg, 0, 4)));
//echo "$AuxTipo/$AuxCod ";
		if ($AuxTipo != $ResCom || $AuxCod != $Cod)
			continue;
		$Descr = substr($sReg, 4, 30);
		$bDormit = (substr($sReg, 35, 1) == 'S');
		$bGaragem = (substr($sReg, 36, 1) == 'S');
		fclose($file);
//echo "-->\n";
		return array(trim($AuxTipo.$Cod) => array($Descr, $bDormit, $bGaragem));
	}
	fclose($file);
//echo "-->\n";

	return array($Tipo => array("?", false, false));
}

//----------------------------------------------------------------------------------
function ListaTipos($ResCom)
{
	GLOBAL $DirDados;
	$iTamReg = 36;
	$ListaTipos = array();

	$file = fopen($DirDados.'tipo_imov.txt', 'r');
	if ($file !== FALSE)
	{
		for(;;)
		{
			$sReg = fgets($file, 4098);
			if (empty($sReg) || strlen($sReg) < $iTamReg+1)
				break;
			$TComRes = substr($sReg, 34, 1);
			if ($ResCom != '*' && $TComRes != $ResCom)
				continue;

			$Cod = substr($sReg, 0, 4);
			$Descr = substr($sReg, 4, 30);
			$bDormit = (substr($sReg, 35, 1) == 'S');
			$bGaragem = (substr($sReg, 36, 1) == 'S');
			$Idx = trim($TComRes.$Cod);
			$ListaTipos[$Idx] = array($Descr, $bDormit, $bGaragem);
		}
		fclose($file);
	}

	return $ListaTipos;
}

//----------------------------------------------------------------------------------
/*
registro de imovel{
cod            8;
tipo           2;
cod_cidade     6;
cod_bairro    60; // deveria ser o codigo do bairro
regiao         3;
endereco      85;
imediacao     30;
qtdedormit     2;
nome_predio   30;
valor         12;
val_cond      12;
val_iptu      12;
situacao       3;
ResCom         1;
chavecarac     8;
area util      8;
tam tipo logr  2;
tam logr       2;
tam nro/compl  2;
cep            8;
vagas estacion 3;
area total     8;
NewLine;
} 
*/

//-----------------------------------------------------------------------------------

$bResizeOK = false;
$Img_Width = Configuracao('LARGURA_THUMBNAILS_PESQUISA', 100);
$exibeThumbNails = (Configuracao('EXIBE_THUMBNAILS') == 'SIM');
if ($exibeThumbNails)
{
	$thumb = 'Thumb';
	if (function_exists('gd_info') && extension_loaded('gd'))
		$bResizeOK = true;
	else
		echo "<!-- no gd/gd_info -->\n";
}
else
	$thumb = '';

$model = new DTemplate($DirModelos);
$iTamReg = 282;

$lv = CampoObrigatorio('lv');
SetSessao('ORIGEM_MSG', $lv);
if ($lv == _VENDA)
{
	$model-> define_templates( array ( 'resultado' => "vendaconsres$thumb.shtml" )); 
	$exibeEnder = Configuracao('EXIBE_ENDERECO_VENDA');
}
else
{
	$model-> define_templates( array ( 'resultado' => "locconsres$thumb.shtml" )); 
	$exibeEnder = Configuracao('EXIBE_ENDERECO_LOCACAO');
}
$model->define_dynamic('IMOV_RES', 'resultado');   //body is the parent of table

if ($exibeEnder == 'NAO')
	$exibeEnder = 'style="display:none"';
else
	$exibeEnder = '';

$Cidade = Campo('selcidade');
if (!empty($Cidade))
{
	$Cidade = explode(':', $Cidade);
	if (count($Cidade) > 1)
	{
		// Pesquisa com tela unica.
		$NomeCidade = trim($Cidade[1]);
		SetSessao('CODCIDADE', trim($Cidade[0]));
		SetSessao('NOMECIDADE', $NomeCidade);
		SetSessao('UF', $Cidade[2]);
		$Cidade = $Cidade[0];
	}
	else 
		$Cidade = intval(trim($Cidade[0]));
}
if (empty($Cidade))
{
	$NomeCidade = 'TODAS CIDADES';
	SetSessao('CODCIDADE', 0);
	SetSessao('NOMECIDADE', '');
	SetSessao('UF', '');
	$Cidade = 0;
}

$Regiao = Campo('selregiao');
$Bairro = Campo('selbairro');
if (empty($Bairro))
{
	$Bairro = array('');
	$TamBairro = 1;
}
else
{
	$TamBairro = count($Bairro);
	for ($i = 0; $i < $TamBairro; $i++)
	{
		$szBairro = $Bairro[$i];
		if (is_numeric($szBairro))
		{
			// Veio codigo do bairro entao busca seu nome
			$Val = PesqCodBairro($lv, $szBairro);
			$Bairro[$i] = $Val[1];
		}
	}
}

$Quartos = Campo('nro_quartos');
if (empty($Quartos))
	$NroQuartos = 0;
else
	$NroQuartos = intval($Quartos);

$Vagas = Campo('nro_vagas');
if (empty($Vagas))
	$NroVagas = 0;
else
	$NroVagas = intval($Vagas);

$Reg_bairro = Campo('reg_bairro');
if (empty($Reg_bairro))
	$Reg_bairro = 'B';

$Val = Campo('faixa1');
if($Val && strlen($Val) > 0)
	$dValini = floatval(TrataFaixa($Val));
else
	$dValini = 0;

$Val = Campo('faixa2');
if($Val && strlen($Val) > 0)
	$dValfim = floatval(TrataFaixa($Val));
else
	$dValfim = 999999999999;

$Tipo = trim(Campo('seltipo'));
if (empty($Tipo))
{
	$bTodosTipos = true;
	$ResCom = Campo('selocup');
	if (empty($ResCom))
		$ResCom = GetSessao('RES_COM');
	$ListaTipos = ListaTipos($ResCom);
	$Quartos = 0;
}
else
{
	$ResCom = substr($Tipo, 0, 1);
	SetSessao('RES_COM', $ResCom);
	$QualTipo = intval(substr($Tipo,1));
	if ($QualTipo <= 0)
	{
		$bTodosTipos = true;
		$ListaTipos = ListaTipos($ResCom);
	}
	else
	{
		$bTodosTipos = false;
		$ListaTipos = PesqTipo($ResCom, $QualTipo);
	}
}

if ($lv == _VENDA)
	$fImov = fopen($DirDados.'imovenda.txt', 'r');
else
	$fImov = fopen($DirDados.'imov.txt', 'r');
if($fImov === FALSE)
{
	Mensagem('Aviso', 'Problema na pesquisa, Favor tente mais tarde!');
	return 0;
}

$iAchados = 0;

//print '<!-- '; print_r($ListaTipos); print " -->\n";

foreach ($ListaTipos as $Tipo => $Val)
{

	$Tipo = intval(substr($Tipo,1,strlen($Tipo)-1));
	$DescTipo = $Val[0];
	$bDormit = intval($Val[1]);
	$bGaragem = $Val[2];

	if (!$bDormit || $Quartos > 0)
	{
		$iDormitorios = $NroQuartos;
		$bTodosDorm = false;
	} else {
		$iDormitorios = 1;
		$NroQuartos = 4;
		$bTodosDorm = true;
	}

	while ($iDormitorios <= $NroQuartos)
	{
		fseek($fImov, 0);

		for ($iCont = 0;;)
		{
			$lPos = ftell($fImov);
			$Linha = fgets($fImov, 4098);
			if (empty($Linha) || strlen($Linha) < $iTamReg+1)
				break;

			if ($Tipo != intval(trim(substr($Linha, 8, 2))))
				continue;
//print '<!-- '.$Linha; print " -->\n";

			$szCid = substr($Linha, 10, 6);
			if ($Cidade > 0 && $Cidade != intval(trim($szCid)))
				continue;
//print "<!-- Cidade $Cidade -->\n";

			$fVal = floatval(substr($Linha, 226, 12));
			if ($dValini > $fVal)
				continue;
//print "<!-- Valini $dValini -->\n";

			if ($dValfim < $fVal)
				continue;
//print "<!-- Valfim $dValfim -->\n";

			if ($bDormit) {
				if ($iDormitorios == 4)
				{
					if (intval(substr($Linha, 194, 2)) < 4)
						continue;
				}
				else if ($iDormitorios != intval(substr($Linha, 194, 2)) )
					continue;
			}
//print "<!-- Dormit $iDormitorios -->\n";

			if (strcmp($Reg_bairro, 'R') == 0)
			{
				if (strcmp($Regiao, '-1') != 0) //- QUALQUER REGIAO -
				{
					if( strcmp($Regiao, trim(substr($Linha, 76, 3))) != 0 )
						continue;
				}
			}
			else
			{
				$bAchou = 0;
				for ($i=0; $i < $TamBairro; $i++)
				{
					$szBairro = trim($Bairro[$i]);
					$sAux = trim(substr($Linha, 16, 60));
					if (empty($szBairro) || strstr($szBairro, 'QUALQUER BAIRRO') !== false ||
						strcmp($szBairro, $sAux) == 0 )
					{
						$bAchou = 1;
						break;
					}
				}
				if (!$bAchou)
					continue;
			}
//print "<!-- Bairro $sAux -->\n";

			if ($NroVagas > 0 && $NroVagas != intval(substr($Linha, 296, 3)))
				continue;
//print "<!-- Vagas $NroVagas -->\n";

			if ($iCont == 0 && ($bTodosDorm || $bTodosTipos)) {
				$szDorm = ($iDormitorios == 1) ? '1 DORMIT&Oacute;RIO' : ($iDormitorios >= 4 ? '4 OU MAIS DORMIT&Oacute;RIOS' : $iDormitorios.' DORMIT&Oacute;RIOS');
				if ($bTodosTipos)
					$szDesc = '-----'.strtoupper($DescTipo).'-----'.($bTodosDorm ? '<br>'.$szDorm : '');
				else if ($bTodosDorm)
					$szDesc = '-----'.$szDorm.'-----';
				$szDesc = '<center><b><big>'.$szDesc.'</big></b></center>';

				if ($exibeThumbNails)
				{
					$model->assign('EXIBE_THUMB', 'style="visibility:hidden;display:none"');
					$model->assign('EXIBE_IMOVEL', 'style="visibility:hidden;display:none"');
					$model->assign('VAL', $szDesc);
				}
				else
				{
					if ($exibeEnder == '') {
						$szEnd = $szDesc;
						$bairroDescr = '';
					} else {
						$bairroDescr = $szDesc;
						$szEnd = '';
					}
					$model->assign('_ALT', '');
					$model->assign('COD', '');
					$model->assign('EXIBE_ENDER', $exibeEnder);
					$model->assign('BAIRRO', $bairroDescr);
					$model->assign('END', empty($exibeEnder) ? rtrim($szEnd) : '');
					$model->assign('_SRC', $DirImagens.'nada.png');
					$model->assign('_CLICK', 'return false');
					$model->assign('AREA', '');
					$model->assign('VAL', '');
				}
				$model->parse('.IMOV_RES'); 
			}

			$iAchados++;
			$szCod = substr($Linha, 0, 8);
			$szBairro = trim(substr($Linha, 16, 60));
			$szSituacao = substr($Linha, 262, 3);
			$szEnd = trim(substr($Linha, 79, 85));

			$sAlt = 'Clique para visualizar imagens deste im&oacute;vel.';
			$foto = ExisteFotos(trim($szCod), '!');
			if ($foto != '')
			{
				$pCodFoto = '!';
				$model->assign('_SRC', $DirImagens.'maquina.png');
				$model->assign('_CLICK', 'return wopen('.trim($szCod).', '.$lPos.", '!')");
			}
			else
			{
				$foto = ExisteFotos(trim($szCod), '');
				if ($foto != '')
				{
					$pCodFoto = $foto;
					$model->assign('_SRC', $DirImagens.'maquina.png');
					$model->assign('_CLICK', 'return wopen('.trim($szCod).', '.$lPos.", '$foto')");
				}
				else
				{
					$pCodFoto = '*';
					$model->assign('_SRC', $DirImagens.'info.png');
					$model->assign('_CLICK', 'return wopen('.trim($szCod).', '.$lPos.", '*');");
					$sAlt = 'Clique para visualizar informa&ccedil;&otilde;es deste im&oacute;vel.';
				}
			}

			if ($Cidade <= 0)
			{
				$Val = PesqCodCidade($lv, intval($szCid));
				$szBairro .= ' / '.$Val[1];
			}

			$model->assign('_ALT', $sAlt);
			$model->assign('COD', $szCod);
			$model->assign('BAIRRO', $szBairro);
			$model->assign('EXIBE_ENDER', $exibeEnder);
			$model->assign('END', empty($exibeEnder) ? rtrim($szEnd) : '');
			$AreaUtil = floatval(trim(substr($Linha, 274, 8)));
			if ($AreaUtil == 0.0)
				$AreaUtil = '&nbsp;';
			else
				$AreaUtil = number_format($AreaUtil, 2, ',', '.');
			$model->assign('AREA', $AreaUtil);
			$AreaTotal = floatval(trim(substr($Linha, 274, 8)));
			if ($AreaTotal == 0.0)
				$AreaTotal = '&nbsp;';
			else
				$AreaTotal = number_format($AreaTotal, 2, ',', '.');
			$model->assign('AREA_TOTAL', $AreaTotal);
			$Val = trim(substr($Linha, 226, 12));
			if ($Val == 0)
				$AuxVal = 'Consulte';
			else
				$AuxVal = 'R$ '.NumberFormat($Val, 2, ',', '.');
			$model->assign('VAL', $AuxVal);

			if ($exibeThumbNails)
			{
				$chavecarac = trim(substr($Linha, 266, 8));
				$szCod = trim($szCod);
				$szValCond = trim(substr($Linha, 238, 12));
				$szValIptu = trim(substr($Linha, 250, 12));
				$aRet = MontaDescrImov($szCod, $chavecarac, $szValCond, $szValIptu);
				$model->assign('DESCR', $aRet[0].$aRet[1]);

				if ($pCodFoto != '*')
					$foto = FotoPath($szCod,$pCodFoto);

				if ($pCodFoto == '*' || $foto === false)
					$model->assign('EXIBE_THUMB', 'style="visibility:hidden;display:none"');
				else
				{
					$model->assign('EXIBE_THUMB', '');
					$model->assign('IMG_FT', $foto);
					if ($bResizeOK)
						$model->assign('IMG_SRC', 'resizeImg.php?file='.urlencode($foto).'&width='.$Img_Width);
					else
						$model->assign('IMG_SRC', $foto);
				}
				$model->assign('IMG_WIDTH', $Img_Width);
				$model->assign('EXIBE_IMOVEL', '');
			}

			$model->parse('.IMOV_RES'); 
			$iCont++;
		}
		$iDormitorios++;
	}
}

if ($iAchados > 0)
{
	$szTipo = ($ResCom=='C' ? ' comerciais' : ($ResCom=='R' ? ' residenciais' : ''));
	if($bTodosTipos)
		$szDesc = 'todos os'.(empty($szTipo) ? ' im&oacute;veis' : $szTipo);
	else if($bDormit) {
		$szDesc = $DescTipo;
		if (!$bTodosDorm)
			$szDesc .= ' '.$Quartos.($NroQuartos==1 ? ' Dormit&oacute;rio' : ' Dormit&oacute;rios');
	} else
		$szDesc = $DescTipo;
	$model->assign('TIPOIMOV', $szDesc);

	if ($iAchados > 1)
		$szDesc = $iAchados.' im&oacute;veis'.$szTipo.' encontrados';
	else
		$szDesc = '';
	$model->assign('ACHADOS', $szDesc);
	$model->assign('NOMECIDADE', $NomeCidade);

	$model->parse('resultado'); 
	$model->DPrint('resultado');

}
else
	Mensagem('Aviso', 'N&atilde;o foi encontrado nenhum im&oacute;vel com estas caracter&iacute;sticas!');

fclose($fImov);

session_write_close ();
?>
