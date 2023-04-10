<?php
include "msg.php";

header('Content-Type: text/html; charset=ISO-8859-1');

$DirDados = Configuracao('DIR_DADOS');
$DirModelos = Configuracao('DIR_MODELOS_AREACLIENTE');
$aColId = array();

//--------------------------------------------------------------------------------
function MoneyFormat($sVal, $iRed=1)	//0=no; 1=se negativo; 2=sempre
{
	global $UsingXmlModel;
	
	$sVal = str_replace(',', '.' , $sVal);
	if ($UsingXmlModel)
		return trim($sVal);
	
	$fVal = floatval($sVal);
	if ($fVal == 0)
		return '&nbsp;';
		
	if ($iRed == 2 && $fVal > 0)
		$fVal = -$fVal;
	$sRet = number_format($fVal, 2, ',', '.');
	if ($iRed != 0 && $fVal < 0)
		$sRet = '<font color="red">'.$sRet.'</font>';
	return $sRet;
}

//--------------------------------------------------------------------------------
function ResumoDOC($codCondom)
{
	global $DirDados, $DirModelos, $UsingXmlModel, $usuario;
	
	$Modelo = 'resumoDOC';
	$model = new DTemplate($DirModelos);
	$model->define_templates( array ($Modelo => Modelo($DirModelos, $Modelo)) ); 
	$model->define_dynamic('EXIBE_BLOCO', $Modelo);
	$model->define_dynamic('EXIBE_DOC', 'EXIBE_BLOCO');
	$model->define_dynamic('EXIBE_TAXA', 'EXIBE_DOC');
	$model->define_dynamic('RESUMO_TAXA', $Modelo);
	$model->define_dynamic('RESUMO_BLOCO', $Modelo);

	$model->assign('USUARIO', ISO8859_1toModel($usuario));
	$model->assign('DATA_ATUAL', date('d/m/Y H:i'));
	$model->assign('ASSESSOR_NOME', Campo('ASSESSOR'));

	$contArqs = 0;
	$contBlocos = 0;
	$InfosGerais = '';
	$Dica = '';
	
	for ($Passo = 0; $Passo < 2; $Passo++) {
		// Monta nome do arquivo fisico
		$TipoDoc = ($Passo == 0) ? 'N' : 'E';
		$UserFile = sprintf('RDOC%05d%s.csv', intval($codCondom), $TipoDoc);
		$FilePath = $DirDados.$UserFile;
		$stat = @stat($FilePath);
		if ($stat === false || $stat['size'] <= 0 || ($File=fopen($FilePath, 'r')) === false)
			continue;

		$contArqs++;
		$Dica .= $UserFile.' ';
		$model->assign('DICA', $Dica);
		
		while (!feof ($File)) {
			$aReg = fgetcsv($File, 1024, ';');

			switch ($aReg[0])
			{
			case "HE":
				if ($aReg[3] != $codCondom) {
					Mensagem('Atenção', 'Dados incompletos no momento!');
					exit(1);
				}
				$model->assign('COND_COD', $codCondom);
				$model->assign('COND_NOME', ISO8859_1toModel($aReg[4]));
				$model->assign('TIPO_DOC', $TipoDoc == 'N' ? 'NORMAL' : 'EXTRA');
				$model->assign('DATA_ARQUIVO', $aReg[6]);
				$model->assign('COMPETENCIA', $aReg[7]);
				$contDOCs = 0;
				break;

			case "IF":
				$InfosGerais .= sprintf("\n  <mensagem_informativa><![CDATA[%s]]></mensagem_informativa>", ISO8859_1toModel($aReg[2]));
				break;

			case "IS":
				$InfosGerais .= sprintf("\n  <instrucao_bancaria><![CDATA[%s]]></instrucao_bancaria>", ISO8859_1toModel($aReg[2]));
				break;

			case "HB":
				if ($contDOCs > 0)
					$model->parse($contDOCs == 1 ? 'EXIBE_DOC' : '.EXIBE_DOC');
				if ($contBlocos > 0)
					$model->parse($contBlocos == 1 ? 'EXIBE_BLOCO' : '.EXIBE_BLOCO');
				$model->assign('BLOCO_COD', $aReg[2]);
				$model->assign('BLOCO_NOME', ISO8859_1toModel($aReg[3]));
				$model->assign('VENCIMENTO', $aReg[4]);
				$contBlocos++;
				$contDOCs = 0;
				break;

			case "HD":
				if ($contDOCs > 0)
					$model->parse($contDOCs == 1 ? 'EXIBE_DOC' : '.EXIBE_DOC');
				$model->assign('ID_ECON', ISO8859_1toModel($aReg[2]));
				$model->assign('CONDOMINO_NOME', ISO8859_1toModel($aReg[3]));
				$model->assign('TIPO_ECON', ISO8859_1toModel($aReg[4]));
				$model->assign('CATEG_ECON', ISO8859_1toModel($aReg[5]));
				$model->assign('FRACAO_ECON', $aReg[6]);
				$model->assign('MAIS_INFOS_DOC', '');
				$contDOCs++;
				$contTaxa = 0;
				break;

			case "ID":
				if (empty($aReg[2])) 
					$MaisInfosDoc = '';
				else if ($UsingXmlModel)
					$MaisInfosDoc = sprintf("\n    <economias_agrupadas>%s</economias_agrupadas>", ISO8859_1toModel($aReg[2]));
				else
					$MaisInfosDoc = sprintf('<BR>Economias agrupadas:%s', ISO8859_1toModel($aReg[2]));
				
				if (!empty($aReg[3])) {
					if ($UsingXmlModel)
						$MaisInfosDoc .= sprintf("\n    <outras_infos>%s</outras_infos>", ISO8859_1toModel($aReg[3]));
					else
						$MaisInfosDoc .= '<BR>'.ISO8859_1toModel($aReg[3]);
				}
				$model->assign('MAIS_INFOS_DOC', $MaisInfosDoc);
				break;

			case "FD":
				$model->assign('NOSSO_NUMERO', $aReg[2]);
				$model->assign('VALOR_DOC', MoneyFormat($aReg[3]));
				break;

			case "TD":
				$model->assign('TAXA', $aReg[2]);
				$model->assign('DESCRICAO', ISO8859_1toModel($aReg[3]));
				$model->assign('COMPLEMENTO_DESCR', ISO8859_1toModel($aReg[4]));
				$model->assign('PARCELA', $aReg[5]);
				$model->assign('VALOR_TAXA', MoneyFormat($aReg[6]));
				$model->parse($contTaxa++ == 0 ? 'EXIBE_TAXA' : '.EXIBE_TAXA');
				break;

			case "RT":
				$model->assign('RT_TAXA', $aReg[2]);
				$model->assign('RT_DESCRICAO', ISO8859_1toModel($aReg[3]));
				$model->assign('RT_VALOR', MoneyFormat($aReg[4]));
				$model->parse('.RESUMO_TAXA');
				break;

			case "RB":
				$model->assign('RB_NOME', ISO8859_1toModel($aReg[2]));
				$model->assign('RB_VALOR', MoneyFormat($aReg[3]));
				$model->parse('.RESUMO_BLOCO');
				break;

			case "TT":
				$model->assign('QTDE_DOCS', $aReg[2]);
				$model->assign('TOTAL_DOCS', MoneyFormat($aReg[3]));
				break;
			default:
				break;
			}
		}

		if ($contDOCs > 0)
			$model->parse($contDOCs == 1 ? 'EXIBE_DOC' : '.EXIBE_DOC');
		if ($contBlocos > 0)
			$model->parse($contBlocos == 1 ? 'EXIBE_BLOCO' : '.EXIBE_BLOCO');
	}

	if ($contArqs == 0) {
		Mensagem('Atenção', 'Dados não disponíveis no momento!');
		exit(1);
	}

	$model->assign('MAIS_INFOS_GERAIS', $InfosGerais);
	$model->parse($Modelo);
	$model->DPrint($Modelo);

	if (!$UsingXmlModel)
		echo "\n<!-- $Modelo.shtml -->\n";    
}

