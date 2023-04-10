<?php

include 'msg.php';
include 'log.php';
include 'pesqCli.php';

$DirDados = Configuracao('DIR_DADOS');
$DirModelos = Configuracao('DIR_MODELOS_AREACLIENTE');
$DirAnexos = Configuracao('DIR_ANEXOS');
$ServicosExtras = (Configuracao('SERVICOS_EXTRAS_COND') == 'SIM');
$ExibePlanilhaGas = (Configuracao('PLANILHA_GAS_COND') == 'SIM');
$ExibeAnexosCond = (Configuracao('EXIBE_COND_ANEXOS') == 'SIM');
$ExibeAnexosLoc = (Configuracao('EXIBE_LOC_ANEXOS') == 'SIM');
$ListaCondominos = (Configuracao('EXIBE_LISTA_CONDOMINOS') == 'SIM');
$BoletosEmArvore = (Configuracao('BOLETOS_EM_ARVORE') == 'SIM');
$DirBoletos = Configuracao('DIR_BOLETOS');
$ExtratoComOrdemBloco = (Configuracao('EXTRATO_ORDEMBLOCO') == 'SIM');
$MesesNoSite = Configuracao('MESES_NO_SITE', 1);
$MesesNoSiteBoleto = Configuracao('MESES_NO_SITE_BOLETOS', $MesesNoSite);
$MesesNoSiteBoletoLoc = Configuracao('MESES_NO_SITE_BOLETOS_LOC', $MesesNoSiteBoleto);
$MesesNoSiteBoletoCond = Configuracao('MESES_NO_SITE_BOLETOS_COND', $MesesNoSiteBoleto);
$MesesNoSiteCond = Configuracao('MESES_NO_SITE_EXTRATOCOND', $MesesNoSite);
$MesesNoSiteLoc = Configuracao("MESES_NO_SITE_DEMONSTRATIVOLOC", $MesesNoSite);
$ExibeExtratoCondAtual = (Configuracao('EXIBE_EXTRATOCOND_ATUAL', 'SIM') == 'SIM');
$TrocaSenha = (Configuracao('TROCA_SENHA', 'SIM') == 'SIM');
$ExibeDocRetido = (Configuracao('EXIBE_DOC_RETIDO', 'SIM') == 'SIM');
$ExibeResumos = (Configuracao('EXIBE_RESUMOS_COND', 'NAO') == 'SIM');

$CondominiosDoUsuario = array();

//--------------------------------------------------------------------------------
//	Obtem o campo do arquivo de boleto
function GetField($handle, $field)
{
	$Valor = '';
	rewind($handle);
	while (!feof ($handle))
	{
		$buffer = fgets($handle, 1024);
		if (empty($buffer))
			break;

		$pos = strpos($buffer, '=');
		if ($pos === false)
			continue;
		$var = substr($buffer, 0, $pos);
		if ($var == $field)
		{
			$Valor = trim(substr($buffer, $pos+1));
			break;
		}
	}

	return $Valor;
}

//----------------------------------------------------------------------------------
function ShowServices(&$model, &$aServicos)
{
	global $UsingXmlModel;

	foreach ($aServicos as $aServ)
	{
		$Tipo = isset($aServ['TIPO']) ? $aServ['TIPO'] : '';
		$model->assign('PRODUTO', ISO8859_1toModel($aServ['PRODUTO']));
		$model->assign('PROD', $aServ['PROD']);
		$model->assign('DESCR', ISO8859_1toModel($aServ['DESCR']));
		$model->assign('TIPO', $Tipo);
		$model->assign('DESCR_PARAM', $aServ['DESCR_PARAM']);
		$model->assign('CHAVE', $aServ['CHAVE']);
		$model->assign('BTN_PHP', $aServ['BTN_PHP']);
		$model->assign('DESC_SERV', $UsingXmlModel ? urlencode($aServ['DESC_SERV']) : ISO8859_1toModel($aServ['DESC_SERV']));

		$botoes = $aServ['BOTOES'];
		foreach ($botoes as $aBotao)
		{
			$model->assign('BTN_SEQ', $aBotao['BTN_SEQ']);
			$model->assign('BTN_ARQ', $aBotao['BTN_ARQ']);
			// Forma nova com a tag do botao no shtml
			$model->assign('BTN_VAL', $aBotao['BTN_VAL']);
			$model->assign('BTN_VAL_EXTRA', isset($aBotao['BTN_VAL_EXTRA']) ? $aBotao['BTN_VAL_EXTRA'] : '');
			// Forma antiga com toda tag do botao
			$model->assign('BTN_MES', $aBotao['BTN_MES']);
			$model->parse('LISTA_BOTOES');
		}
		
		$model->parse('.LISTA_SERVICOS');
	}
}

