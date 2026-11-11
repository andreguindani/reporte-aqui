// Funções de mapa compartilhadas (Leaflet + OpenStreetMap).
window.RA = window.RA || {};

RA.situacoes = {
  recebida:    { nome: 'Recebida',    cor: '#c98a00' },
  em_analise:  { nome: 'Em análise',  cor: '#2563c9' },
  em_execucao: { nome: 'Em execução', cor: '#7b3fc4' },
  concluida:   { nome: 'Concluída',   cor: '#23893f' }
};

RA.esc = function (texto) {
  var d = document.createElement('div');
  d.textContent = texto == null ? '' : String(texto);
  return d.innerHTML;
};

RA.criarMapa = function (elemento, opcoes) {
  opcoes = opcoes || {};
  var mapa = L.map(elemento, { scrollWheelZoom: opcoes.rolagem !== false })
    .setView([opcoes.lat || RA.mapa.lat, opcoes.lng || RA.mapa.lng], opcoes.zoom || RA.mapa.zoom);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '&copy; colaboradores do <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
  }).addTo(mapa);
  return mapa;
};

// Pino colorido conforme a situação (ou uma cor fixa).
RA.pino = function (situacao, cor) {
  cor = cor || (RA.situacoes[situacao] || {}).cor || '#f26b21';
  var svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 28 38" width="28" height="38">' +
    '<path d="M14 1C6.800 1 1 6.800 1 14c0 9.500 11.300 21.600 12.200 22.600a1.100 1.100 0 0 0 1.600 0C15.700 35.600 27 23.500 27 14 27 6.800 21.200 1 14 1z" fill="' + cor + '" stroke="#fff" stroke-width="1.500"/>' +
    '<circle cx="14" cy="14" r="5" fill="#fff"/></svg>';
  return L.divIcon({ className: 'ra-pino', html: svg, iconSize: [28, 38], iconAnchor: [14, 37], popupAnchor: [0, -34] });
};

// Mapas pequenos, só de leitura: <div data-mapa-fixo data-lat data-lng data-situacao>
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('[data-mapa-fixo]').forEach(function (el) {
    var lat = parseFloat(el.dataset.lat), lng = parseFloat(el.dataset.lng);
    var mapa = RA.criarMapa(el, { lat: lat, lng: lng, zoom: 17, rolagem: false });
    L.marker([lat, lng], { icon: RA.pino(el.dataset.situacao) }).addTo(mapa);
  });
});
