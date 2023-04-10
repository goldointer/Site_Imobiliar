<?
set_time_limit(1200); 
include("inc/mysql.php");


function qry($sqlfn) {
  $qry = mysql_query($sqlfn);
  return $qry;
}

//////////////////////////////////////////////////////////////////////////////////////////////
/////////////////////////////////// CIDADES //////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////////

$iTamReg = 46;
$iCont = 0;
$Cidades = array();

$caminho = "imobiliar/prg/dados/"; //nao esquecer a "/" no final
$caminhofotos = "imobiliar/prg/Fotos/"; //nao esquecer a "/" no final

if (!file_exists($caminho."cidades.txt")) die("verifique se o caminho dos arquivos do imobiliar estao corretos.<br /><strong>".$caminho."</strong>");

$file = @fopen($caminho."cidades.txt", "r");

if($file !== false) {
	while (!feof($file)) {
		$sReg = fgets($file, 1024);
		if (empty($sReg) || strlen($sReg) < $iTamReg+1)
			break;
		$Cidade = trim(strtoupper(substr($sReg, 0, 40)));
		$CodCid = trim(substr($sReg, 40, 6));
		$UF = trim(substr($sReg, 46, 2));
		$Cidades[] = array($Cidade, $CodCid, $UF);
		$iCont++;
	}
	fclose($file);
	
	//// ATUALIZACAO BD ////
	if(count($Cidades) > 0){
		reset($Cidades);
		$sqlApaga = qry("truncate imobiliar_cidades");
		foreach($Cidades as $reg){
			$sqlVerifica = qry("INSERT INTO imobiliar_cidades (nomeCidade, codigoCidade, uf) VALUES ('".$reg[0]."', '".$reg[1]."', '".$reg[2]."')");			
		}
	}
	unset($Cidades);
	unset($reg);
}

//echo print_r($aCidades);

//////////////////////////////////////////////////////////////////////////////////////////////
/////////////////////////////////// BAIRROS //////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////////
$iTamReg = 71;
$iCont = 0;
$Bairros = array();

$file = @fopen($caminho."bairros_loc.txt", "r");

if ($file !== false) {
	while (!feof($file)) {
		$sReg = fgets($file, 1024);
		if (empty($sReg) || strlen($sReg) < $iTamReg+1)
			break;
		$codigoBairro = trim(strtoupper(substr($sReg, 0, 5)));
		$nomeBairro = addslashes(trim(substr($sReg, 5, 60)));
		$codigoCidade = trim(substr($sReg, 65, 6));
		$Bairros[] = array($codigoBairro, $nomeBairro, $codigoCidade);
		$iCont++;
	}
	fclose($file);
	
	//// ATUALIZACAO BD ////
	if(count($Bairros) > 0){
		reset($Bairros);
		$sqlApaga = qry("truncate imobiliar_bairros_loc");
		foreach($Bairros as $reg){
			$sqlVerifica = qry("INSERT INTO imobiliar_bairros_loc (codigoBairro, nomeBairro, codigoCidade) VALUES ('".$reg[0]."', '".$reg[1]."', '".$reg[2]."')");			
		}
	}
	unset($Bairros);
	unset($reg);
}
//echo print_r($Bairros);

//////////////////////////////////////////////////////////////////////////////////////////////
/////////////////////////////////// TIPOS ////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////////
$iTamReg = 36;
$iCont = 0;
$Tipos = array();

$file = @fopen($caminho."tipo_imov.txt", "r");

if ($file !== false) {
	while (!feof($file)) {
		$sReg = fgets($file, 1024);
		if (empty($sReg) || strlen($sReg) < $iTamReg+1)
			break;
		$codigoTipo = trim(strtoupper(substr($sReg, 0, 4)));
		$descricaoTipo = addslashes(trim(substr($sReg, 4, 30)));
		$comercialResidencial = trim(substr($sReg, 34, 1));
		$indicadorDormitorio = trim(substr($sReg, 35, 2));
		$Tipos[] = array($codigoTipo, $descricaoTipo, $comercialResidencial, $indicadorDormitorio);
		$iCont++;
	}
	fclose($file);
	
	//// ATUALIZACAO BD ////
	if(count($Tipos) > 0){
		reset($Tipos);
		$sqlApaga = qry("truncate imobiliar_tipo_imov");
		foreach($Tipos as $reg){
			$sqlVerifica = qry("INSERT INTO imobiliar_tipo_imov (codigoTipo, descricaoTipo, comercialResidencial, indicadorDormitorio) VALUES ('".$reg[0]."', '".$reg[1]."', '".$reg[2]."', '".$reg[3]."')");
		}
	}
	unset($Tipos);
	unset($reg);
}
//echo print_r($Tipos);

