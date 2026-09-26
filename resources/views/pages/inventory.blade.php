@extends('layouts.site')
@section('content')
<section class="hero">
    <div class="hero-copy"><span class="eyebrow"><span class="live-dot"></span> TU PRÓXIMA OPORTUNIDAD ESTÁ AQUÍ</span><h1>Un nuevo camino.<br><span>Un mejor comienzo.</span></h1><p>Explora, compara y encuentra tu próximo vehículo.<br>Participa en subastas en vivo, desde donde estés.</p><a class="button" href="#inventario">Explorar vehículos <span aria-hidden="true">↗</span></a><div class="hero-benefits"><span>✓ Ofertas en tiempo real</span><span>✓ Registro gratuito</span></div></div>
    <div class="hero-art" aria-hidden="true"><div class="orbit orbit-one"></div><div class="orbit orbit-two"></div><span class="art-label"><span class="live-dot"></span> Cada oferta cuenta</span><svg class="car-art" viewBox="0 0 600 300" fill="none"><ellipse cx="313" cy="250" rx="229" ry="18" fill="#9ab6ac" opacity=".2"/><path d="M78 177 139 160 199 96Q211 85 234 85H377Q398 85 416 107L467 164 520 179Q538 186 540 205V232H72V201Q72 184 78 177Z" fill="#edf3f0" stroke="#93a99f" stroke-width="3"/><path d="m211 101-49 59h130v-59Zm98 0v59h131l-50-59Z" fill="#688b80"/><path d="m211 104-39 48h47l43-48Z" fill="#a3bdb3" opacity=".65"/><path d="M75 185h57l-14 20H73M536 191h-41l9 16h36" fill="#f7d596"/><path d="M151 175h299M312 104v105M187 218h235" stroke="#bdcbc5" stroke-width="3"/><rect x="325" y="174" width="24" height="5" rx="2.5" fill="#728b80"/><rect x="184" y="174" width="24" height="5" rx="2.5" fill="#728b80"/><circle cx="157" cy="229" r="39" fill="#283d37"/><circle cx="157" cy="229" r="23" fill="#e0e9e5"/><circle cx="157" cy="229" r="10" fill="#8ca29a"/><circle cx="447" cy="229" r="39" fill="#283d37"/><circle cx="447" cy="229" r="23" fill="#e0e9e5"/><circle cx="447" cy="229" r="10" fill="#8ca29a"/></svg><div class="art-ticket"><span class="ticket-icon">↗</span><div><strong>Tu siguiente vehículo</strong><span>A una oferta de distancia</span></div></div></div>
</section>
<section id="inventario" class="inventory-section">
    <div class="section-heading"><div><span class="eyebrow muted">ENCUENTRA EL INDICADO</span><h2>Explora el inventario</h2></div><span class="live-label"><span class="live-dot"></span> Subastas actualizadas</span></div>
    <div class="inventory-layout">
        <aside class="filters panel"><div class="filter-heading"><h3>Filtrar vehículos</h3><button class="text-button" id="clear-filters" type="button">Limpiar</button></div>
            <form id="filters">
                <label>Buscar<input name="buscar" type="search" maxlength="100" placeholder="Marca, modelo o descripción"></label>
                <label>Marca<select name="marca" id="filter-marca"><option value="">Todas las marcas</option></select></label>
                <label>Modelo<select name="modelo" id="filter-modelo"><option value="">Todos los modelos</option></select></label>
                <div class="form-grid"><label>Año desde<input type="number" name="anio_desde" min="1900" max="2200" placeholder="2015"></label><label>Año hasta<input type="number" name="anio_hasta" min="1900" max="2200" placeholder="2027"></label></div>
                <label>Combustible<select name="combustible" id="filter-combustible"><option value="">Todos</option></select></label>
                <label>Estado del vehículo<select name="nivel_dano"><option value="">Cualquier estado</option><option value="verde">Verde · Menor / limpio</option><option value="amarillo">Amarillo · Reparable</option><option value="rojo">Rojo · Salvamento</option></select></label>
                <details><summary>Más filtros <span>＋</span></summary><div class="extra-filters">
                    <label>Tipo de artículo<select name="tipo_articulo" id="filter-tipo"><option value="">Todos</option></select></label>
                    <label>Motor<select name="motor" id="filter-motor"><option value="">Todos</option></select></label>
                    <label>Transmisión<select name="transmision" id="filter-transmision"><option value="">Todas</option></select></label>
                    <label>Tracción<select name="tren_manejo"><option value="">Todas</option>@foreach(['AWD', 'FWD', 'RWD', '4WD'] as $drive)<option>{{ $drive }}</option>@endforeach</select></label>
                    <label>Cilindros<input type="number" name="numero_cilindros" min="0" max="24" placeholder="Cualquiera"></label>
                    <div class="form-grid"><label>Base desde (Q)<input name="precio_desde" inputmode="decimal" placeholder="20000.00"></label><label>Base hasta (Q)<input name="precio_hasta" inputmode="decimal" placeholder="100000.00"></label></div>
                    <label>Subasta<select name="estado"><option value="">Activas y próximas</option><option>Activa</option><option>Pendiente</option><option>Finalizada</option><option>Desierta</option></select></label>
                </div></details>
                <button class="button full-width" type="submit">Aplicar filtros</button>
            </form>
            <div class="filter-tip"><strong>Encuentra tu oportunidad</strong><p>Revisa la ficha y las fotografías antes de realizar una oferta.</p></div>
        </aside>
        <div class="inventory-results"><div class="results-toolbar"><span id="results-count" aria-live="polite">Consultando vehículos…</span><label class="sort-label">Ordenar<select id="sort"><option value="recientes">Más recientes</option><option value="anio_desc">Año: más nuevos</option><option value="anio_asc">Año: más antiguos</option></select></label></div><div id="vehicles" class="vehicle-grid" aria-live="polite"><div class="loading">Cargando inventario…</div></div><nav id="pagination" class="pagination" aria-label="Páginas del inventario"></nav></div>
    </div>
</section>
<section class="seller-banner"><div><span class="eyebrow">DA EL SIGUIENTE PASO</span><h2>Tu vehículo también tiene una oportunidad.</h2><p>Publica su ficha, agrega las fotos y elige cuándo comienza tu subasta.</p></div><a class="button button-outline" href="/publicar">Publicar mi vehículo <span>↗</span></a></section>
@endsection
