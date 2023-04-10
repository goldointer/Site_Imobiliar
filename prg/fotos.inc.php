<?php

// Lista de identificacoes default das fotos, utilizada se
// nao existir o arquivo "descrfoto.txt".
$aDescr = array("fa"=>"Fachada",
				"ar"=>"&Aacute;rea de Servi&ccedil;o" ,
				"ba"=>"Banheiro",
				"br"=>"Sala de Brinquedo",
				"bs"=>"Banheiro Su&iacute;te", 
				"cb"=>"Cobertura",
				"ci"=>"Circula&ccedil;&atilde;o",
				"cl"=>"Closet",
				"co"=>"Cozinha",
				"ch"=>"Churrasqueira",
				"cp"=>"Copa",
				"ct"=>"Cobertura/Terra&ccedil;o",
				"de"=>"Depend&ecirc;ncia",
				"do"=>"Dormit&oacute;rio",
				"dp"=>"Dep&oacute;sito",
				"ei"=>"Estar &Iacute;ntimo",
				"es"=>"Quadra de Esportes",
				"ex"=>"Externa",
				"fo"=>"Foto",
				"gb"=>"Gabinete",
				"ga"=>"Garagem",
				"ha"=>"Hall",
				"in"=>"Interna",
				"lv"=>"Lavabo",
				"la"=>"Lavanderia",
				"li"=>"Living",
				"lo"=>"Loja",
				"pl"=>"Playground",
				"ps"=>"Piscina",
				"sc"=>"Sacada",
				"sa"=>"Sala",
				"sg"=>"Sala Gin&aacute;stica",
				"ja"=>"Sala Jantar",
				"sj"=>"Sala Jogos",
				"sf"=>"Sal&atilde;o de Festas",
				"su"=>"Su&iacute;te",
				"to"=>"Tour",
				"vi"=>"Vista",
				"wa"=>"WC Auxiliar",
				"wc"=>"WC Cobertura",
				"xx"=>"Outras"
);

//----------------------------------------------------------------------------------
if(!function_exists('fnmatch')) {
	function fnmatch($pattern, $string) {
		return preg_match("#^".strtr(preg_quote($pattern, '#'), array('\*' => '.*', '\?' => '.'))."$#i", $string);
	}
}

if(!function_exists('scandir')) {
	function scandir($dir, $sortorder = 0) {
		if(is_dir($dir))
		{
			$files = array();
			$dirlist = @opendir($dir);
			if ($dirlist !== false)
			{
				while( ($file = readdir($dirlist)) !== false)
				{
					if(!is_dir($file))
						$files[] = $file;
				}
				($sortorder == 0) ? asort($files) : rsort($files); // arsort was replaced with rsort
				closedir($dirlist);
				return $files;
			}
		}
		return false;
	}
}

//----------------------------------------------------------------------------------
function Filtro($var)
{
	return (fnmatch("*.jpg", $var));
}

//----------------------------------------------------------------------------------
function ExisteFotos($pCod,$CodFoto)
{
	GLOBAL $DirFotos;
	static $tipos = array(
		"0","1","a","b","c","d","e","2","3","4","5","6","7",
		"8","9","f","g","h","i","j","k","l","m","n","o","p",
		"q","r","s","t","u","v","w","x","y","z");

//echo "<!--ExisteFotos($pCod,$CodFoto)\n";
	if (strlen($CodFoto) == 0)
	{
		$tam = count($tipos);
		for($i = 0; $i < $tam; $i++)
		{
			$tipo = $tipos[$i];
			$szFoto=sprintf("%simo%04d_%s.jpg", $DirFotos, $pCod, $tipo);
//echo "$szFoto ";
			$bTemFotos = @is_file($szFoto);
			if ($bTemFotos)
			{
				$szFoto = $tipo;
				break;
			}
			$szFoto=sprintf("%simo%d_%s.jpg", $DirFotos, $pCod, $tipo);
//echo "$szFoto ";
			$bTemFotos = @is_file($szFoto);
			if ($bTemFotos)
			{
				$szFoto = $tipo;
				break;
			}
		}
	}
	else if ($CodFoto=="!")
	{
		// Verifica se existe diretorio de fotos com este codigo
		$szFoto=$DirFotos.$pCod;
		$bTemFotos=false;
		if (@is_dir($szFoto))
		{
			// Verifica se o diretorio efetivamente tem fotos
			$files = scandir($szFoto);
			if (is_array($files))
			{
				$files = array_filter($files, "Filtro");
				$bTemFotos = (count($files) > 0);
			}
		}
	}
	else
	{
		$szFoto=sprintf("%simo%04d_%s.jpg", $DirFotos, $pCod, $CodFoto);
		$bTemFotos=@is_file($szFoto);
		if(!$bTemFotos)
		{
			$szFoto=sprintf("%simo%d_%s.jpg", $DirFotos, $pCod, $CodFoto);
			$bTemFotos=@is_file($szFoto);
		}
	}

//if ($bTemFotos) echo "ACHOU '$szFoto'\n	";
//echo"-->\n";

	return($bTemFotos?$szFoto:"");
}