//////////////////////////////////////////////////////////////////////////////////////////////
//////////////////////////////// CARACTERISTICAS /////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////////
$iTamReg = 40;
$iCont = 0;
$Caracteristicas = array();

$file = @fopen($caminho."carac.txt", "r");

if ($file !== false) {
	while (!feof($file)) {
		$sReg = fgets($file, 1024);
		if (empty($sReg) || strlen($sReg) < $iTamReg+1)
			break;
		$codigoCaracteristica = trim(strtoupper(substr($sReg, 0, 4)));
		$descricaoCaracteristica = addslashes(trim(strtoupper(substr($sReg, 4, 25))));
		$reservado = trim(strtoupper(substr($sReg, 29,11)));
		
		$Caracteristicas[] = array($codigoCaracteristica, $descricaoCaracteristica, $reservado);
		$iCont++;
	}
	fclose($file);
	
	//// ATUALIZACAO BD ////
	if(count($Caracteristicas) > 0){
		reset($Caracteristicas);
		$sqlApaga = qry("truncate imobiliar_carac");
		foreach($Caracteristicas as $reg){
			$sqlVerifica = qry("INSERT INTO imobiliar_carac (codigoCaracteristica, descricaoCaracteristicas, reservado) VALUES ('".$reg[0]."', '".$reg[1]."', '".$reg[2]."')");			
		}
	}
	unset($Caracteristicas);
	unset($reg);
}
//echo print_r($Caracteristicas[30]);

//////////////////////////////////////////////////////////////////////////////////////////////
//////////////////////////// CARACTERISTICAS - IMOVEL ////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////////
$iTamReg = 60;
$iCont = 0;
$CaracteristicasImovel = array();

$file = @fopen($caminho."caract_imo.txt", "r");

if ($file !== false) {
	while (!feof($file)) {
		$sReg = fgets($file, 1024);
		if (empty($sReg) || strlen($sReg) < $iTamReg+1)
			break;
		$codigoListaCaracteristica = trim(strtoupper(substr($sReg, 0, 8)));
		$quantidade = trim(strtoupper(substr($sReg, 8, 6)));
		$complementoCaracteristica = trim(strtoupper(substr($sReg, 14, 10)));
		$codigoCaracteristica = trim(strtoupper(substr($sReg, 24, 4)));
		$caracteristicaExtenso = addslashes(trim(strtoupper(substr($sReg, 28, 25))));
		$indicadorQuantidade = trim(strtoupper(substr($sReg, 53 , 1)));
		$unidadeQuantidade = trim(strtoupper(substr($sReg, 54, 10)));
		$tipoCaracteristica = trim(strtoupper(substr($sReg, 64, 3)));
		
		
		$CaracteristicasImovel[] = array($codigoListaCaracteristica, $quantidade, $complementoCaracteristica, $codigoCaracteristica, $caracteristicaExtenso, $indicadorQuantidade, $unidadeQuantidade, $tipoCaracteristica);
		$iCont++;
	}
	fclose($file);
	
	//// ATUALIZACAO BD ////
	if(count($CaracteristicasImovel) > 0){
		reset($CaracteristicasImovel);
		$sqlApaga = qry("truncate imobiliar_caract_imo");
		foreach($CaracteristicasImovel as $reg){
			$sqlVerifica = qry("INSERT INTO imobiliar_caract_imo (codigoListaCaracteristica, quantidade, complementoCaracteristica, codigoCaracteristica, caracteristicaExtenso, indicadorQuantidade, unidadeQuantidade, tipoCaracteristica) VALUES ('".$reg[0]."', '".$reg[1]."', '".$reg[2]."', '".$reg[3]."', '".$reg[4]."', '".$reg[5]."', '".$reg[6]."', '".$reg[7]."')");			
		}
	}
	unset($CaracteristicasImovel);
	unset($reg);
}
//echo print_r($CaracteristicasImovel);

//////////////////////////////////////////////////////////////////////////////////////////////
////////////////////////////////// DESCRICAO - IMOVEL ////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////////
$iTamReg = 8;
$iCont = 0;
$ImovelDescricao = array();

$file = @fopen($caminho."imovdescr.txt", "r");

