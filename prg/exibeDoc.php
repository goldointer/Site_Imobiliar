<?php
if (isset($_GET['sessionid'])) {
	$sessionid=$_GET['sessionid'];
	session_id($sessionid);
	session_start();
}

include "msg.php";

header('Content-Type: text/html; charset=ISO-8859-1');

$DirDados = Configuracao('DIR_DADOS');
$DirImagens = Configuracao('DIR_IMAGENS');
$DirModelos = Configuracao('DIR_MODELOS_AREACLIENTE');

//--------------------------------------------------------------------------------
function MoneyFormat($sVal, $iRed=1)	//0=no; 1=se negativo; 2=sempre
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

//--------------------------------------------------------------------------------
function ClearImovelItem(&$model)
{
	$model->assign('DATA', '');
	$model->assign('COMPET', '');
	$model->assign('HIST', '&nbsp;SEM MOVIMENTO&nbsp;');
	$model->assign('DEBT', '');
	$model->assign('CRED', '');
	$model->assign('LIQUIDO', '');
}

//--------------------------------------------------------------------------------
function ExisteImgLanctos($iLancto)
{
	GLOBAL $DirLanctos;
//echo "<!--ExisteImgLanctos($iLancto)\n";

	// Verifica se existe imagem com este codigo de lanamento
	$aLanctos = array();
	$sDir = $DirLanctos.sprintf("%d/%d/", intval($iLancto / 1000) * 1000, $iLancto);
	$hDir = @opendir($sDir);
	$sArq = @readdir($hDir); 
	while (!empty($sArq)) {
		$sExt = substr($sArq, strlen($sArq)-4);
		if (strcasecmp($sExt, '.jpg') == 0 || strcasecmp($sExt, '.pdf') == 0)
			$aLanctos[] = $sDir.$sArq;
		$sArq = @readdir($hDir);
	}

	sort($aLanctos);

// if (count($aLanctos) > 0) echo "*ACHOU* '$sDir$sArq'";
//else echo "Nao achou '$sDir*.jpg'";
//echo" -->\n";

	return ($aLanctos);
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

$FilePath = Campo('ARQ');
if (!empty($FilePath))
{
	if (@file($DirDados.$FilePath))
		header('Location: '.$DirDados.$FilePath);
	else
		Mensagem('Erro', "Não existe $FilePath!");
	exit;
}

$bConselheiro = (GetSessao('tipo_cond') == 'S');
$usuario = ISO8859_1toModel($usuario);

$model = new DTemplate($DirModelos);

$Imob = getenv('IMOB_NAME');
$DataAtual = date('d/m/Y H:i');

$Modelo = Campo('MODELO');
$Prod = CampoObrigatorio('PROD');
if ($Prod == 'WSI')
{	// Chamada como Web Service tem argumentos diferentes
	$Condom = CampoObrigatorio('CONDOMINIO');
	$Bloco = Campo('BLOCO');
}
else
{
	$Chave = CampoObrigatorio('CHAVE');
	$Btn = CampoObrigatorio('btn');
	$Data = explode('/', $Btn);
	if (count($Data) != 2)
		$ok = false;
	else
	{
//echo "<!--\n"; print_r($Data); echo " -->\n";
		$Ano = substr(trim($Data[1]), -2);
		$Mes = substr(trim($Data[0]), -2);
		$ok = checkdate($Mes, 1, $Ano);
//echo "<!--$Mes / $Ano -->\n";
	}

	if (!checkdate($Mes, 1, $Ano))
	{
		Mensagem('Atenção', 'Dados não disponíveis no momento (ERRO INTERNO)!');
		exit(1);
	}

	if ($Prod == 'C' || $Prod == 'I')
	{
		// Verifica se extrato de condominio deve ser unificado.
		$bExtratoCond_Unif = (GetSessao('EXTRATOCOND_UNIF') == 'SIM');
	}
	else
	{
		// Monta nome do arquivo fisico
		$UserFile = $Prod.$Chave.'.'.$Ano.$Mes;
		$FilePath = $DirDados.$UserFile;
		$stat = @stat($FilePath);
		if ($stat === false || $stat['size'] <= 0 || ($File=fopen($FilePath, 'r')) === false)
		{
			Mensagem('Atenção', 'Dados não disponíveis no momento!');
			exit(1);
		}
		$DataArq = date("d/m/Y H:i", $stat['mtime']);
		$bExtratoCond_Unif = false;
	}
}

if ($Prod == 'S')
{
	// Demonstrativo sintetico de proprietario
	if (empty($Modelo))
		$Modelo = 'extratoloc';
	$model->define_templates( array ( $Modelo => Modelo($DirModelos, $Modelo) )); 

	if ($UsingXmlModel)
		$model->assign('DICA', $UserFile);
	else
		echo "<!-- $UserFile -->\n";

	$Extrato = '';
	while (!feof ($File))
	{
		$Linha = fgets($File, 1024);
		if (empty($Linha))
			break;
		$Extrato .= $Linha;
	}
	fclose ($File);

	$model->assign('DATA_ATUAL', $DataAtual);
	$model->assign('DATA_ARQUIVO', $DataArq);
	$model->assign('USUARIO', $usuario);
	$model->assign('EXTRATO', ISO8859_1toModel($Extrato));
	$model->parse('extratoloc');
	$model->DPrint('extratoloc');
}
elseif ($Prod == 'L')
{
/*
registro do demonstrativo do proprietario {
id_row   1;
hist    80;
compet   7;
data    10;
debt    12;
cred    12;
liq     12;
NewLine;
	OU
id_row   1; Qdo for 'I'
imovel 133;
NewLine;
}*/
	$iTamReg = 134;
	if (empty($Modelo))
		$Modelo = 'demonspropr';

	$model-> define_templates( array ($Modelo => Modelo($DirModelos, $Modelo) )); 
	if ($UsingXmlModel)
	{
		$model->assign('DICA', $UserFile);
		$model->define_dynamic('EXTRATO', $Modelo);
		$model->define_dynamic('EXIBE_IMOVEIS', $Modelo);
		$model->define_dynamic('IMOVEL', 'EXIBE_IMOVEIS');
		$model->define_dynamic('IMOVEL_ITEM', 'IMOVEL');
		$model->define_dynamic('TOTAIS', $Modelo);
		$model->define_dynamic('EXIBE_POR_DIA', $Modelo);
		$model->define_dynamic('POR_DIA', 'EXIBE_POR_DIA');
	}
	else
	{
		$model->define_dynamic('EXTRATO', $Modelo);
		$model->define_dynamic('IMOVEL', $Modelo);
		$model->define_dynamic('IMOVEL_ITEM', 'IMOVEL');
		$model->define_dynamic('TOTAIS', $Modelo);
		$model->define_dynamic('POR_DIA', $Modelo);
		echo "<!-- $UserFile -->\n";
	}

	$bTemImoveis = false;
	$bTemPorDia = false;
	$bImovel = false;
	$exibeImagemLanctos = true;
	$iContItem = 0;
	$Rodape = '';
	$Descr = '';
	$model->assign('TEM_IMOVEIS', ''); 
	$model->assign('TEM_PORDIA', ''); 
	ClearImovelItem($model);
	fseek ($File, 0);

	if (Configuracao('EXIBE_IMAGEM_LANCTO_LOC') == 'SIM')
	{
		$exibeImagemLanctos = true;
		$DirLanctos = Configuracao('DIR_LANCAMENTOS_LOC');
		SetSessao('tipo_lancamento', 'L');
	}

	for(;;)
	{
		$sReg = fgets($File, 4098);
		if (empty($sReg) || strlen($sReg) < $iTamReg+1)
			break;

		$TipoReg = substr($sReg, 0, 1);
		$Hist = ISO8859_1toModel(trim(substr($sReg, 1, 80)));
		$Compet = substr($sReg, 81, 7);
		$Data = substr($sReg, 88, 10);
		$Debt = substr($sReg, 98, 12);
		$Cred = substr($sReg, 110, 12);
		$Liq = substr($sReg, 122, 12);

		if ($TipoReg == 'E')
		{
			$model->assign('DATA', $Data);
			$model->assign('COMPET', $Compet);
			$model->assign('HIST', $Hist);
			$model->assign('DEBT', MoneyFormat($Debt, 2));
			$model->assign('CRED', MoneyFormat($Cred));
			$model->assign('LIQUIDO', MoneyFormat($Liq));
			$iLancto = trim(substr($sReg, 135, 11));
			$Imagens = '';
//			echo "<!-- exibeImagemLanctos=$exibeImagemLanctos | iLancto=$iLancto -->\n";

			if ($exibeImagemLanctos && !empty($iLancto))
			{
				$aLanctos = ExisteImgLanctos($iLancto);
				if (!empty($aLanctos))
				{
					if ($UsingXmlModel)
					{
						$Imagens = sprintf("\n   <imagens>\n");
						foreach ($aLanctos as $img)
							$Imagens .= sprintf("    <imagem>%s</imagem>\n", GetFullUrl($img));
						$Imagens .= "   </imagens>";
					}
					else
					{
						$sImg = '<input type="image" height="20" src="'.$DirImagens.'maquina.png" width="25" border="0" onclick="return wopen('.$iLancto.')" title="Clique para visualizar a imagem deste lan&ccedil;amento.">';
						$model->assign('HIST', $sImg.$Hist);
					}
				}
			}
			if ($UsingXmlModel)
			{
				// echo "<!-- $Imagens -->\n";
				$model->assign('IMAGENS_LANCTO', $Imagens);
			}

			if ($bImovel)
				$model->parse($iContItem++==0 ? 'IMOVEL_ITEM' : '.IMOVEL_ITEM');
			else
				$model->parse('.EXTRATO');
		}
		else if ($TipoReg == 'I')
		{
			if ($bImovel)
			{
				if ($iContItem == 0)
				{
					$model->assign('DATA', '');
					$model->assign('COMPET', '');
					$model->assign('HIST', 'NADA CONSTA');
					$model->assign('DEBT', '');
					$model->assign('CRED', '');
					$model->assign('LIQUIDO', '');
					if ($UsingXmlModel)
						$model->assign('IMAGENS_LANCTO', '');
					$model->parse('IMOVEL_ITEM');
				}
				$model->parse('.IMOVEL');
				ClearImovelItem($model);
				$iContItem = 0;
			}

			$bImovel = true;
			if (!empty($Descr))
				// Trata-se de outra linha de informacoes do imovel.
				$Descr .= '<br>';
			$Descr .= ISO8859_1toModel(trim(substr($sReg, 1, 133)));
			$model->assign('DESCR', $Descr);
			$model->assign('TEM_IMOVEIS', 'T');
			$bTemImoveis = true;
		}
		else if ($TipoReg == 'L')
		{
			if ($bImovel)
			{
				$Descr .= '<br>Locat&aacute;rio:&nbsp;'.ISO8859_1toModel(trim(substr($sReg, 1, 255)));
				$model->assign('DESCR', $Descr);
			}
			$Descr = '';
		}
		else if ($TipoReg == 'T')
		{
			if ($bImovel)
			{
				if ($iContItem == 0)
					$model->parse('IMOVEL_ITEM');
				$model->parse('.IMOVEL');
				ClearImovelItem($model);
				$iContItem = 0;
				$bImovel = false;
				$Descr = '';
			}
			$model->assign('HIST', $Hist);
			$model->assign('DEBT', MoneyFormat($Debt,2));
			$model->assign('CRED', MoneyFormat($Cred));
			$model->assign('LIQUIDO', MoneyFormat($Liq));
			$model->parse('.TOTAIS');
		}
		else if ($TipoReg == 'P')
		{
			$model->assign('DIA', $Compet);
			$model->assign('VALOR', MoneyFormat($Liq));
			$model->assign('SITUACAO', $Hist);
			$model->parse('.POR_DIA');
			$bTemPorDia = true;
		}
		else if ($TipoReg == 'M')
		{
			$Rodape .= ISO8859_1toModel(trim(substr($sReg, 1, 255))).' ';
		}
	}

	if ($UsingXmlModel)
	{
		if ($bTemImoveis)
			$model->parse('EXIBE_IMOVEIS');
		else
			$model->clear_dynamic('EXIBE_IMOVEIS');
		if ($bTemPorDia)
			$model->parse('EXIBE_POR_DIA');
		else
			$model->clear_dynamic('EXIBE_POR_DIA');
	}
	else
	{
		if ($bTemPorDia)
			$model->assign('TEM_PORDIA', 'T');
		else
		{
			$model->assign('DIA', '');
			$model->assign('VALOR', '');
			$model->assign('SITUACAO', '');
		}
	}

	fclose ($File);

	$model->assign('IMOB',$Imob); 
	$model->assign('DESCR', ISO8859_1toModel(Campo('DESC_SERV')));
	$model->assign('DATA_ARQUIVO', $DataArq);
	$model->assign('DATA_ATUAL', $DataAtual);
	$model->assign('USUARIO', $usuario);
	$model->assign('ASSESSOR_NOME', Campo('ASSESSOR'));
	$model->assign('MSG_RODAPE', $Rodape);

	$model->parse($Modelo);
	$model->DPrint($Modelo);
}
elseif ($Prod == 'WSI') // Consulta de inadimplencia como Web Service.
{
	$Modelo = 'webservice-inadimplencia';
	$model-> define_templates( array ($Modelo  => Modelo($DirModelos, $Modelo)) );

	$model->define_dynamic('EXIBE_INADIMP', $Modelo);
	$model->define_dynamic('INADIMP', 'EXIBE_INADIMP');

	$model->assign('DICA');
	$model->assign('CONTEUDO', 'INADIMPLENCIA_CONDOMINIO');

	$AuxVal = Campo('CODPESSOA');
	if (empty($AuxVal))
		$CodPessoa = $usuario_id;
	else if (strcasecmp($AuxVal, "TODOS") == 0)
		$CodPessoa = false;
	else
		$CodPessoa = array($AuxVal);

	$bTemInadimp = false;

	// Seleciona arquivos da ultima competencia gerada.
	$Chave = sprintf("NC%05d%s", $Condom, $Bloco);
	$BlocosSessao = GetSessao('blocos');
	$aBlocos = array();
	$Mes = date('m');
	$Ano = date('y');
	for ($i = 0; $i < 2; $i++)
	{
		$Ext = sprintf("%02d%02d", $Ano, $Mes);
		if (!empty($BlocosSessao))
		{
			foreach ($BlocosSessao as $UserFile) 
			{
				$arr = explode('.', $UserFile);
				if (empty($Bloco))
					$AuxVal = substr($arr[0], 0, 7);
				else
					$AuxVal = $arr[0];
				if ($AuxVal != $Chave)
					continue;

				if ($arr[1] == $Ext)
				{
					$UserFile = substr($UserFile, 1);
					$FilePath = $DirDados.$UserFile;
					$stat = @stat($FilePath);
					if ($stat !== false && $stat['size'] > 0)
					{
						$aBlocos[] = $UserFile;
						$DataArq = date("d/m/Y H:i", $stat['mtime']);
					}
				}
			}
		}
		if (!empty($aBlocos))
			break;
		if (--$Mes == 0)
		{
				$Mes = 12;
				$Ano--;
		}
	}

	if (empty($aBlocos))
	{
		Mensagem('ERRO', 'Dados não disponíveis no momento!');
		exit(1);
	}
	
	// Percorre arquivos pesquisando inadimplencia da pessoa em questao.
	foreach ($aBlocos as $UserFile) 
	{
		$FilePath = $DirDados.$UserFile;
		if (($File=fopen($FilePath, 'r')) === false)
		{
			Mensagem('Atenção', 'Dados não disponíveis no momento!');
			exit(1);
		}
	
		for(;;)
		{
			$sReg = fgets($File, 4098);
			if (empty($sReg))
				break;

			$TipoReg = substr($sReg, 0, 1);

			if ($TipoReg == ' ')	// Dados do Condominio
			{
				$model->assign('ASSESSOR_NOME', Campo('ASSESSOR'));
				$model->assign('COND_COD', trim(substr($sReg, 21, 5)));
				$model->assign('COND_NOME', ISO8859_1toModel(trim(substr($sReg, 26, 60))));
				$model->assign('BLOCO_COD', trim(substr($sReg, 86, 3)));
				$model->assign('BLOCO_NOME', ISO8859_1toModel(trim(substr($sReg, 89, 30))));
				$model->assign('BLOCOBASE_COD', trim(substr($sReg, 119, 3)));
				$model->assign('BLOCO_TIPO', trim(substr($sReg, 122, 1)));
				continue;
			}

			if ($TipoReg == 'C')	// Endereco do Bloco
			{
				$model->assign('BLOCO_ENDER', ISO8859_1toModel(trim(substr($sReg, 11, 125))));
				continue;
			}

			if ($TipoReg == 'I')	// Inadimplencia
			{
				$CodPessoa_reg = trim(substr($sReg, 219, 8));
				if (!empty($CodPessoa) && array_search($CodPessoa_reg, $CodPessoa) === false)
					// Nao e' a pessoa desejada.
					continue;

				$Data = substr($sReg, 1, 10);
				$Hist = trim(substr($sReg, 11, 80));
				$Debt = substr($sReg, 91, 12);
				$Cred = substr($sReg, 103, 12);
				$Adv = trim(substr($sReg, 257, 100));

				$model->assign('DATA', $Data);
				$model->assign('DEBT', MoneyFormat($Debt, 2));
				$model->assign('CRED', MoneyFormat($Cred));
				$model->assign('HIST', ISO8859_1toModel($Hist));
				$model->assign('TIPO_ECON', trim(substr($sReg, 115, 30)));
				$model->assign('TIPO_DOC', trim(substr($sReg, 145, 1)));
				$model->assign('COMPET', trim(substr($sReg, 146, 7)));
				$model->assign('MULTA', trim(substr($sReg, 153, 12)));
				$model->assign('JUROS', trim(substr($sReg, 165, 12)));
				$model->assign('CORRECAO', trim(substr($sReg, 177, 12)));
				$model->assign('NOSSO_NRO', trim(substr($sReg, 189, 20)));
				$model->assign('ID_ECON', trim(substr($sReg, 209, 10)));
				$model->assign('COD_PESSOA', $CodPessoa_reg);
				$model->assign('ADVOGADO_NOME', ISO8859_1toModel($Adv));
//echo "<!-- $CodPessoa_reg -->\n";
				$model->parse('.INADIMP');

				$bTemInadimp = true;
			}
		}
		fclose($File);
	}
	
	if ($bTemInadimp)
		$model->parse('EXIBE_INADIMP');
	else
		$model->clear_dynamic('EXIBE_INADIMP');

	$model->assign('DATA_ATUAL', $DataAtual);
	$model->assign('DATA_ARQUIVO', $DataArq);
	$model->assign('USUARIO', $usuario);

	$model->parse($Modelo);
	$model->DPrint($Modelo);
//echo '<!-- BlocosSessao: '; print_r($BlocosSessao); echo "\n-->\n";
//echo '<!-- Blocos: '; print_r($aBlocos); echo "\n-->\n";
}
elseif ($Prod == 'C' || $Prod == 'I')
{
/*
registro do extrato de condominio {
id_row   1;
data    10;
hist    80;
debt    12;
cred    12;
NewLine;
}*/
	// Prepara o modelo a ser preenchido.
	$iTamReg = 115;
	if (empty($Modelo))
	{
		if ($bExtratoCond_Unif)
		{
			if (file_exists($DirModelos.Modelo($DirModelos, 'extratocond_unif')))
				$Modelo = 'extratocond_unif';
			else
				// Nao existe modelo unificado.
				$bExtratoCond_Unif = false;
		}
		if (empty($Modelo))
			$Modelo = 'extratocond';
	}

	$model->define_templates( array ($Modelo  => Modelo($DirModelos, $Modelo)) );

	$model->define_dynamic('EXIBE_EXTRATO', $Modelo);

	if ($bExtratoCond_Unif || $UsingXmlModel)
	{
		// Trata-se de dois casos: 1) modelo unificado 'shtml' ou 'sxml' ;
		// 2) antigo modelo 'sxml' com blocos separados.
		if ($bExtratoCond_Unif)
		{ // Caso 1.
			$model->define_dynamic('BLOCO', 'EXIBE_EXTRATO');
			$bloco = 'BLOCO';
			$model->define_dynamic('EXTRATO', $bloco);
		}
		else
		{ // Caso 2.
			$model->define_dynamic('EXTRATO', 'EXIBE_EXTRATO');
			$bloco = $Modelo;
		}
		$model->define_dynamic('EXIBE_FUTUROS', $bloco);
		$model->define_dynamic('FUTUROS', 'EXIBE_FUTUROS');
		$model->define_dynamic('EXIBE_RESUMO', $bloco);
		$model->define_dynamic('RESUMO', 'EXIBE_RESUMO');
		$model->define_dynamic('EXIBE_RESUMO_REC', $bloco);
		$model->define_dynamic('RESUMO_REC', 'EXIBE_RESUMO_REC');
		$model->define_dynamic('EXIBE_RESUMO_ENT', $bloco);
		$model->define_dynamic('RESUMO_ENT', 'EXIBE_RESUMO_ENT');

		$model->define_dynamic('EXIBE_INADIMP', $Modelo);
		$model->define_dynamic('INADIMP', 'EXIBE_INADIMP');
		$model->define_dynamic('EXIBE_TOTALDOCS', $Modelo);
		$model->define_dynamic('TOTALDOCS', 'EXIBE_TOTALDOCS');
		$model->define_dynamic('EXIBE_LST_CONSULTORES', $Modelo);
		$model->define_dynamic('LST_CONSULTORES', 'EXIBE_LST_CONSULTORES');
		$model->define_dynamic('EXIBE_LST_NOTAS_FISCAIS', $Modelo);
		$model->define_dynamic('LST_NOTAS_FISCAIS', 'EXIBE_LST_NOTAS_FISCAIS');
		$model->define_dynamic('EXIBE_LST_MANUTENCOES', $Modelo);
		$model->define_dynamic('LST_MANUTENCOES', 'EXIBE_LST_MANUTENCOES');
	}
	else
	{
		// Trata-se do caso antigo: 3) modelo 'shtml' que exibe blocos separados.
		$model->define_dynamic('EXTRATO', $Modelo);
		$model->define_dynamic('FUTUROS', $Modelo);
		$model->define_dynamic('INADIMP', $Modelo);
		$model->define_dynamic('RESUMO', $Modelo);
		$model->define_dynamic('RESUMO_REC', $Modelo);
		$model->define_dynamic('RESUMO_ENT', $Modelo);
		$model->define_dynamic('TOTALDOCS', $Modelo);
		$model->define_dynamic('LST_CONSULTORES', $Modelo);
		$model->define_dynamic('LST_NOTAS_FISCAIS', $Modelo);
		$model->define_dynamic('LST_MANUTENCOES', $Modelo);
	}

	$bTemInadimp = false;
	$bTemTotalDocs = false;
	$contObsInadimpl = 0;
	$bTemConsultor = false;
	$bTemNotaFiscal = false;
	$bTemManutencao = false;

	$exibeTotalDocs = false;
	$exibeImagemLanctos = false;
	$exibeLanctosFuturos = false;
	$exibeObsInadimpl = (Configuracao('EXIBE_OBS_INADIMPLENCIA') == 'SIM');
	$exibeLstConsultores = (Configuracao('EXIBE_LiSTA_CONSULTORES') == 'SIM');
	$exibeLstNotasFiscais = (Configuracao('EXIBE_LiSTA_NOTAS_FISCAIS') == 'SIM');
	$exibeLstManutencoes = (Configuracao('EXIBE_LiSTA_MANUTENCOES') == 'SIM');
	
	if ($exibeObsInadimpl)
	{
		$maxObsInadimpl = intval(Configuracao('MAX_OBS_INADIMPLENCIA'));
		if (empty($maxObsInadimpl))
			$maxObsInadimpl = 99;
	}

	if ($Prod == 'I')	// Inadimplencia apenas
	{
		$exibeInadimpSeparado = false;
		if ($UsingXmlModel)
			$model->assign('CONTEUDO', 'INADIMPLENCIA_CONDOMINIO');
		else
		{
			$model->assign('TEM_EXTRATO', '');
			$model->clear_dynamic('EXTRATO');
		}
	}
	else
	{
		$exibeInadimpSeparado = (Configuracao("EXIBE_INADIMPL_SEPARADO") == "SIM");
		$exibeLanctosFuturos = (Configuracao('EXIBE_COND_LANCTOS_FUTUROS') != 'NAO');
		if (Configuracao('EXIBE_COND_TOTAL_DOCS') == 'SIM')
			$exibeTotalDocs = true;

		if (Configuracao('EXIBE_IMAGEM_LACTO') == 'SIM' || Configuracao('EXIBE_IMAGEM_LANCTO') == 'SIM')
		{
			$exibeImagemLanctos = true;
			$DirLanctos = Configuracao('DIR_LANCAMENTOS');
			SetSessao('tipo_lancamento', '');
		}
		if ($UsingXmlModel)
			$model->assign('CONTEUDO', 'EXTRATO_CONDOMINIO');
		else
			$model->assign('TEM_EXTRATO', 'T');
	}

	// Monta lista de blocos.
	$UserFile = 'C'.$Chave.'.'.$Ano.$Mes;
	$model->assign('DICA', $UserFile);
	$aBlocos[] = 'N'.$UserFile;
	if ($bExtratoCond_Unif)
	{
		// Veio bloco base na chave agora adiciona demais blocos.
		$BlocosSessao = GetSessao('blocos');
		$Condom = intval(substr($Chave,0,5));
		$Ext = $Ano.$Mes;
		foreach ($BlocosSessao as $UserFile) 
		{
			if ($Prod == 'I' && $UserFile{0} != 'N')
				continue;	// apenas blocos normais p/inadimplencia 
			$AuxVal = intval(substr($UserFile,2,5));
			if ($AuxVal != $Condom)
				continue;
			$arr = explode('.', $UserFile);
			if ($arr[1] == $Ext && array_search($UserFile, $aBlocos) === FALSE)
				$aBlocos[] = $UserFile;
		}
	}

	foreach ($aBlocos as $UserFile) 
	{
		// Abre o arquivo deste bloco.
		$UserFile = substr($UserFile, 1);
		$model->assign('BLOCO_ARQ', $UserFile);
		$FilePath = $DirDados.$UserFile;
		$stat = @stat($FilePath);
		if ($stat === false || $stat['size'] <= 0 || ($File=fopen($FilePath, 'r')) === false)
		{
			Mensagem('Atenção', 'Dados não disponíveis no momento!');
			exit(1);
		}
		if (empty($DataArq))
			$DataArq = date("d/m/Y H:i", $stat['mtime']);

		$bTemLancto = false;
		$bTemFuturos = false;
		$bTemResumo = false;
		$bTemResumoRec = false;
		$bTemResumoEnt = false;
		$Rodape = '';

		if ($bExtratoCond_Unif)
		{
			// Verifica se e' o arquivo de resumo de saldos que nao tem cabecalho.
			$arr = explode('.', $UserFile);
			if (substr($arr[0], 6) == 'XXX')
			{
				$model->assign('BLOCO_COD', 'XXX');
				$model->assign('BLOCO_NOME', 'RESUMO DE SALDOS');
				$model->assign('BLOCO_TIPO', ' ');
			}
		}

		for(;;)
		{
			$sReg = fgets($File, 4098);
//echo "<!-- $sReg -->\n";
			if (empty($sReg))
				break;

			$TipoReg = substr($sReg, 0, 1);

			if ($TipoReg == ' ')	// Dados do Condominio
			{
				$model->assign('ASSESSOR_NOME', Campo('ASSESSOR'));
				$model->assign('COND_COD', trim(substr($sReg, 21, 5)));
				$model->assign('COND_NOME', ISO8859_1toModel(trim(substr($sReg, 26, 60))));
				$model->assign('BLOCO_COD', trim(substr($sReg, 86, 3)));
				$model->assign('BLOCO_NOME', ISO8859_1toModel(trim(substr($sReg, 89, 30))));
				$model->assign('BLOCOBASE_COD', trim(substr($sReg, 119, 3)));
				$model->assign('BLOCO_TIPO', trim(substr($sReg, 122, 1)));
				continue;
			}
			
			if ($TipoReg == 'C')	// Endereco do Bloco
			{
				$model->assign('BLOCO_ENDER', ISO8859_1toModel(trim(substr($sReg, 11, 125))));
				continue;
			}
			
			if ($TipoReg == 'S')	// Dados do Sindico e Conselho
			{
				if ($bConselheiro)
					continue;
//echo "<!-- ";
				$conselho = explode(',', substr(trim($sReg),1));
				foreach ($conselho as $aux)
				{
					$aux = intval($aux);
//echo $aux.': ';
					if (is_array($usuario_id))
					{
//echo '{';
						for ($i = count($usuario_id)-1; $i >= 0; $i--)
						{
//echo $usuario_id[$i].' ';
							if ($aux == intval($usuario_id[$i]))
							{
								$bConselheiro = true;
								break;
							}
						}
//echo '} ';
					}
					else
					{
//echo ' '.$usuario_id;
						$bConselheiro = ($usuario_id == $aux);
					}
					if ($bConselheiro)
						break;
				}
//echo " Conselheiro:".$bConselheiro."-->\n";
				continue;
			}

			if ($TipoReg == 'B')	// Observacoes de Inadimplencia
			{
				if ($bConselheiro && $exibeObsInadimpl && $contObsInadimpl < $maxObsInadimpl)
				{
					$Hist = ISO8859_1toModel(trim(substr($sReg, 1, 4098)));
					$model->assign('DEBT', '');
					$model->assign('CRED', '');
					$model->assign('DATA', '');
					$model->assign('HIST', $Hist);
					$model->assign('LIQ', '');
					$model->parse($bTemInadimp?'.INADIMP':'INADIMP');
					$bTemInadimp = true;
					$contObsInadimpl++;
				}
				continue;
			}

			if (strlen($sReg) < $iTamReg)
				break;

			$Data = substr($sReg, 1, 6).substr($sReg, 9, 2);
			$Hist = ISO8859_1toModel(trim(substr($sReg, 11, 80)));
			$Debt = substr($sReg, 91, 12);
			$Cred = substr($sReg, 103, 12);

			if ($TipoReg ==  'D')	// Controle de DOCs
			{
				if ($exibeTotalDocs)
				{
					$model->assign('DOC_DESCR', $Hist);
					$model->assign('DOC_PERCENT', $Cred);
					$model->assign('DOC_TOTAL', MoneyFormat($Debt));
					$model->assign('DOC_QTDE', $Data);
					$model->parse($bTemTotalDocs?'.TOTALDOCS':'TOTALDOCS');
					$bTemTotalDocs = true;
				}
				continue;
			}

			$model->assign('DEBT', MoneyFormat($Debt, 2));
			$model->assign('CRED', MoneyFormat($Cred));
			$model->assign('DATA', $Data);
			$model->assign('HIST', $Hist);
			$model->assign('LIQ', '');

			if ($TipoReg == 'I')	// Inadimplencia
			{
				$contObsInadimpl = 0;
				if ($bConselheiro && !$exibeInadimpSeparado)
				{
					if ($UsingXmlModel)
					{
						$model->assign('TIPO_ECON', trim(substr($sReg, 115, 30)));
						$model->assign('TIPO_DOC', trim(substr($sReg, 145, 1)));
						$model->assign('COMPET', trim(substr($sReg, 146, 7)));
						$model->assign('MULTA', trim(substr($sReg, 153, 12)));
						$model->assign('JUROS', trim(substr($sReg, 165, 12)));
						$model->assign('CORRECAO', trim(substr($sReg, 177, 12)));
						$model->assign('NOSSO_NRO', trim(substr($sReg, 189, 20)));
						$model->assign('ID_ECON', trim(substr($sReg, 209, 10)));
						$model->assign('COD_PESSOA', trim(substr($sReg, 219, 8)));
					}
					$model->assign('DEBT', MoneyFormat($Debt, 0));
					$model->parse($bTemInadimp?'.INADIMP':'INADIMP');
					$bTemInadimp = true;
				}
				continue;
			}

			if ($Prod == 'I')
				// Deve apenas listar inadimplencia entao despreza demais tipos de registro.
				continue;

			if ($TipoReg == 'E')	// Lancamentos do Extrato
			{
				$iLancto = trim(substr($sReg, 115, 10));
				$Imagens = '';
				if ($exibeImagemLanctos && !empty($iLancto))
				{
					$aLanctos = ExisteImgLanctos($iLancto);
					if (!empty($aLanctos))
					{
						if ($UsingXmlModel)
						{
							$Imagens = sprintf("\n   <imagens>\n");
							foreach ($aLanctos as $img)
								$Imagens .= sprintf("    <imagem>%s</imagem>\n", GetFullUrl($img));
							$Imagens .= "   </imagens>";
						}
						else
						{
							$sImg = '<input type="image" height="20" src="'.$DirImagens.'maquina.png" width="25" border="0" onclick="return wopen('.$iLancto.')" title="Clique para visualizar a imagem deste lan&ccedil;amento.">';
							$model->assign('HIST', $sImg.$Hist);
						}
					}
				}
				if ($UsingXmlModel)
				{
					$Saldo = substr($sReg, 125, 12);
					$model->assign('SALDO', MoneyFormat($Saldo));
					$model->assign('NRO_LANCTO', $iLancto);
					$model->assign('IMAGENS_LANCTO', $Imagens);
				}
				$model->parse($bTemLancto?'.EXTRATO':'EXTRATO');
				$bTemLancto = true;
			}
			else if ($TipoReg == 'F')	// Lancamentos Futuros
			{
				if ($exibeLanctosFuturos)
				{
					if ($UsingXmlModel)
						$model->assign('NRO_LANCTO', $iLancto);
					$model->parse($bTemFuturos?'.FUTUROS':'FUTUROS');
					$bTemFuturos = true;
				}
			}
			else if ($TipoReg == 'T')	// Resumo de Taxas de Despesas
			{
				$model->parse($bTemResumo?'.RESUMO':'RESUMO'); 
				$bTemResumo = true;
			}
			else if ($TipoReg == 'R')	// Resumo de Taxas de Receitas
			{
				$model->parse($bTemResumoRec?'.RESUMO_REC':'RESUMO_REC'); 
				$bTemResumoRec = true;
			}
			else if ($TipoReg == 'N')	// Resumo de Entradas
			{
				$model->parse($bTemResumoEnt?'.RESUMO_ENT':'RESUMO_ENT');
				$bTemResumoEnt = true;
			}
			else if ($TipoReg == 'P')
			{
				$Rodape .= ' '.ISO8859_1toModel(trim(substr($sReg, 1, 255)));
			}
			else if ($TipoReg == 'A')	// Lista de Consultores
			{
				if ($exibeLstConsultores)
				{
					$model->assign('ATUACAO', trim(substr($sReg, 1, 30)));
					$model->assign('NOME', ISO8859_1toModel(trim(substr($sReg, 31, 40))));
					$model->assign('EMAIL', trim(substr($sReg, 71, 64)));
					$model->assign('TELEFONE', trim(substr($sReg, 135, 30)));
					$model->parse('.LST_CONSULTORES');
					$bTemConsultor = true;
				}
				continue;
			}
			else if ($TipoReg == 'L')	// Lista de Notas Fiscais
			{
				if ($exibeLstNotasFiscais)
				{
					$model->assign('DATAPAGTO', trim(substr($sReg, 1, 10)));
					$model->assign('DESCRICAO', ISO8859_1toModel(trim(substr($sReg, 11, 80))));
					$model->assign('VLRORIGINAL', trim(substr($sReg, 91, 12)));
					$model->assign('RETENCAOINSS', trim(substr($sReg, 103, 12)));
					$model->assign('RETENCAOIRF', trim(substr($sReg, 115, 12)));
					$model->assign('RETENCAOFEDERAL', trim(substr($sReg, 127, 12)));
					$model->assign('RETENCAOISSQN', trim(substr($sReg, 139, 12)));
					$model->assign('VALORNOTA', trim(substr($sReg, 151, 12)));
					$model->assign('DESCONTO', trim(substr($sReg, 163, 12)));
					$model->parse('.LST_NOTAS_FISCAIS');
					$bTemNotaFiscal = true;
				}
				continue;
			}
			else if ($TipoReg == 'M')	// Lista de Manutencoes
			{
				if ($exibeLstManutencoes)
				{
					$model->assign('DATAFIM', trim(substr($sReg, 1, 10)));
					$model->assign('FORNECEDOR', ISO8859_1toModel(trim(substr($sReg, 11, 64))));
					$model->assign('OPERACAO', trim(substr($sReg, 75, 50)));
					$model->parse('.LST_MANUTENCOES');
					$bTemManutencao = true;
				}
				continue;
			}
		}

		// Terminou de ler o arquivo.
		fclose ($File);
		$model->assign('DEBT', '');
		$model->assign('CRED', '');
		$model->assign('DATA', '');
		$model->assign('HIST', '');
		$model->assign('LIQ', '');
		$model->assign('MSG_RODAPE', $Rodape);

		if ($UsingXmlModel)
		{
			$model->parse('EXIBE_RESUMO');
			if (!$bTemResumo)
				$model->clear_dynamic_output('EXIBE_RESUMO');

			$model->parse('EXIBE_RESUMO_REC');
			if (!$bTemResumoRec)
				$model->clear_dynamic_output('EXIBE_RESUMO_REC');

			$model->parse('EXIBE_RESUMO_ENT');
			if (!$bTemResumoEnt)
				$model->clear_dynamic_output('EXIBE_RESUMO_ENT');

			$model->parse('EXIBE_FUTUROS');
			if (!$bTemFuturos)
				$model->clear_dynamic_output('EXIBE_FUTUROS');

			if ($Prod == 'I')
			{
				$model->parse('EXIBE_EXTRATO');
				$model->clear_dynamic_output('EXIBE_EXTRATO');
			}
			else
			{
				if (!$bTemLancto)
				{
					$model->assign('HIST', 'NADA CONSTA');
					$model->parse('EXTRATO');
				}
				if ($bExtratoCond_Unif)
					$model->parse('.BLOCO');
				$model->parse('EXIBE_EXTRATO');
			}
		}
		else
		{
			echo "<!-- $UserFile -->\n";
			if (!$bTemResumo)
				$model->clear_dynamic_output('RESUMO');
			$model->assign('TEM_RESUMO', $bTemResumo ? 'T' : '');

			if (!$bTemResumoRec)
				$model->clear_dynamic_output('RESUMO_REC');
			$model->assign('TEM_RESUMO_REC', $bTemResumoRec ? 'T' : '');

			if (!$bTemResumoEnt)
				$model->clear_dynamic_output('RESUMO_ENT');
			$model->assign('TEM_RESUMO_ENT', $bTemResumoEnt ? 'T' : '');

			if (!$bTemFuturos)
				$model->clear_dynamic_output('FUTUROS');
			$model->assign('TEM_FUTUROS', $bTemFuturos ? 'T' : '');
		}
	}

	// Encerrou acesso aos arquivos.
	if ($UsingXmlModel)
	{
		if ($bExtratoCond_Unif)
		{
			if ($Prod == 'I')
				$model->clear_dynamic_output('EXIBE_EXTRATO');
			else
				$model->parse('EXIBE_EXTRATO');
		}

		if ($bTemInadimp)
			$model->parse('EXIBE_INADIMP');
		else
			$model->clear_dynamic('EXIBE_INADIMP');

		if ($bTemTotalDocs)
			$model->parse('EXIBE_TOTALDOCS');
		else
			$model->clear_dynamic('EXIBE_TOTALDOCS');

		if ($bTemConsultor)
			$model->parse('EXIBE_LST_CONSULTORES');
		else
			$model->clear_dynamic('EXIBE_LST_CONSULTORES');

		if ($bTemNotaFiscal)
			$model->parse('EXIBE_LST_NOTAS_FISCAIS');
		else
			$model->clear_dynamic('EXIBE_LST_NOTAS_FISCAIS');

		if ($bTemManutencao)
			$model->parse('EXIBE_LST_MANUTENCOES');
		else
			$model->clear_dynamic('EXIBE_LST_MANUTENCOES');

	}
	else
	{
		if (!$bTemInadimp)
		{
			$model->assign('HIST', 'NADA CONSTA');
			$model->parse('INADIMP');
		}
		$model->assign('TEM_INADIMP', $bTemInadimp ? 'T' : '');

		if (!$bTemTotalDocs)
			$model->clear_dynamic('TOTALDOCS');
		$model->assign('TEM_TOTALDOCS', $bTemTotalDocs ? 'T' : '');

		if (!$bTemConsultor)
			$model->clear_dynamic('LST_CONSULTORES');
		$model->assign('TEM_CONSULTOR', $bTemConsultor ? 'T' : '');

		if (!$bTemNotaFiscal)
			$model->clear_dynamic('LST_NOTAS_FISCAIS');
		$model->assign('TEM_NOTA_FISCAL', $bTemNotaFiscal ? 'T' : '');

		if (!$bTemManutencao)
			$model->clear_dynamic('LST_MANUTENCOES');
		$model->assign('TEM_MANUTENCAO', $bTemManutencao ? 'T' : '');
	}
	
	$model->assign('IMOB',$Imob); 
	$model->assign('DESCR', ISO8859_1toModel(Campo('DESC_SERV')));
	$model->assign('DATA_ATUAL', $DataAtual);
	$model->assign('DATA_ARQUIVO', $DataArq);
	$model->assign('USUARIO', $usuario);

	$model->parse($Modelo);
	$model->DPrint($Modelo);
//if(isset($BlocosSessao)){echo '<!-- BlocosSessao: '; print_r($BlocosSessao); echo "\n-->\n";}
//echo '<!-- aBlocos: '; print_r($aBlocos); echo "\n-->\n";
}
elseif ($Prod == 'A')
{
/*
registro do extrato de locacao {
id_row   1;
data    10;
hist    80;
debt    12;
cred    12;
competencia    6;
NewLine;
}*/
	$iTamReg = 122;
	if (empty($Modelo))
		$Modelo = 'extratolanalit';

	$model-> define_templates( array ($Modelo => Modelo($DirModelos, $Modelo) ));

	if ($UsingXmlModel)
	{
		$model->assign('DICA', $UserFile);
		$model->define_dynamic('EXTRATO', $Modelo);
		$model->define_dynamic('EXIBE_FUTUROS', $Modelo);
		$model->define_dynamic('FUTUROS', 'EXIBE_FUTUROS');
		$model->define_dynamic('EXIBE_RESUMO', $Modelo);
		$model->define_dynamic('RESUMO', 'EXIBE_RESUMO');
	}
	else
	{
		$model->define_dynamic('EXTRATO', $Modelo);
		$model->define_dynamic('FUTUROS', $Modelo);
		$model->define_dynamic('RESUMO', $Modelo);
		$model->assign('TEM_INADIMP', '');
		$model->assign('TEM_FUTUROS', '');
		$model->assign('TEM_RESUMO', '');
		echo "<!-- $UserFile -->\n";
	}

	$bTemResumo = false;
	$bTemFuturos = false;
	$bTemInadimp = false;
	fseek ($File, 0);
	for(;;)
	{
		$sReg = fgets($File, 4098);
		if (empty($sReg) || strlen($sReg) < $iTamReg+1)
			break;

		$TipoReg = substr($sReg, 0, 1);
		$Data = substr($sReg, 1, 6).substr($sReg, 9, 2);
		$Hist = ISO8859_1toModel(trim(substr($sReg, 11, 80)));
		$Compet = substr($sReg, 115, 7);
		if ($Ano == 2 and $Mes <= 9)
		{
			$Debt = substr($sReg, 91, 12)/100;
			$Cred = substr($sReg, 103, 12)/100;
		}
		else
		{
			$Debt = substr($sReg, 91, 12);
			$Cred = substr($sReg, 103, 12);
		}

		$model->assign('DEBT', MoneyFormat($Debt, 2));
		$model->assign('CRED', MoneyFormat($Cred));
		$model->assign('DATA', $Data);
		$model->assign('HIST', $Hist);
		$model->assign('COMPET', $Compet);
		$model->assign('LIQ', '');

		if ($TipoReg == 'E')
			$model->parse('.EXTRATO');
		else if ($TipoReg == 'F')
		{
			$model->parse('.FUTUROS'); 
			$bTemFuturos = true;
		}
		else if ($TipoReg == 'T')
		{
			$model->parse('.RESUMO');
			$bTemResumo = true;
		}
	}

	$model->assign('DEBT', '');
	$model->assign('CRED', '');
	$model->assign('DATA', '');
	$model->assign('HIST', '');
	$model->assign('COMPET', '');
	$model->assign('LIQ', '');

	if ($UsingXmlModel)
	{
		if ($bTemResumo)
			$model->parse('EXIBE_RESUMO');
		else
			$model->clear_dynamic('EXIBE_RESUMO');

		if ($bTemFuturos)
			$model->parse('EXIBE_FUTUROS');
		else
			$model->clear_dynamic('EXIBE_FUTUROS');
	}
	else
	{
		$model->assign('IMOB',$Imob);
		$model->assign('DESCR', isset($_POST['DESC_SERV']) ? ISO8859_1toModel($_POST['DESC_SERV']) : '');

		if (!$bTemResumo)
			$model->clear_dynamic('RESUMO');
		$model->assign('TEM_RESUMO', $bTemResumo ? 'T' : '');

		if (!$bTemFuturos)
			$model->clear_dynamic('FUTUROS');
		$model->assign('TEM_FUTUROS', $bTemFuturos ? 'T' : '');
	}

	fclose ($File);

	$model->assign('ASSESSOR_NOME', Campo('ASSESSOR'));
	$model->assign('DATA_ATUAL', $DataAtual);
	$model->assign('DATA_ARQUIVO', $DataArq);
	$model->assign('USUARIO', $usuario);

	$model->parse($Modelo);
	$model->DPrint($Modelo);
}

/*
echo "<!--\n";
echo '$_SESSION = ';
print_r($_SESSION);
global $HTTP_SESSION_VARS;
echo '$HTTP_SESSION_VARS = ';
print_r($HTTP_SESSION_VARS);
echo "\n-->\n";
*/

session_write_close();

//if (!empty($sReg)) echo "\n<html><hr><center><h4>INFORMAO TRUNCADA!<br>Contacte a Imobiliria.</h4></center></html>\n";

if (!$UsingXmlModel)
	echo "\n<!-- $Modelo.shtml -->\n";
?>