//-----------------------------------------------------------------------------
function MontaDescr($pDescr, $vCaracs, $vQtds, $vCompl, $vTipo)
{
	GLOBAL $DirDados;
	static $LastTipo = "";

	$iTamReg = 40;

	if (($file = @fopen($DirDados."carac.txt", "r")) === false)
		return "";

	$iLen = count($vCaracs);
	for ($i=0; $i<$iLen; $i++)
	{
		$Tipo = $vTipo[$i];
		if (!empty($Tipo) && $Tipo != $LastTipo)
		{
			$LastTipo = $Tipo;
			if ($Tipo == "CON")
				$pDescr .= "<br><br><b>INFRA-ESTRUTURA DO CONDOM&Iacute&NIO:</b><br>";
		}

		$szCod = $vCaracs[$i];
		$DescrC = "";
		fseek ($file, 0);
		for(;;)
		{
			$sReg = fgets($file, 4098);
			if (empty($sReg) || strlen($sReg) < $iTamReg+1)
				break;
			$CodC = trim(substr($sReg, 0, 4));
			if ($szCod == $CodC)
			{
				$DescrC = trim(substr($sReg, 4, 25));
				break;
			}
		}

		if (!empty($DescrC))
		{
			if (strlen($vCompl[$i]) > 0)
				$DescrC .= " ".$vCompl[$i];
			if ($vQtds[$i] != 0)
				$DescrC .= "(".$vQtds[$i].")";
			$pDescr .= $DescrC.", ";
		}
	}
	fclose($file);

	//Retira a ultima ", "
	$pDescr = substr($pDescr, 0, strlen($pDescr)-2);
//echo "\n<!-- MONTOU de carac.txt: \n";
//echo $pDescr."\n";
//echo "-->\n";
	return $pDescr;
}

//----------------------------------------------------------------------------------
function MontaDescrImov($pCod, $pChv, $szValCond, $szValIptu)
{
	GLOBAL $DirDados;

	$pDescr = "";
	$pCod = trim($pCod);

	if (($fDesc = @fopen($DirDados."imovdescr.txt", "r")) !== false)
	{
		while (!feof($fDesc))
		{
			$Linha = fgets($fDesc,4098);
			if (empty($Linha))
				break;

			$CodAux = trim(substr($Linha, 0, 8));
			if ($pCod == $CodAux)
			{
				$pDescr = substr($Linha, 8);
//echo "\n<!-- ACHOU $pCod em imovdescr.txt: \n";
//echo $pDescr."\n";
//echo "-->\n";
				break;
			}
		}
		fclose($fDesc);
	}

	$bAchou = false;
	if (empty($pDescr) && ($file = @fopen($DirDados."caract_imo.txt", "r")) !== false)
	{
		$iTamReg = 64;
		$i = 0;
		for(;;)
		{
			$sReg = fgets($file, 4098);
			if (empty($sReg) || strlen($sReg) < $iTamReg+1)
				break;
			$Chave = trim(substr($sReg, 0, 8));
			if ($Chave == $pChv)
			{
				$vCaracs[$i] = trim(substr($sReg, 24, 4));
				$vCompl[$i] = trim(substr($sReg, 14, 10));
				$vTipo[$i] = trim(substr($sReg, 64, 3));
				if (trim(substr($sReg, 53, 1)) == "S")
					$vQtds[$i] = intval(trim(substr($sReg, 8, 6)));
				else
					$vQtds[$i] = 0;
				$bAchou = true;
				$i++;
			}
		}
		fclose($file);

		if ($bAchou)
			$pDescr = MontaDescr($pDescr, $vCaracs, $vQtds, $vCompl, $vTipo);
	}

	if (intval($szValCond) > 0)
		$szVal_cond_iptu = " Cond: ".NumberFormat($szValCond, 2, ",", ".");
	else
		$szVal_cond_iptu = "";

	if (intval($szValIptu) > 0)
	{
		if (strlen($szVal_cond_iptu) > 0 )
			$szVal_cond_iptu .= " -";
		$szVal_cond_iptu .= " Iptu: ".NumberFormat($szValIptu, 2, ",", ".");
	}

	return array($pDescr, $szVal_cond_iptu);
}