if ($file !== false) {
	while (!feof($file)) {
		$sReg = fgets($file, 1024);
		if (empty($sReg) || strlen($sReg) < $iTamReg+1)
			break;
		$codigoImovel = trim(strtoupper(substr($sReg, 0, 8)));
		$caracteristicasExtenso = addslashes(trim(strtoupper(substr($sReg, 8))));
		
		$ImovelDescricao[] = array($codigoImovel, $caracteristicasExtenso);
		$iCont++;
	}
	fclose($file);
	
	//// ATUALIZACAO BD ////
	if(count($ImovelDescricao) > 0){
		reset($ImovelDescricao);
		$sqlApaga = qry("truncate imobiliar_imovdescr");
		foreach($ImovelDescricao as $reg){
			$sqlVerifica = qry("INSERT INTO imobiliar_imovdescr (codigoImovel, caracteristicasExtenso) VALUES ('".$reg[0]."', '".$reg[1]."')");			
		}
	}
	unset($ImovelDescricao);
	unset($reg);
}
//echo print_r($ImovelDescricao);

//////////////////////////////////////////////////////////////////////////////////////////////
////////////////////////////////// SITUACAO - IMOVEL /////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////////
$iTamReg = 3;
$iCont = 0;
$SituacaoImoveis = array();

$file = @fopen($caminho."situacaoimo.txt", "r");

if ($file !== false) {
	while (!feof($file)) {
		$sReg = fgets($file, 1024);
		if (empty($sReg) || strlen($sReg) < $iTamReg+1)
			break;
		$codigoSituacao = trim(strtoupper(substr($sReg, 0, 3)));
		$descricaoSituacao = addslashes(trim(strtoupper(substr($sReg, 3))));
		
		$SituacaoImoveis[] = array($codigoSituacao, $descricaoSituacao);
		$iCont++;
	}
	fclose($file);
	
	//// ATUALIZACAO BD ////
	if(count($SituacaoImoveis) > 0){
		reset($SituacaoImoveis);
		$sqlApaga = qry("truncate imobiliar_situacaoimo");
		foreach($SituacaoImoveis as $reg){
			$sqlVerifica = qry("INSERT INTO imobiliar_situacaoimo (codigoSituacao, descricaoSituacao) VALUES ('".$reg[0]."', '".$reg[1]."')");			
		}
	}
	unset($SituacaoImoveis);
	unset($reg);
}
//echo print_r($SituacaoImoveis);

//////////////////////////////////////////////////////////////////////////////////////////////
//////////////////////////////// CARACTERISTICAS /////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////////
$iTamReg = 40;
$iCont = 0;
$DescricaoFoto = array();

$file = @fopen($caminho."descrfoto.txt", "r");

if ($file !== false) {
	while (!feof($file)) {
		$sReg = fgets($file, 1024);
		if (empty($sReg) || strlen($sReg) < $iTamReg+1)
			break;
		$legenda = addslashes(trim(strtoupper(substr($sReg, 0, 50))));
		$sigla = trim(strtoupper(substr($sReg, 50, 3)));
	
		$DescricaoFoto[] = array($legenda, $sigla);
		$iCont++;
	}
	fclose($file);
	
	//// ATUALIZACAO BD ////
	if(count($DescricaoFoto) > 0){
		reset($DescricaoFoto);
		$sqlApaga = qry("truncate imobiliar_descrfoto");
		foreach($DescricaoFoto as $reg){
			$sqlVerifica = qry("INSERT INTO imobiliar_descrfoto (legenda, sigla) VALUES ('".$reg[0]."', '".$reg[1]."')");
			//echo mysql_error();
		}
	}
	unset($DescricaoFoto);
	unset($reg);
}
//echo print_r($DescricaoFoto);

//////////////////////////////////////////////////////////////////////////////////////////////
/////////////////////////////////// IMOVEIS //////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////////
$iTamReg = 260;
$iCont = 0;
$Imoveis = array();

$file = @fopen($caminho."imov.txt", "r");

