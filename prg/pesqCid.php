<?php
include "msg.php";
include "pesqImov.inc.php";

$DirModelos = Configuracao('DIR_MODELOS_PESQUISA');

//---main-------------------------------------------------------------------------
header('Content-Type: text/html; charset=ISO-8859-1');
$model = new DTemplate($DirModelos);

$lv = CampoObrigatorio('lv');
SetSessao("ORIGEM_MSG", $lv);

if ($lv == _VENDA) {
	$model->define_templates( array ( 'cidade' => 'cidadevenda.shtml')); 
	$CidadeDefault = Configuracao("CIDADE_VENDA");
	$ExibeResCom = (strtoupper(Configuracao('EXIBE_RESID_COMERC_VENDA')) != 'NAO');
} else {
	$model->define_templates( array ( 'cidade' => 'cidadeloc.shtml')); 
	$CidadeDefault = Configuracao("CIDADE_LOC");
	$ExibeResCom = (strtoupper(Configuracao('EXIBE_RESID_COMERC_LOCACAO')) != 'NAO');
}
$model->define_dynamic('CIDADES', 'cidade');

$model->TelaUnica = 1;
MontaCidades($model, $lv, $CidadeDefault);

if (file_exists("pesqCid.inc.php"))
	include "pesqCid.inc.php";

if (!$ExibeResCom)
	$model->assign('EXIBE_RES_COM', 'style="display:none"');

$model->parse('cidade'); 
$model->DPrint('cidade');

session_write_close ();
?>
