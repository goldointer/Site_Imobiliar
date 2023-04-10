<?php
include 'msg.php'; 
include 'BarCode.php';

$DirModelos = Configuracao('DIR_MODELOS_AREACLIENTE');
$DirImagens = Configuracao('DIR_IMAGENS');
$ExibirPdf = (Configuracao('PDF_DOC') == 'SIM');

if(!function_exists('fnmatch')) {
	// Esta funcao nao esta disponivel no Windows
    function fnmatch($pattern, $string) {
        return preg_match("#^".strtr(preg_quote($pattern, '#'), array('\*' => '.*', '\?' => '.'))."$#i", $string);
    }
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

$filename = CampoObrigatorio('fboleto');
$stat = @stat($filename);
if ($stat === false || $stat['size'] <= 0 || ($handle=fopen($filename, 'r')) === false)
{
	Mensagem('Aviso', 'DOC não está disponível no momento!', substr($filename,16));
	exit (0);
}

$formato = Campo('FORMATO');
if ($formato == 'PDF')
{
	$ExibirPdf = true;
	$Modelo = 'boleto.shtml';
}
else
	$Modelo = 'boleto';

$model = new DTemplate($DirModelos);
$model->define_templates( array( $Modelo => Modelo($DirModelos, $Modelo) ));

$sLocalLogo = $DirImagens.'logos';
if (!is_dir($sLocalLogo))
{
	Mensagem('Aviso', 'Diretório de logotipos de bancos não disponível. Favor contactar a Imobiliária!', $sLocalLogo);
	return;
}

if ($UsingXmlModel)
{
	$model->define_dynamic('LINHA_DETALHE', $Modelo);
	$model->assign('DICA', basename(dirname($filename)).'/'.basename($filename));

	// Isola o codigo no nome do arquivo
	$Codigo = basename($filename);
	if (is_numeric(substr($Codigo, 0, 1)))
		$Codigo = substr($Codigo, 0, -4);
	else
		$Codigo = substr($Codigo, 1, -4);
	$competencia = strstr($Codigo, 'N');
	if (!empty($competencia))
		$Codigo = substr($Codigo, 0, -7);
	else
	{
		$competencia = strstr($Codigo, 'E');
		if (!empty($competencia))
			$Codigo = substr($Codigo, 0, -9);
	}
}
else
{
	$model->define_dynamic('LINHA_DETALHE_A', $Modelo);
	$model->define_dynamic('LINHA_DETALHE_B', $Modelo);
}


//Le arquivo de boleto (estes arquivos sobem no formato HTML entities).
$Obs = '';
$sCodBarra = '';
$sDataPagamento = '';
$bInformativo = false;
$bBalancete = false;
$bTaxas = true;
$sRetido = '';
$sOutrasInfos = '';
$DescrTaxas = array();
$iDiasPermanencia = -1;

while (!feof ($handle))
{
	$buffer = fgets($handle, 4096);
	if (empty($buffer))
		break;

	if ($buffer[0] != '#')
	{
		// Extrai nome e valor da tag
		$pos = strpos($buffer, '=');
		if ($pos === false)
		{
			$Tag = $buffer;
			$Value = ' ';
		}
		else
		{
			$Tag = substr($buffer, 0, $pos);
			$Value = substr($buffer, $pos+1);
			$aux = trim($Value);
			if (empty($aux))
				$Value = ' ';
			else
			{
				// Converte o valor em HTML entities para o formato do modelo.
				$Value = HTMLtoModel($Value);
				$Value = rtrim(str_replace(array("\x0a","\x0d"), " ", $Value));
			}
		}

		// Efetua o tratamento especifico de cada tag
		if ($Tag == 'TIPO')
			$sTipo = trim($Value);
		else if ($Tag == 'IsVencto')
			$sVenctoOrig = $Value;
		else if ($Tag == 'IsDataVenc')
			$sVenctoAlterado = $Value;
		else if ($Tag == 'IsRetido')
			$sRetido = trim($Value);
		else if ($Tag == 'IsDiasPermanencia')
			$iDiasPermanencia = intval($Value);
		else if ($Tag == 'IsCodBarra1')
			$sCodBarra = trim($Value);
		else if ($Tag == 'IsDebConta')
			$sDebConta = trim($Value);
		else if ($Tag == 'IsDataPagamento')
			$sDataPagamento = trim($Value);
		else if ($Tag == 'IsSacado1')
		{
			$sSacado = trim($Value);
			$pos = strrpos($sSacado,'Econ.:');
			if ($pos > 0)
				$sSacado = trim(substr($sSacado, 0, $pos-1));
		}
		else if ($Tag == 'IsCodBanco')
		{
			if (!file_exists($sLocalLogo))
			{
				Mensagem('Aviso', 'Logotipo de banco não disponível. Favor contactar a Imobiliária!');
				return;
			}
//echo '<!-- ';
			$sLogo = 'logo_'.$Value.'.png';
//echo "$sLogo| ";
			if (!file_exists($sLocalLogo.'/'.$sLogo))
			{
				$sLogo = 'logo_'.$Value.'.gif';
//echo "$sLogo| ";
			}
			if (!file_exists($sLocalLogo.'/'.$sLogo))
			{
				$aDir = scandir($sLocalLogo);
//print_r($aDir);
				for ($i = 0; $i < 2; $i++)
				{
					$sLogo = 'logo_'.substr($Value,0,3).($i == 0 ? '*.png' : '*.gif');
//echo "$sLogo| ";
					foreach ($aDir as $file)
					{
//echo "$file| ";
						if (fnmatch($sLogo, $file))
						{
							$sLogo = $file;
							$i = 2;
							break;
						}
					}
				}
			}
			$sLogo = $sLocalLogo.'/'.$sLogo;
			if (!file_exists($sLogo))
			{
				$sLogo = $sLocalLogo.'/none.png';
				if (!file_exists($sLogo))
					$sLogo = $sLocalLogo.'/none.gif';
			}
//echo "\n[$sLogo] -->\n";
		}
		else if (substr($Tag,0,8) == 'IsDetLin')
		{
			if ($UsingXmlModel)
				; // Usar o valor diretamente, nada a fazer.
			else if (strpos($Value, 'I N F O R M A T I V O') !== false)
			{
				$bTaxas = false;
				$bBalancete = false;
				$bInformativo = true;
				$Value = str_replace('-','',$Value);
				$Value = '</td><td colspan=4 style="font-size: 7pt">----------'.$Value.'----------';
			}
			else if (strpos($Value, 'BALANCETE DEMONSTRATIVO') !== false)
			{
				$bTaxas = false;
				$bBalancete = true;
				$bInformativo = false;
				$Value = str_replace('-','',$Value);
				$Value = '</td><td colspan=4 style="font-size: 7pt">-----'.$Value.'-----';
			}
			else if ($bInformativo)
			{
				$Value = trim($Value);
				$Value = '</td><td colspan=4 style="font-size: 6pt">'.(empty($Value)?'&nbsp;':$Value);
			}
			else if ($bBalancete)
			{
				// Precisa converter para ISO8859-1 para usar substring e reconveter depois
				if ($ModelCharset == CHARSET_HTML)
					$Value = HTMLtoISO8859_1($Value);
				else if ($ModelCharset == CHARSET_UTF8)
					$Value = UTF8toISO8859_1($Value);
				$col1 = trim(substr($Value,0,31));
				$col2 = trim(substr($Value,31,10));
				$col3 = trim(substr($Value,56,31));
				$col4 = trim(substr($Value,87,10));
				$Value = '</td><td nowrap>'.(empty($col1)?'&nbsp;':ISO8859_1toModel($col1)).
						 '</td><td align="right">'.(empty($col2)?'&nbsp;':ISO8859_1toModel($col2)).
						 '</td><td nowrap>'.(empty($col3)?'&nbsp;':ISO8859_1toModel($col3)).
						 '</td><td align="right">'.(empty($col4)?'&nbsp;':ISO8859_1toModel($col4));
			}
			else if ($Value{0} == '*')
			{	// Trata-se de mensagem extra
				if (empty($Obs))
					$Obs = trim($Value);
				else
					$Obs .= '<br>'.trim($Value);
				$Value = ' ';
				$bTaxas = false;
				$bBalancete = false;
				$bInformativo = false;
			}
			else
			{
				$Value = trim($Value);
				if ($bTaxas)
				{
					$pos = strrpos($Value,' ');
					if ($pos === false)
					{
						$col1 = $Value;
						$col2 = '';
					}
					else
					{
						$col1 = trim(substr($Value,0,$pos));
						$col2 = '&nbsp;'.trim(substr($Value,$pos));
					}
					if (substr($Value,0,13) == 'TOTAL C/MULTA')
						$bTaxas = false;
				}
				else
				{
					$len = strlen($Value);
					if ($len > 80)
					{
						$col1 = '<table width="100%" border=0><tr><td style="font-size: 6pt">'.$Value.'</td></tr></table>';
						$col2 = '';
					}
					else
					{
						$col1 = $Value;
						$col2 = '';
					}
				}

				if (empty($col1))
					$col1 = '&nbsp;';
				if (empty($col2))
					$Value = '</td><td colspan=4 nowrap>'.$col1;
				else
					$Value = '</td><td colspan=3 nowrap>'.$col1.'</td><td align="right">'.$col2;
			}

			$DescrTaxas[substr($Tag,8)] = $Value;
		}
		else if ($UsingXmlModel)
		{
			if ($Tag == 'IsId1')
			{
				if ($sTipo == 'C')
				{
					// Extrai o codcondom
					$Aux = explode(':', $Value);
					$sOutrasInfos .= sprintf("\n   <condominio_codigo>%d</condominio_codigo>", intval($Aux[1]));
					$XmlTag = 'economia_codigo';
				}
				else
					$XmlTag = 'imovel_codigo';

				$sOutrasInfos .= sprintf("\n   <%s>%s</%s>", $XmlTag, $Codigo, $XmlTag);
				$model->assign('OUTRAS_INFOS', $sOutrasInfos);
			}
		}

		if (!empty($Tag))
			$model->assign($Tag, $Value);
	}
}

fclose ($handle);

// Descricao das taxas para novo modelo extendido com linhas detalhe ilimitadas.
if ($UsingXmlModel)
{
	foreach ($DescrTaxas as $Tag => $Value)
	{
		$model->assign('IsDetLin', $Value);
		$model->parse($Tag == 1 ? 'LINHA_DETALHE' : '.LINHA_DETALHE');
	}
}
else
{
	if (count($DescrTaxas) % 2 != 0)
		$DescrTaxas[] = ''; // Ajusta para par o total de linhas
	$LinhasPorCol = count($DescrTaxas) / 2;
	$Linha = 0;
	foreach ($DescrTaxas as $Tag => $Value)
	{
		$Col = ($Linha < $LinhasPorCol) ? 'A' : 'B';
		$model->assign('IsDetLin'.$Col, $Value);
		if ($Linha == 0 || $Linha == $LinhasPorCol)
			$model->parse('LINHA_DETALHE_'.$Col);
		else
			$model->parse('.LINHA_DETALHE_'.$Col);
		$Linha++;
	}
}

// Codigo de Barras
$bQuitado = !empty($sDataPagamento);
if (empty($sCodBarra))
{
	$Value = $sDebConta;
	$sCodBarraTxt = $Value;
	if (stristr($Value, 'Quitado') != false)
	{
		$Value = "<h2>$Value</h2><br>";
		$bQuitado = true;
	}
}
else
{
	$sCodBarraTxt = $sCodBarra;
	$Value = fbarcode($sCodBarraTxt);
}

// Validade do DOC
if ($bQuitado)
	$model->assign('IsSituacao', 'Quitado');
else
{
	if ($sRetido == 'S' || $sVenctoAlterado == '01/01/1900')
	{
		Mensagem('Aviso', 'Contate sua Administradora para segunda via de boleto!');
		exit;
	}
	$model->assign('IsSituacao', 'EmAberto');

	if (strstr($filename, '.sempre.txt') != '.sempre.txt')
	{
		// Nao e' o nome especial que indica ser sempre valido entao
		// verifica a expiracao do tempo limite de exibicao.
		$iTempoVencido = time() - mktime(23,59,59,substr($sVenctoOrig, 3, 2),substr($sVenctoOrig, 0, 2),substr($sVenctoOrig, 6, 4));
		if ($iDiasPermanencia < 0)
		{
			// Nao veio no arquivo entao pega da configuracao.
			if ($sTipo == 'L')
				$iDiasPermanencia = Configuracao('DIAS_VALIDADE_DOC_VENCIDO_LOC', -1);
			else
				$iDiasPermanencia = Configuracao('DIAS_VALIDADE_DOC_VENCIDO_COND', -1);
			if ($iDiasPermanencia < 0)
				$iDiasPermanencia = Configuracao('DIAS_VALIDADE_DOC_VENCIDO', 30);
		}
		$iDiasPermanencia *= 86400;
		$sData = substr($sVenctoOrig, 6, 4) . '-' . substr($sVenctoOrig, 3, 2) . '-' . substr($sVenctoOrig, 0, 2);
		$dtData = new DateTime($sData);
		$iSemana =  $dtData->format("w");
		// Verifica se vencimento cai no fim de semana
		if($iSemana == 6)
			$iDiasPermanencia += 2 * 86400; // Se venceu no sabado, dah mais 2 dias
		else if ($iSemana == 0)
			$iDiasPermanencia += 86400;	  // Se venceu no domingo, dah mais 1 dia

//printf("<!-- Tipo='%s' Vencto=%s Expira=%d -->\n", $sTipo, $iTempoVencido, $iDiasPermanencia);
		if ($iTempoVencido > $iDiasPermanencia)
		{
			Mensagem('Aviso', "Este boleto possui vencimento em '$sVenctoOrig' e já expirou. Favor contactar a Imobiliária!");
			return;
		}
	}
}

// Montagem final e apresentacao do DOC
$model->assign('TITULO', "DOC-$sSacado");
$model->assign('BANCOLOGO', GetFullUrl($sLogo));
$model->assign('EXIBE_HTML', $ExibirPdf ? 'false' : 'true');
$model->assign('OBSERVACOES', $Obs);
$model->assign('IsCodBarra1', $Value);
$model->assign('IsCodBarraTxt', $sCodBarraTxt);
$model->assign('IsCodBarraHtml', empty($sCodBarra) ? '' : $Value);
$model->parse($Modelo); 
$html = $model->fetch($Modelo);

if ($UsingXmlModel)
	// XML output
	print $html;
else 
{
	if ($ExibirPdf && !headers_sent() && file_exists('mpdf/mpdf.php'))
	{
		try {
			// PDF Output
			ob_start();
			error_reporting(E_ERROR | E_PARSE);
			@include('mpdf/mpdf.php');
			$mpdf=new mPDF('UTF-8','A4'); 
			$mpdf->WriteHTML(ISO8859_1toUTF8($html));
			$mpdf->Output('','S');
			ob_end_clean();
			// Tudo certo entao exibe o PDF
			$mpdf->Output('DOC-'.ISO8859_1toASCII(str_replace(' ','_',$sSacado.' '.$sData)).'.pdf','I');
			return;
		} catch(Exception $e) { 
			ob_end_clean();
		}
	}

	// HTML output
	echo('<!-- '.substr($filename,16)." -->\n");
	print $html;
}
?>