if ($file !== false) {
	while (!feof($file)) {
		$sReg = fgets($file, 1024);
		if (empty($sReg) || strlen($sReg) < $iTamReg+1)
			break;
		$codigoImovel = trim(strtoupper(substr($sReg, 0, 8)));
		$tipoImovel = trim(strtoupper(substr($sReg, 8, 2)));
		$codigoCidade = trim(strtoupper(substr($sReg, 10,6)));
		$nomeBairro = addslashes(trim(strtoupper(substr($sReg, 16,60))));
		$reservado = trim(strtoupper(substr($sReg, 76, 3)));
		$endereco = addslashes(trim(strtoupper(substr($sReg, 79, 85))));
		$imediacao = addslashes(trim(strtoupper(substr($sReg, 164, 30))));
		$quantidadeDormitorios = trim(strtoupper(substr($sReg, 194, 2)));
		$nomePredio = addslashes(trim(strtoupper(substr($sReg, 196, 30))));
		$valor = trim(strtoupper(substr($sReg, 226, 12)));
		$valorCondominio = trim(strtoupper(substr($sReg, 238, 12)));
		$valorIptu = trim(strtoupper(substr($sReg, 250, 12)));
		$codigoSituacao = trim(strtoupper(substr($sReg, 262, 3)));
		$comercialResidencial = trim(strtoupper(substr($sReg, 265, 1)));
		$codigoListaCaracteristicas = trim(strtoupper(substr($sReg, 266, 8)));
		$areaUtil = trim(strtoupper(substr($sReg, 274, 8)));
		$tamanhoTipoLogradouroEndereco = trim(strtoupper(substr($sReg, 282, 2)));
		$tamanhoLogradouroEndereco = trim(strtoupper(substr($sReg, 284, 2)));
		$tamanhoNumeroEndereco = trim(strtoupper(substr($sReg, 286, 2)));
		$cep = trim(strtoupper(substr($sReg, 288, 8)));
		$vagasEstacionamento = trim(strtoupper(substr($sReg, 296, 3)));
		$areaTotal = trim(strtoupper(substr($sReg, 299, 8)));
		
		$Imoveis[] = array($codigoImovel, $tipoImovel, $codigoCidade, $nomeBairro, $reservado, $endereco, $imediacao, $quantidadeDormitorios, $nomePredio, $valor, $valorCondominio, $valorIptu, $codigoSituacao, $comercialResidencial, $codigoListaCaracteristicas, $areaUtil, $tamanhoTipoLogradouroEndereco, $tamanhoLogradouroEndereco, $tamanhoNumeroEndereco, $cep, $vagasEstacionamento, $areaTotal);
		$iCont++;
	}
	fclose($file);
	
	//// ATUALIZACAO BD ////
	if(count($Imoveis) > 0){
		reset($Imoveis);
		$sqlApaga = qry("truncate imobiliar_imov");
		foreach($Imoveis as $reg){
			$param1="";$param2="";$param3="";$param4="";$param5="";$param6="";
			$sql_tipo = qry("SELECT descricaoTipo FROM imobiliar_tipo_imov WHERE codigoTipo = '{$reg[1]}'");
			if(mysql_num_rows($sql_tipo) > 0){
				$tup = mysql_fetch_array($sql_tipo);
				$param1 = $tup['descricaoTipo'];
			}
			$sql_sit = qry("SELECT descricaoSituacao FROM imobiliar_situacaoimo WHERE codigoSituacao = '{$reg[12]}'");
			if(mysql_num_rows($sql_sit) > 0){
				$tup = mysql_fetch_array($sql_sit);
				$param2 = addslashes($tup['descricaoSituacao']);
			}
			$sql_bairro = qry("SELECT codigoBairro FROM imobiliar_bairros_loc WHERE nomeBairro = '{$reg[3]}' AND codigoCidade = '{$reg[2]}'");
			if(mysql_num_rows($sql_bairro) > 0){
				$tup = mysql_fetch_array($sql_bairro);
				$param3 = $tup['codigoBairro'];
			}
			
			$sql_cidade = qry("SELECT nomeCidade, uf FROM imobiliar_cidades WHERE codigoCidade = '{$reg[2]}'");
			if(mysql_num_rows($sql_cidade) > 0){
				$tup = mysql_fetch_array($sql_cidade);
				$param4 = addslashes($tup['nomeCidade']);
				$param5 = $tup['uf'];
			}
			$sql_desc = qry("SELECT caracteristicasExtenso FROM imobiliar_imovdescr WHERE codigoImovel = '{$reg[0]}'");
			if(mysql_num_rows($sql_desc) > 0){
				$tup = mysql_fetch_array($sql_desc);
				$param6 = addslashes($tup['caracteristicasExtenso']);
			}

			$sqlVerifica = qry("INSERT INTO imobiliar_imov (descricaoTipo, descricaoSituacao, codigoBairro, nomeCidade, uf, descricaoImovel, codigoImovel, tipoImovel, codigoCidade, nomeBairro, reservado, endereco, imediacao, quantidadeDormitorios, nomePredio, valor, valorCondominio, valorIptu, codigoSituacao, comercialResidencial, codigoListaCaracteristicas, areaUtil, tamanhoTipoLogradouroEndereco, tamanhoLogradouroEndereco, tamanhoNumeroEndereco, cep, vagasEstacionamento, areaTotal) VALUES ('".$param1."','".$param2."','".$param3."','".$param4."','".$param5."', '".$param6."','".$reg[0]."', '".$reg[1]."', '".$reg[2]."', '".$reg[3]."', '".$reg[4]."', '".$reg[5]."', '".$reg[6]."', '".$reg[7]."', '".$reg[8]."', '".$reg[9]."', '".$reg[10]."', '".$reg[11]."', '".$reg[12]."', '".$reg[13]."', '".$reg[14]."', '".$reg[15]."', '".$reg[16]."', '".$reg[17]."', '".$reg[18]."', '".$reg[19]."', '".$reg[20]."', '".$reg[21]."')");	
			
			//echo "INSERT INTO imobiliar_imov (descricaoTipo, descricaoSituacao, codigoBairro, nomeCidade, uf, descricaoImovel, codigoImovel, tipoImovel, codigoCidade, nomeBairro, reservado, endereco, imediacao, quantidadeDormitorios, nomePredio, valor, valorCondominio, valorIptu, codigoSituacao, comercialResidencial, codigoListaCaracteristicas, areaUtil, tamanhoTipoLogradouroEndereco, tamanhoLogradouroEndereco, tamanhoNumeroEndereco, cep, vagasEstacionamento, areaTotal) VALUES ('".$param1."','".$param2."','".$param3."','".$param4."','".$param5."','".$reg[0]."', '".$reg[1]."', '".$reg[2]."', '".$reg[3]."', '".$reg[4]."', '".$reg[5]."', '".$reg[6]."', '".$reg[7]."', '".$reg[8]."', '".$reg[9]."', '".$reg[10]."', '".$reg[11]."', '".$reg[12]."', '".$reg[13]."', '".$reg[14]."', '".$reg[15]."', '".$reg[16]."', '".$reg[17]."', '".$reg[18]."', '".$reg[19]."', '".$reg[20]."', '".$reg[21]."')";
		}
	}
	unset($Imoveis);
	unset($reg);
}
//echo print_r($Imoveis[37]);

