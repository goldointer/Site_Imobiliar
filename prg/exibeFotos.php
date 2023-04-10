<?php
include 'msg.php';
include 'fotos.inc.php';
include "pesqImov.inc.php";

header('Content-Type: text/html; charset=ISO-8859-1');

$DirDados = Configuracao('DIR_DADOS');
$DirFotos = Configuracao('DIR_FOTOS');
$DirModelos = Configuracao('DIR_MODELOS_PESQUISA');
$DirImagens = Configuracao('DIR_IMAGENS');

//-----------------------------------------------------------------------------
function CarregaDescr()
{
	GLOBAL $DirDados, $aDescr;

	$iTamRegMinimo = 53;

	if (($file = @fopen($DirDados.'descrfoto.txt', 'r')) !== false)
	{
		$aDescr = array();
		while (!feof($file))
		{
			$sReg = fgets($file,4098);
			if (empty($sReg) || strlen($sReg) < $iTamRegMinimo)
				break;

			$descr = trim(substr($sReg, 0, 50));
			$sigla = trim(substr($sReg, 50, 2));
			$aDescr[$sigla] = $descr;
		}
		fclose($file);
	}
}

//--------------------------------------------------------------------------------
function NumberFormat($iNumber, $iDecimals, $sDec_point, $sThousands_sep)
{
	$iNumber = str_replace('.', '' , $iNumber);
	$iNumber = str_replace(',', '.' , $iNumber);
	return number_format($iNumber, $iDecimals, ',', '.');
}

//---main-------------------------------------------------------------------------
//session_cache_limiter('public');
//$cache = session_cache_expire(5); 

$Img_Width = Configuracao('LARGURA_THUMBNAILS_FOTOS', 80);
$GoogleMapsKey = Configuracao('CHAVE_GOOGLEMAPS');

$exibeThumbNails = (Configuracao('EXIBE_THUMBNAILS') == 'SIM') || (Configuracao('EXIBE_THUMBNAILS_FOTOS') == 'SIM');
if ($exibeThumbNails)
	$thumb = 'Thumb';

$model = new DTemplate($DirModelos);
$model-> define_templates( array (	'imovinfo' => 'imovinfo.shtml',
									'fotos' => "fotos$thumb.shtml" ));
$model->define_dynamic('FOTOS', 'fotos');
$model->define_dynamic('ARRAY_FOTOS', 'fotos');

$pCod = Campo('cod');
$pCodFoto = Campo('codfoto');
$lv = Campo('lv');
if (Configuracao('LINK_IMOVEL_DIRETO') != 'NAO')
	$pPos = Campo('p');
$passos = 1;
if(empty($pPos))
{
	$pPos = -1;
	if ($lv == 'LV')
		// Deve pesquisar nos dois arquivos (locacao e venda)
		$passos = 2;
}
else
{
	// Ja' vem da lista com posicao da foto entao tem que ter 'lv'
	if(empty($lv))
		Mensagem('Aviso', 'Im&oacute;vel n&atilde;o foi encontrado: problema na pesquisa!');
}

//echo "\n<!--\n";
//print_r($aDescr);
//echo "\n-->\n";

$iTamReg = 282;

for ($passo = 0; $passo < $passos; $passo++)
{
	if ($lv == 'LV')
		$arq = ($passo == 0) ? _LOCACAO : _VENDA;
	else
		$arq = $lv;

	if ($arq == _LOCACAO)
	{
		$fImov = fopen($DirDados.'imov.txt', 'r');
		$exibeEnder = (Configuracao('EXIBE_ENDERECO_LOCACAO') != 'NAO');
	}
	else
	{
		$fImov = fopen($DirDados.'imovenda.txt', 'r');
		$exibeEnder = (Configuracao('EXIBE_ENDERECO_VENDA') != 'NAO');
	}

	$Linha = '';
	if ($fImov === false)
		continue;

	// Le o registro correspondente ao imovel
	if ($pPos < 0)
	{
		// Pesquisa o imovel pelo codigo
		for (;;)
		{
			$Linha = fgets($fImov, 1024);
			if (empty($Linha) || strlen($Linha) < $iTamReg+1)
				break;
			if ($pCod == trim(substr($Linha, 0, 8)))
			{
				// Encontrou este codigo de imovel
				$lv = $arq;
				$passos = 0;
				break;
			}
		}
	}
	else
	{
		// Aponta para a posicao passada
		if (fseek($fImov, $pPos, SEEK_SET) == 0)
			$Linha = fgets($fImov, 1024);
		else
			$Linha = false;
		$passos = 0;
	}
}