//----------------------------------------------------------------------------------
// 	Verifica se o usuario tem anexos de locacao.
function GetAnexosLoc(&$model, $Id)
{
	GLOBAL $DirDados, $DirAnexos;

//echo "<!-- GetAnexosLoc($Id)\n";
	$File = @fopen($DirDados.'AcessoLoc2.csv', 'r');
	if ($File === FALSE)
		return false;

	$OpcoesExtras = array();
	$aAnexosPropr = array();
	$aAnexosLocat = array();
	$aAnexosProprCad = array();
	$Id = intval($Id);
	$bAnexo = false;

	while (!feof ($File)) {
		$aReg = fgetcsv($File, 1024, ';');
		$Prod = '';
		$cod = intval($aReg[0]);
//print_r($aReg); echo "\n";
		if ($cod == $Id)
		{
//echo 'ACHOU==> '; print_r($aReg); echo "\n";
			$Oper = $aReg[1];
			if ($Oper == 'LC')
			{
				$Chave = $aReg[3];
				$Descr = $aReg[5];
				$Tipo = 'Locatario';
				$Param = 'imovel';
				$Subdir = "Locacao/ContratoLoc/$Chave/$aReg[4]";
				if (is_dir($DirAnexos.$Subdir))
				{
					$Prod = 'Contrato de Locação';
					$aAnexosLocat[$Chave] = array($Subdir);
				}
			}
			else if ($Oper == 'PA')
			{
				$Chave = $aReg[2];
				$Descr = $aReg[5];
				$Tipo = 'Proprietario';
				$Param = 'contratoadm';
				$Subdir = "Locacao/ContratoAdm/$Chave/0";
				if (is_dir($DirAnexos.$Subdir))
				{
					$aAnexosPropr[$Chave] = array($Subdir);
					$Prod = 'Contrato de Administração';
				}
			}
			else if ($Oper == 'PC')
			{
				$Chave = $aReg[2];
				$Descr = $aReg[5];
				$Tipo = 'Proprietario';
				$Param = 'cadastro';
				$Subdir = "Locacao/Proprietario/$cod/0";
				if (is_dir($DirAnexos.$Subdir))
				{
					$aAnexosProprCad[$Chave] = array($Subdir);
					$Prod = 'Cadastro de Proprietário';
				}
			}
			else if ($Oper == 'PL')
			{
				$Chave = $aReg[2];
				$Imovel = $aReg[3];
				$ContratoLoc = $aReg[4];
				$bNovaOpcao = false;
				if ($ContratoLoc > 0)
				{
					$Subdir = "Locacao/ContratoLoc/$Imovel/$ContratoLoc";
					if (is_dir($DirAnexos.$Subdir))
					{
						$bNovaOpcao = !array_key_exists($Chave, $aAnexosPropr);
//echo "bNovaOpcao=$bNovaOpcao\n";
						$aAnexosPropr[$Chave][] = $Subdir;
					}
				}
				$Subdir = "Locacao/Imovel/$Imovel/0";
				if (is_dir($DirAnexos.$Subdir))
				{
					$bNovaOpcao = $bNovaOpcao || !array_key_exists($Chave, $aAnexosPropr);
//echo "bNovaOpcao=$bNovaOpcao\n";
					$aAnexosPropr[$Chave][] = $Subdir;
				}
				if ($bNovaOpcao)
					/// Proprietario tem anexo digitalizado dos imoveis mas não do contrato de administracao.
					$Prod = 'Contrato de Administração';
			}
			else
				continue;

			if (!empty($Prod))
			{
				$OpcoesExtras[] = array(
					'PRODUTO'=>$Prod, 
					'PROD'=>$Oper,
					'DESCR'=>$Descr, 
					'DESC_SERV'=>'',
					'DESCR_PARAM'=>' '.$Param.'="'.$Chave.'"',
					'TIPO'=>$Tipo,
					'CHAVE'=>$Chave,
					'BTN_PHP'=>'anexo.php', 
					'BOTOES'=>array(
						array(
						'BTN_VAL'=>'Documentos',
						'BTN_SEQ'=>$aReg[4], 
						'BTN_ARQ'=>$Subdir, 
						'BTN_MES'=>'')));
				$bAnexo = true;
			}
		}
		else if ($cod > $Id)
			break;
	}
//echo "\n-->\n";

	if ($bAnexo)
	{
		ShowServices($model, $OpcoesExtras);
		SetSessao('anexos_propr_cad', $aAnexosProprCad);
		SetSessao('anexos_propr', $aAnexosPropr);
		SetSessao('anexos_locat', $aAnexosLocat);
	}
//echo '<!-- Anexos Propr: '; print_r($aAnexosPropr); echo "\n-->\n";
//echo '<!-- Anexos Locat: '; print_r($aAnexosLocat); echo "\n-->\n";
	
	return $bAnexo;
}

//----------------------------------------------------------------------------------
//	Verifica se o usuario tem observacoes de comercializacao.
function GetObsComerc(&$model, $pId)
{
	GLOBAL $DirDados, $UsingXmlModel;

	$bObs = false;
	if (Configuracao('EXIBE_OBS_COMERCIALIZACAO') == 'SIM')
	{
		$file = sprintf('%sobscomerc/P%08d.txt', $DirDados, $pId);
		$bObs = is_file($file);
	}

	$model->define_dynamic('OBS_COMERCIALIZACAO', 'clionline');
	$model->assign('OBS_ID', $pId);
	if ($UsingXmlModel)
	{
		if ($bObs)
			$model->parse('OBS_COMERCIALIZACAO');
		else
			$model->clear_dynamic('OBS_COMERCIALIZACAO');
	}
	else
		$model->assign('TEM_OBSCOMERC', $bObs? 'T' : '');

	return $bObs;
}

