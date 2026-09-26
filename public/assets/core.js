export const $ = (selector, root = document) => root.querySelector(selector);
export const $$ = (selector, root = document) => [...root.querySelectorAll(selector)];
export const esc = value => String(value ?? '').replace(/[&<>"']/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[char]));
export const money = value => value == null ? 'Sin ofertas' : new Intl.NumberFormat('es-GT', { style: 'currency', currency: 'GTQ', maximumFractionDigits: 2 }).format(Number(value));
export const date = value => value ? new Intl.DateTimeFormat('es-GT', { dateStyle: 'medium', timeStyle: 'short', timeZone: 'America/Guatemala' }).format(new Date(value)) : '—';
export const token = () => sessionStorage.getItem('lote.token');
export function user() { try { return JSON.parse(sessionStorage.getItem('lote.user') || 'null'); } catch { return null; } }
export function clearSession() { sessionStorage.removeItem('lote.token'); sessionStorage.removeItem('lote.user'); }
export function saveSession(data) { sessionStorage.setItem('lote.token', data.token); sessionStorage.setItem('lote.user', JSON.stringify(data.usuario)); }
export function nextPath() { const next = new URLSearchParams(location.search).get('next'); return next && next.startsWith('/') && !next.startsWith('//') && !next.includes('\\') ? next : '/'; }
export function loginRedirect() { clearSession(); location.assign('/login?next=' + encodeURIComponent(location.pathname + location.search)); }
export async function api(path, { method = 'GET', body, signal } = {}) {
  const headers = { Accept: 'application/json' };
  if (token()) headers.Authorization = `Bearer ${token()}`;
  if (body !== undefined && !(body instanceof FormData)) { headers['Content-Type'] = 'application/json'; body = JSON.stringify(body); }
  let response;
  try { response = await fetch('/api' + path, { method, headers, body, signal }); }
  catch (error) { if (error.name === 'AbortError') throw error; throw new Error('No pudimos conectar. Revisa tu conexión e inténtalo de nuevo.'); }
  const data = response.status === 204 ? null : await response.json().catch(() => null);
  if (!response.ok) {
    if (response.status === 401 && !path.startsWith('/auth/login') && !path.startsWith('/auth/register') && token()) loginRedirect();
    const error = new Error(data?.error?.mensaje || (response.status === 413 ? 'El lote de fotografías supera el tamaño permitido.' : 'No se pudo completar la solicitud. Inténtalo nuevamente.'));
    error.status = response.status; error.details = data?.error?.detalles; error.code = data?.error?.codigo; throw error;
  }
  return data;
}
export function showError(error, target = $('#global-error')) {
  if (!target || error.name === 'AbortError') return;
  const details = error.details && Object.values(error.details).filter(Array.isArray).flat();
  target.textContent = details?.length ? details.join(' ') : error.message;
  target.hidden = false;
}
export function toast(message) { const box = $('#toast'); box.textContent = message; box.hidden = false; clearTimeout(toast.timer); toast.timer = setTimeout(() => box.hidden = true, 5000); }
export async function submit(form, operation) {
  const errorBox = $('.form-error', form); if (errorBox) errorBox.hidden = true;
  const buttons = $$('button[type="submit"]', form); buttons.forEach(button => button.disabled = true); form.setAttribute('aria-busy', 'true');
  try { return await operation(); } catch (error) { showError(error, errorBox); } finally { buttons.forEach(button => button.disabled = false); form.removeAttribute('aria-busy'); }
}
export function safeUrl(value) { try { const url = new URL(value, location.origin); return ['https:', 'http:', 'blob:'].includes(url.protocol) ? url.href : ''; } catch { return ''; } }
export const placeholder = `<div class="photo-placeholder"><svg viewBox="0 0 80 42" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m8 25 10-4 10-13h24l13 14 8 4v9H7V25Z"/><path d="m29 12-7 10h36l-9-10Z"/><circle cx="21" cy="34" r="6" fill="#eaf0eb"/><circle cx="59" cy="34" r="6" fill="#eaf0eb"/></svg><span>Sin fotografías</span></div>`;
const blobs = new Map();
export async function photoUrl(photo) {
  const safe = safeUrl(photo.url); if (!safe) return '';
  const url = new URL(safe);
  if (!token() || url.origin !== location.origin || !url.pathname.startsWith('/api/media/')) return safe;
  if (!blobs.has(safe)) blobs.set(safe, fetch(safe, { headers: { Authorization: `Bearer ${token()}` } }).then(async response => {
    if (!response.ok) throw new Error('No se pudo cargar una fotografía.');
    return URL.createObjectURL(await response.blob());
  }).catch(error => { blobs.delete(safe); throw error; }));
  return blobs.get(safe);
}
window.addEventListener('pagehide', () => { for (const promise of blobs.values()) promise.then(url => URL.revokeObjectURL(url)).catch(() => {}); });
export function damage(value) { const colors = { Verde: 'green', Amarillo: 'yellow', Rojo: 'red' }; const labels = { Verde: 'Menor / limpio', Amarillo: 'Reparable', Rojo: 'Salvamento' }; return `<span class="badge badge-${colors[value] || 'green'}"><span class="damage-dot"></span>${esc(value)} · ${esc(labels[value])}</span>`; }
export function stateBadge(state) { const color = { Activa: 'green', Pendiente: 'blue', Finalizada: 'blue', Desierta: 'yellow', Cancelada: 'red' }[state] || ''; return `<span class="badge ${color ? 'badge-' + color : ''}">${esc(state || 'Borrador')}</span>`; }
export function personal(state) { return ({ sin_participar: 'Aún no has ofertado', ganando: 'Vas ganando esta subasta', superado: 'Tu oferta fue superada', ganada: '¡Ganaste esta subasta!', perdida: 'La subasta ha finalizado' })[state] || 'Aún no has ofertado'; }
export function empty(title, message, action = '') { return `<div class="empty-state"><span class="empty-icon" aria-hidden="true">↗</span><h3>${esc(title)}</h3><p>${esc(message)}</p>${action}</div>`; }
export function pagination(data, load) {
  const meta = data.meta || data; const box = $('#pagination'); if (!box) return;
  if (meta.last_page <= 1) { box.innerHTML = ''; return; }
  box.innerHTML = `<button type="button" data-page="${meta.current_page - 1}" ${meta.current_page <= 1 ? 'disabled' : ''}>← Anterior</button><span>${meta.current_page} de ${meta.last_page}</span><button type="button" data-page="${meta.current_page + 1}" ${meta.current_page >= meta.last_page ? 'disabled' : ''}>Siguiente →</button>`;
  $$('button', box).forEach(button => button.onclick = () => load(Number(button.dataset.page)).catch(showError));
}
export function editable(vehicle) { return !vehicle.subasta || (vehicle.subasta.estado === 'Pendiente' && new Date(vehicle.subasta.fecha_inicio) > new Date(vehicle.subasta.fecha_servidor)); }
export function countdown(end, now = Date.now()) {
  const seconds = Math.max(0, Math.floor((new Date(end).getTime() - now) / 1000));
  const days = Math.floor(seconds / 86400), hours = Math.floor(seconds % 86400 / 3600), minutes = Math.floor(seconds % 3600 / 60);
  return `${days ? days + 'd ' : ''}${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(seconds % 60).padStart(2, '0')}`;
}
export function card(vehicle, own = false) {
  const s = vehicle.subasta, photo = vehicle.fotos?.find(p => p.es_principal) || vehicle.fotos?.[0], end = s?.estado === 'Pendiente' ? s.fecha_inicio : s?.fecha_cierre;
  const image = photo ? `<img data-photo="${esc(photo.url)}" alt="${esc(vehicle.marca + ' ' + vehicle.modelo)}" loading="lazy">` : placeholder;
  return `<article class="vehicle-card"><a class="card-image" href="/vehiculos/${vehicle.id}" aria-label="Ver ${esc(vehicle.marca + ' ' + vehicle.modelo)}">${image}${stateBadge(s?.estado)}${photo ? `<span class="photo-number">▧ ${vehicle.fotos.length} fotos</span>` : ''}</a><div class="card-body"><div class="card-kicker">${esc(vehicle.tipo_articulo)} · ${esc(vehicle.anio)}</div><h3 class="card-title"><a href="/vehiculos/${vehicle.id}">${esc(vehicle.marca)} ${esc(vehicle.modelo)}</a></h3><div class="card-specs"><span>${esc(vehicle.transmision)}</span><span>${esc(vehicle.tipo_combustible)}</span><span>${esc(vehicle.tren_manejo)}</span></div>${damage(vehicle.estado_danio)}<div class="card-price" style="margin-top:14px"><div><span class="price-label">${s?.monto_actual ? 'Oferta actual' : 'Precio base'}</span><strong>${s ? money(s.monto_actual || s.monto_base) : 'Por definir'}</strong></div><a class="card-arrow" href="/vehiculos/${vehicle.id}" aria-label="Ver detalle">↗</a></div><div class="card-footer"><span>${s ? (s.estado === 'Pendiente' ? 'Comienza en' : s.estado === 'Activa' ? 'Cierra en' : 'Subasta cerrada') : 'Completa tu publicación'}</span>${s && ['Activa', 'Pendiente'].includes(s.estado) ? `<span data-clock="${esc(end)}" data-offset="${new Date(s.fecha_servidor).getTime() - Date.now()}">${countdown(end, new Date(s.fecha_servidor).getTime())}</span>` : ''}</div></div>${own ? `<div class="card-actions"><a class="button button-outline" href="/vehiculos/${vehicle.id}/editar">${editable(vehicle) ? 'Editar publicación' : 'Ver ficha'}</a>${!s ? `<button class="button button-outline button-danger" data-delete="${vehicle.id}">Eliminar</button>` : ''}</div>` : ''}</article>`;
}
export async function hydratePhotos(root = document) { await Promise.all($$('img[data-photo]', root).map(async image => { try { image.src = await photoUrl({ url: image.dataset.photo }); image.onerror = () => { image.outerHTML = placeholder; }; } catch { image.outerHTML = placeholder; } })); }
setInterval(() => $$('[data-clock]').forEach(clock => clock.textContent = countdown(clock.dataset.clock, Date.now() + Number(clock.dataset.offset || 0))), 1000);
export function confirmAction(title, message, label = 'Confirmar') {
  return new Promise(resolve => {
    const dialog = document.createElement('dialog'); dialog.className = 'dialog';
    dialog.innerHTML = `<h2>${esc(title)}</h2><p>${esc(message)}</p><div class="row-actions"><button class="button button-outline" data-cancel>Cancelar</button><button class="button" data-confirm>${esc(label)}</button></div>`;
    document.body.append(dialog); const finish = value => { dialog.close(); dialog.remove(); resolve(value); };
    $('[data-cancel]', dialog).onclick = () => finish(false); $('[data-confirm]', dialog).onclick = () => finish(true); dialog.addEventListener('cancel', event => { event.preventDefault(); finish(false); }); dialog.showModal();
  });
}
