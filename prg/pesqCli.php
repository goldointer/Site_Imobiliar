<?php

$hUsuarios = false;
$bCript = false;
$sCript = "";

function regCli()
{
// Registro do usuario:
//	id        10;
//	pass      20;
//	name      35;
//	----> Formato original: 65 bytes ate' aqui <----
//	cpfcnpj   14;
//	email     64;
	GLOBAL $hUsuarios, $bCript;

	if ($hUsuarios === false)
	{
		// Deve abrir o arquivo de usuario
		GLOBAL $DirDados;

		$name = $DirDados.'usuarios';
		$hUsuarios = @fopen($name, 'r');
		if ($hUsuarios === false)
		{
			$name .= '.txt';
			$hUsuarios = @fopen($name, 'r');
		}
		else
		{
			@unlink($name.".txt");
			$bCript = true;
		}
//print("<!-- $name ($hUsuarios)\n");
	}

	if ($hUsuarios === false)
		$sReg = '';
	else if ($bCript)
	{
		GLOBAL $sCript;
		for ($sReg = '';;)
		{
			if (empty($sCript) || $sCript == "\n")
			{
				$sCript = fgets($hUsuarios, 4096);
				if (empty($sCript))
					break;
			}
			$ch = substr($sCript,1,3);
			$sCript = substr($sCript, 4);
			$sReg .= chr(octdec($ch));
			if ($ch == "012")
				break;
		}
//print("#$sReg \n");
	}
	else
	{
		$sReg = fgets($hUsuarios, 4096);
//print("|$sReg \n");
	}

	return $sReg;
}

function TestaNovaSenha($pId, &$pPass)
{
	GLOBAL $DirDados;
	
//print("<!-- TestaNovaSenha($pId, $pPass) -->\n");
	$bRet = true;
	$bTemNovaSenha = false;
	$name = $DirDados."retornoImobiliar.txt";
	$hArq = @fopen($name, "r");
	if ($hArq !== false)
	{
//print("<!-- $name OK\n");
		while (!feof($hArq))
		{
			$aReg = fgetcsv($hArq, 0, ';');
//print_r($aReg);
			if (empty($aReg))
				continue;
			if ($aReg[0] == 'S' && $aReg[1] == $pId)
			{
				// Usuario tem nova senha
				$bTemNovaSenha = true;
				if ($aReg[2] == $pPass)
					$bRet = true;	// Confere
				else
					$bRet = false;	// NAO confere
			}
		}
		fclose($hArq);
	}
//else print("<!-- $name NOT\n");
//print("-->\n");

	if ($bTemNovaSenha)
		$pPass = NULL;

	return $bRet;
}

function GravaNovaSenha($pId, $pPass)
{
	GLOBAL $DirDados;
	
//print("<!-- GravaNovaSenha($pId, $pPass) -->\n");
	$bRet = true;
	$name = $DirDados."retornoImobiliar.txt";
//print('<!--'.getcwd()." $name -->\n");
	$hArq = @fopen($name, "a");
	if ($hArq === false)
		return false;

	$bRet = fputcsv($hArq, array('S',$pId, $pPass), ';');
	fclose($hArq);

//print("<!-- OK -->\n");
	return $bRet;
}