//----------------------------------------------------------------------------------
//	Busca todos os boletos do usuario.
//	Obs. Estes arquivos são gerados com "html entities"
function GetBoletos(&$model, $pId)
{

	GLOBAL $DirBoletos, $UsingXmlModel, $BoletosEmArvore, $CondominiosDoUsuario, $ExibeDocRetido;
	GLOBAL $MesesNoSiteBoletoLoc, $MesesNoSiteBoletoCond; 

//echo "<!-- GetBoletos($pId) -->\n";
	$bTemBoleto = false;
	if ($BoletosEmArvore)
		$dir = sprintf('%s%05d000/0%d00/%d', $DirBoletos, $pId/1000, ($pId%1000)/100, $pId);
	else
		$dir = $DirBoletos.$pId;
//echo "<!-- DIR=$dir\n";
	if (is_dir($dir))
	{
		$Bloquetos = array();
		$fh = opendir($dir);
		while (false !== ($dirEntry = readdir($fh)))
		{
			if ($dirEntry == '.' || $dirEntry == '..')
				continue;
			if (strstr($dirEntry, '.txt') != '.txt')
				continue;
//echo "\n$dirEntry: ";
			$filename = $dir.'/'.$dirEntry;
			$stat = @stat($filename);
			if ($stat === false || $stat['size'] <= 0)
				continue;

			if (($handle = @fopen ($filename, 'r')) === false)
				continue;

			$param = '';
			if (is_numeric(substr($dirEntry, 0, 1)))
				$Codigo = substr($dirEntry, 0, -4);
			else
				$Codigo = substr($dirEntry, 1, -4);

			$competencia = strstr($dirEntry, 'E');
			if (empty($competencia))
			{
				$docextra = '';
				$bIsDocExtra = false;
				$competencia = strstr($dirEntry, 'N');
				if (!empty($competencia))
					$Codigo = substr($Codigo, 0, -7);
			}
			else
			{
				// DOC extra
				$docextra = ' EXTRA';
				$bIsDocExtra = true;
				$Codigo = substr($Codigo, 0, -9);
			}

			$Tipo = GetField($handle, 'TIPO');
			if ($Tipo == 'L')
			{
				$TipoDoc = "DOC$docextra: Loca&ccedil;&atilde;o &nbsp;&nbsp;";
				$param = ' doc_tipo="L" imovel_codigo="'.intval($Codigo).'"';
				$Meses = $MesesNoSiteBoletoLoc;
			}
			else if ($Tipo == 'C')
			{
				// Extrai o codcondom
				$Aux = explode(':', GetField($handle, 'IsId1'));
				$CodCondom = intval($Aux[1]);
/* DESATIVADO
				// Verifica se condominio esta ativo
				if (!array_key_exists($CodCondom, $CondominiosDoUsuario))
				{
					fclose ($handle);
					continue;
				}
*/
				$TipoDoc = "DOC$docextra: Condom&iacute;nio &nbsp;&nbsp;";
				$param = ' doc_tipo="C" condominio="'.$CodCondom.'" economia_codigo="'.intval($Codigo).'"';
				$Meses = $MesesNoSiteBoletoCond;
			}
			else
			{
				fclose ($handle);
				continue;
			}

			if ($bIsDocExtra)
			{
				// DOC extra
				$ano = substr($competencia, 1, 4);
				$mes = substr($competencia, 5, 2);
				$dia = substr($competencia, 7, 2);
				if (!checkdate($mes,$dia,$ano))
				{
					fclose ($handle);
					continue;
				}

				$datalimite = mktime(0,0,0,$mes+$Meses,$dia,$ano);
				if (time() > $datalimite && strstr($filename, '.sempre.txt') != '.sempre.txt')
				{
					// Bloqueto extra ja' expirou e nao e' nome especial que indica ser 
					// sempre valido entao nao exibe e tenta remove-lo.
					fclose ($handle);
					@unlink($filename);
					continue;
				}

				$datalimite = mktime(0,0,0,$mes-1,$dia,$ano);
				if (time() <= $datalimite)
				{
					// Bloqueto extra de mes adiante nao exibe
//echo "datalimite=$datalimite ";
					fclose ($handle);
					continue;
				}
			}

			if (!$ExibeDocRetido && GetField($handle, 'IsRetido') == 'S')
			{
//echo "DOC RETIDO ";
				fclose ($handle);
				continue;
			}

			$sDataVenc = GetField($handle, 'IsVencto');
			$dia = substr($sDataVenc, 0, 2);
			$mes = substr($sDataVenc, 3, 2);
			$ano = substr($sDataVenc, 6, 4);
//echo "IsVencto=$dia/$mes/$ano (extraido de $sDataVenc) ";
			if (checkdate($mes,$dia,$ano) !== false)
			{
				$datalimite = mktime(0,0,0,$mes+$Meses,$dia,$ano);
				if (time() > $datalimite)
				{
					// Bloqueto ja' expirou entao nao exibe e tenta remove-lo
					if (strstr($filename, '.sempre.txt') != '.sempre.txt')
					{
						// Nao e' nome especial que indica ser sempre valido
						fclose ($handle);
						@unlink($filename);
						continue;
					}
				}
			}

			$Quitado = '';
			$IsCodBarra1 = GetField($handle, 'IsCodBarra1');
			if (empty($IsCodBarra1))
			{
				$DebConta = GetField($handle, 'IsDebConta');
				if (stristr($DebConta, 'Quitado') != false)
					$Quitado = '&nbsp;(QUITADO)';
			}
			$IsDataPagamento = GetField($handle, 'IsDataPagamento');
			if (empty($Quitado) && GetField($handle, 'IsDataVenc') == '01/01/1900' && empty($IsDataPagamento))
			{
				// Bloqueto foi cancelado entao nao exibe e tenta remove-lo
				fclose ($handle);
				@unlink($filename);
				continue;
			}

			$Aux = explode(':', GetField($handle, 'IsId2'));
			$Ender = trim($Aux[1]);
			if ($Tipo == 'C')
			{
				// Acrescenta nome da economia (AP, SALA, etc.)
				$Aux = explode(':', GetField($handle, 'IsId4'));
				if (isset($Aux[2]))
					// Tem outras informacoes no fim desta linha entao retira-las
					$extrair = strlen(strrchr($Aux[1], ' '));
				else
					$extrair = 0;
					
				if ($extrair > 0)
					$Ender .= ' - '.trim(substr($Aux[1], 0, -$extrair));
				else
					$Ender .= ' - '.trim($Aux[1]);
			}

			$Bloquetos[$filename] = array(
				'vencto' => HTMLtoModel("Vencimento: $sDataVenc<br>"),
				'ender' => HTMLtoModel($Ender.$Quitado),
				'extra' => $bIsDocExtra ? "S" : "N",
				'param' => $param,
				'tipo' => $Tipo,
				'tipoDoc' => HTMLtoModel($TipoDoc) );

			fclose ($handle);
			$bTemBoleto = true;
		}
		closedir($fh);

		$ultCod = 0;
		$contLoc = 0;
		$contCond = 0;
		krsort($Bloquetos);
//echo "\n"; print_r($Bloquetos); echo "\nMesesNoSiteBoletoLoc=$MesesNoSiteBoletoLoc, MesesNoSiteBoletoCond=$MesesNoSiteBoletoCond ==>\n";
		foreach ($Bloquetos as $file => $infos)
		{
			$aPath = explode('/', $file);
			$arq = $aPath[count($aPath)-1];
			$cod = intval(substr($arq, 1));
			$extra = $infos['extra'];
			$Tipo = $infos['tipo'];
			if ($extra == 'N')
			{
				// So faz contagem de DOC normal
				if ($Tipo == 'L')
				{ // Locacao
					if ($cod != $ultCod) 
						$contLoc = 1;
					else 
						$contLoc++;
					if ($contLoc > $MesesNoSiteBoletoLoc)
						continue;
				}
				else
				{ // Condominio
					if ($cod != $ultCod) 
						$contCond = 1;
					else 
						$contCond++;
					if ($contCond > $MesesNoSiteBoletoCond)
						continue;
				}
			}
//echo "$file($Tipo/$extra)\n";
			$model->assign('DESCR_PARAM', $infos['param']);
			$model->assign('VENC_BOLETO', $infos['vencto']);
			$model->assign('END_BOLETO', $infos['ender']);
			$model->assign('TIPO_BOLETO', $infos['tipoDoc']);
			$model->assign('TIPO', $Tipo == 'C' ? 'Condomino' : 'Locatario');
			$model->assign('FBOLETO', $file);
			$model->assign('BTN_SEGUNDAVIA', '<input type=submit value=" Exibir" name="btn"><BR><BR>');  // Forma antiga com toda tag do botao
			$model->parse('.LISTA_BOLETOS');
			$ultCod = $cod;
		}
	}
//echo "\n-->\n";

	return $bTemBoleto;
}

