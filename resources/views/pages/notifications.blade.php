@extends('layouts.site')
@section('content')
<div class="page-heading"><div><span class="eyebrow">AL DÍA CON TUS SUBASTAS</span><h1>Notificaciones</h1><p>Ofertas superadas, resultados y nuevas oportunidades.</p></div><button class="button button-outline" id="refresh-notices">Actualizar ↻</button></div>
<div class="tabs" role="tablist" aria-label="Lectura de notificaciones"><button class="tab active" role="tab" aria-selected="true" data-read="">Todas</button><button class="tab" role="tab" aria-selected="false" data-read="0">Sin leer</button><button class="tab" role="tab" aria-selected="false" data-read="1">Leídas</button></div>
<div id="notice-list" class="list-stack"><div class="loading">Cargando notificaciones…</div></div><nav id="pagination" class="pagination" aria-label="Páginas de notificaciones"></nav>
@endsection
