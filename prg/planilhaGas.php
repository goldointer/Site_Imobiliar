<?php
include "msg.php"; 

header('Content-Type: text/html; charset=ISO-8859-1');

$DirDados = Configuracao('DIR_DADOS');
$DirModelos = Configuracao('DIR_MODELOS_AREACLIENTE');
$contDel = 0;
$bSetTimeLimit = TRUE;

//----------------------------------------------------------------------------------
function myErrorHandler($type, $info, $file, $row)
{
	global $bSetTimeLimit;
	$bSetTimeLimit = FALSE;
//echo "#SEM set_time_limit()\n";
}

//----------------------------------------------------------------------------
function LimpaCSVs($compet, $dirBase, $subdir='')
{
	GLOBAL $bSetTimeLimit, $geraCmdFTP, $contDel;

	$cont = 0;
//echo "#LimpaCSVs($compet,$dirBase,$subdir)\n";
	$fh = opendir($dirBase.$subdir);

	while (false !== ($dirEntry = readdir($fh))) {
//echo "#$dirEntry\n";
		if ($dirEntry{0} == '.')
			continue;

		$file = $subdir.$dirEntry;
		if (is_file($dirBase.$file)) {
			if ($dirEntry{0} != 'G' && $dirEntry{0} != 'A')
				continue;

			// Verifica competencia
			$pos = strrpos($dirEntry, '-');
			$competArq = substr($dirEntry,$pos+1);
			$pos = strrpos($competArq, '.');
			if ($pos === FALSE || substr($competArq, $pos) != '.csv')
				continue;
			$competArq = substr($competArq,0, $pos);
			if ($competArq >= $compet)
				continue;

			// Apagar arquivo antigo
			$file = $dirBase.$file;
			if (empty($geraCmdFTP) && @unlink($file) === false)
				$geraCmdFTP = true;
			if (!empty($geraCmdFTP))
			{
				echo "del $file\n";
				flush();
				$contDel++;
				$geraCmdFTP = true;
			}
			if (++$cont > 50)
			{
				$cont = 0;
				if ($bSetTimeLimit) set_time_limit(60);
			}
			if ($contDel > 500)
				break;

		} else if (is_dir($dirBase.$file)) {
			flush();
			$cont += LimpaCSVs($compet, $dirBase, $file."/");
		}
	}

	closedir($fh);
}

//----------------------------------------------------------------------------
function Limpa()
{
	GLOBAL $DirDados, $contDel;

	$competencia = CampoObrigatorio('competatual');
	$competencia = date('Ym', mktime(0,0,0, substr($competencia, 0, 2)-2, 1, substr($competencia, 3, 4)));

	/* Prepara chamada do FTP. */
	header('Content-type: text/plain');
	echo "pwd\n";
	flush();
	LimpaCSVs($competencia, $DirDados."gasagua/");

	/* Finaliza chamada do FTP. */
	echo "#OK $contDel\n";
}

//----------------------------------------------------------------------------
function ListaCSVs($dirBase, $subdir='')
{
	global $bSetTimeLimit;
	GLOBAL $Limite;

	$cont = 0;
	$contTimeout = 0;
	$fh = opendir($dirBase.$subdir);

	while (false !== ($dirEntry = readdir($fh))) {
		if ($Limite == 0)
			break;
		if ($dirEntry{0} == '.')
			continue;

		$file = $subdir.$dirEntry;
		if (is_file($dirBase.$file)) {
			if ($dirEntry{0} != 'G' && $dirEntry{0} != 'A')
				continue;
			$pos = strrpos($dirEntry, '.');
			if ($pos !== FALSE && substr($dirEntry, $pos) == '.csv') {
				$fd = @fopen($dirBase.$file, 'r');
				if ($fd !== FALSE) {
					$Linha = fgets($fd, 1024);
					$pos = strrpos($Linha, ';');
					if ($pos !== FALSE && substr($Linha,0,2) == 'CA' && $Linha[$pos+1] == 'S') {
						print "$file\n";
						$cont++;
						$Limite--;
					}
					fclose($fd);
				}
			}

			if (++$contTimeout > 50) {
				$contTimeout = 0;
				if ($bSetTimeLimit) set_time_limit(60);
			}
		} else if (is_dir($dirBase.$file)) {
			flush();
			$cont += ListaCSVs($dirBase, $file."/");
		}
	}

	closedir($fh);
	return $cont;
}

//----------------------------------------------------------------------------
function Encerradas()
{
	GLOBAL $DirDados, $Limite;

	header('Content-type: text/plain');
	$Limite = Campo('max');
	if (empty($Limite))
		$Limite = -1;
	$cont = ListaCSVs($DirDados."gasagua/");
	if ($cont == 0)
		print "#OK 0\n";
}