//----------------------------------------------------------------------------------
//	Obtem as planilhas de gas/agua
function GetPlanilhas(&$model, $CodCondom)
{
	GLOBAL $DirDados, $UsingXmlModel, $PlanilhasGeradas;
	
//echo "<!-- GetPlanilhas($CodCondom) -->\n";
	$model->assign('DESCR_PARAM','');
	$DirGasAgua = $DirDados.'gasagua/'.$CodCondom;
	if (file_exists($DirGasAgua))
	{
//echo "<!-- DirGasAgua=$DirGasAgua -->\n";
		// Acrescenta botao para exibir planilha de gas separadamente para o sindico.
		// Ex.: .../gasagua/00108/G00108UNC-201404.csv
		$dtAtual = date('Ym', mktime(0,0,0, date('m'), 1, date('y')));
		$dtAnt = date('Ym', mktime(0,0,0, date('m')-1, 1, date('y')));
		$CsvFiles = scandir($DirGasAgua);
		rsort($CsvFiles);
//echo "<!-- Arq's GasAgua\n";
//print_r($CsvFiles);
//echo "PlanilhasGeradas=$PlanilhasGeradas\n";
//echo "-->\n";

		for ($i = 0; $i < 2; $i++)
		{
		    $tipoPlan = ($i == 0 ? 'G' : 'A');
			$UltBloco = '';
		    $Cont = 0;
//echo "\n<!-- tipoPlan=$tipoPlan -->\n";

			foreach ($CsvFiles as $CsvFile)
			{
//echo "<!-- UserFile=$CsvFile -->\n";
				if ($CsvFile[0] != $tipoPlan[0] || !is_file("$DirGasAgua/$CsvFile"))
					continue;

				if (preg_match('/ '.substr($CsvFile,0,-11).' /', $PlanilhasGeradas))
					continue;

				$Bloco = substr($CsvFile, 6, 3);
				if ($Bloco == $UltBloco)
					continue;

				$dt = $dtAtual;
//echo '<!-- preg_match(/'.$tipoPlan.'.*-'.$dt.'\.csv/, "'.$CsvFile."\") -->\n";
				$bTem = preg_match('/'.$tipoPlan.'.*-'.$dt.'\.csv$/', $CsvFile);
				if (!$bTem)
				{
					$dt = $dtAnt;
//echo '<!-- preg_match(/'.$tipoPlan.'.*-'.$dt.'\.csv/, "'.$CsvFile."\") -->\n";
					$bTem = preg_match('/'.$tipoPlan.'.*-'.$dt.'\.csv$/', $CsvFile);
				}

				if ($bTem)
				{
//echo "<!-- [[ ACHOU planilha $CsvFile ]] -->\n";
					$UltBloco = $Bloco;
					$File = @fopen("$DirGasAgua/$CsvFile", 'r+');
					if ($File === FALSE)
						continue;
					$aReg = fgetcsv($File, 1024, ';');
					fclose($File);

					$PlanilhasGeradas .= substr($CsvFile,0,-11).' ';
					if ($Cont == 0)
					{
						$model->assign('PRODUTO', ISO8859_1toModel('Planilha de Consumo de '.($tipoPlan == 'G' ? 'Gás' : 'Água')));
						$model->assign('PROD', $tipoPlan);
						$model->assign('DESCR', ISO8859_1toModel($aReg[3].' '.$aReg[4])); // Nome do Condominio e Bloco
						$model->assign('BTN_PHP', 'planilhaGas.php');
						$model->assign('CHAVE', $CodCondom);
					}
					// Forma nova com a tag do botao no shtml
					$Bloco = $aReg[4].' - '.substr($dt,4,2).'/'.substr($dt,0,4); // Nome do Bloco e competencia
					if ($UsingXmlModel)
						$model->assign('BTN_VAL', ISO8859_1toModel($CsvFile.'|'.$Bloco));
					else
					{
						$model->assign('BTN_VAL', ISO8859_1toModel($Bloco));
						$model->assign('BTN_VAL_EXTRA', $CsvFile);
					}
					$model->assign('BTN_SEQ', 1);
					$model->assign('BTN_ARQ', $CsvFile);
					$model->parse($Cont == 0 ? 'LISTA_BOTOES' : '.LISTA_BOTOES');
					$model->assign('BTN_MES', ''); // Forma antiga com toda tag do botao
					if ($UsingXmlModel)
						$model->assign('TIPO', $CsvFile);
					else
						$model->assign('TIPO', '');
					$Cont++;
//echo "<!-- [[ Botao $Bloco ]] -->\n";
				}
//else echo "<!-- [[ NAO serve a planilha $CsvFile ]] -->\n";
			}

			if ($Cont != 0)
				$model->parse('.LISTA_SERVICOS');
		}
	}
}