if (empty($Linha) || strlen($Linha) < $iTamReg+1)
{
	Mensagem('Aviso', 'Este im&oacute;vel n&atilde;o foi encontrado!');
	exit(1);
}
CarregaDescr();

// Acessa o registro, buscando outras informacoes do imovel
$szCod = trim(substr($Linha, 0, 8));
$szCodCid = trim(substr($Linha, 10, 6));
$szBairro = substr($Linha, 16, 60);
$szEnd = trim(substr($Linha, 79, 85));
$szImediacao = trim(substr($Linha, 164, 30));
$szNome_pred = trim(substr($Linha, 197, 30));
$szSituacao = trim(substr($Linha, 262, 3));
$chavecarac = trim(substr($Linha, 266, 8));
$AreaUtil = floatval(trim(substr($Linha, 274, 8)));
if ($AreaUtil == 0.0)
	$AreaUtil = '&nbsp;';
else
	$AreaUtil = number_format($AreaUtil, 2, ',', '.').'m&sup2;';

$szValCond = trim(substr($Linha, 238, 12));
$szValIptu = trim(substr($Linha, 250, 12));
$szCEP = trim(substr($Linha, 288, 8));
$AreaTotal = floatval(trim(substr($Linha, 274, 8)));
if ($AreaTotal == 0.0)
	$AreaTotal = '&nbsp;';
else
	$AreaTotal = number_format($AreaTotal, 2, ',', '.').'m&sup2;';

$aRet = MontaDescrImov($szCod, $chavecarac, $szValCond, $szValIptu);
$pDescr = $aRet[0];
$szVal_cond_iptu = $aRet[1];

if ($szSituacao != 'NOR')
	$szSituacao = BuscaSituacao($szSituacao);
else
	$szSituacao = 'DISPON&Iacute;VEL';

// Separa campos do endereco
$pos = 0;
$tam = intval(substr($Linha, 282, 2));
$szTipoL = trim(substr($szEnd, $pos, $tam));
$pos += $tam;
$tam = intval(substr($Linha, 284, 2));
$szLograd = trim(substr($szEnd, $pos, $tam));
$pos += $tam;
$tam = intval(substr($Linha, 286, 2));
$szNumero = substr($szEnd, $pos, $tam);
if (empty($szTipoL) && empty($szLograd) && empty($szNumero))
{
	$szLograd = trim($szEnd);
	$szCompl = '';
}
else
{
	$pos += $tam;
	$szCompl = trim(substr($szEnd, $pos));
	$pos = strpos($szLograd, ',');
	if ($pos !== false)
		$szLograd = trim(substr($szLograd, $pos+1)).' '.trim(substr($szLograd, 0, $pos));
}

// Verifica existencia de fotos
if (empty($pCodFoto))
{
	if (ExisteFotos($szCod, '!') != '')
		$pCodFoto = '!';
	else
	{
		$pCodFoto = ExisteFotos($szCod, '');
		if($pCodFoto == '')
			$pCodFoto = '*';
	}
}

if ($pCodFoto == '*')
	$FileOut = 'imovinfo';