//--------------------------------------------------------------------------------
function LinhaRecDesp(&$model, &$contLinha, &$aReg)
{
	global $aColId;
	
	$model->assign('DESCRICAO', $aReg[2]);
	$model->assign('COLUNA_ID', $aColId[3]);
	$model->assign('COLUNA_VALOR', MoneyFormat($aReg[3]));
	$model->parse('RESUMO_COLUNA');
	$maxCol = count($aColId);

	for ($col = 4; $col < $maxCol; $col++) {
		$model->assign('COLUNA_ID', $aColId[$col]);
		$model->assign('COLUNA_VALOR', MoneyFormat($aReg[$col]));
		$model->parse('.RESUMO_COLUNA');
	}

	$TagLinha = ($contLinha++ == 0) ? 'RESUMO_LINHA' : '.RESUMO_LINHA';
	$model->parse($TagLinha);
}

//--------------------------------------------------------------------------------
function ResumoRecDesp($codCondom)
{
	global $DirDados, $DirModelos, $UsingXmlModel, $aColId, $usuario;
	
	$Modelo = 'resumoRecDesp';
	$model = new DTemplate($DirModelos);
	$model->define_templates( array ($Modelo => Modelo($DirModelos, $Modelo)) ); 
	$model->define_dynamic('RESUMO_TABELA', $Modelo);
	$model->define_dynamic('RESUMO_LINHA', 'RESUMO_TABELA');
	$model->define_dynamic('RESUMO_COLUNA', 'RESUMO_LINHA');
	if (!$UsingXmlModel)
		$model->define_dynamic('RESUMO_CABEC', 'RESUMO_TABELA');

	// Monta nome do arquivo fisico
	$UserFile = sprintf('RRD%05d.csv', intval($codCondom));
	$FilePath = $DirDados.$UserFile;
	$stat = @stat($FilePath);
	if ($stat === false || $stat['size'] <= 0 || ($File=fopen($FilePath, 'r')) === false) {
		Mensagem('Atenção', 'Dados não disponíveis no momento!');
		exit(1);
	}

	$model->assign('USUARIO', ISO8859_1toModel($usuario));
	$model->assign('DATA_ATUAL', date('d/m/Y H:i'));
	$model->assign('ASSESSOR_NOME', Campo('ASSESSOR'));
	$model->assign('DICA', $UserFile);
	
	while (!feof ($File)) {
		$aReg = fgetcsv($File, 1024, ';');

		switch ($aReg[0])
		{
		case "HE":
			if ($aReg[3] != $codCondom) {
				Mensagem('Atenção', 'Dados incompletos no momento!');
				exit(1);
			}
			$model->assign('COND_COD', $codCondom);
			$model->assign('COND_NOME', ISO8859_1toModel($aReg[4]));
			$model->assign('DATA_ARQUIVO', $aReg[5]);
			break;

		case "TI":
			$aColId = $aReg;
			$model->assign('PERIODO_INICIO', $aColId[3]);
			$model->assign('PERIODO_FIM', $aColId[14]);
			break;

		case "RS":
			unset($aColId[15]);
			unset($aColId[16]);
			unset($aColId[17]);
		case "TD":
		case "TR":
			if ($UsingXmlModel) {
				$model->assign('RESUMO_ID', $aReg[2]);                
			} else {
				$maxCol = count($aColId);
				$model->assign('COLUNA_ID', $aReg[2]);
				$model->parse('RESUMO_CABEC');
				for ($col = 3; $col < $maxCol; $col++) {
					$model->assign('COLUNA_ID', $aColId[$col]);
					$model->parse('.RESUMO_CABEC');
				}
			}
			$contLinha = 0;
			break;

		case "DD":
		case "DR":
			LinhaRecDesp($model, $contLinha, $aReg);
			break;

		case "TE":
		case "TT":
			LinhaRecDesp($model, $contLinha, $aReg);
			$model->parse('.RESUMO_TABELA');
			break;

		case "SB":
			LinhaRecDesp($model, $contLinha, $aReg);
			break;

		case "FS":
			$aReg[2] = "SUBTOTAL";
			LinhaRecDesp($model, $contLinha, $aReg);
			break;

		case "FF":
			$aReg[2] = "TOTAL GERAL";
			LinhaRecDesp($model, $contLinha, $aReg);
			break;

		default:
			break;
		}
	}

	$model->parse('.RESUMO_TABELA');
	$model->parse($Modelo);
	$model->DPrint($Modelo);

	if (!$UsingXmlModel)
		echo "\n<!-- $Modelo.shtml -->\n";    
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
		header('Location: '.$sUrl);
	exit;
}
$usuario = ISO8859_1toModel($usuario);

$Chave = CampoObrigatorio('CHAVE');
$codCondom = intval(substr($Chave,0,5));
$Prod = CampoObrigatorio('PROD');
if ($Prod == 'RDOC')
	ResumoDOC($codCondom);
else if ($Prod == 'RRD')
	ResumoRecDesp($codCondom);
else
	Mensagem('Atenção', 'Chamada inválida!');

session_write_close();
?>