//----------------------------------------------------------------------------------
function GetServices(&$model, $CondLoc, $pId, $bExtratoCond_Unif, &$aArquivos)
{
/*
	registro de acesso{
	id             10;
	produto         1;
	chave           8;
	assessor       35;
	tipo            1;
	assessor_email 50;
	descricao      80;
	condomino       1;      'S'indico; 'N'ormal;
	----> Formato original: 186 bytes ate aqui <----
	tipo_bloco      1;      'N'ormal; 'F'undo Reserva;
	ordembloco      2;
	filial          3;
}
*/
	GLOBAL $DirDados, $DirAnexos, $ServicosExtras, $ExtratoComOrdemBloco, $ExibeAnexosCond, 
			$ListaCondominos, $CondominiosDoUsuario, $ExibeExtratoCondAtual, $ExibeResumos,
			$MesesNoSite, $MesesNoSiteCond, $MesesNoSiteLoc, $ExibePlanilhaGas, $UsingXmlModel, 
			$aAnexosCond;

//echo "\n<!-- GetServices($CondLoc, $pId, $bExtratoCond_Unif) -->\n";
	$iTamReg = 186;
	$PlanilhasGeradas = ' ';

	if ($CondLoc == 'Cond')
	{
		$bInadimpSeparado = (Configuracao('EXIBE_INADIMPL_SEPARADO') == 'SIM');
		$MesesExibir = $MesesNoSiteCond;
		$ExibeExtratoAtual = $ExibeExtratoCondAtual;
		$aBlocos = GetSessao('blocos');
		$name = $DirDados.'AcessoCond.txt';
	}
	else
	{
		$bInadimpSeparado = false;
		$MesesExibir = $MesesNoSiteLoc;
		$ExibeExtratoAtual = true;
		$aBlocos = array();
		$name = $DirDados.'Acesso'.$CondLoc.'.txt';
	}

	$stat = @stat($name);
	if ($stat === false || $stat['size'] <= 0)
		return 0;

	$file = @fopen($name, 'r');
	if ($file === false)
		return 0;

	$bNenhum = true;
	$slast = '';
	$pId = intval(trim($pId));
	$UltAnexoCond = '';
	$UltLista = '';
	$UltPlanilha = '';
    $UltResumo = '';
	$OpcoesExtras = array();

	$cont = 1;
	for(;;)
	{
		$sReg = fgets($file, 4096);
		if (empty($sReg) || strlen($sReg) < $iTamReg+1)
			break;

		$Id = intval(trim(substr($sReg, 0, 10)));
		if ($Id > $pId)
			break;

		if ($Id == $pId)
		{
//echo "<!-- $sReg -->\n";
			$Tipo = substr($sReg, 54, 1);
			if ( strcmp($Tipo, 'P') == 0)
				$Tipo = 'Proprietario';
			elseif ( strcmp($Tipo, 'C') == 0)
				$Tipo = 'Condomino';
			elseif ( strcmp($Tipo, 'S') == 0)
				$Tipo = 'Sindico';

			// Verifica o produto.
			$TipoProd = substr($sReg, 10, 1);
			if ($TipoProd == 'C')
				$Produto = 'Extrato de Condomínio';
			elseif ($TipoProd == 'A')
				$Produto = 'Extrato de Proprietário';
			elseif ($TipoProd == 'S')
				$Produto = 'Demonstrativo Sintético';
			elseif ($TipoProd == 'L')
				$Produto = 'Demonstrativo de Proprietário';
			elseif ($TipoProd == 'I')
				$Produto = 'Inadimplências';
			else
				continue;

			$aChaves = array();
			$Assessor = substr($sReg, 19, 35);
			$EAssessor = substr($sReg, 55, 50);
			$Chave = trim(substr($sReg, 11, 8));
			$Descr = trim(substr($sReg, 105, 80));
			$Condomino = substr($sReg, 185, 1);
			$BlocoBase = trim(substr($sReg, 189, 3));
			SetSessao('tipo_cond', $Condomino);
			$model->assign('DESCR_PARAM','');
//echo "<!-- BlocoBase='$BlocoBase' -->\n";

			if ($CondLoc == 'Cond')
			{
				$CodCondom = intval(substr($Chave, 0, 5));
				if (!array_key_exists($CodCondom, $CondominiosDoUsuario))
				{
					// Inicio de tratamento de condominio.
					ShowServices($model, $OpcoesExtras);
					$OpcoesExtras = array();
				}
				
                $CodBloco = substr($Chave, 5, 3);
				$NomeCondom = trim(substr($sReg, 105, 39));
				$TipoBloco = trim(substr($sReg, 186, 1));
				if (empty($TipoBloco) || $TipoBloco == "\n")
					// Formato antigo entao assume como normal.
					$TipoBloco = ($CodBloco == 'XXX' ? ' ' : 'N');

				if ($ExtratoComOrdemBloco)
				{
					$Ordem = substr($sReg, 187, 2);
					if (!empty($Ordem))
						$Chave = sprintf('%s_%02d_%s', substr($Chave, 0, 5),$Ordem,$CodBloco);
				}
				if (array_key_exists($Chave, $aArquivos))
					// Ja' tem este acesso incluido na lista de servicos.
					continue;
				$aArquivos[$Chave] = $Descr;
				$aChaves[$Chave] = $Descr;

				if ($ServicosExtras)
				{
					$model->assign('CODIGO_CONDOMINIO', $CodCondom);
					$model->assign('NOME_CONDOMINIO', ISO8859_1toModel($NomeCondom));
					$model->assign('USUARIO_TIPO', $Condomino);
					$model->parse(count($CondominiosDoUsuario)==0 ? 'SERVICOS_EXTRAS_COND' : '.SERVICOS_EXTRAS_COND');
				}
				$CondominiosDoUsuario[$CodCondom] = array($NomeCondom);
				$model->assign('DESCR_PARAM', ' condominio="'.$CodCondom.'" bloco="'.$CodBloco.'" bloco_tipo="'.$TipoBloco.'"');
			}
			elseif ($CondLoc == 'Loc')
			{
				$TipoBloco = '?';
				$Filial = trim(substr($sReg, 186, 3));
				if (!empty($Filial))
				{
					$Descr .= " (Filial $Filial)";
					$Chave = $Chave.'-'.$Filial;
				}
				$aChaves[$Chave] = $Descr;
			}
			elseif ($CondLoc == 'Loc2')
			{
				$TipoBloco = '?';
				$name = $TipoProd.$Chave.'*.*';
				$aArqs = @glob($DirDados.$name);
//echo '<!-- $aArqs: '; print_r($aArqs); echo "\n-->\n";
				if (empty($aArqs))
					$aChaves[$Chave] = $Descr;
				else
				{
					foreach ($aArqs as $ArqDados)
					{
						$ArqDados = basename($ArqDados);
						$Chave = explode('.', $ArqDados);
						$Chave = substr($Chave[0], 1);
						$Filial = strstr($Chave, '-');
						if (empty($Filial))
							$aChaves[$Chave] = $Descr;
						else
							$aChaves[$Chave] = $Descr.' (Filial '.substr($Filial,1).')';
					}
					asort($aChaves);
				}
			}
//echo '<!-- $aChaves: '; print_r($aChaves); echo "\n-->\n";

			foreach ($aChaves as $Chave=>$Descr)
			{
//echo "<!-- Chave=$Chave -->\n";
				$model->assign('PRODUTO', ISO8859_1toModel($Produto));
				$model->assign('DESCR', ISO8859_1toModel($Descr));
				$model->assign('BTN_PHP', 'exibeDoc.php');
				$model->assign('ID', $Id);
				$model->assign('PROD', $TipoProd);
				$model->assign('ASSESSOR', ISO8859_1toModel(rtrim($Assessor)));
				$model->assign('ASSESSOR_EMAIL', rtrim($EAssessor));
				$model->assign('TIPO', $Tipo);
				$model->assign('TIPO_CONDOMINIO', $TipoBloco);
				$model->assign('TIPO_CONDOMINO', $Condomino);
				$model->assign('CHAVE', $Chave);
				$model->assign('DESC_SERV', $UsingXmlModel ? urlencode($Descr) : ISO8859_1toModel($Descr));

				// Monta lista de blocos com nome dos arquivos fisicos
				$btn = '';
				$mes = date('m');
				$ano = date('y');
//echo "<!-- $mes/$ano -->\n";
				$iContItem = 0;
				$iContExtra = 0;
				for ($i=0; $iContItem < $MesesExibir && $i <= $MesesExibir && $iContExtra < 2; $i++)
				{
					if ($ExibeExtratoAtual || $i > 0) {
						$dt = date('ym', mktime(0,0,0, $mes, 1, $ano));
						$ArqDados = $TipoProd.$Chave.'.'.$dt;
						if (@file_exists($DirDados.$ArqDados))
						{
							if (filesize($DirDados.$ArqDados) > 0)
							{
								if ($CondLoc == 'Cond')
								{
									if (array_search($TipoBloco.$ArqDados, $aBlocos) === FALSE)
										$aBlocos[] = $TipoBloco.$ArqDados;
									$Bloco = explode('.', $ArqDados);
									$Bloco = trim(substr($Bloco[0], 6));
								}
//echo "<!-- $ArqDados Bloco='$Bloco'-->\n";

								if ($CondLoc != 'Cond' || !$bExtratoCond_Unif || empty($BlocoBase) || strcmp($Bloco, $BlocoBase) == 0)
								{
									$data = substr($dt,2,2).'/20'.substr($dt,0,2);
									$btn .= "<input type=submit value=\" Exibir $data\" name=\"btn\" ><!-- $ArqDados ($iContItem)--><BR><BR>\n";

									// Forma nova com a tag do botao no shtml
									$model->assign('BTN_VAL', $data);
									$model->assign('BTN_SEQ', $iContItem+1);
									$model->assign('BTN_ARQ', $ArqDados);
									$model->parse($iContItem++==0 ? 'LISTA_BOTOES' : '.LISTA_BOTOES');
								}
							}
						}
						else
						{
//echo "<!-- NAO ABRIU $ArqDados -->\n";
							if ($iContItem == 0 && $i == $MesesExibir)
							{
								// Nao achou nenhum documento entao extende para competencia mais antiga.
								$MesesExibir++;
								$iContExtra++;
							}
						}
					}
					$mes--;
					if ($mes <= 0) {
						$mes = 12;
						$ano--;
					}
				}
				$model->assign('BTN_MES', $btn); // Forma antiga com toda tag do botao

				if ($iContItem > 0)
				{
					$model->parse('.LISTA_SERVICOS');
					$bNenhum = false;

					// Verificacoes adicionais para condominio.
					if ($TipoBloco == 'N')
					{
						$dt = date('ym', mktime(0,0,0, date('m'), 1, date('y')));
						$ArqDados = $TipoProd.$Chave.'.'.$dt;

						if ($bInadimpSeparado)
						{
							// Acrescenta botao para exibir inadimplencia separadamente para o sindico
							if (($File=@fopen($DirDados.$ArqDados, 'r')) !== false)
							{
								// Pula reg. header
								for (;;) {
									$sReg = fgets($File, 4098);
									if (empty($sReg) || substr($sReg, 0, 1) == 'S')
										break;
								}
								if (substr($sReg, 0, 1) == 'S')
								{
									// E' reg. de Sindico
									$bConselheiro = false;
									$conselho = explode(',', substr(trim($sReg),1));
									foreach ($conselho as $aux)
									{
										$aux = intval($aux);
										if ($aux == intval($Id))
										{
											$bConselheiro = true;
											break;
										}
									}

									if ($bConselheiro)
									{
										// Forma nova com a tag do botao no shtml
										$data = substr($dt,2,2).'/20'.substr($dt,0,2);
										// Forma antiga com toda tag do botao
										$btn = "<input type=submit value=\" Exibir $data\" name=\"btn\"><!-- $ArqDados --><BR><BR>\n";
										
										$OpcoesExtras[] = array(
											'PRODUTO'=>'Inadimplência', 
											'PROD'=>'I',
											'DESCR'=>$Descr, 
											'DESC_SERV'=>'',
											'DESCR_PARAM'=>' condominio="'.$CodCondom.'" bloco="'.$CodBloco.'" bloco_tipo="'.$TipoBloco.'"',
											'CHAVE'=>$Chave,
											'TIPO'=>$Tipo,
											'BTN_PHP'=>'exibeDoc.php',
											'BOTOES'=>array(
												array(
												'BTN_VAL'=>$data, 
												'BTN_SEQ'=>1, 
												'BTN_ARQ'=>$ArqDados, 
												'BTN_MES'=>$btn)));
									}
								}
								fclose($File);
							}
						}
						
						if ($ExibeResumos && $TipoProd == 'C' && $Condomino == 'S' && $CodCondom != $UltResumo)
						{
							// Acrescenta botao para exibir resumo de DOCs
							$ResumFile = 'RDOC'.str_pad($CodCondom, 5, '0', STR_PAD_LEFT);
							$arq = @fopen($DirDados.$ResumFile.'N.csv', 'r');
							if ($arq === false)
								$arq = @fopen($DirDados.$ResumFile.'E.csv', 'r');
							if ($arq !== false)
							{
								$aReg = fgetcsv($arq, 1024, ';');
								if ($aReg[0] == 'HE')	// Cabecalho
									$descrResumo = $aReg[4];
								else
									$descrResumo = $Descr;
								fclose($arq);

								$OpcoesExtras[] = array(
									'PRODUTO'=>'Resumo de DOCs', 
									'PROD'=>'RDOC',
									'DESCR'=>trim($descrResumo), 
									'DESC_SERV'=>'',
									'DESCR_PARAM'=>' condominio="'.$CodCondom.'"',
									'CHAVE'=>$Chave,
									'TIPO'=>$Tipo,
									'BTN_PHP'=>'resumosCond.php', 
									'BOTOES'=>array(
										array(
										'BTN_VAL'=>'', 
										'BTN_SEQ'=>1, 
										'BTN_ARQ'=>'', 
										'BTN_MES'=>'')));
							}

							// Acrescenta botao para exibir resumo de receitas e despesas
							$ResumFile = 'RRD'.str_pad($CodCondom, 5, '0', STR_PAD_LEFT).'.csv';
							$arq = @fopen($DirDados.$ResumFile, 'r');
							if ($arq !== false)
							{
								$aReg = fgetcsv($arq, 1024, ';');
								if ($aReg[0] == 'HE')	// Cabecalho
									$descrResumo = $aReg[4];
								else
									$descrResumo = $Descr;
								fclose($arq);

								$OpcoesExtras[] = array(
									'PRODUTO'=>'Resumo de Receitas e Despesas', 
									'PROD'=>'RRD',
									'DESCR'=>trim($descrResumo), 
									'DESC_SERV'=>'',
									'DESCR_PARAM'=>' condominio="'.$CodCondom.'"',
									'CHAVE'=>$Chave,
									'TIPO'=>$Tipo,
									'BTN_PHP'=>'resumosCond.php', 
									'BOTOES'=>array(
										array(
										'BTN_VAL'=>'', 
										'BTN_SEQ'=>1, 
										'BTN_ARQ'=>'', 
										'BTN_MES'=>'')));
							}

							$UltResumo = $CodCondom;
						}

						if ($ExibeAnexosCond && $TipoProd == 'C' && $CodCondom != $UltAnexoCond)
						{
							$DirAnexosCond = $DirAnexos.'Condom/'.$CodCondom;
							if (is_dir($DirAnexosCond))
							{
								$arq = @fopen($DirDados.$ArqDados, 'r');
								if ($arq !== false)
								{
									$sAux = fgets($arq, 4096);
									if ($sAux{0} == ' ')	// Cabecalho
										$Descr = substr($sAux, 26, 60);
									fclose($arq);
								}

								$OpcoesExtras[] = array(
									'PRODUTO'=>'Documentos do Condomínio (atas, convocações, etc.)', 
									'PROD'=>'C',
									'DESCR'=>trim($Descr), 
									'DESC_SERV'=>'',
									'DESCR_PARAM'=>' condominio="'.$CodCondom.'"',
									'CHAVE'=>$CodCondom,
									'TIPO'=>$Tipo,
									'BTN_PHP'=>'anexo.php', 
									'BOTOES'=>array(
										array(
										'BTN_VAL'=>'Documentos', 
										'BTN_SEQ'=>1, 
										'BTN_ARQ'=>$ArqDados, 
										'BTN_MES'=>'')));
								$aAnexosCond[$CodCondom] = array("Condom/$CodCondom");
							}
							$UltAnexoCond = $CodCondom;
						}

						$Planilha = substr($Chave, 0, 5);
						if ($ExibePlanilhaGas && $TipoProd == 'C' && $Condomino == 'S' && $Planilha != $UltPlanilha)
						{
	//echo "<!-- UltChave=$UltPlanilha -->\n";
							$UltPlanilha = $Planilha;
							GetPlanilhas($model, $Planilha);
						}

						if ($ListaCondominos && $TipoProd == 'C' && $Condomino == 'S' && $CodCondom != $UltLista)
						{
							// Acrescenta botao para exibir lista de condominos
							$ListFile = 'I'.str_pad($CodCondom, 5, '0', STR_PAD_LEFT).'.TXT';
							$arq = @fopen($DirDados.$ListFile, 'r');
							if ($arq !== false)
							{
								$Descr = fgets($arq, 4096);
								$TipoReg = $Descr{0};
								if ($TipoReg == 'C')	// Cabecalho
								{
									$Descr = substr($Descr, 6, 60);
									$iPos = strpos($Descr, '-');
									$Descr = trim(substr($Descr, $iPos+1));
								}
								fclose($arq);

								$OpcoesExtras[] = array(
									'PRODUTO'=>'Lista de Condôminos', 
									'PROD'=>'L',
									'DESCR'=>$Descr, 
									'DESC_SERV'=>'',
									'DESCR_PARAM'=>' condominio="'.$CodCondom.'"',
									'CHAVE'=>$Chave,
									'BTN_PHP'=>'condominos.php', 
									'BOTOES'=>array(
										array(
										'BTN_VAL'=>'',
										'BTN_SEQ'=>1, 
										'BTN_ARQ'=>$ListFile, 
										'BTN_MES'=>'')));
							}
							$UltLista = $CodCondom;
						}
					}
				}
			}
		}
	}

	ShowServices($model, $OpcoesExtras);

	fclose($file);
	if ($CondLoc == 'Cond')
	{
		SetSessao('blocos', $aBlocos);
		SetSessao('anexos_cond', $aAnexosCond);
//echo '<!-- Blocos: '; print_r($aBlocos); echo "\n-->\n";
//echo '<!-- Anexos Cond: '; print_r($aAnexosCond); echo "\n-->\n";
	}

	return (!$bNenhum);
}

