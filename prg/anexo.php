<?php
if (isset($_GET['sessionid'])) {
	$sessionid=$_GET['sessionid'];
	session_id($sessionid);
	session_start();
}

include 'msg.php';
include 'imagens.inc.php';

$DirAnexos = Configuracao('DIR_ANEXOS');
$DirImagens = Configuracao('DIR_IMAGENS');
$DirModelos = Configuracao('DIR_MODELOS_AREACLIENTE');

//-----------------------------------------
function FichaImovel($codigo)
{
	global $DirAnexos, $DirImagens, $UsingXmlModel, $model, $iContCateg;

//echo "<!-- FichaImovel($codigo) -->\n";
	$sDir = sprintf('%s/FichaImov/%d', $DirAnexos, $codigo);
	$aExt = $UsingXmlModel ? array('.pdf', '.csv') : array('.pdf');
	$aArqs = array();
	$aDir = @scandir($sDir);
	if ($aDir !== false) {
		// Busca arquivos agrupados por extensao.
		foreach ($aExt as $sExt) {
			foreach ($aDir as $sArq) {
				$sArq = "$sDir/$sArq";
				$stat = stat($sArq);
				if ($stat === false || $stat['size'] <= 0)
					continue;
				$sArqExt = substr($sArq, -4);
				if ($sExt == $sArqExt)
					$aArqs[] = $sArq;
			}
		}
	}

	if (empty($aArqs))
	{
		Mensagem('Aviso', 'Esta informação não está disponível!');
		exit;
	}

	// Cria link para cada documento existente.
	$model->assign('CATEG_ANEXO', ISO8859_1toModel('FICHA DO IMÓVEL'));
	$model->assign('ANEXO_DESCR', ISO8859_1toModel('FICHA DO IMÓVEL '.$codigo));
	$iContAnexos = 0;
	$sExt = '';

	foreach ($aArqs as $sArq) {
		$sArqExt = strtoupper(substr($sArq, -3));
		if ($sExt == $sArqExt)
			continue;//Aceita apenas um arquivo do mesmo tipo por pasta!!!
		$sExt = $sArqExt;
		$model->assign('ANEXO_TIPO_ALT', $sArqExt);
		if ($UsingXmlModel)
		{
			$model->assign('ANEXO_LINK', GetFullUrl($sArq));
			$model->parse('URL');
		}
		else
		{
			$link = "AbrePdf('$sArq')";
			$model->assign('ANEXO_LINK', $link);
			$icone = $DirImagens.'anexo-pdf.png';
			$model->assign('ANEXO_TIPO', $icone);
		}
		$model->parse($iContAnexos++==0 ? 'ANEXO' : '.ANEXO');
	}

	if ($iContAnexos > 0)
		$model->parse($iContCateg++==0 ? 'CATEGORIA' : '.CATEGORIA');
}

//-----------------------------------------
function AnexoSubdir($subdirAnexos, &$Opcoes)
{
	global $DirAnexos, $DirImagens, $UsingXmlModel, $model, $iContCateg;

	// Obtem todas as categorias - diretorios
	$codigo = explode('/', $subdirAnexos);
	$codigo = isset($codigo[2]) ? $codigo[2] : $codigo[1];
	$DirCategs = scandir($DirAnexos.$subdirAnexos);
//echo "<!-- Dir $subdirAnexos="; print_r($DirCategs); echo " -->\n";
	foreach ($DirCategs as $categ)
	{
		if ($categ == '.' || $categ == '..')
			continue;

		$dirCateg = $subdirAnexos.'/'.$categ;
		if (is_dir($DirAnexos.$dirCateg))
		{
			// Obtem todas as descricoes das categoria
			$iContAnexos = 0;
			$aDescrs = scandir($DirAnexos.$dirCateg);
//echo "<!-- Dir $dirCateg="; print_r($aDescrs); echo " -->\n";
			foreach( $aDescrs as $descr )
			{
				if ($descr == '.' || $descr == '..')
					continue;

				$Documentos = array();
				$descrSubdir = $dirCateg.'/'.$descr;
				$descrDir = $DirAnexos.$descrSubdir;
				if (file_exists($descrDir) && is_dir($descrDir))
				{
					// Tem anexos nesta entrada entao cria link para abrir pagina de navegacao de imagens.
					$descrMtime = filemtime($descrDir);
					if ($UsingXmlModel)
					{
						$iSeq = 1 ;
						$Imagens = array();
						$aArqs = BuscaImagens($descrDir);
						foreach ($aArqs as $sArq) {
							$stat = @stat($sArq);
							if ($stat === false || $stat['size'] <= 0)
								continue;
							$sExt = substr($sArq, strlen($sArq)-4);
							if ($sExt == '.jpg')
								$tipoId = 'Imagem';
							else if ($sExt == '.pdf')
								$tipoId = 'PDF';
							else
								continue;
							$key = $stat['mtime'];
							while (isset($Documentos[$key])) $key++;
							$Documentos[$key] = array(GetFullUrl($sArq), $tipoId, $codigo);
//echo "<!-- link=$link / codigo=$codigo / Documentos="; print_r($Documentos); echo " -->\n";
						}
					}
					else
					{
						$tipoId = 'PDF';
						$link = "AbreImagens('', '$descrSubdir')";
						while (isset($Documentos[$descrMtime])) $descrMtime++;
						$Documentos[$descrMtime] = array($link, $tipoId, $codigo);
//echo "<!-- link=$link / codigo=$codigo / Documentos="; print_r($Documentos); echo " -->\n";
					}
				}

				if (!empty($Documentos))
				{
					krsort($Documentos, SORT_NUMERIC);
					while (isset($Opcoes[$categ][$descrMtime])) $descrMtime++;
					$Opcoes[$categ][$descrMtime] = array($descr, $Documentos);
				}
			}
		}
		krsort($Opcoes[$categ], SORT_NUMERIC);
	}
}