else if ($pCodFoto == '!')
{
	$dir = $DirFotos.$pCod;
	if (is_dir($dir))
	{
		// Prepara lista das fotos
		$bFmtAntigo = false;
		$fh = opendir($dir);
		$files = array();
		while (false !== ($file = readdir($fh)))
		{
			if ($file == '.' || $file == '..')
				continue;
			$aux = explode('.', $file);
			$aux = strtolower($aux[count($aux)-1]);
			if ($aux != "jpg")
				continue;
			if (file_exists($dir.'/'.$file))
				$files[$file] = BuscaOrdem($aDescr, $file).substr($file,14);
			$bFmtAntigo |= ($file{8} == '_');
		}

		// Ordena a lista das fotos e monta indice
//echo "\n<!-- ANTES do sort\n";
//print_r($files);
	if ($bFmtAntigo)
		asort($files);
	else
		ksort($files);
//echo "\n----- DEPOIS do sort\n";
//print_r($files);
//echo "\n-->\n";
		reset($files);
		$cont = 0;
		foreach ($files as $file => $ordem)
		{
			if ($exibeThumbNails)
			{
				$img_ft = 'resizeImg.php?file='.urlencode("$dir/$file");
				$model->assign('IMG_FT', $img_ft);
				$model->assign('IMG_IDX', $cont+1);
				$model->assign('IMG_SRC', $img_ft.'&width='.$Img_Width);
			}
			else
				$model->assign('PARAM', sprintf('lv=%s&cod=%d&codfoto=!&p=%s&f=%s', $lv, intval($pCod), $pPos, $file));

			$model->assign('NOME_FT', BuscaDescr($aDescr, $file));
			$model->assign('IMG_WIDTH', $Img_Width);
			$model->parse('.FOTOS');
			$model->parse('.ARRAY_FOTOS');
			$cont++;
		}
		if ($cont == 0)
		{
			if ($exibeThumbNails)
			{
				$model->assign('IMG_FT', '');
				$model->assign('IMG_IDX', '0');
				$model->assign('IMG_SRC', '');
			}
			else
				$model->assign('PARAM', '');

			$model->assign('NOME_FT', '');
			$model->assign('IMG_WIDTH', 0);
			$model->parse('.FOTOS');
			$model->parse('.ARRAY_FOTOS');
		}

		$pArqFoto = Campo('f');
		if (empty($pArqFoto))
		{
			foreach ($files as $file => $ordem)
				break;
			$pArqFoto = $file;
		}
		$szDirFoto = sprintf('%s%d/%s', $DirFotos, intval($pCod), $pArqFoto);
		if (!file_exists($szDirFoto))
			$szDirFoto = $DirImagens.'nada.png';
		$model->assign('IMG_IMOV', 'resizeImg.php?file='.urlencode($szDirFoto));
		$FileOut = 'fotos';
	}
}
else
{
	$cont = 0;
	$szDirFoto = ExisteFotos(intval($pCod), '0');
	if (!empty($szDirFoto))
	{
		if ($exibeThumbNails)
		{
			$model->assign('IMG_FT', $szDirFoto);
			$model->assign('IMG_IDX', $cont+1);
			if ($bResizeOK)
				$model->assign('IMG_SRC', 'resizeImg.php?file='.urlencode($szDirFoto).'&width='.$Img_Width);
			else
				$model->assign('IMG_SRC', $szDirFoto);
		}
		else
			$model->assign('PARAM', sprintf('lv=%s&cod=%d&codfoto=0&p=%s', $lv, intval($pCod), $pPos));
		$model->assign('NOME_FT', 'Fachada');
		$model->assign('IMG_WIDTH', $Img_Width);
		$model->parse('.FOTOS');
		$model->parse('.ARRAY_FOTOS');
		$cont++;
	}

	$let = 'a';
	$len = 26;
	for ($i=0; $i<$len; $i++)
	{
		$szDirFoto = ExisteFotos(intval($pCod), chr(ord($let)+$i));
		if (!empty($szDirFoto))
		{
			if ($exibeThumbNails)
			{
				$model->assign('IMG_FT', $szDirFoto);
				$model->assign('IMG_IDX', $cont+1);
				if ($bResizeOK)
					$model->assign('IMG_SRC', 'resizeImg.php?file='.urlencode($szDirFoto).'&width='.$Img_Width);
				else
					$model->assign('IMG_SRC', $szDirFoto);
			}
			else
				$model->assign('PARAM', sprintf('lv=%s&cod=%d&codfoto=%c&p=%s', $lv, intval($pCod), ord($let)+$i, $pPos));
			$szNomeF = sprintf(' Foto&nbsp;%d', $cont+1);
			$model->assign('NOME_FT', $szNomeF);
			$model->assign('IMG_WIDTH', $Img_Width);
			$model->parse('.FOTOS');
			$model->parse('.ARRAY_FOTOS');
			$cont++;
		}
	}

	$len = 29;
	for ($seq = 1; $seq <= $len; $seq++)
	{
		$szDirFoto = ExisteFotos(intval($pCod), $seq);
		if (!empty($szDirFoto))
		{
			if ($exibeThumbNails)
			{
				$model->assign('IMG_FT', $szDirFoto);
				$model->assign('IMG_IDX', $cont+1);
				if ($bResizeOK)
					$model->assign('IMG_SRC', 'resizeImg.php?file='.urlencode($szDirFoto).'&width='.$Img_Width);
				else
					$model->assign('IMG_SRC', $szDirFoto);
			}
			else
				$model->assign('PARAM', sprintf('lv=%s&cod=%d&codfoto=%d&p=%s', $lv, intval($pCod), $seq, $pPos));
			$szNomeF = sprintf(' Foto&nbsp;%d', $cont+1);
			$model->assign('NOME_FT', $szNomeF);
			$model->assign('IMG_WIDTH', $Img_Width);
			$model->parse('.FOTOS');
			$model->parse('.ARRAY_FOTOS');
			$cont++;
		}
	}

	if ($cont == 0)
	{
		if ($exibeThumbNails)
		{
			$model->assign('IMG_FT', '');
			$model->assign('IMG_IDX', '0');
			$model->assign('IMG_SRC', '');
		}
		else
			$model->assign('PARAM', '');
		$model->assign('NOME_FT', '');
		$model->assign('IMG_WIDTH', 0);
		$model->parse('.FOTOS');
		$model->parse('.ARRAY_FOTOS');
	}

	$szDirFoto = ExisteFotos(intval($pCod), $pCodFoto);
	if (empty($szDirFoto))
		$szDirFoto = $DirImagens.'nada.png';
	$model->assign('IMG_IMOV', $szDirFoto);
	$FileOut = 'fotos';
}