//----------------------------------------------------------------------------
function Processada()
{
	GLOBAL $DirDados;

	$arq = CampoObrigatorio('arq');
	header('Content-type: text/plain');
	if (@stat($DirDados.'gasagua/'.$arq) === FALSE) {
		echo "Error: '$arq' not found";
		exit(1);
	}
	$File = @fopen($DirDados.'gasagua/'.$arq, 'r+');
	if ($File === FALSE) {
		echo "Error: '$arq' not open for write";
		exit(1);
	}

	$Linha = fgets($File, 1024);
	$pos = strrpos($Linha, ';');
	if (empty($Linha) || substr($Linha,0,3) != 'CA;' || $pos === FALSE) {
		echo "Error: '$arq' invalid";
		exit(1);
	}
	if (!is_writable($DirDados.'gasagua/'.$arq) || fseek($File, $pos+1) != 0 || fwrite($File, 'P', 1) === FALSE) {
		echo "Error: '$arq' not writeble";
		exit(1);
	}
	fclose($File);
	echo 'Success';
}

//----------------------------------------------------------------------------
function SalvarDigitacao($File)
{
	GLOBAL $aRegs, $bEncerrado;

	$aEcon = array();
	foreach ($_REQUEST as $key => $value) {
		if (substr($key, 0, 5) == 'ECON_') {
			if (empty($value))
				$value = -1;
			$aEcon[substr($key, 5)] = $value;
		}
	}

	$bErro = FALSE;
	if (ftruncate($File, 0)) {
		if (fseek($File, 0) == 0) {
			foreach ($aRegs as $key => $aReg) {
				if ($aReg[0] == 'LE') {
					$val = $aEcon[$aReg[1]];
					$aRegs[$key][7] = $val;
					$aReg[7] = $val;
				}
				else if ($aReg[0] == 'CA') {
					if ($bEncerrado) {
						$aRegs[$key][11] = 'S';
						$aReg[11] = 'S';
					}
				}
				else
					continue;
				if (fputcsv($File, $aReg, ';') === FALSE) {
					$bErro = TRUE;
					break;
				}
			}
		}
		else
			$bErro = TRUE;
	}
	else
		$bErro = TRUE;

	if ($bErro) {
		Mensagem('Aten&ccedil;&auml;o', 'N&atilde;o foi poss&iacute;vel salvar os dados da planilha!');
		exit(1);
	}
}

//----------------------------------------------------------------------------
function LerArquivo($File)
{
	GLOBAL $aRegs;

	$aRegs = array();
	while (!feof ($File)) {
		$aReg = fgetcsv($File, 1024, ';');
		$aRegs[] = $aReg;
		if ($aReg[0] == 'CA')
			$bEncerrado =  ($aReg[11] == 'S');
	}
}