//-----------------------------------------------------------------------------
function BuscaDescr($aDescr, $file)
{
//echo "<!-- BuscaDescr($aDescr, $file) -->\n";
	$abrev = substr($file,11,2);
	if (!isset($aDescr[$abrev]))
		return "Outras";

	$descr = $aDescr[$abrev];
	$pos = strpos($file,".");
	if ($pos !== false)
	{
		$file = substr($file,0,$pos);
		$iPos = strpos($file,"-");
		if ($iPos !== false)
		{
			if ($abrev == "xx")
				$descr = substr($file,$iPos+1);
			else
				$descr .= " ".substr($file,$iPos+1);
		}
	}

	return trim($descr);
}

//-----------------------------------------------------------------------------
function BuscaOrdem($aDescr, $file)
{
	$descr = BuscaDescr($aDescr, $file);

	$abrev = substr($file,11,2);
	if ($abrev == "fa")
		// Fachada deve ser no inicio da lista
		$descr = " ".$descr;

	return $descr." ";
}

//----------------------------------------------------------------------------------
function FotoPath($pCod,$pCodFoto)
{
	GLOBAL $DirFotos;

	if ($pCodFoto == '*')
		return false;

	if ($pCodFoto == '!')
	{
		$dir = $DirFotos.$pCod;
		if (!is_dir($dir))
			return false;

		// Prepara lista das fotos
		$bFmtAntigo = false;
		$fh = opendir($dir);
		$files = array();
		while (false !== ($file = readdir($fh)))
		{
			if ($file == "." || $file == "..")
				continue;
			$aux = explode('.', $file);
			$aux = strtolower($aux[count($aux)-1]);
			if ($aux != "jpg")
				continue;
			if (file_exists($dir."/".$file))
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
		list($pArqFoto, $ordem) = each($files);
		$szDirFoto = sprintf("%s%d/%s", $DirFotos, intval($pCod), $pArqFoto);
		if (!file_exists($szDirFoto))
			return false;
	}
	else
	{
		$szDirFoto = ExisteFotos(intval($pCod), $pCodFoto);
		if (empty($szDirFoto))
			return false;
	}

	return $szDirFoto;
}

//-----------------------------------------------------------------------------------
function PesqCodCidade($lv, $cidade)
{
	GLOBAL $DirDados, $CidadeDefault;

	$iTamReg = 46;
	$iCont = 0;

	if ($lv == _VENDA)
		$file = @fopen($DirDados."cidadesvenda.txt", "r");
	else
		$file = @fopen($DirDados."cidades.txt", "r");
	if ($file === false)
		return false;

	for(;;)
	{
		$sReg = fgets($file, 1024);
		if (empty($sReg) || strlen($sReg) < $iTamReg+1)
			break;
		$CodCid = trim(substr($sReg, 40, 6));

		if ($cidade == intval($CodCid))
		{
			$Cidade = trim(strtoupper(substr($sReg, 0, 40)));
			$UF = trim(substr($sReg, 46, 2));
			break;
		}
	}
	fclose($file);

	return array($CodCid, $Cidade, $UF);
}

?>