function PesqCli($pId, $pPass=NULL)
{
/*
Registro de usuario:
Cod_pessoa	10;
Senha		20;
Nome		35;
Cpf/Cnpj	14;
Email		64;

* Os registros devem vir ordenados pelo campo a pesquisar, a menos que
  a configuracao contenha "LOGIN_DESORDENADO=SIM".
* O campo de pesquisa default e' o codigo da pessoa.
* Para utilizar pesquisa por Cpf/Cnpj a configuracao deve ter "LOGIN_CPFCNPJ=SIM".
* Para utilizar pesquisa por Email a configuracao deve ter "LOGIN_EMAIL=SIM".
* O arquivo pode ser utilizado criptografado:
	Para criptografar: od -A n -t oC usuarios.txt >usuarios
	Para consultar: crLogin.php?usuarios
* A configuracao deve ser efetuada no "imobiliar.ini" do servidor do Imobiliar e
  no "config_inc.php" do site.
*/
//print("<!-- PesqCli($pId, $pPass) -->\n");

	$iTamReg = 65;
	$bCpfCnpj = (Configuracao('LOGIN_CPFCNPJ') == 'SIM');
	$bEmail = (Configuracao('LOGIN_EMAIL') == 'SIM');
	$bOrdenado = (Configuracao('LOGIN_DESORDENADO') != 'SIM');
//print('<!-- Pesquisa '.($bOrdenado?'ordenada ':'desordenada ').($bCpfCnpj?'por CPF/CNPJ':($bEmail?'por Email':'por Codigo de Pessoa'))." -->\n");

	$pId = trim($pId);
	if (!$bEmail && !$bCpfCnpj)
		$pId = floatval($pId);
	if (empty($pId))
		return NULL;
	if ($bCpfCnpj)
	{
		$pId = preg_replace("/[^0-9]/", "", $pId);// Deixa apenas os numeros do Cpf/Cnpj
		$pId = ltrim($pId, '0');
	}
	else if (!$bEmail && floatval($pId) == 0)
		return NULL;

	if (!is_null($pPass)) {
		$pPass = trim($pPass);
		if (empty($pPass))
			return NULL;
		if (!TestaNovaSenha($pId, $pPass))
			// Senha nao conferiu
			return array('####', '0', '0', '');
	}

 	// Pesquisa referencias a este usuario
//print("\n<!-- INICIO\n");
	for(;;)
	{
		$sReg = regCli();
//print("reg=$sReg\n");
		if (empty($sReg) || strlen($sReg) < $iTamReg)
			break;

		if ($bCpfCnpj) {
			$sUserId = trim(substr($sReg, 65, 14));
			$aUserId = array($sUserId);
		} else if ($bEmail) {
			// Vai permitir lista de emails separados por ';'
			$sUserId = trim(substr($sReg, 79, 64));
			$aUserId = explode(';', $sUserId);
			foreach ($aUserId as $key => $val)
				$aUserId[$key] = trim($val);
		} else {
			$sUserId = trim(substr($sReg, 0, 10));
			$aUserId = array($sUserId);
		}
		
		// Verifica se bate a chave no registro corrente
//print("aUserId="); print_r($aUserId);
		$bAchou = false;
		foreach ($aUserId as $sChave) {
			if (strcmp($pId, $sChave) == 0) {
				$bAchou = true;
				break;
			}
		}
		
		if (empty($aRet))
		{
			// Procurando primeira ocorrencia da chave
			if ($bAchou)
			{
//print("$pId IN ($sUserId) [achou ID] \n");
				$sUserPass = trim(substr($sReg, 10, 20));
				$sMd5Pass = md5(strtoupper($sUserPass));
				if (is_null($pPass) || $pPass == $sUserPass || strcasecmp($pPass, $sMd5Pass) == 0)
				{
					$sCpfCnpj = trim(substr($sReg, 65, 14));
					$sEmail = trim(substr($sReg, 79, 64));
					$sUser = trim(substr($sReg, 30, 35));
//print(is_null($pPass)? "[nova senha OK]\n" : "$pPass == $sUserPass [senha OK]\n");
				}
				else if ($bCpfCnpj || $bEmail)
				{
					$sCpfCnpj = '0';
					$sEmail = '';
					$sUser = '';
				}
				else
					return array('####', '0', '0', '');
				$aRet = array($sUser, $sCpfCnpj, $sEmail, trim(substr($sReg, 0, 10)));
			}
			else if ($bOrdenado && intval($pId) < intval($sUserId))
			{
				// Como esta ordenado, nao vai mais encontrar o codigo
//print("$pId < $sUserId (NAO achou) \n");
				break;
			}
		}
		else
		{
			// Procurando outras ocorrencias da chave
			if ($bAchou)
			{
				if (empty($aRet[0]))
				{
					$sUserPass = trim(substr($sReg, 10, 20));
					$sMd5Pass = md5(strtoupper($sUserPass));
					if (is_null($pPass) || $pPass == $sUserPass || strcasecmp($pPass, $sMd5Pass) == 0)
					{
//print("$pPass == $sUserPass | \n");
						$aRet[0] = substr($sReg, 30, 35);
					}
				}
//print("$pId IN ($sUserId) [ACHOU outra ocorrencia] \n");
				$aRet[] = trim(substr($sReg, 0, 10));
			}
			else if ($bOrdenado) 
			{
					// Como esta ordenado, nao vai mais encontrar o codigo
//print("$pId NOT IN ($sUserId) [NAO achou outra ocorrencia] \n");
					break;
			}
		}
	}
//print("\nFIM -->\n");

	if (empty($aRet))
		// Codigo nao encontrado
		return array('', '0', '0', '');
	if (empty($aRet[0]))
		// Senha nao conferiu
		return array('####', '0', '0', '');

	return $aRet;
}

/* */
function ValidaLogin($pId, $pPass, $retornaErro=false)
{
//echo "<!-- ValidaLogin($pId,$retornaErro) -->\n";
	$Mensagem = '';
	if (empty($pId))
		$Mensagem = 'Usuario invalido!';
	else
	{
		// Valida usuario
		$LoginComSenha = (Configuracao('LOGIN_SENHA', 'SIM') == 'SIM');
		if ($LoginComSenha)
		{
			if (empty($pPass))
				$usuario_id = array('####', '0', '0', '');
			else
				$usuario_id = PesqCli($pId, $pPass);
		}
		else
			$usuario_id = PesqCli($pId);
//echo "<!-- ValidaLogin: "; print_r($usuario_id); print(" -->\n");

		if (is_array($usuario_id))
			$usuario = $usuario_id[0];

		if (empty($usuario))
			$Mensagem = 'Usuário não cadastrado!';
		else if (strcmp($usuario, '####') == 0)
			$Mensagem = 'Senha inválida!';
		else
		{
			SetSessao('usuario_cod', $pId);
			SetSessao('usuario_senha', $pPass);
		}
	}

	if (!empty($Mensagem))
	{
		if ($retornaErro)
			return $Mensagem;
		Mensagem('ERRO', $Mensagem);
		exit;
	}

	return $usuario_id;
}

/*
Exibe arquivo de usuarios do Sistema Imobiliar.
*/
function showCli()
{
	if ($bCript)
	{
		header("Content-Type: text/plain");
		for (;;)
		{
			$sReg = regCli();
			if (empty($sReg))
				break;
			echo $sReg;
		}
	}
	exit;
}
?>