//----------------------------------------------------------------------------------
//	Busca todos as informacoes de IR do usuario.
function GetInfoIR(&$model, $pId, $bMultiplos)
{
	GLOBAL $DirDados;

//echo("<!-- GetInfoIR(model, $pId, $bMultiplos) -->\n");
	if (Configuracao('EXIBE_IR_ANUAL') != 'SIM')
		return false;

	$DirIRanual = Configuracao('DIR_IRANUAL');
	$iContItem = 0;
	$bTemInfo = false;
	$pId = intval($pId);
	$dir = sprintf('%s%d000/%d', $DirIRanual, $pId/1000, $pId);
	if (is_dir($dir))
	{
		$files = scandir($dir);
		foreach ($files as $dirEntry)
		{
			if (intval(substr($dirEntry, 0, 8)) != $pId || strstr($dirEntry, '.csv') != '.csv')
				continue;

			$file = $dir.'/'.$dirEntry;
			$stat = @stat($file);
			if ($stat === false || $stat['size'] <= 0)
				continue;

			$bTemInfo = true;
			$Comp = substr($dirEntry, 9, 4);
			$model->assign('COMPETENCIA', $Comp);
			$model->assign('IR_ID', $bMultiplos ? 'Cod.'.$pId : '');
			$model->parse($iContItem++==0 ? 'LISTA_INFOS_IR' : '.LISTA_INFOS_IR');
		}
	}

	if ($bTemInfo)
	{
		$model->assign('ID', $pId);
		$model->assign('DESCR_PARAM', '');
		$model->parse('.INFOS_IR_ANUAL');
	}

//echo("<!-- GetInfoIR: $bTemInfo -->\n");
	return $bTemInfo;
}