//////////////////////////////////////////////////////////////////////////////////////////////
////////////////////////////////// FOTOS - IMOVEL ////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////////

// pega o endereco do diretorio
chdir($caminhofotos);
$diretorio = getcwd(); 
// abre o diretorio
$ponteiro  = opendir($diretorio);
$itensPasta = array();
$itens = array();
// monta os vetores com os itens encontrados na pasta
while ($nome_itens = readdir($ponteiro)) {
	if(!($nome_itens == "." || $nome_itens == ".." || $nome_itens == "Thumbs.db")){
		if(is_dir($nome_itens)){
			chdir($nome_itens);
			$diretorio = getcwd(); 
			$ponteiro2  = opendir($diretorio);
			while ($nome_itens = readdir($ponteiro2)) {
				if(!($nome_itens == "." || $nome_itens == ".." || $nome_itens == "Thumbs.db"))
					$itensPasta[] = $nome_itens;
			}
			chdir("../");
		}
		else
			$itens[] = $nome_itens;	
	}
}

if((count($itens) > 0) || (count($itensPasta) > 0)){
	/// Raiz
	reset($itens);
	$sqlApaga = qry("truncate imobiliar_fotos");
	foreach($itens as $reg){
		//$sqlVerifica = qry("INSERT INTO imobiliar_fotos (codigoImovel, pasta) VALUES ('".$reg."', 0)");
	}
	unset($itens);
	unset($reg);
	/// Pastas individuais
	reset($itensPasta);
	foreach($itensPasta as $Reg){
		$arquivo = $Reg;
		$codigoImovel = trim(strtoupper(substr($Reg, 0, 8)));
		$sigla = trim(strtoupper(substr($Reg, 9, 1)));
		$legenda = trim(strtoupper(substr($Reg, 11, 2)));
		$ordem = trim(strtoupper(substr($Reg, 14, 1)));
		
		$sql_foto = qry("SELECT legenda FROM imobiliar_descrfoto WHERE sigla = '{$legenda}'");
		if(mysql_num_rows($sql_foto) > 0){
			$tup = mysql_fetch_array($sql_foto);
			$descricaoLegenda = $tup['legenda'];
		}
		else
			$descricaoLegenda = "";
		
		$sqlVerifica = qry("INSERT INTO imobiliar_fotos (arquivo, codigoImovel, sigla, legenda, ordem, pasta, descricaoLegenda) VALUES ('".$arquivo."' ,'".$codigoImovel."', '".$sigla."', '".$legenda."', '".$ordem."', 1, '".$descricaoLegenda."')");
	}
	unset($itensPasta);
	unset($reg);
}
?>