<?php
include "msg.php";
include "pesqImov.inc.php";

$DirDados = Configuracao('DIR_DADOS');
$DirModelos = Configuracao('DIR_MODELOS_PESQUISA');

//---main-------------------------------------------------------------------------
//session_cache_limiter('public');
//$cache = session_cache_expire(5); 
header('Content-Type: text/html; charset=ISO-8859-1');

// Esquema antigo de pagina que tinha tag {QUARTOS}. Agora esta direto num <TABLE> da pagina.
$Quartos = '&nbsp;&nbsp;N&ordm; Dormitm&oacute;rios:<SELECT name=nro_quartos><OPTION selected>Todos</OPTION><OPTION>1</OPTION><OPTION>2</OPTION><OPTION>3</OPTION><OPTION>4 ou mais</OPTION></SELECT>';

$model = new DTemplate($DirModelos);

$lv = Campo('lv');
if ($lv === false) {
	$lv = 'lv';
	$bLoc = true;
	$bVenda = true;
	$model->TelaUnica = 2;
	$ExibeResCom = true;
} else if ($lv == _LOCACAO) {
	$bLoc = true;
	$bVenda = false;
	$model->TelaUnica = 1;
	$ExibeResCom = (strtoupper(Configuracao('EXIBE_RESID_COMERC_LOCACAO')) != 'NAO');
} else if ($lv == _VENDA) {
	$bLoc = false;
	$bVenda = true;
	$model->TelaUnica = 1;
	$ExibeResCom = (strtoupper(Configuracao('EXIBE_RESID_COMERC_VENDA')) != 'NAO');
} else {
	Mensagem("O campo 'lv' possui valor invalido: '$lv'");
	exit(1);
}

if ($bLoc)
	$CidadeDefaultL = strtoupper(Configuracao("CIDADE_LOC"));
if ($bVenda)
	$CidadeDefaultV = strtoupper(Configuracao("CIDADE_VENDA"));

if ($ExibeResCom)
	$ResCom = Campo('seltipo');
else
	$ResCom = '*';	// Exibe comerciais e residenciais juntos

$OpcCidade = Campo('selcidade');
if (!empty($ResCom) && !empty($OpcCidade))
{
	// Pesquisa com 2 telas (cidade e tipo ja' selecionados).
	$Cidade = explode(":", $OpcCidade);
	$Cidade[0] = trim($Cidade[0]);
	$Cidade[1] = trim($Cidade[1]);
	$Cidade[2] = trim($Cidade[2]);
	$Modelos = array(_VENDA => "buscavenda.shtml", _LOCACAO => "busca.shtml");
	$model->TelaUnica = 0;
	SetSessao("RES_COM", $ResCom);
	SetSessao("CODCIDADE", $Cidade[0]);
	SetSessao("NOMECIDADE", $Cidade[1]);
	SetSessao("UF", $Cidade[2]);
	if ($ResCom == 'C' || $ResCom == '*')
		$Quartos = "&nbsp;";
} else {
	$ResCom = 'R';
	if ($model->TelaUnica == 1) {
		// Pesquisa em tela unica com venda OU locacao.
		$Modelos = array(_VENDA => "pesqImovVenda.shtml", _LOCACAO => "pesqImovLoc.shtml");
		$Cidade = PesqCidade($lv, $bLoc ? $CidadeDefaultL : $CidadeDefaultV);
	} else {
		// Pesquisa em tela unica com os dois (venda E locacao).
		$Pagina = Campo('pagina');
		if (empty($Pagina))
			$Modelos = array($lv => "pesqImovUnif.shtml");
		else
		{
			if (!@is_file($DirModelos.$Pagina))
			{
				Mensagem($DirModelos.$Pagina, "N&atilde;o encontrou o modelo especificado em: 'pagina=$Pagina'");
				exit(1);
			}
			$Modelos = array($lv => $Pagina);
		}
		$Cidade = PesqCidade($lv, $bLoc ? $CidadeDefaultL : $CidadeDefaultV);
	}
}

echo "<!-- \n"; print_r($Cidade); echo "-->\n";

$model-> define_templates( array ( 'pesquisa' => $Modelos[$lv]));
$model->define_dynamic('CARAC', 'pesquisa');
$model->define_dynamic('BAIRROS', 'pesquisa');
$model->define_dynamic('TIPOS', 'pesquisa');

$model->assign('CIDADE', implode(':',$Cidade));
$model->assign('NOMECIDADE', empty($Cidade[1]) ? $Cidade[1] : 'TODAS CIDADES');
$model->assign('QUARTOS', $Quartos);
$model->assign('RES_COM', $ResCom);

MontaCaracs($model);	// Apaga campo nas telas antigas

if ($model->TelaUnica < 2) {
	if ($model->TelaUnica == 1)
		$model->define_dynamic('CIDADES', 'pesquisa');
	$result = MontaTipos($model, $lv, $ResCom);
	$lista = str_replace("var TiposR = [", "var TiposR".$lv." = [", $result);
	$lista = str_replace("var TiposC = [", "var TiposC".$lv." = [", $lista);
	$model->assign('LISTA_DE_TIPOS', $lista);

	$result = MontaBairros($model, $lv, $Cidade[0]);
	$lista = str_replace("var Bairros = [", "var Bairros".$lv." = [", $result[1]);
	$model->assign('LISTA_DE_BAIRROS', $lista);
	$lista = str_replace("var Cidades = [", "var CidBairros".$lv." = [", $result[0]);

	if ($model->TelaUnica == 1) {
		$result = MontaCidades($model, $lv, $bLoc ? $CidadeDefaultL : $CidadeDefaultV);
		$lista .= str_replace("var Cidades = [", "var Cidades".$lv." = [", $result);
		$model->assign('LISTA_DE_CIDADES', $lista);
	}
} else {
	$model->define_dynamic('CIDADES', 'pesquisa');
	$model->TelaUnica = 2;

	for ($i = 0; $i < 2; $i++) {
		$lv = ($i == 0 ? _LOCACAO : _VENDA);

		$result = MontaTipos($model, $lv, $ResCom);
		$lista = str_replace("var TiposR = [", "var TiposR".$lv." = [", $result);
		$lista = str_replace("var TiposC = [", "var TiposC".$lv." = [", $lista);
		$model->assign('LISTA_DE_TIPOS_'.$lv, $lista);

		$result = MontaBairros($model, $lv, $Cidade[0]);
		$lista = str_replace("var Bairros = [", "var Bairros".$lv." = [", $result[1]);
		$model->assign('LISTA_DE_BAIRROS_'.$lv, $lista);
		$lista = str_replace("var Cidades = [", "var CidBairros".$lv." = [", $result[0]);

		$result = MontaCidades($model, $lv, $lv==_LOCACAO ? $CidadeDefaultL : $CidadeDefaultV);
		$lista .= str_replace("var Cidades = [", "var Cidades".$lv." = [", $result);
		$model->assign('LISTA_DE_CIDADES_'.$lv, $lista);
	}
}

$model->parse('pesquisa'); 
$model->DPrint('pesquisa');
session_write_close ();

//phpinfo();

return 1;
?>