//---main-------------------------------------------------------------------------

//Mensagem('Aviso', 'Site em manutenção!'); return 0;

	SetSessao('ORIGEM_MSG', 'O'); //cliente on-line
	if (isset($_SERVER['HTTP_REFERER']))
		SetSessao('ORIGEM_TELA', $_SERVER['HTTP_REFERER']); 

	$bExtratoCond_Unif = false;
	$pId = Campo('LOGIN');
	if (!empty($pId))
	{
		// Verifica se deve gerar XML.
		$XmlMode = (strcasecmp(Campo('FORMATO'), 'XML') == 0);
		SetSessao('XML_MODE', $XmlMode);

		// As paginas podem ser geradas com 3 diferentes charsets.
		$ModelCharset = Campo('CHARSET');
		if (empty($ModelCharset))
			$ModelCharset = $XmlMode ? CHARSET_ISO8859_1 : CHARSET_HTML;
		else if (strcasecmp($ModelCharset, 'ISO-8859-1') == 0 || strcasecmp($ModelCharset, 'ISO8859-1') == 0)
			$ModelCharset = CHARSET_ISO8859_1;
		else if (strcasecmp($ModelCharset, 'UTF-8') == 0 || strcasecmp($ModelCharset, 'UTF8') == 0)
			$ModelCharset = CHARSET_UTF8;
		else if (strcasecmp($ModelCharset, 'HTML') == 0)
			$ModelCharset = CHARSET_HTML;
		else
		{
			Mensagem('ERRO', 'CHARSET com valor inválido');
			exit;
		}
		SetSessao('MODEL_CHARSET', $ModelCharset);

		// Verifica se deve ser extrato de condominio unificado.
		if (strcasecmp(Campo('EXTRATOCOND'), 'UNIFICADO') == 0 && $XmlMode)
			if (file_exists($DirModelos.Modelo($DirModelos, 'extratocond_unif', $XmlMode)))
				$bExtratoCond_Unif = true;
		SetSessao('EXTRATOCOND_UNIF', $bExtratoCond_Unif ? 'SIM' : 'NAO');

		// Trata demais campos do login.
		$pPass = CampoObrigatorio('SENHA');
		$usuario_id = ValidaLogin($pId, $pPass);
//echo "<!-- "; print_r($usuario_id); print(" -->\n");
		$usuario = array_shift($usuario_id);
		$usuario_cpfcnpj = array_shift($usuario_id);
		$usuario_email = array_shift($usuario_id);
		SetSessao('usuario', $usuario);
		SetSessao('usuario_cpfcnpj', $usuario_cpfcnpj);
		SetSessao('usuario_email', $usuario_email);
		SetSessao('usuario_id', $usuario_id);

		$sSenhaDefault = Configuracao('SENHA_DEFAULT', '');
		if (!empty($sSenhaDefault))
		{
			$sValorDefault = @eval("return $sSenhaDefault;");
//echo "<!-- usuario_id="; print_r($usuario_id); print(" sSenhaDefault='$sSenhaDefault' sValorDefault='$sValorDefault' -->\n");
			if ($pPass == $sValorDefault)
			{
				Submeter('alteraSenha.php',
					array('OPERACAO'=>'expirou','ORIG'=>'crLogin','REFERER'=>$_SERVER["HTTP_REFERER"],
							'SENHA'=>$pPass, 'SENHADEFAULT'=>$sValorDefault));
			}
		}
	}
	else
	{
		$logout = GetSessao('logout_url');
		$usuario = GetSessao('usuario');
		$usuario_id = GetSessao('usuario_id');
		$usuario_cpfcnpj = GetSessao('usuario_cpfcnpj');
		$usuario_email = GetSessao('usuario_email');
		if (!empty($logout) || empty($usuario) || empty($usuario_id))
		{
			$page = Campo('page');
			if (empty($page))
				$page = GetSessao('login_url');

			if (empty($page))
				Mensagem('Erro', 'Sessão expirada, efetue novo LOGIN!');
			else
			{
				SetSessao('login_url', $page);
				header("Location: $page");
			}
			exit;
		}
		$pId = $usuario_id[0];
		$bExtratoCond_Unif = (GetSessao('EXTRATOCOND_UNIF') == 'SIM');
	}

	// Vai preencher as linhas da tabela com os servicos disponiveis
	$model = new DTemplate($DirModelos);
	$model->define_templates( array ( 'clionline' => Modelo($DirModelos, 'clionline_new')));
	$model->define_dynamic('LISTA_SERVICOS', 'clionline');		//body is the parent of table
	$model->define_dynamic('LISTA_BOTOES', 'LISTA_SERVICOS');	//body is the parent of table
	$model->define_dynamic('LISTA_BOLETOS', 'clionline');
	$model->define_dynamic('SERVICOS_EXTRAS_COND', 'clionline');
	$model->define_dynamic('INFOS_IR_ANUAL', 'clionline');
	$model->define_dynamic('LISTA_INFOS_IR', 'INFOS_IR_ANUAL');

	$model->assign('ID', $pId);
	$model->assign('USUARIO', ISO8859_1toModel($usuario));
	$model->assign('USUARIO_EMAIL', $usuario_email);
	if ($UsingXmlModel)
	{
		if (isset($_SERVER["SCRIPT_URI"]))
		{
			$protocolo = explode(':',$_SERVER["SCRIPT_URI"]);
			$protocolo = $protocolo[0];
		}
		else
			$protocolo = 'http';
		$model->assign('HOST', $protocolo.'://'.$_SERVER['HTTP_HOST'].dirname($_SERVER["REQUEST_URI"]).'/');
		$model->assign('SESSID',session_id());
		$model->assign('TIMESTAMP', time());
	}
	else
		$model->assign('TROCA_SENHA', $TrocaSenha ? "" : "none");

	$cont = count($usuario_id) - 1;
	$aAnexosCond = array();
	$ServicosL = $ServicosC = $ServicosB = $ServicosO = $ServicosIR = $ServicosAL = false;
	SetSessao('blocos', array());

	$aArquivos = array();
	for ($i = $cont; $i >= 0; $i--)
	{
		if (GetServices($model, 'Loc', $usuario_id[$i], false, $aArquivos))
			$ServicosL = true;
		else
			$ServicosL |= GetServices($model, 'Loc2', $usuario_id[$i], false, $aArquivos);
	}

	$aArquivos = array();
	for ($i = $cont; $i >= 0; $i--)
		$ServicosC |= GetServices($model, 'Cond', $usuario_id[$i], $bExtratoCond_Unif, $aArquivos);

	for ($i = $cont; $i >= 0; $i--)
		$ServicosB |= GetBoletos($model, $usuario_id[$i]);

	for ($i = $cont; $i >= 0; $i--)
		$ServicosO |= GetObsComerc($model, $usuario_id[$i]);

	for ($i = $cont; $i >= 0; $i--)
		$ServicosIR |= GetInfoIR($model, $usuario_id[$i], $cont>0);

	if ($ExibeAnexosLoc)
		for ($i = $cont; $i >= 0; $i--)
			$ServicosAL |= GetAnexosLoc($model, $usuario_id[$i]);
	
	if (!$ServicosL && !$ServicosC && !$ServicosB && !$ServicosO && !$ServicosIR && !$ServicosAL)
	{
		Mensagem('Aviso', 'Não existem informações para este usuário!');
		return 0;
	}
	if (!$ServicosL && !$ServicosC && !$ServicosAL)
		$model->clear_dynamic('LISTA_SERVICOS');
	if (!$ServicosC)
		// Se nao tem condominios entao nao tem servicos extras de condominio
		$ServicosExtras = false;
	if (!$ServicosExtras)
		$model->clear_dynamic('SERVICOS_EXTRAS_COND');
	if (!$ServicosB)
		$model->clear_dynamic('LISTA_BOLETOS');
	if (!$ServicosIR)
		$model->clear_dynamic('INFOS_IR_ANUAL');

	SetSessao('produto', 'cred');
	SetSessao('logout_url', '');
	phpLog('LI', 'Cliente-online', $pId);

	$model->parse('clionline');
	$model->DPrint('clionline');

/*
echo "<!--\n";
echo '$_SESSION = ';
print_r($_SESSION);
global $HTTP_SESSION_VARS;
echo '$HTTP_SESSION_VARS = ';
print_r($HTTP_SESSION_VARS);
echo "\n-->\n";
*/
//$aBlocos = GetSessao('blocos');echo '<!-- Blocos: '; print_r($aBlocos); echo "\n-->\n";
?>
