<?php
if (isset($_GET['sessionid'])) {
	$sessionid=$_GET['sessionid'];
	session_id($sessionid);
	session_start();
}

include "msg.php"; 
include "imagens.inc.php";

//--- main ------------------------------------

$iLancto = CampoObrigatorio("cod");
$iIdx = Campo("idx");
$TipoLancto = GetSessao('tipo_lancamento');

if ($TipoLancto == 'L')
	$DirLanctos = Configuracao('DIR_LANCAMENTOS_LOC');
else
	$DirLanctos = Configuracao('DIR_LANCAMENTOS');
$sDir = $DirLanctos.sprintf("%d/%d", intval($iLancto / 1000) * 1000, $iLancto);
$aArqs = BuscaImagens($sDir);
$contArqs = count($aArqs); 
if ($contArqs < 1)
{
	Mensagem('Nao existe imagem para este lancamento ('.$iLancto.').');
	exit(1);
}

 if (empty($iIdx)) {
	// Exibir a tela de lancamentos
	if ($contArqs < 2) {
		foreach ($aArqs as $sArq) break;
		ExibeUmaImagem($sArq);
	} else {
		ExibeTelaComImagens($aArqs, $iLancto, $TipoLancto);
	}
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