//----------------------------------------------------------------------------
function Planilha($Oper)
{
	GLOBAL $DirDados, $DirModelos, $aRegs, $bEncerrado;

	$usuario = GetSessao('usuario');
	$usuario_id = GetSessao('usuario_id');
	if (empty($usuario) || empty($usuario_id)) {
		// Ja' foi efetuado um logout, deve ser pagina anterior.
		$sUrl = GetSessao('login_url');
		if (empty($sUrl))
			Mensagem('Erro', 'Sess&aacute; encerrada, efetue o LOGIN!');
		else
			header('Location: '.$sUrl);
		exit;
	}
	
	// Monta nome do arquivo fisico
	$Chave = CampoObrigatorio('CHAVE');
	$Id = CampoObrigatorio('id');
	$Assessor = CampoObrigatorio('ASSESSOR');
	$tipoPlan = CampoObrigatorio('PROD');
	$Btn = CampoObrigatorio('btn');

	$UserFile = Campo('TIPO');
	if (empty($UserFile))
		$stat = FALSE;
	else {
		$UserFile = substr($Chave,0,5).'/'.$UserFile;
		$FilePath = $DirDados.'gasagua/'.$UserFile;
		$stat = @stat($FilePath);
	}

	if ($stat === FALSE || $stat['size'] <= 0) {
		$arr = explode('|', $Btn);
		if (count($arr) > 1)
			// Forma nova que passa nome do arquivo como valor extra
			$UserFile = $arr[0];
		else
		{
			// Forma antiga que so' passa competencia
			$arr = explode('/', $Btn);
			$aux = count($arr);
//echo "<!--\n"; print_r($arr); echo " -->\n";
			$Ano = trim($arr[$aux-1]);
			$Ano = substr($Ano, -4);
			$Mes = trim($arr[$aux-2]);
			$Mes = substr($Mes, -2);
			$ok = checkdate($Mes, 1, $Ano);
//echo "<!--$Mes / $Ano -->\n";
			if (!$ok) {
				Mensagem('Aten&ccedil;&atilde;o', 'Dados n&atilde;o dispon&iacute;veis no momento (ERRO INTERNO)!');
				exit(1);
			}
			$UserFile = $tipoPlan.$Chave.'-'.$Ano.$Mes.'.csv';
		}

		$UserFile = substr($Chave,0,5).'/'.$UserFile;
		$FilePath = $DirDados.'gasagua/'.$UserFile;
		$stat = @stat($FilePath);
		if ($stat === FALSE || $stat['size'] <= 0) {
			echo "\n<!-- $UserFile -->\n";
			Mensagem('Aten&ccedil;&auml;o', 'Dados n&atilde;o dispon&iacute;veis no momento!');
			exit(1);
		}
	}
	echo "\n<!-- $UserFile -->\n";

	if ($Oper == 'salvar' || $Oper == 'encerrar') {
		$File = @fopen($FilePath, 'r+');
		if ($File !== FALSE) {
			LerArquivo($File);
			$bEncerrado = ($Oper == 'encerrar');
			SalvarDigitacao($File);
		}
	}
	else {
		$File = @fopen($FilePath, 'r');
		if ($File !== FALSE) {
			LerArquivo($File);
			$bEncerrado = !is_writable($FilePath);
		}
	}
	if ($File === FALSE)
	{
		Mensagem('Aten&ccedil;&auml;o', 'Dados n&atilde;o dispon&iacute;veis no momento (sem permiss&atilde;)!');
		exit(1);
	}
	fclose ($File);

	$ArqModelo = 'gasdigitacao.html';
	$model = new DTemplate($DirModelos);
	$model->define_templates( array ( 'planilha' => $ArqModelo ));
	$model->define_dynamic('LEITURAS', 'planilha');
	$model->define_dynamic('BOTOES', 'planilha');

	$model->assign('ID', $Id);
	$model->assign('PROD', $tipoPlan);
	$model->assign('BTN', $Btn);
	$model->assign('ASSESSOR', $Assessor);
	$model->assign('CHAVE', $Chave);
	$model->assign('TIPO_PLANILHA', $tipoPlan=='G' ? 'G&Aacute;S' : '&Aacute;GUA');

	$aEcon = array();
	foreach ($aRegs as $aReg) {
		if ($aReg[0] == 'LE')
		{
			$leituraAtual = floatval($aReg[4]);
			if ($leituraAtual <= 0)
				$leituraAtual = $aReg[3];
			$model->assign('IDECONOMIA', $aReg[1]);
			$model->assign('CODECONOMIA', $aReg[2]);
			$model->assign('LEITURAANT', $aReg[3]);
			$model->assign('LEITURAATU', $leituraAtual);
			$model->assign('CONSUMO', $aReg[5]);
			$NovaLeitura = floatval($aReg[7]);
			if ($NovaLeitura <= 0)
				$NovaLeitura = '';
			$model->assign('NOVALEITURA', $NovaLeitura);
			$model->parse('.LEITURAS');
			$aEcon[] = array($aReg[1],$aReg[2]);
		}
		else if ($aReg[0] == 'CA')
		{
			$model->assign('CODCONDOM', $aReg[1]);
			$model->assign('CODBLOCO', $aReg[2]);
			$model->assign('NOMECONDOM', $aReg[3]);
			$model->assign('NOMEBLOCO', $aReg[4]);
			$model->assign('DIAVENCDOC', $aReg[8]);
			$model->assign('ASSESSOR', $Assessor);
			if ($aReg[11] == 'S') {
				$bEncerrado = true;
				$model->assign('READONLY', 'readonly');
				$model->assign('COMPETENCIA', $aReg[5].' (ENCERRADA)');
			} else {
				$bEncerrado = false;
				$model->assign('READONLY', '');
				$model->assign('COMPETENCIA', $aReg[5]);
			}
		}
	}

	if ($bEncerrado) {
		$model->assign('ENCERRADO', 'S');
		$model->clear_dynamic('BOTOES');
	} else {
		$model->assign('ENCERRADO', 'N');
		$model->parse('BOTOES');
	}

	$lst = '';
	foreach ($aEcon as $value)
		$lst .= '['.$value[0].',"'.$value[1].'"],';
	$model->assign('LISTA_ECONOMIAS', substr($lst, 0, -1));

	$model->parse('planilha');
	$model->DPrint('planilha');

	echo "\n<!-- $ArqModelo -->\n";
}

//---main---------------------------------------------------------------------
set_error_handler("myErrorHandler");
set_time_limit(60);
restore_error_handler();

$Oper = Campo('oper');
if (empty($Oper) || $Oper == 'salvar' || $Oper == 'encerrar') {
	$aRegs = array();
	$bEncerrado = FALSE;
	Planilha($Oper);
} else if ($Oper == 'encerradas') {
	$Limite = -1;
	Encerradas();
} else if ($Oper == 'processada')
	Processada();
else if ($Oper == 'limpa') {
	$geraCmdFTP = TRUE; //FALSE;
	Limpa();
} else
	Mensagem('Aten&ccedil;&auml;o', 'Chamada inv&aacute;lida (ERRO INTERNO)!');

?>
