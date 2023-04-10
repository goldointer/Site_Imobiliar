<?php
include 'msg.php';
include 'pesqCli.php';

header('Content-Type: text/html; charset=ISO-8859-1');

$DirDados = Configuracao('DIR_DADOS');
$DirModelos = Configuracao('DIR_MODELOS_AREACLIENTE');

//---main-------------------------------------------------------------------------

$model = new DTemplate($DirModelos);
$model->define_templates( array( 'alteraSenha' => Modelo($DirModelos, 'alteraSenha') ));

$Mensagem = '';
$oper = Campo('OPERACAO');
$pId = GetSessao('usuario_cod');
$usuario = GetSessao('usuario');

//print_r($_REQUEST);
//print_r($_SESSION);

if (strcasecmp($oper, 'alterar') == 0)
{
	if (isset($_REQUEST["cancelar"]))
	{
		if (strcasecmp($oper, 'expirou') == 0)
		{
			$tela = GetSessao('ORIGEM_TELA');
			if ($tela != '')
				header("Location: $tela");
			else
				Submeter('alteraSenha.php', array('OPERACAO'=>'expirou','ORIG'=>'alteraSenha','REFERER'=>$_SERVER["HTTP_REFERER"]));
		}
		else
			Submeter('crLogin.php', array('LOGIN'=>$pId, 'SENHA'=>GetSessao('usuario_senha')));
		exit;
	}
	$pSenha = CampoObrigatorio('SENHA');
	$usuario_id = ValidaLogin($pId, $pSenha, true);
	if (is_array($usuario_id))
	{
		$pNova = Campo('SENHANOVA1');
		if ($pNova === false)
			$pNova = CampoObrigatorio('SENHANOVA');

		if (!$UsingXmlModel)
		{
			$pNova2 = CampoObrigatorio('SENHANOVA2');

			if ($pNova != $pNova2)
				$Mensagem = 'Novas senhas nao conferem entre si!';
			else if ($pSenha == $pNova)
				$Mensagem = 'Nova senha deve ser diferente da senha atual!';
		}
		if (empty($pNova))
				$Mensagem = 'Nova senha nao pode ser vazia!';
		if (empty($Mensagem))
		{
			if (!GravaNovaSenha($pId, $pNova))
				$Mensagem = 'Nao foi possivel trocar a senha! Continue a usar a senha antiga.';
			else if (!$UsingXmlModel)
				Submeter('crLogin.php', array('LOGIN'=>$pId, 'SENHA'=>$pNova));
		}
	}
	else
		$Mensagem = $usuario_id;
}

if ($UsingXmlModel)
{
	if (empty($Mensagem))
	{
		$model->assign('CODIGO', $pId);
		$model->assign('USUARIO', ISO8859_1toModel($usuario));
		$model->assign('DATA_ATUAL', date('d/m/Y H:i'));
	}
	else
	{
		$model->assign('CODIGO', $pId);
		Mensagem("ALTERACAO DE SENHA", $Mensagem);
		exit();
	}
}
else
{
	echo "<!-- DTemplate: alteraSenha.shtml ($oper, $Mensagem) -->\n";
	$model->assign('OPERACAO', $oper);
	$model->assign('TIMESTAMP', time());
	$model->assign('MSG', $Mensagem);
}

$model->parse('alteraSenha');
$model->DPrint('alteraSenha');
?>
