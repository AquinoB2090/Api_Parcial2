import { $, $$, api, esc, money, date, token, showError, toast, submit, photoUrl, placeholder, damage, stateBadge, personal, countdown, confirmAction } from './core.js';
import { watchAuction } from './realtime.js';

export async function auction() {
  const id = document.body.dataset.vehicleId;
  const vehicle = (await api('/vehiculos/' + id)).data;
  let s = vehicle.subasta, offset = s ? new Date(s.fecha_servidor).getTime() - Date.now() : 0, index = 0, urls = [], pending = false, lastRefresh = 0;
  const specs = { Año: vehicle.anio, 'Tipo de artículo': vehicle.tipo_articulo, Marca: vehicle.marca, Modelo: vehicle.modelo, Motor: vehicle.motor, Transmisión: vehicle.transmision, Combustible: vehicle.tipo_combustible, Tracción: vehicle.tren_manejo, Cilindros: vehicle.numero_cilindros };
  $('#auction-content').innerHTML = `<div class="detail-heading"><div><span class="eyebrow">${s ? 'SUBASTA #' + s.id : 'PUBLICACIÓN EN BORRADOR'}</span><h1>${esc(vehicle.anio)} ${esc(vehicle.marca)} ${esc(vehicle.modelo)}</h1><p>${esc(vehicle.tipo_articulo)} · ${esc(vehicle.transmision)} · ${esc(vehicle.tipo_combustible)}</p></div>${damage(vehicle.estado_danio)}</div><div class="auction-layout"><div><div class="gallery" aria-label="Galería del vehículo">${vehicle.fotos.length ? '<img id="gallery-image" alt="Fotografía principal del vehículo"><button class="gallery-button gallery-prev" aria-label="Fotografía anterior">‹</button><button class="gallery-button gallery-next" aria-label="Siguiente fotografía">›</button><span class="gallery-counter" id="gallery-counter"></span>' : placeholder}</div><div class="gallery-thumbs" aria-label="Seleccionar fotografía"></div><section class="panel technical"><h2>Conoce cada detalle</h2><dl class="spec-grid">${Object.entries(specs).map(([key, value]) => `<div><dt>${esc(key)}</dt><dd>${esc(value)}</dd></div>`).join('')}</dl><p class="vehicle-description">${esc(vehicle.descripcion || 'El propietario no ha agregado una descripción adicional.')}</p></section></div><aside class="panel bid-panel">${s ? `<div class="auction-topline"><div id="auction-state">${stateBadge(s.estado)}</div><span class="connection-status" id="connection"><span class="live-dot"></span> Conectando…</span></div><span class="price-label" id="amount-label">${s.monto_actual ? 'OFERTA ACTUAL' : 'PRECIO BASE'}</span><div class="large-price" id="current-price">${money(s.monto_actual || s.monto_base)}</div><div class="base-line">Precio base: ${money(s.monto_base)}</div><div class="timer-box"><span id="timer-label"></span><strong id="auction-clock"></strong></div><div class="personal-status" id="personal-state" role="status" aria-live="polite"></div><form id="bid-form" ${vehicle.es_mio ? 'hidden' : ''}><label>Tu oferta (Q)<input name="monto" inputmode="decimal" placeholder="23100.00" required aria-describedby="minimum-bid"></label><p class="field-help" id="minimum-bid"></p><div class="notice notice-error form-error" role="alert" hidden></div><button class="button full-width" type="submit" id="bid-button">Realizar oferta ↗</button></form>${vehicle.es_mio ? `<p class="notice">Esta es tu publicación. Puedes seguir las ofertas, pero no pujar por tu propio vehículo.</p><a class="button button-outline full-width" href="/vehiculos/${id}/editar">Ver o editar publicación</a>` : ''}<p class="bid-rules">Las nuevas ofertas deben superar al menos un 10 % la oferta actual. Si aún no hay ofertas, deben superar el precio base. La identidad de los postores es privada.</p><div class="bid-dates"><div><span>Inicio · Guatemala</span><strong>${esc(date(s.fecha_inicio))}</strong></div><div><span>Cierre · Guatemala</span><strong>${esc(date(s.fecha_cierre))}</strong></div></div><details class="my-history"><summary>Mis ofertas en esta subasta</summary><ul id="bid-history"></ul></details>` : `<span class="eyebrow">EL SIGUIENTE PASO</span><h2>Completa tu publicación</h2><p class="muted">Agrega al menos cinco fotos y define las condiciones para comenzar a recibir ofertas.</p><a class="button full-width" href="/vehiculos/${id}/editar">Continuar publicación ↗</a>`}</aside></div>`;
  if (vehicle.fotos.length) {
    urls = await Promise.all(vehicle.fotos.map(photo => photoUrl(photo).catch(() => '')));
    $('.gallery-thumbs').innerHTML = urls.map((url, i) => `<button type="button" data-photo-index="${i}" aria-label="Ver fotografía ${i + 1}" ${i === 0 ? 'class="active"' : ''}><img src="${esc(url)}" alt="Vista ${i + 1}" loading="lazy"></button>`).join('');
    function display(number) { index = (number + urls.length) % urls.length; $('#gallery-image').src = urls[index]; $('#gallery-image').alt = `Fotografía ${index + 1} de ${vehicle.marca} ${vehicle.modelo}`; $('#gallery-counter').textContent = `${index + 1} / ${urls.length}`; $$('[data-photo-index]').forEach(button => { button.classList.toggle('active', Number(button.dataset.photoIndex) === index); button.setAttribute('aria-current', String(Number(button.dataset.photoIndex) === index)); }); }
    $('.gallery-prev').onclick = () => display(index - 1); $('.gallery-next').onclick = () => display(index + 1);
    $$('[data-photo-index]').forEach(button => button.onclick = () => display(Number(button.dataset.photoIndex))); display(0);
  }
  if (!s) return;
  const form = $('#bid-form');
  function update(payload = {}) {
    if (payload.version !== undefined && payload.version < s.version) return;
    s = { ...s, ...payload };
    if (payload.fecha_servidor) offset = new Date(payload.fecha_servidor).getTime() - Date.now();
    $('#auction-state').innerHTML = stateBadge(s.estado);
    $('#current-price').textContent = money(s.monto_final || s.monto_actual || s.monto_base);
    $('#amount-label').textContent = s.estado === 'Finalizada' ? 'MONTO FINAL' : s.monto_actual ? 'OFERTA ACTUAL' : 'PRECIO BASE';
    $('#minimum-bid').textContent = s.proxima_puja_minima ? `Mínimo para la próxima oferta: ${money(s.proxima_puja_minima)}` : 'Se alcanzó el importe máximo permitido.';
    if (!form.elements.monto.value) form.elements.monto.placeholder = s.proxima_puja_minima || '';
    const box = $('#personal-state'); box.textContent = personal(s.mi_estado);
    box.className = 'personal-status ' + (['ganando', 'ganada'].includes(s.mi_estado) ? 'winning' : s.mi_estado === 'superado' ? 'outbid' : '');
    if (s.estado === 'Desierta') box.textContent = 'Subasta cerrada sin ofertas válidas. Vehículo no vendido.';
    if (s.estado === 'Cancelada') box.textContent = 'Esta subasta fue cancelada.';
    tick();
  }
  function tick() {
    const now = Date.now() + offset, before = now < new Date(s.fecha_inicio).getTime(), ended = now >= new Date(s.fecha_cierre).getTime() || ['Finalizada', 'Desierta', 'Cancelada'].includes(s.estado);
    $('#timer-label').textContent = ended ? 'Subasta cerrada' : before ? 'Comienza en' : 'Cierra en';
    $('#auction-clock').textContent = ended ? '00:00:00' : countdown(before ? s.fecha_inicio : s.fecha_cierre, now);
    $('#bid-button').disabled = pending || vehicle.es_mio || before || ended || !s.proxima_puja_minima;
    $('#bid-button').textContent = ended ? 'Subasta cerrada' : before ? 'La subasta aún no comienza' : 'Realizar oferta ↗';
    form.elements.monto.disabled = before || ended;
  }
  async function history() {
    const { data } = await api(`/subastas/${s.id}/pujas?per_page=100`);
    $('#bid-history').innerHTML = data.mis_pujas.data.length ? data.mis_pujas.data.map(p => `<li><strong>${money(p.monto)}</strong><time>${esc(date(p.fecha))}</time></li>`).join('') : '<li>Aún no has realizado ofertas.</li>';
  }
  form.onsubmit = event => { event.preventDefault(); if (pending) return; pending = true; tick(); submit(form, async () => {
    const amount = form.elements.monto.value.trim().replace(',', '.');
    if (!/^\d{1,10}(\.\d{1,2})?$/.test(amount)) throw new Error('Ingresa un monto válido con hasta dos decimales.');
    if (!await confirmAction('Confirmar tu oferta', `Vas a ofertar ${money(amount)} por este vehículo. Tu oferta se validará con el monto vigente de la subasta.`, 'Confirmar oferta')) return;
    try { const { data } = await api(`/subastas/${s.id}/pujas`, { method: 'POST', body: { monto: amount } }); update(data.subasta); form.reset(); await history(); toast('Tu oferta fue aceptada.'); }
    catch (error) { if (error.status === 409) update((await api(`/subastas/${s.id}/estado`)).data); throw error; }
  }).finally(() => { pending = false; tick(); }); };
  update(); await history();
  const abort = new AbortController(), timer = setInterval(tick, 1000);
  // Respaldo de reconexión: el stream lleva el estado; REST recupera fallos de transporte.
  const recovery = setInterval(() => { if (Date.now() - lastRefresh > 12000) api(`/subastas/${s.id}/estado`).then(data => update(data.data)).catch(() => {}); }, 15000);
  window.addEventListener('pagehide', () => { abort.abort(); clearInterval(timer); clearInterval(recovery); }, { once: true });
  watchAuction({ baseUrl: '', id: s.id, token: token(), signal: abort.signal, onEvent(name, data) {
    if (name === 'ConexionInterrumpida') { $('#connection').textContent = 'Reconectando…'; return; }
    lastRefresh = Date.now(); $('#connection').innerHTML = '<span class="live-dot"></span> En vivo';
    if (name === 'Reloj') { offset = new Date(data.fecha_servidor).getTime() - Date.now(); tick(); }
    if (['EstadoSincronizado', 'PujaActualizada', 'SubastaActualizada', 'SubastaIniciada', 'SubastaCerrada'].includes(name)) update(data);
    if (name === 'EstadoPujaActualizado') update({ mi_estado: data.estado, version: data.version });
    if (name === 'NotificacionCreada') toast(data.mensaje);
  } }).catch(error => { if (error.message === 'SESSION_EXPIRED') { abort.abort(); $('#connection').textContent = 'Sesión finalizada'; $('#bid-button').disabled = true; clearInterval(timer); clearInterval(recovery); showError(new Error('Tu sesión terminó. Inicia sesión nuevamente para continuar.')); } else showError(error); });
}
