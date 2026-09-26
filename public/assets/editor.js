import { $, $$, api, esc, editable, showError, toast, submit, photoUrl, confirmAction } from './core.js';

export async function editor() {
  let id = document.body.dataset.vehicleId, vehicle = null;
  const vehicleForm = $('#vehicle-form'), photoForm = $('#photos-form'), auctionForm = $('#auction-form');
  $('#timezone-note').textContent = 'Las fechas se ingresan en tu zona horaria: ' + Intl.DateTimeFormat().resolvedOptions().timeZone + '.';
  function state() {
    const locked = vehicle && !editable(vehicle);
    $('#vehicle-fields').disabled = !!locked; $('#photo-fields').disabled = !id || !!locked;
    $('#auction-fields').disabled = !id || !!locked || (vehicle?.fotos?.length || 0) < 5;
    $('#editor-locked').hidden = !locked; $('#editor-state').textContent = vehicle?.subasta?.estado || 'Borrador';
    $('#publish-button').textContent = vehicle?.subasta ? 'Guardar condiciones' : 'Publicar subasta ↗';
    $('#photo-count').textContent = `${vehicle?.fotos?.length || 0} / 5 mínimo`;
    $('#upload-selection').textContent = id ? 'Selecciona entre 1 y 20 fotos. Tu publicación necesita al menos cinco.' : 'Guarda primero la ficha del vehículo.';
  }
  const localDate = value => { const d = new Date(value); return new Date(d.getTime() - d.getTimezoneOffset() * 60000).toISOString().slice(0, 16); };
  async function load(fill = false) {
    vehicle = (await api('/vehiculos/' + id)).data;
    if (!vehicle.es_mio) { $('#vehicle-fields').disabled = true; throw new Error('Solo el propietario puede editar este vehículo.'); }
    if (fill) {
      for (const [key, value] of Object.entries(vehicle)) {
        const input = vehicleForm.elements.namedItem(key); if (!input) continue;
        if (input.tagName === 'SELECT' && value && ![...input.options].some(option => option.value === String(value))) input.add(new Option(value, value));
        input.value = value ?? '';
      }
      if (vehicle.subasta) {
        auctionForm.elements.monto_base.value = vehicle.subasta.monto_base;
        auctionForm.elements.fecha_inicio.value = localDate(vehicle.subasta.fecha_inicio);
        auctionForm.elements.fecha_cierre.value = localDate(vehicle.subasta.fecha_cierre);
      }
    }
    state();
    $('#editor-photos').innerHTML = vehicle.fotos.map(p => `<div class="editor-photo"><img alt="Fotografía ${p.orden} del vehículo" data-id="${p.id}">${editable(vehicle) ? `<button type="button" data-remove="${p.id}" aria-label="Eliminar fotografía ${p.orden}">×</button>` : ''}${p.es_principal ? '<span class="badge badge-green">Portada</span>' : ''}</div>`).join('');
    await Promise.all(vehicle.fotos.map(async p => { try { const image = $(`#editor-photos img[data-id="${p.id}"]`); image.src = await photoUrl(p); } catch (error) { showError(error); } }));
    $$('[data-remove]').forEach(button => button.onclick = async () => {
      if (!await confirmAction('¿Eliminar esta fotografía?', 'Una publicación debe conservar al menos cinco fotografías.', 'Eliminar foto')) return;
      button.disabled = true;
      try { await api(`/vehiculos/${id}/fotos/${button.dataset.remove}`, { method: 'DELETE' }); await load(); toast('Fotografía eliminada.'); } catch (error) { showError(error, $('.form-error', photoForm)); button.disabled = false; }
    });
  }
  vehicleForm.onsubmit = event => { event.preventDefault(); submit(vehicleForm, async () => {
    const data = Object.fromEntries(new FormData(vehicleForm));
    const result = await api('/vehiculos' + (id ? '/' + id : ''), { method: id ? 'PUT' : 'POST', body: data });
    id = String(result.data.id); history.replaceState(null, '', '/vehiculos/' + id + '/editar'); await load(); toast('Ficha guardada. Puedes continuar con las fotografías.');
  }); };
  $('#photo-files').onchange = () => {
    const files = [...$('#photo-files').files];
    $('#upload-selection').textContent = files.length ? `${files.length} fotografías seleccionadas · ${(files.reduce((sum, file) => sum + file.size, 0) / 1048576).toFixed(1)} MB` : 'Selecciona las fotografías que deseas subir.';
  };
  photoForm.onsubmit = event => { event.preventDefault(); submit(photoForm, async () => {
    const files = [...$('#photo-files').files];
    if (!id) throw new Error('Guarda primero la ficha del vehículo.');
    if (!files.length) throw new Error('Selecciona al menos una fotografía.');
    if (files.length + vehicle.fotos.length > 20) throw new Error('El vehículo puede tener un máximo de 20 fotografías.');
    if (files.some(file => file.size > 5 * 1024 * 1024)) throw new Error('Cada fotografía debe pesar como máximo 5 MB.');
    if (files.some(file => !['image/jpeg', 'image/png', 'image/webp'].includes(file.type))) throw new Error('Utiliza fotografías JPG, PNG o WebP.');
    await api(`/vehiculos/${id}/fotos`, { method: 'POST', body: new FormData(photoForm) });
    photoForm.reset(); await load(); toast('Fotografías guardadas.');
  }); };
  auctionForm.onsubmit = event => { event.preventDefault(); submit(auctionForm, async () => {
    const data = Object.fromEntries(new FormData(auctionForm));
    data.monto_base = data.monto_base.trim().replace(',', '.');
    if (!/^\d{1,10}(\.\d{1,2})?$/.test(data.monto_base) || Number(data.monto_base) < 20000) throw new Error('El precio base debe ser al menos Q 20,000.00, con hasta dos decimales.');
    const start = new Date(data.fecha_inicio), end = new Date(data.fecha_cierre);
    if (Number.isNaN(start.getTime()) || Number.isNaN(end.getTime()) || start <= new Date() || end <= start) throw new Error('Elige un inicio futuro y un cierre posterior al inicio.');
    data.fecha_inicio = start.toISOString(); data.fecha_cierre = end.toISOString();
    if (!vehicle.subasta) data.id_vehiculo = Number(id);
    await api('/subastas' + (vehicle.subasta ? '/' + vehicle.subasta.id : ''), { method: vehicle.subasta ? 'PUT' : 'POST', body: data });
    location.assign('/vehiculos/' + id);
  }); };
  if (id) await load(true); else state();
}
