<?php
include "msg.php";
include "pesqCli.php";

$DirDados = Configuracao('DIR_DADOS');
$DirModelos = Configuracao('DIR_MODELOS_AREACLIENTE');

//---main-------------------------------------------------------------------------

// Vai autenticar
$usuario = GetSessao('usuario');
$usuario_id = GetSessao('usuario_id');
if (empty($usuario) || empty($usuario_id))
{
	// Ja' foi efetuado um logout, deve ser pagina anterior.
	$sUrl = GetSessao('login_url');
	if (empty($sUrl))
		Mensagem("Erro", "Sessão encerrada, efetue o LOGIN!");
	else
		header("Location: " .$sUrl);
	exit;
}

$Cond = intval(substr(CampoObrigatorio('CHAVE'),0,5));

$model = new DTemplate($DirModelos);
$Modelo = 'condominos';
$model-> define_templates( array ($Modelo => Modelo($DirModelos, $Modelo)));
$model->define_dynamic('CONDONIMO', $Modelo);

$sArq = 'I'.str_pad($Cond, 5, '0', STR_PAD_LEFT).'.TXT';
$stat = @stat($DirDados.$sArq);
if ($stat === false || $stat['size'] <= 0 || ($File=fopen($DirDados.$sArq, 'r')) === false)
{
	Mensagem('Atenção', "Dados não disponíveis para o condomínio $Cond!");
	exit(1);
}
if ($UsingXmlModel)
{
	$model->assign('DICA', $sArq);
	$model->assign('USUARIO', ISO8859_1toModel($usuario));
	$model->assign('DATA_ATUAL', date('d/m/Y H:i'));
	$model->assign('DATA_ARQUIVO', date("d/m/Y H:i", $stat['mtime']));
}
else
	echo "<!-- $sArq -->\n";
$iCont = 0;
$sDescr = '';
$sFones = '';
$sUnidade = '';
$sCondomino = '';

