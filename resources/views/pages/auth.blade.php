@extends('layouts.site')
@section('content')
<section class="auth-layout">
    <div class="auth-intro"><a class="back-link" href="/">← Volver al inventario</a><span class="eyebrow">EL SIGUIENTE PASO ES TUYO</span><h1>Más oportunidades.<br><span>Un solo lugar.</span></h1><p>Encuentra tu próximo vehículo o dale un nuevo comienzo al tuyo.</p><ol class="steps"><li><span>01</span><div><strong>Crea tu cuenta</strong><p>Un mismo perfil para comprar y vender.</p></div></li><li><span>02</span><div><strong>Encuentra tu oportunidad</strong><p>Compara la ficha y revisa cada fotografía.</p></div></li><li><span>03</span><div><strong>Participa en vivo</strong><p>Sigue tus ofertas y recibe notificaciones.</p></div></li></ol></div>
    <div class="panel auth-panel"><span class="eyebrow">BIENVENIDO A LOTE</span><h2>{{ $page === 'register' ? 'Crea tu cuenta' : 'Qué bueno verte de nuevo' }}</h2><p class="muted">{{ $page === 'register' ? 'Empieza a publicar y participar en subastas.' : 'Inicia sesión para seguir tus oportunidades.' }}</p>
        <form id="auth-form">
            @if($page === 'register')
            <div class="form-grid"><label>Nombre<input name="nombre" autocomplete="given-name" maxlength="100" required></label><label>Apellido<input name="apellido" autocomplete="family-name" maxlength="100" required></label></div>
            <label>Teléfono<input name="telefono" type="tel" autocomplete="tel" maxlength="20" placeholder="Ej. 5555 1234" required></label>
            @endif
            <label>Correo electrónico<input name="correo" type="email" autocomplete="email" maxlength="150" placeholder="tu@correo.com" required></label>
            <label>Contraseña<input name="password" type="password" autocomplete="{{ $page === 'register' ? 'new-password' : 'current-password' }}" minlength="8" maxlength="72" required></label>
            @if($page === 'register')<p class="field-help">Usa al menos 8 caracteres, mayúsculas, minúsculas y números.</p><label>Confirmar contraseña<input name="password_confirmation" type="password" autocomplete="new-password" minlength="8" maxlength="72" required></label>@endif
            <div class="notice notice-error form-error" role="alert" hidden></div><div class="notice notice-success" id="auth-success" role="status" hidden></div>
            <button type="submit" class="button full-width">{{ $page === 'register' ? 'Crear cuenta' : 'Iniciar sesión' }} <span>↗</span></button>
        </form>
        <p class="auth-switch">{{ $page === 'register' ? '¿Ya tienes una cuenta?' : '¿Es tu primera visita?' }} <a href="{{ $page === 'register' ? '/login' : '/registro' }}">{{ $page === 'register' ? 'Inicia sesión' : 'Crea tu cuenta' }}</a></p>
    </div>
</section>
@endsection
