<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f7f8fa">
    <meta name="description" content="Encuentra vehículos, publica el tuyo y participa en subastas en tiempo real en Guatemala.">
    <title>{{ $title }} · Lote</title>
    <link rel="icon" href="/assets/favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="/assets/site.css?v={{ filemtime(public_path('assets/site.css')) }}">
    <script type="module" src="/assets/site.js?v={{ filemtime(public_path('assets/site.js')) }}"></script>
</head>
<body data-page="{{ $page }}" data-vehicle-id="{{ $vehicleId ?? '' }}">
    <a class="skip" href="#main">Saltar al contenido</a>
    <header class="site-header">
        <div class="container header-inner">
            <a class="brand" href="/" aria-label="Lote, inicio"><span class="brand-mark">↗</span>lote<span class="brand-dot">.</span><span class="brand-description">SUBASTAS DE VEHÍCULOS</span></a>
            <nav class="main-nav" aria-label="Navegación principal">
                <a href="/" class="{{ $page === 'inventory' ? 'current' : '' }}">Explorar</a>
                <a href="/mis-vehiculos" data-auth hidden class="{{ in_array($page, ['mine', 'editor']) ? 'current' : '' }}">Mis vehículos</a>
                <a href="/mis-pujas" data-auth hidden class="{{ $page === 'bids' ? 'current' : '' }}">Mis pujas</a>
            </nav>
            <div class="header-actions">
                <a class="notification-link" data-auth hidden href="/notificaciones" aria-label="Notificaciones" title="Notificaciones"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/></svg></a>
                <span class="user-name" data-user-name data-auth hidden></span>
                <button class="text-button" id="logout" data-auth hidden>Salir</button>
                <a class="login-link" href="/login" data-guest>Iniciar sesión</a>
                <a class="button button-small" href="/publicar"><span aria-hidden="true">＋</span> Publicar vehículo</a>
            </div>
        </div>
    </header>
    <main id="main" class="container main-content" tabindex="-1">
        <div id="global-error" class="notice notice-error" role="alert" hidden></div>
        @yield('content')
    </main>
    <footer class="site-footer"><div class="container footer-inner"><a href="/" class="brand footer-brand">lote<span class="brand-dot">.</span></a><p>Una nueva oportunidad para cada vehículo.</p><span>Guatemala · Precios en quetzales (GTQ)</span></div></footer>
    <div id="toast" class="toast" role="status" aria-live="polite" hidden></div>
    <noscript><p class="notice notice-error">Activa JavaScript para consultar vehículos, iniciar sesión y participar en las subastas.</p></noscript>
</body>
</html>