//-----------------------------------------
function Anexos($aDirAnexos, $usarPrefixo)
{
	global $UsingXmlModel, $DirAnexos, $iContCateg, $DirImagens, $model;

//echo "<!-- Anexos("; print_r($aDirAnexos); echo ") -->\n";
	$Opcoes = array();
	foreach ($aDirAnexos as $dirOrigem)
		AnexoSubdir($dirOrigem, $Opcoes, $usarPrefixo);

//echo "<!-- Opcoes="; print_r($Opcoes); echo ") -->\n";
	if (empty($Opcoes))
		return;

	foreach ($Opcoes as $categ=>$Descricoes)
	{
		$model->assign('CATEG_ANEXO', decodeFileName($categ));
		$iContAnexos = 0;

		foreach ($Descricoes as $Arquivos)
		{
			$iSeq = 0;
			$descr = $Arquivos[0];

			foreach ($Arquivos[1] as $Documento)
			{
				$tipo = $Documento[1];
				$icone = $DirImagens.($tipo=='PDF'?'anexo-pdf.png':'anexo-img.png');
				if ($usarPrefixo)
					$descrAnexo = ' '.$Documento[2].'-'.$descr;
				else
					$descrAnexo = ' '.$descr;

				$model->assign('ANEXO_DESCR', ISO8859_1toModel(decodeFileName($descrAnexo)));
				$model->assign('ANEXO_TIPO_ALT', $tipo);
				$model->assign('ANEXO_TIPO', $icone);
				$model->assign('ANEXO_LINK', $Documento[0]);

				if ($UsingXmlModel)
					$model->parse($iSeq++ == 0 ? 'URL' : '.URL');
				else
					$model->parse($iContAnexos++==0 ? 'ANEXO' : '.ANEXO');
			}

			if ($UsingXmlModel)
				$model->parse($iContAnexos++==0 ? 'ANEXO' : '.ANEXO');
		}

		if ($iContAnexos > 0)
			$model->parse($iContCateg++==0 ? 'CATEGORIA' : '.CATEGORIA');
	}
}

//---main---------------------------------------------------------------------
$usuario = GetSessao('usuario');
$usuario_id = GetSessao('usuario_id');
if (empty($usuario) || empty($usuario_id))
{
	// Ja' foi efetuado um logout, deve ser pagina anterior.
	$sUrl = GetSessao('login_url');
	if (empty($sUrl))
		Mensagem('Erro', 'Sessão encerrada, efetue o LOGIN!');
	else
		header('Location: ' .$sUrl);
	exit;
}

$orig = CampoObrigatorio('PROD');
$codigo = CampoObrigatorio('CHAVE');
$usarPrefixo = false;
if ($orig == 'C')
{
	$Relacion = 'CONDOMÍNIO';
	$aAnexos = GetSessao('anexos_cond');
}
else if ($orig == 'PA' || $orig == 'PL')
{
	$Relacion = 'CONTRATO DE ADMINISTRAÇÃO';
	$aAnexos = GetSessao('anexos_propr');
	$usarPrefixo = true;
}
else if ($orig == 'PC')
{
	$Relacion = 'CADASTRO DE PROPRIETÁRIO';
	$aAnexos = GetSessao('anexos_propr_cad');
	$usarPrefixo = true;
}
else if ($orig == 'LC')
{
	$Relacion = 'CONTRATO DE LOCAÇÃO';
	$aAnexos = GetSessao('anexos_locat');
	$usarPrefixo = true;
 }
else if ($orig == 'FI')
	$Relacion = 'LOCAÇÃO';
else
{
	Mensagem('Aviso', 'Esta informação não está disponível!');
	exit;
}

// Definicoes do template
$model = new DTemplate($DirModelos);
$model->define_templates( array ( 'anexo' => Modelo($DirModelos, 'anexo')));
$model->define_dynamic('CATEGORIA', 'anexo');
$model->define_dynamic('ANEXO', 'CATEGORIA');

$model->assign('TIPO_RELACIONAMENTO', ISO8859_1toModel($Relacion));

if ($UsingXmlModel)
{
	$model->define_dynamic('URL', 'ANEXO');
	$model->assign('CODIGO', $codigo);
	$model->assign('USUARIO', ISO8859_1toModel($usuario));
	$model->assign('DATA_ATUAL', date('d/m/Y H:i'));
	if (isset($_SERVER["SCRIPT_URI"]))
	{
		$protocolo = explode(':',$_SERVER["SCRIPT_URI"]);
		$protocolo = $protocolo[0];
	}
	else
		$protocolo = 'http';
	$UrlBase = $protocolo.'://'.$_SERVER['HTTP_HOST'].dirname($_SERVER["REQUEST_URI"]).'/exibeAnexos.php';
}
else
{
	$model->assign('DESCR_SERV', ISO8859_1toModel(Campo('DESC_SERV')));
}

// Monta a(s) tabela(s) de categoria(s) dos anexos
$iContCateg = 0;
if ($orig == 'FI')
	FichaImovel($codigo);
else if (isset($aAnexos[$codigo]))
	Anexos($aAnexos[$codigo], $usarPrefixo);

if ($iContCateg == 0)
{
	//Mensagem('Aviso', 'Nenhum anexo no momento!');
	exit;
}

$model->parse('anexo');
$model->DPrint('anexo');
?>
