@extends('layouts.site')
@section('content')
<div class="page-heading"><div><span class="eyebrow">CADA OFERTA CUENTA</span><h1>Mis pujas</h1><p>Sigue tus participaciones y consulta las subastas que has ganado.</p></div><a class="button button-outline" href="/">Explorar inventario ↗</a></div>
<div class="tabs" role="tablist" aria-label="Participaciones"><button class="tab active" role="tab" aria-selected="true" data-bids="mis-pujas">Mis participaciones</button><button class="tab" role="tab" aria-selected="false" data-bids="ganadas">Subastas ganadas</button></div>
<div id="bids-list" class="list-stack"><div class="loading">Cargando tus participaciones…</div></div><nav id="pagination" class="pagination" aria-label="Páginas de participaciones"></nav>
@endsection
