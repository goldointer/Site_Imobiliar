var map = null;
var geocoder = null;
var TamListaThumbs = 0;

function Inicializar(tipo) {
	var elem = document.getElementById("mapa");
	if (elem)
		elem.style.display = ExibirMapa ? '' : 'none';
	if (tipo == "V")
		elem = document.getElementById("val_venda");
	else
		elem = document.getElementById("val_aluguel");
	if (elem)
		elem.style.display = '';
	elem = document.getElementById("listaThumbs");
	if (elem)
		TamListaThumbs = elem.style.width;
}

function GMapsInit() {
	if (GBrowserIsCompatible()) {
		map = new GMap2(document.getElementById("map_canvas"));
//		map.disableInfoWindow();
		map.enableScrollWheelZoom();
		map.addControl(new GSmallMapControl());
		map.addControl(new GMapTypeControl());
		geocoder = new GClientGeocoder();
	}
	else
		ExibirMapa = false;
}

function GMapsEnd() {
	if (map != null)
		GUnload();
}

function RetiraAcentos(str) {
	str = str.toUpperCase()
	str = str.replace(/�/g, "A");
	str = str.replace(/�/g, "A");
	str = str.replace(/�/g, "A");
	str = str.replace(/�/g, "A");
	str = str.replace(/�/g, "E");
	str = str.replace(/�/g, "E");
	str = str.replace(/�/g, "I");
	str = str.replace(/�/g, "O");
	str = str.replace(/�/g, "O");
	str = str.replace(/�/g, "O");
	str = str.replace(/�/g, "U");
	str = str.replace(/�/g, "U");
	str = str.replace(/�/g, "C");
	return str;
}

function ExibeMapa() {
  if (geocoder) {
	var ender = RetiraAcentos(EnderecoParaMapa);
	geocoder.getLatLng(
	  ender,
	  function(point) {
		if (!point) {
		  alert("N�o encontrou endere�o: " + ender);
		} else {
		  map.setCenter(point, 15);
		  var marker = new GMarker(point);
		  map.addOverlay(marker);
		  ender = ender.split(',');
		  marker.openInfoWindowHtml(EnderecoDoImovel+'<br>'+ender[1]+' - '+ender[2]);
		}
	  }
	);
  }
  else
	ExibirMapa = false;
}

function ExibeFoto(idx) {
	var seta;
	var divfoto = document.getElementById("div_foto");
	var foto = document.getElementById("foto");
	var mapa = document.getElementById("map_canvas");
	var nrofotos = Fotos.length - 1;
	var elem = document.getElementById("mapa");
	if (elem)
		elem.style.display = ExibirMapa ? '' : 'none';
	if (typeof idx == 'number')
	{
		if (idx > nrofotos)
			idx = 'mapa';
		else if (idx > 0 && idx <= nrofotos)
		{
			mapa.style.display = 'none';
			foto.src = Fotos[idx][0];
			foto.title = Fotos[idx][1];
			foto.style.display = '';
			if (divfoto != null)
				divfoto.style.display = '';
			idxFotoAtual = idx;
			seta = document.getElementById("proxima");
			if (seta != null)
				seta.style.visibility = (idxFotoAtual < nrofotos || ExibirMapa) ? 'visible' : 'hidden';
			seta = document.getElementById("anterior");
			if (seta != null)
				seta.style.visibility = (idxFotoAtual > 1) ? 'visible' : 'hidden';
			elem = document.getElementById("listaThumbs");
			if (elem && TamListaThumbs > 0)
				elem.style.width = TamListaThumbs;
		}
	}
	if (typeof idx == 'string' && idx == 'mapa')
	{
		seta = document.getElementById("proxima");
		if (seta != null)
			seta.style.visibility = 'hidden';
		seta = document.getElementById("anterior");
		if (seta != null)
			seta.style.visibility = 'visible';
		if (divfoto != null)
			divfoto.style.display = 'none';
		foto.style.display = 'none';
		mapa.style.display = '';
		if (map == null)
			GMapsInit();
		if (!map.isLoaded())
			ExibeMapa();
		idxFotoAtual = nrofotos + 1;
	}
	return false;
}

