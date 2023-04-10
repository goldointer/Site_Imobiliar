<?php
include 'msg.php';

header('Content-Type: text/html; charset=ISO-8859-1');

$DirIRanual = Configuracao('DIR_IRANUAL');
$DirModelos = Configuracao('DIR_MODELOS_AREACLIENTE');

//--------------------------------------------------------------------------------
function MoneyFormat($sVal, $iRed=0)	//0=no; 1=se negativo; 2=sempre
{
	global $UsingXmlModel;
	
	$sVal = str_replace('.', '' , $sVal);
	$sVal = str_replace(',', '.' , $sVal);
	if ($UsingXmlModel)
		return trim($sVal);
	
	$iVal = floatval($sVal);
	if ($iRed == 2 && $iVal > 0)
		$iVal = -$iVal;
	$sRet = number_format($iVal, 2, ',', '.');
	if ($iRed != 0 && $iVal < 0)
		$sRet = '<font color="red">'.$sRet.'</font>';
	return $sRet;
}

//---main-------------------------------------------------------------------------

$usuario = GetSessao('usuario');
$usuario_id = GetSessao('usuario_id');
if (empty($usuario) || empty($usuario_id))
{
	// Ja foi efetuado um logout, deve ser pagina anterior.
	$sUrl = GetSessao('login_url');
	if (empty($sUrl))
		Mensagem('Erro', 'Sessão encerrada, efetue o LOGIN!');
	else
		header('Location: ' .$sUrl);
	exit;
}

$pId = intval(CampoObrigatorio('id'));
$Competencia = CampoObrigatorio('comp');

$filepath = sprintf('/%d000/%d/%08d_%d.csv', $pId/1000, $pId, $pId, $Competencia);
$stat = @stat($DirIRanual.$filepath);
if ($stat === false || $stat['size'] <= 0 || ($handle=@fopen($DirIRanual.$filepath, 'r')) === false)
{
	echo("<!-- $DirIRanual.$filepath -->\n");
	Mensagem('Aviso', 'Esta informação não está disponível no momento!');
	exit (0);
}
$DataArq = date("d/m/Y H:i", $stat['mtime']);

$Modelo = 'infoIR';
$model = new DTemplate($DirModelos);
$model->define_templates( array ($Modelo => Modelo($DirModelos, $Modelo)) ); 
	
if ($UsingXmlModel)
{
	$model->define_dynamic('EXIBE_RESUMO_PF', $Modelo);
	$model->define_dynamic('RESUMO_PF', 'EXIBE_RESUMO_PF');
	$model->define_dynamic('EXIBE_RESUMO_PJ', $Modelo);
	$model->define_dynamic('RESUMO_PJ', 'EXIBE_RESUMO_PJ');
	$model->define_dynamic('IMOVEL', $Modelo);
	$model->define_dynamic('LOCATARIO', 'IMOVEL');
	$model->define_dynamic('RESUMO_LOCAT', 'LOCATARIO');
	$model->assign('DICA', $filepath);
	$model->assign('CONTEUDO', ISO8859_1toModel('IMPOSTO DE RENDA - ALUGUÉIS E ROYALTIES'));
	$model->assign('USUARIO', ISO8859_1toModel($usuario));
	$model->assign('DATA_ATUAL', date('d/m/Y H:i'));
}
else
{
	$model->define_dynamic('RESUMO_PF', $Modelo);
	$model->define_dynamic('RESUMO_PJ', $Modelo);
	$model->define_dynamic('IMOVEL', $Modelo);
	$model->define_dynamic('LOCATARIO', 'IMOVEL');
	$model->define_dynamic('RESUMO_LOCAT', 'LOCATARIO');
	echo("<!-- $filepath -->\n");
}

//Le arquivo do cliente
$Obs = '';
$sCodBarra = '';
$bInformativo = false;
$bBalancete = false;
$bTaxas = true;
$iLinha = 0;
$iContImov = 0;
$iContResPF = $iContResPJ = 0;
$model->assign('INFO_ANO', $Competencia);
$model->assign('DATA_ARQUIVO', $DataArq);

