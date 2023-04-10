<?php
include 'msg.php'; 
include 'imagens.inc.php';

$DirAnexos = Configuracao('DIR_ANEXOS');
$UsingXmlModel = GetSessao('USING_XML_MODEL');

//--- main ------------------------------------

$arq = Campo('pdf');
if (!empty($arq))
	ExibePdf($DirAnexos.$arq);

$iCod = CampoObrigatorio('cod');
$sParam = Campo('param');

if (empty($iCod))
	// Paginas novas nao informam codigo e vem com diretorio base completo.
	$sDir = $DirAnexos.$sParam;
else
	// Paginas antigas tem codigo de condominio e tem que apontar
	// so' para pasta de anexos de condominio.
	$sDir = sprintf($DirAnexos.'Condom/%d/%s', $iCod, $sParam);

$aArqs = BuscaImagens($sDir, true);
$contArqs = count($aArqs); 
if ($contArqs == 0)
{
	Mensagem('Aviso', 'Imagem não disponível!');
	exit(1);
}

$iIdx = Campo("idx");
if ($iIdx === false) {
	// Exibir a tela de anexos
	if ($contArqs < 2) {
		foreach ($aArqs as $sArq) break;
		ExibeUmaImagem($sArq);
	} else 
		ExibeTelaComImagens($aArqs, $iCod, $sParam);
} else {
	// Exibir apenas a imagem solicitada
	if (intval($iIdx) <= 0)
		$iIdx = 1;
	$seq = 1;
	foreach ($aArqs as $sArq) {
		if ($seq++ == $iIdx)
			break;
	}
	ExibeUmaImagem($sArq);
}
?>
