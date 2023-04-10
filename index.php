<?php
chdir('prg');
include 'msg.php';

$fmt = Campo('fmt');
if (empty($fmt))
	$thumb = Configuracao('EXIBE_THUMBNAILS');
else {
	$thumb = ($fmt == 'T' ? 'SIM': 'NAO');
	$_SESSION['_CONF_EXIBE_THUMBNAILS'] = $thumb;
	session_write_close ();
}
echo "<!-- EXIBE_THUMBNAILS=$thumb -->\n";
echo "<!-- _CONF_EXIBE_THUMBNAILS=${_SESSION['_CONF_EXIBE_THUMBNAILS']} -->\n";
?>

<html>
<head>
  <meta content="text/html; charset=ISO-8859-1"
 http-equiv="content-type">
  <title>Inetsoft Imobiliar</title>

<script language="JavaScript" type="text/JavaScript">
<!--
function ExibeDestaque(lv, cod){
	url = "prg/exibeFotos.php?cod=" + cod + "&lv=" + lv + "&destaque=sim";
	window.open(url,"fotos","width=665,height=580,top=50,left=100,toolbar=no,resizable=yes,scrollbars=yes");
}

function TrocaFormato(){
	var fmt = document.fmt.rbFmt[0].checked ? "L" : "T";
	document.location.replace("index.php?fmt=" + fmt);
}
//-->
</script>
</head>

<body>
<big><big>
<big style="color: rgb(51, 102, 255); text-decoration: underline;">
<font style="font-weight: bold;">Imobiliar - Demonstra&ccedil;&atilde;o Internet <br>
</font></big></big></big><br>

<big><big>Op&ccedil;&otilde;es de formato de exibi&ccedil;&atilde;o das pesquisas:
</big></big>
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
<form name="fmt" method="get" action="">
<input type="radio" name="rbFmt" value="L" onchange="TrocaFormato()" <?php if ($fmt != "T") echo "checked"; ?> >Lista
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
<input type="radio" name="rbFmt" value="T" onchange="TrocaFormato()" <?php if ($fmt == "T") echo "checked"; ?> >Thumbnails
</form>

<hr>
<big><big>Op&ccedil;&otilde;es para incluir no menu do site:
</big></big><br>
<!-- No argumento 'page' deve ser especificado um URI absoluto para
	 a pagina de identificacao do usuario com campos ID e SENHA. -->
<a href="prg/crLogin.php?page=../clionline.html">Cliente On-Line</a>
<br><br>
<a href="prg/pesqImov.php">Pesquisa Unificada de Im&oacute;veis (loca&ccedil;&atilde;o e venda)</a>
<br>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;OU<br>
<a href="prg/pesqImov.php?lv=L">Pesquisa de Im&oacute;veis para Loca&ccedil;&atilde;o (uma tela)</a>
<br>
<a href="prg/pesqImov.php?lv=V">Pesquisa de Im&oacute;veis para Venda (uma tela)</a>
<!--
<br>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;OU<br>
<a href="prg/pesqCid.php?lv=L">Pesquisa de Im&oacute;veis para Loca&ccedil;&atilde;o (duas telas: cidade+busca)</a>
<br>
<a href="prg/pesqCid.php?lv=V">Pesquisa de Im&oacute;veis para Venda (duas telas: cidade+busca)</a>
<br><br>
-->

<hr>
<big><big>Exemplo de pesquisa r&aacute;pida:</big></big><br>
(utilizada quando se quer incluir na pagina principal)<br>
<iframe src="prg/pesqImov.php?pagina=pesqImovReduzida.shtml" width="360" height="180" allowtransparency="yes" frameborder="1" scrolling="No" style="background-color: rgb(153, 153, 153);">
</iframe>
<br>

<hr>
<big><big>Exemplo de inser&ccedil;&atilde;o de destaque para loca&ccedil;&atilde;o:
</big></big><br>
=> <a href="javascript:ExibeDestaque('L', 10375);">Casa com 340m2 no Bairro Tr&ecirc;s Figueiras</a>
<br>
=> <a href="javascript:ExibeDestaque('L', 13742);">Casa em condominio fechado, cond. com piscina, quadra de esportes, play ground, sal&atilde;o de festas c/ churrasq. e ampla &aacute;rea verde</a>
<br>

<hr>
<big><big>Exemplo de inser&ccedil;&atilde;o de destaque para venda:
</big></big><br>
=> <a href="javascript:ExibeDestaque('V', 14148);">Apartamento 2 dormit&oacute;rios, sal&atilde;o de festas, playground, lareira, etc.</a>
<br>
=> <a href="javascript:ExibeDestaque('V', 13562 );">Loja na Ramiro Barcelos</a>
<br>

</body>
</html>