for(;;)
{
	$sReg = fgets($File, 4098);
	if (strlen($sReg) <= 1)
		break;

	$TipoReg = $sReg{0};
	if ($TipoReg == 'C')	// Cabecalho
	{
		$sCondNome = substr($sReg, 6, 60);
		$iPos = strpos($sCondNome, '-');
		$sCondNome = trim(substr($sCondNome, $iPos+1));
		$sAssessor = trim(substr($sReg, 72, 30));
		$model->assign('CONDOM_COD', $Cond);
		$model->assign('CONDOM_NOME', ISO8859_1toModel($sCondNome));
		$model->assign('ASSESSOR_NOME', ISO8859_1toModel($sAssessor));
		continue;
	}
	
	if ($TipoReg == 'B')	// Bloco
	{
		$sBloco = substr($sReg, 1, 3);
		$sBlocoNome = substr($sReg, 4, 15);
		$model->assign('BLOCO_COD', $sBloco);
		$model->assign('BLOCO_NOME', ISO8859_1toModel($sBlocoNome));
		continue;
	}
	
	if ($TipoReg == 'E')	// Economia
	{
		if ($UsingXmlModel && !empty($sUnidade))
		{
			if (!empty($sFones))
				$sOutrasInfos .= sprintf("   <fones>\n%s   </fones>\n", $sFones);
			$model->assign('OUTRAS_INFOS', $sOutrasInfos);
			$model->assign('NOME', ISO8859_1toModel($sCondomino));
			$model->parse($iCont++ == 0 ? 'CONDONIMO' : '.CONDONIMO');
		}

		$sUnidade = trim(substr($sReg, 1, 35));
		$sCondomino = substr($sReg, 36, 60);
		$iPos = strpos($sCondomino, '-');
		$sCondomino = trim(substr($sCondomino, $iPos+1));
		$sCelular = trim(substr($sReg, 96, 30));
		$sOutrasInfos = '';
		$sFones = '';
		continue;
	}

	if ($TipoReg == 'D')	// Endereco
	{
		if (empty($sCondomino))
			continue;

		$sTipo = trim(substr($sReg, 1, 4));
		$sEnder = trim(substr($sReg, 5, 70));
		$sCid = trim(substr($sReg, 75, 40));
		$sCep = trim(substr($sReg, 115, 10));
		$sFone1 = trim(substr($sReg, 165, 20));
		$sRamal1 = trim(substr($sReg, 185, 10));
		$sFone2 = trim(substr($sReg, 195, 20));
		$sRamal2 = trim(substr($sReg, 215, 10));
		$model->assign('UNID', ISO8859_1toModel($sUnidade));
		if ($UsingXmlModel)
		{
			$model->assign('CEP', $sCep);
			$model->assign('CIDADE', ISO8859_1toModel($sCid));
			$model->assign('ENDER', ISO8859_1toModel($sEnder));
		}
		else
		{
			$sDescr = '<td valign="top">'.$sCondomino.'</td><td valign="top">'.$sEnder.'</td>';
			$model->assign('DESCR', $sDescr);
			$model->assign('EXIBE_SEPARADOR', '');
			$model->parse($iCont++ == 0 ? 'CONDONIMO' : '.CONDONIMO');
			$sUnidade = '';
			$sCondomino = '';
			$sDescr = '<td valign="top"></td><td valign="top">'.$sCep.' - '.$sCid.'</td>';
		}
	}
	else if ($TipoReg == 'I')	// Informacoes
	{
		$sTipo = trim(substr($sReg, 1, 4));
		if ($sTipo == 'EMAI')
		{
			$sEmail = trim(substr($sReg, 5, 120));
			if (strlen($sEmail) > 10)
			{
				if ($UsingXmlModel)
					$sOutrasInfos .= sprintf("   <email><![CDATA[%s]]></email>\n", ISO8859_1toModel($sEmail));
				else
					$sDescr = '<td valign="top" colspan=2> '.strtolower($sEmail).'</td>';
			}
		}
	}
	
	if (!empty($sDescr))
	{
		$model->assign('UNID', $sUnidade);
		$model->assign('DESCR', $sDescr);
		$model->assign('EXIBE_SEPARADOR', empty($sUnidade) ? 'style="visibility:hidden"' : '');
			$model->parse($iCont++ == 0 ? 'CONDONIMO' : '.CONDONIMO');
		$sUnidade = '';
		$sDescr = '';
	}

	if (isset($sCelular) && strlen($sCelular) > 1)
	{
	
		if ($UsingXmlModel)
			$sFones .= sprintf("    <fone>%s</fone>\n", ISO8859_1toModel($sCelular));
		else
		{
			$model->assign('UNID', $sUnidade);
			$sDescr = '<td valign="top" colspan=2>FONE: '.$sCelular.'</td>';
			$model->assign('DESCR', $sDescr);
			$model->assign('EXIBE_SEPARADOR', empty($sUnidade) ? 'style="visibility:hidden"' : '');
			$model->parse($iCont++ == 0 ? 'CONDONIMO' : '.CONDONIMO');
			$sUnidade = '';
		}
	}
		
	if (isset($sFone1) && strlen($sFone1) > 1)
	{
		if ($sFone1 != $sCelular)
		{
			if ($UsingXmlModel)
				$sFones .= sprintf("    <fone>%s%s</fone>\n", ISO8859_1toModel($sFone1), (strlen($sRamal1) > 1 ? 'Ramal '.$sRamal1 : ''));
			else
			{
				$model->assign('UNID', $sUnidade);
				$sDescr = '<td valign="top" colspan=2>FONE: '.$sFone1.(strlen($sRamal1) > 1 ? 'Ramal '.$sRamal1 : '').'</td>';
				$model->assign('DESCR', $sDescr);
				$model->assign('EXIBE_SEPARADOR', empty($sUnidade) ? 'style="visibility:hidden"' : '');
				$model->parse($iCont++ == 0 ? 'CONDONIMO' : '.CONDONIMO');
				$sUnidade = '';
			}
		}
	}

	if (isset($sFone2) && strlen($sFone2) > 1)
	{
		if ($sFone2 != $sCelular && $sFone2 != $sFone1)
		{
			if ($UsingXmlModel)
				$sFones .= sprintf("    <fone>%s%s</fone>\n", ISO8859_1toModel($sFone2), (strlen($sRamal2) > 1 ? ' Ramal '.$sRamal2 : ''));
			else
			{
				$model->assign('UNID', $sUnidade);
				$sDescr = '<td valign="top" colspan=2>FONE: '.$sFone2.(strlen($sRamal2) > 1 ? 'Ramal '.$sRamal2 : '').'</td>';
				$model->assign('DESCR', $sDescr);
				$model->assign('EXIBE_SEPARADOR', empty($sUnidade) ? 'style="visibility:hidden"' : '');
				$model->parse($iCont++ == 0 ? 'CONDONIMO' : '.CONDONIMO');
				$sUnidade = '';
			}
		}
	}

	$sCelular = '';
	$sFone1 = '';
	$sFone2 = '';
}

if ($UsingXmlModel && !empty($sCondomino))
{
	if (!empty($sFones))
		$sOutrasInfos .= sprintf("   <fones>\n%s   </fones>\n", $sFones);
	$model->assign('OUTRAS_INFOS', $sOutrasInfos);
	$model->assign('NOME', ISO8859_1toModel($sCondomino));
	$model->parse($iCont++ == 0 ? 'CONDONIMO' : '.CONDONIMO');
}

$model->parse('condominos');
$model->DPrint('condominos');

session_write_close();

if (!$UsingXmlModel)
	echo "\n<!-- $Modelo.shtml -->\n";
?>
