import { $, $$, api, esc, money, date, token, user, saveSession, clearSession, nextPath, loginRedirect, showError, toast, submit, card, hydratePhotos, pagination, stateBadge, personal, empty, confirmAction } from './core.js';

const page = document.body.dataset.page;
const protectedPages = ['mine', 'editor', 'auction', 'bids', 'notifications'];
function header() {
  $$('[data-auth]').forEach(el => el.hidden = !token()); $$('[data-guest]').forEach(el => el.hidden = !!token());
  $('[data-user-name]').textContent = user()?.nombre || '';
}
$('#logout').onclick = async () => { try { await api('/auth/logout', { method: 'POST' }); clearSession(); location.assign('/'); } catch (error) { showError(error); } };
header();
async function boot() {
  if (protectedPages.includes(page) && !token()) { loginRedirect(); return; }
  if (token()) {
    try { const data = await api('/auth/me'); sessionStorage.setItem('lote.user', JSON.stringify(data.data)); header(); }
    catch (error) { if (protectedPages.includes(page)) throw error; clearSession(); header(); }
  }
  if (page === 'inventory' || page === 'mine') await inventory(page === 'mine');
  if (page === 'login' || page === 'register') auth();
  if (page === 'editor') { const { editor } = await import('./editor.js'); await editor(); }
  if (page === 'auction') { const { auction } = await import('./auction.js'); await auction(); }
  if (page === 'bids') await bids();
  if (page === 'notifications') await notifications();
}
function auth() {
  const form = $('#auth-form');
  if (new URLSearchParams(location.search).get('registered')) { $('#auth-success').textContent = 'Tu cuenta está lista. Inicia sesión para continuar.'; $('#auth-success').hidden = false; }
  form.onsubmit = event => { event.preventDefault(); submit(form, async () => {
    const data = Object.fromEntries(new FormData(form));
    if (page === 'register') {
      if (data.password !== data.password_confirmation) throw new Error('Las contraseñas no coinciden.');
      await api('/auth/register', { method: 'POST', body: data });
      location.assign('/login?registered=1&next=' + encodeURIComponent(nextPath()));
    } else { saveSession((await api('/auth/login', { method: 'POST', body: data })).data); location.assign(nextPath()); }
  }); };
  const switchLink = $('.auth-switch a'); if (location.search.includes('next=')) switchLink.href += '?next=' + encodeURIComponent(nextPath());
}
async function inventory(own) {
  const form = own ? $('#mine-search') : $('#filters'); let query = new URLSearchParams(location.search), current = 1, latest = 0;
  for (const [key, value] of query) { const input = form.elements.namedItem(key); if (input && input.tagName !== 'SELECT') input.value = value; }
  if (!own) {
    const catalogMap = { 'filter-marca': 'marcas', 'filter-modelo': 'modelos', 'filter-combustible': 'combustibles', 'filter-transmision': 'transmisiones', 'filter-motor': 'motores', 'filter-tipo': 'tipos_articulo' };
    const fill = (select, values) => { select.querySelectorAll('option:not(:first-child)').forEach(option => option.remove()); values.forEach(value => select.add(new Option(String(value), String(value)))); };
    try { const { data } = await api('/catalogos'); for (const [id, key] of Object.entries(catalogMap)) fill($('#' + id), data[key]); } catch (error) { showError(error); }
    for (const [key, value] of query) { const input = form.elements.namedItem(key); if (input?.tagName === 'SELECT') input.value = value; }
    $('#sort').value = query.get('orden') || 'recientes';
    $('#filter-marca').onchange = async () => { try { const { data } = await api('/catalogos?marca=' + encodeURIComponent($('#filter-marca').value)); fill($('#filter-modelo'), data.modelos); } catch (error) { showError(error); } };
    $('#sort').onchange = () => reload().catch(showError);
    $('#clear-filters').onclick = () => { form.reset(); $('#sort').value = 'recientes'; reload().catch(showError); };
  }
  const reload = async (number = 1) => {
    current = number; const requestId = ++latest; query = new URLSearchParams();
    for (const [key, value] of new FormData(form)) if (String(value).trim()) query.set(key, String(value).trim());
    if (!own) query.set('orden', $('#sort').value);
    query.set('page', String(number)); query.set('per_page', own ? '12' : '9');
    history.replaceState(null, '', location.pathname + '?' + query);
    const data = await api((own ? '/vehiculos/mios?' : '/vehiculos?') + query);
    if (requestId !== latest) return;
    $('#global-error').hidden = true;
    if (!own) $('#results-count').textContent = `${data.meta.total} ${data.meta.total === 1 ? 'vehículo disponible' : 'vehículos disponibles'}`;
    $('#vehicles').innerHTML = data.data.length ? data.data.map(v => card(v, own)).join('') : empty(own ? 'Tu próxima publicación empieza aquí' : 'No encontramos vehículos', own ? 'Crea una ficha, agrega al menos cinco fotos y programa tu primera subasta.' : 'Prueba otros filtros o vuelve pronto para descubrir nuevas oportunidades.', own ? '<a class="button" href="/publicar">Publicar mi primer vehículo ↗</a>' : '<button id="empty-clear" class="button button-outline">Limpiar filtros</button>');
    if ($('#empty-clear')) $('#empty-clear').onclick = () => $('#clear-filters').click();
    pagination(data, reload); hydratePhotos($('#vehicles'));
    $$('[data-delete]').forEach(button => button.onclick = async () => {
      if (!await confirmAction('¿Eliminar este borrador?', 'El vehículo dejará de aparecer en tus publicaciones. Esta acción no afecta otras subastas.', 'Eliminar')) return;
      button.disabled = true;
      try { await api('/vehiculos/' + button.dataset.delete, { method: 'DELETE' }); toast('Borrador eliminado.'); await reload(current); } catch (error) { showError(error); button.disabled = false; }
    });
  };
  form.onsubmit = event => { event.preventDefault(); reload().catch(showError); };
  await reload(Number(query.get('page')) || 1);
}
async function bids() {
  let category = 'mis-pujas';
  async function load(number = 1) {
    const data = await api('/subastas/' + category + '?page=' + number);
    $('#bids-list').innerHTML = data.data.length ? data.data.map(s => `<article class="list-row"><span class="list-icon">↗</span><div class="row-content">${stateBadge(s.estado)}<h3>Subasta #${s.id}</h3><p>${esc(personal(s.mi_estado))} · Cierre: ${esc(date(s.fecha_cierre))}</p></div><div class="row-money"><span class="price-label">${s.estado === 'Finalizada' ? 'Monto final' : 'Oferta actual'}</span>${money(s.monto_final || s.monto_actual)}</div><a class="button button-outline" href="/vehiculos/${s.id_vehiculo}">Ver subasta ↗</a></article>`).join('') : empty(category === 'ganadas' ? 'Las oportunidades siguen' : 'Todavía no has realizado ofertas', category === 'ganadas' ? 'Aquí aparecerán las subastas que ganes.' : 'Explora el inventario y encuentra un vehículo para empezar.', '<a class="button" href="/">Explorar vehículos ↗</a>');
    pagination(data, load);
  }
  $$('[data-bids]').forEach(button => button.onclick = () => { category = button.dataset.bids; $$('[data-bids]').forEach(el => { el.classList.toggle('active', el === button); el.setAttribute('aria-selected', String(el === button)); }); load().catch(showError); });
  await load();
}
async function notifications() {
  let read = '', current = 1;
  async function load(number = 1) {
    current = number; const data = await api('/notificaciones?page=' + number + (read !== '' ? '&leida=' + read : ''));
    $('#notice-list').innerHTML = data.data.length ? data.data.map(n => `<article class="list-row ${n.leida ? '' : 'unread'}"><span class="list-icon">${n.leida ? '✓' : '↗'}</span><div class="row-content"><h3>${esc(n.mensaje)}</h3><p>${n.id_subasta ? 'Subasta #' + n.id_subasta : 'Aviso de tu cuenta'}</p><time datetime="${esc(n.fecha)}">${esc(date(n.fecha))}</time></div><div class="row-actions">${n.id_subasta ? `<button class="button button-outline" data-auction="${n.id_subasta}">Ver subasta</button>` : ''}${n.leida ? '<span class="badge">Leída</span>' : `<button class="text-button" data-mark="${n.id}">Marcar como leída</button>`}</div></article>`).join('') : empty('Todo está al día', read === '0' ? 'No tienes notificaciones pendientes de leer.' : 'Los avisos de tus subastas aparecerán aquí.');
    pagination(data, load);
    $$('[data-mark]').forEach(button => button.onclick = async () => { button.disabled = true; try { await api('/notificaciones/' + button.dataset.mark + '/leer', { method: 'PUT' }); await load(current); } catch (error) { button.disabled = false; showError(error); } });
    $$('[data-auction]').forEach(button => button.onclick = async () => { try { const { data: s } = await api('/subastas/' + button.dataset.auction); location.assign('/vehiculos/' + s.id_vehiculo); } catch (error) { showError(error); } });
  }
  $$('[data-read]').forEach(button => button.onclick = () => { read = button.dataset.read; $$('[data-read]').forEach(el => { el.classList.toggle('active', el === button); el.setAttribute('aria-selected', String(el === button)); }); load().catch(showError); });
  $('#refresh-notices').onclick = () => load(current).catch(showError);
  await load();
}
boot().catch(showError);
