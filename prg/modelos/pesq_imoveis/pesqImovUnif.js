
function VerQuartos() {
	var disp, elem = document.getElementById("seltipo");
	var val = elem.options[elem.selectedIndex].value;
	var opt = elem.options[elem.selectedIndex].text;
	if (opt.indexOf('->') > 0) disp = 'visible';
	else disp = 'hidden';
	elem = document.getElementById("tem_quartos");
	if (elem != null)
		elem.style.visibility = disp;
	if (val.indexOf('G') > 0) disp = 'visible';
	else disp = 'hidden';
	elem = document.getElementById("tem_garagem");
	if (elem != null)
		elem.style.visibility = disp;
}

function VerCaracteristicas() {
	var elem = document.getElementById("selcarac");
	if (elem != null && elem.length > 0) disp = '';
	else disp = 'none';
	elem = document.getElementById("divcarac1");
	if (elem != null)
		elem.style.display = disp;
	elem = document.getElementById("divcarac2");
	if (elem != null)
		elem.style.display = disp;
}

function SelectClear(cmb) {
	if (cmb == null || cmb.tagName != "SELECT")
		return;

	for(i = cmb.length - 1; i >= 0; i--)
		cmb.options[i] = null;
}

function SelectFill(cmb, cmbVal, sel) {
	var optionValues, optionLen = cmbVal.length;
	var val, cmbLen;
	if (cmb == null || cmb.tagName != "SELECT")
		return;

	cmbLen = cmb.length;
	for (i = 0; i < optionLen; i++) {
		optionValues = cmbVal[i].split("|");
		val = Trim(optionValues[1]);
		if (i == 0 && cmbLen > 0 && val.length == 0)
			// Nao inclui se e' opcao inicial e ja' foi preenchido antes
			cmbLen--;
		else
			cmb.options[cmbLen+i] = new Option(optionValues[0], val);
	}

	if (sel==null)
		sel = 0;
	cmb.options[sel].selected = true;
}

function Trim(s) {
	while (s.substring(0,1) == ' ')
		s = s.substring(1,s.length);

	while (s.substring(s.length-1,s.length) == ' ')
		s = s.substring(0,s.length-1);

	return s;
}

function VerCidades(lv) {
	var elem;
	if (lv==null)
	{
		elem = document.getElementById("lvL");
		if (elem)
			lv = elem.checked ? 'L' : 'V';
		else
			lv = document.getElementById("lv").value;
	}
	elem = document.getElementById("selcidade");
	SelectClear(elem);
	if (lv == 'L') 
		SelectFill(elem, CidadesL, DefaultL);
	else
		SelectFill(elem, CidadesV, DefaultV);
}

function VerBairros() {
	var i, lv, cid
	var elem = document.getElementById("lvL");
	if (elem)
		lv = elem.checked ? 'L' : 'V';
	else
		lv = document.getElementById("lv").value;
	elem = document.getElementById("selcidade");
	if (elem) {
		cid = elem.options[elem.selectedIndex].value.split(":");
		cid = Trim(cid[0]);
	}
	elem = document.getElementById("selbairro");
	if (elem) {
		SelectClear(elem);
		if (lv == 'L') {
			for (i=0; i<CidBairrosL.length; i++) {
				if (CidBairrosL[i] == cid) {
					SelectFill(elem, BairrosL[i]);
					return;
				}
			}
		} else {
			for (i=0; i<CidBairrosV.length; i++) {
				if (CidBairrosV[i] == cid) {
					SelectFill(elem, BairrosV[i]);
					return;
				}
			}
		}
		SelectFill(elem, ['- QUALQUER BAIRRO -|- QUALQUER BAIRRO -']);
	}
}

function VerTipos(ocup) {
	var lv, elem = document.getElementById("lvL");
	if (elem)
		lv = elem.checked ? 'L' : 'V';
	else
		lv = document.getElementById("lv").value;
	if (ocup==null) {
		elem = document.getElementById("selocup");
		if (elem) {
			ocup = elem.form.selocup[0].value;
			if (elem.form.selocup[1].checked)
				ocup = elem.form.selocup[1].value;
			else if (elem.form.selocup[2] && elem.form.selocup[2].checked)
				ocup = elem.form.selocup[2].value;
		}
	}
	elem = document.getElementById("seltipo");
	if (elem) {
		SelectClear(elem);
		if (ocup == '*' || ocup == 'R')
			SelectFill(elem, lv=='L'?TiposRL:TiposRV);
		if (ocup == '*' || ocup == 'C')
			SelectFill(elem, lv=='L'?TiposCL:TiposCV);
	}
}

function VerLV(lv) {
	var elem, ocup;
	if (lv==null)
	{
		elem = document.getElementById("lvL");
		if (elem)
			lv = elem.checked ? 'L' : 'V';
		else
			lv = document.getElementById("lv").value;
	}
	elem = document.getElementById("selocup");
	if (elem) {
		ocup = elem.form.selocup[0].value;
		if (elem.form.selocup[1].checked)
			ocup = elem.form.selocup[1].value;
		else if (elem.form.selocup[2] && elem.form.selocup[2].checked)
			ocup = elem.form.selocup[2].value;
		VerTipos(ocup);
		VerCidades(lv);
		VerBairros();
	}
}

// Chamada que abre janela "pop-up" com fotos ou informacoes do imovel.
// A chamada window.open() deve ser parametrizada conforme o projeto visual.
function ExibeImovel(wparam, lv)
{
	var elem = document.getElementById("codimovel");
	var cod = elem.value;
	if (cod.length == 0)
	{
		alert("Digite o código do imóvel desejado!");
		elem.focus();
		return false;
	}
	if (lv==null)
	{
		elem = document.getElementById("lvL");
		if (elem)
			lv = elem.checked ? 'L' : 'V';
		else
		{
			lv = document.getElementById("lv");
			if (elem)
				lv = elem.value;
			else
				lv = 'LV';
		}
	}
	if (wparam==null)
		wparam = "width=665,height=580,top=50,left=100,toolbar=no,resizable=yes,scrollbars=yes";
	url = "exibeFotos.php?cod=" + cod + "&lv=" + lv;
	window.open(url,"fotos",wparam);
	return false;
}
