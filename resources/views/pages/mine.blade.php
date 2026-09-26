@extends('layouts.site')
@section('content')
<div class="page-heading"><div><span class="eyebrow">TU ESPACIO</span><h1>Mis vehículos</h1><p>Administra tus publicaciones y prepara tu próxima subasta.</p></div><a class="button" href="/publicar">＋ Publicar vehículo</a></div>
<form id="mine-search" class="search-bar"><input type="search" name="buscar" maxlength="100" placeholder="Buscar en mis vehículos" aria-label="Buscar en mis vehículos"><button class="button button-outline" type="submit">Buscar</button></form>
<div id="vehicles" class="vehicle-grid mine-grid"><div class="loading">Cargando tus vehículos…</div></div><nav id="pagination" class="pagination" aria-label="Páginas de mis vehículos"></nav>
@endsection