while (!feof ($handle))
{
	$aCampo = fgetcsv($handle, 4096, ';');
	if (empty($aCampo))
		break;
	$campos = count($aCampo);

//	if ($aCampo[1] != ++$iLinha)
//		Mensagem('Aviso', 'Esta informação não está disponível no momento!');

	switch ($aCampo[0]) {
		case 'A':
			$model->assign('IMOBILIARIA_NOME', ISO8859_1toModel($aCampo[2]));
			$model->assign('IMOBILIARIA_CNPJ', $aCampo[3]);
			break;
		case 'B':
			$model->assign('CLIENTE_COD', $aCampo[2]);
			$model->assign('CLIENTE_NOME', ISO8859_1toModel($aCampo[3]));
			$model->assign('CLIENTE_CNPJ', $aCampo[4]);
			break;
		case 'C':
			$model->assign('CLIENTE_ENDER', ISO8859_1toModel($aCampo[2]));
			break;
		case 'D':
			$model->assign('CLIENTE_CEP', $aCampo[2]);
			$model->assign('CLIENTE_BAIRRO', ISO8859_1toModel($aCampo[3]));
			$model->assign('CLIENTE_CIDADE', ISO8859_1toModel($aCampo[4]));
			$model->assign('CLIENTE_UF', $aCampo[5]);
			break;
		case 'E':
			$model->assign('PF_MES', ISO8859_1toModel($aCampo[2]));
			$model->assign('PF_ALUGUEL', MoneyFormat($aCampo[3]));
			$model->assign('PF_MULTA', MoneyFormat($aCampo[4]));
			$model->assign('PF_TX_ADM', MoneyFormat($aCampo[5]));
			$model->assign('PF_LIQUIDO', MoneyFormat($aCampo[6]));
			$model->parse($iContResPF++ == 0 ? 'RESUMO_PF' : '.RESUMO_PF');
			break;
		case 'F':
			$model->assign('PF_TOT_DESCR', ISO8859_1toModel($aCampo[2]));
			$model->assign('PF_TOT_ALUGUEL', MoneyFormat($aCampo[3]));
			$model->assign('PF_TOT_MULTA', MoneyFormat($aCampo[4]));
			$model->assign('PF_TOT_TX_ADM', MoneyFormat($aCampo[5]));
			$model->assign('PF_TOT_LIQUIDO', MoneyFormat($aCampo[6]));
			break;
		case 'G':
			$model->assign('PJ_LOCAT_NOME', ISO8859_1toModel($aCampo[2]));
			$model->assign('PJ_LOCAT_CNPJ', $aCampo[3]);
			$model->assign('PJ_ALUGUEL_MULTA', MoneyFormat($aCampo[4]));
			$model->assign('PJ_TX_ADM', MoneyFormat($aCampo[5]));
			$model->assign('PJ_IR_FONTE', MoneyFormat($aCampo[6]));
			$model->assign('PJ_LIQUIDO', MoneyFormat($aCampo[7]));
			$model->parse($iContResPJ++ == 0 ? 'RESUMO_PJ' : '.RESUMO_PJ');
			break;
		case 'H':
			$model->assign('PJ_TOT_DESCR', ISO8859_1toModel($aCampo[2]));
			$model->assign('PJ_TOT_ALUGUEL_MULTA', MoneyFormat($aCampo[3]));
			$model->assign('PJ_TOT_TX_ADM', MoneyFormat($aCampo[4]));
			$model->assign('PJ_TOT_IR_FONTE', MoneyFormat($aCampo[5]));
			$model->assign('PJ_TOT_LIQUIDO', MoneyFormat($aCampo[6]));
			break;
		case 'I':
			if ($iContImov > 0)
			{
				if ($iContLocat > 0)
					$model->parse($iContLocat == 1 ? 'LOCATARIO' : '.LOCATARIO');
				$model->parse($iContImov == 1 ? 'IMOVEL' : '.IMOVEL');
			}
			$iContImov++;
			$iContLocat = 0;
			$model->assign('IMOV_COD', $aCampo[2]);
			$model->assign('IMOV_ENDER', ISO8859_1toModel($aCampo[3]));
			$model->assign('IMOV_TIPO', $aCampo[4]);
			$model->assign('IMOV_NR_IPTU', '');
			$model->assign('IMOV_AREAUTIL', '');
			$model->assign('IMOV_AREATOTAL', '');
			$model->assign('IMOV_MATRICULA', '');
			$model->assign('IMOV_ZONA', '');
			break;
		case 'J':
			if ($iContLocat > 0)
				$model->parse($iContLocat == 1 ? 'LOCATARIO' : '.LOCATARIO');
			$iContLocat++;
			$iContResLocat = 0;
			$model->assign('LOCAT_CNPJ', $aCampo[2]);
			$model->assign('LOCAT_NOME', ISO8859_1toModel($aCampo[3]));
			break;
		case 'K':
			$model->assign('LOCAT_MES', ISO8859_1toModel($aCampo[2]));
			$model->assign('LOCAT_ALUGUEL', MoneyFormat($aCampo[3]));
			$model->assign('LOCAT_MULTA', MoneyFormat($aCampo[4]));
			$model->assign('LOCAT_TX_ADM', MoneyFormat($aCampo[5]));
			$model->assign('LOCAT_IR_FONTE', MoneyFormat($aCampo[6]));
			$model->parse($iContResLocat++ == 0 ? 'RESUMO_LOCAT' : '.RESUMO_LOCAT');
			break;
		case 'L':
			$model->assign('LOCAT_TOT_DESCR', ISO8859_1toModel($aCampo[2]));
			$model->assign('LOCAT_TOT_ALUGUEL', MoneyFormat($aCampo[3]));
			$model->assign('LOCAT_TOT_MULTA', MoneyFormat($aCampo[4]));
			$model->assign('LOCAT_TOT_TX_ADM', MoneyFormat($aCampo[5]));
			$model->assign('LOCAT_TOT_IR_FONTE', MoneyFormat($aCampo[6]));
			break;
		case 'M':
			$model->assign('IMOV_TOT_DESCR', ISO8859_1toModel($aCampo[2]));
			$model->assign('IMOV_TOT_ALUGUEL', MoneyFormat($aCampo[3]));
			$model->assign('IMOV_TOT_MULTA', MoneyFormat($aCampo[4]));
			$model->assign('IMOV_TOT_TX_ADM', MoneyFormat($aCampo[5]));
			$model->assign('IMOV_TOT_IR_FONTE', MoneyFormat($aCampo[6]));
			break;
		case 'N':
			$model->assign('IMOV_NR_IPTU', $aCampo[2]);
			$model->assign('IMOV_AREAUTIL', $aCampo[3]);
			$model->assign('IMOV_AREATOTAL', $aCampo[4]);
			$model->assign('IMOV_MATRICULA', ISO8859_1toModel($aCampo[5]));
			$model->assign('IMOV_ZONA', ISO8859_1toModel($aCampo[6]));
			break;
		case 'X':
			$model->assign('DATA_ARQUIVO', $aCampo[2]);
			break;
	}
}