$model->assign('TIPO_COMERC', $lv);
$model->assign('COD_IMOV', $szCod);
if ($lv == 'V')
	$model->assign('ALUG_VENDA', 'Valor:');
else
	$model->assign('ALUG_VENDA', 'Aluguel:');

$Val = trim(substr($Linha, 226, 12));
$model->assign('VAL_IMOV', NumberFormat($Val, 2, ',', '.'));

$model->assign('VAL_COND_IPTU', $szVal_cond_iptu);

$model->assign('AREA', $AreaUtil);
$model->assign('AREA_TOTAL', $AreaTotal);
$model->assign('SITUACAO_IMOVEL', $szSituacao);
$model->assign('END', $exibeEnder ? rtrim($szEnd) : '');
$model->assign('BAIRRO', $szBairro);
$model->assign('DESCR', trim($pDescr).' ');

$model->assign('TIPOLOGR', $exibeEnder ? $szTipoL : '');
$model->assign('LOGRADOURO', $exibeEnder ? $szLograd : '');
$model->assign('NUMERO', $exibeEnder ? $szNumero : '');
$model->assign('COMPLEMENTO', $exibeEnder ? $szCompl : '');
$model->assign('CEP', $exibeEnder ? $szCEP : '');
$model->assign('EXIBE_ENDER', $exibeEnder ? '' : ' style="display:none"');
$model->assign('GOOGLEMAPS_KEY', $exibeEnder && $GoogleMapsKey != '' ? $GoogleMapsKey : '');

if ($exibeEnder && $GoogleMapsKey != '')
{

	if (isset($_SERVER["SCRIPT_URI"]))
	{
		$protocolo = explode(':',$_SERVER["SCRIPT_URI"]);
		$protocolo = $protocolo[0];
	}
	else
		$protocolo = 'http';
	$model->assign('GOOGLEMAPS_INC', sprintf("<script src=\"%s://maps.google.com/maps?file=api&amp;v=2&amp;sensor=false&amp;key=%s\" type=\"text/javascript\"></script>", $protocolo, $GoogleMapsKey));
	$model->assign('GOOGLEMAPS_KEY', $GoogleMapsKey);
}
else
{
	$model->assign('GOOGLEMAPS_INC', '');
	$model->assign('GOOGLEMAPS_KEY', '');
}

$Val = PesqCodCidade($lv, intval($szCodCid));
$model->assign('NOMECIDADE', $Val[1]);
$model->assign('UF', $Val[2]);

$model->parse($FileOut);
$model->DPrint($FileOut);

fclose($fImov);
?>