fclose ($handle);

// Efetua a finalizacao dos blocos dinamicos e gera pagina
if ($iContLocat > 0)
	$model->parse($iContLocat == 1 ? 'LOCATARIO' : '.LOCATARIO');
else
	$model->clear_dynamic('LOCATARIO');

if ($iContImov > 0)
	$model->parse($iContImov == 1 ? 'IMOVEL' : '.IMOVEL');
else
	$model->clear_dynamic('IMOVEL');

if ($UsingXmlModel)
{
	if ($iContResPF > 0)
		$model->parse('EXIBE_RESUMO_PF');
	else
		$model->clear_dynamic('EXIBE_RESUMO_PF');

	if ($iContResPJ > 0)
		$model->parse('EXIBE_RESUMO_PJ');
	else
		$model->clear_dynamic('EXIBE_RESUMO_PJ');
}
else 
{
	if ($iContResPF > 0)
		$model->assign('TEM_RESUMO_PF', 'S');
	else
	{
		$model->assign('TEM_RESUMO_PF', 'N');
		$model->clear_dynamic('RESUMO_PF');
	}

	if ($iContResPJ > 0)
		$model->assign('TEM_RESUMO_PJ', 'S');
	else
	{
		$model->assign('TEM_RESUMO_PJ', 'N');
		$model->clear_dynamic('RESUMO_PJ');
	}
}

$model->parse($Modelo);
$model->DPrint($Modelo);

if (!$UsingXmlModel)
	echo "\n<!-- $Modelo.shtml -->\n";    
?>
