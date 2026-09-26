@extends('layouts.site')
@section('content')
<a class="back-link" href="/mis-vehiculos">← Mis vehículos</a>
<div class="page-heading"><div><span class="eyebrow">UNA NUEVA OPORTUNIDAD</span><h1>{{ $vehicleId ? 'Editar vehículo' : 'Publicar un vehículo' }}</h1><p>Completa la ficha, agrega las fotos y programa tu subasta.</p></div><span class="badge" id="editor-state">Borrador</span></div>
<div id="editor-locked" class="notice" hidden>La subasta ya inició. Su ficha, fotografías y condiciones se conservan sin cambios.</div>
<div class="editor-layout">
<div class="editor-main">
<section class="panel editor-section"><div class="step-heading"><span>01</span><div><h2>Ficha del vehículo</h2><p>Los detalles ayudan a tomar una mejor decisión.</p></div></div>
<form id="vehicle-form"><fieldset id="vehicle-fields"><div class="form-grid three-columns">
<label>Año<input type="number" name="anio" min="1900" max="{{ now()->year + 1 }}" placeholder="2020" required></label>
<label>Tipo de artículo<input name="tipo_articulo" maxlength="50" placeholder="Automóvil" required></label>
<label>Marca<input name="marca" maxlength="100" placeholder="Toyota" required></label>
<label>Modelo<input name="modelo" maxlength="100" placeholder="Corolla" required></label>
<label>Motor<input name="motor" maxlength="100" placeholder="1.8 L" required></label>
<label>Transmisión<select name="transmision" required><option value="">Selecciona</option><option>Automática</option><option>Manual</option><option>CVT</option><option>Otra</option></select></label>
<label>Combustible<select name="tipo_combustible" required><option value="">Selecciona</option><option>Gasolina</option><option>Diésel</option><option>Eléctrico</option><option>Híbrido</option><option>Otro</option></select></label>
<label>Tracción<select name="tren_manejo" required><option value="">Selecciona</option>@foreach(['AWD', 'FWD', 'RWD', '4WD'] as $drive)<option>{{ $drive }}</option>@endforeach</select></label>
<label>Número de cilindros<input type="number" name="numero_cilindros" min="0" max="24" placeholder="4" required></label>
</div><label>Estado de daño<select name="estado_danio" required><option value="">Selecciona el estado</option><option value="Verde">Verde · Daño menor / limpio</option><option value="Amarillo">Amarillo · Daño medio / reparable</option><option value="Rojo">Rojo · Daño severo / salvamento</option></select></label><label>Descripción <span class="optional">(opcional)</span><textarea name="descripcion" rows="3" maxlength="1000" placeholder="Describe el estado y los detalles importantes del vehículo."></textarea></label><div class="notice notice-error form-error" role="alert" hidden></div><button type="submit" class="button button-outline">Guardar ficha</button></fieldset></form>
</section>
<section class="panel editor-section"><div class="step-heading"><span>02</span><div><h2>Fotografías <span id="photo-count" class="badge">0 / 5 mínimo</span></h2><p>Muestra el exterior, el interior y los detalles del vehículo.</p></div></div><div id="editor-photos" class="editor-photos"></div>
<form id="photos-form"><fieldset id="photo-fields" disabled><label class="upload-box"><span class="upload-symbol">↑</span><strong>Selecciona las fotografías</strong><span>JPG, PNG o WebP · Hasta 5 MB por foto · Máximo 20</span><input id="photo-files" type="file" name="fotos[]" accept="image/jpeg,image/png,image/webp" multiple required></label><p id="upload-selection" class="field-help">Guarda primero la ficha del vehículo.</p><div class="notice notice-error form-error" role="alert" hidden></div><button class="button button-outline" type="submit">Subir fotografías</button></fieldset></form>
</section>
<section class="panel editor-section"><div class="step-heading"><span>03</span><div><h2>Condiciones de la subasta</h2><p>Establece el precio base y el período para recibir ofertas.</p></div></div><form id="auction-form"><fieldset id="auction-fields" disabled><div class="form-grid"><label>Precio base (Q)<input name="monto_base" inputmode="decimal" placeholder="20000.00" required></label><div class="field-note">Base mínima: Q 20,000.00.<br>Las pujas siguientes aumentan al menos un 10 %.</div><label>Fecha y hora de inicio<input type="datetime-local" name="fecha_inicio" step="1" required></label><label>Fecha y hora de cierre<input type="datetime-local" name="fecha_cierre" step="1" required></label></div><p class="field-help" id="timezone-note"></p><div class="notice notice-error form-error" role="alert" hidden></div><button class="button" type="submit" id="publish-button">Publicar subasta ↗</button></fieldset></form></section>
</div>
<aside class="panel editor-aside"><span class="eyebrow">LISTO PARA PUBLICAR</span><h3>Una buena ficha hace la diferencia.</h3><ul class="checklist"><li>Ficha técnica completa</li><li>Al menos cinco fotografías</li><li>Estado de daño correcto</li><li>Precio base y fechas definidos</li></ul><p>Podrás editar tu publicación antes de que comience la subasta. Después del inicio, las condiciones se mantienen para todos los postores.</p><a href="/mis-vehiculos" class="text-link">Guardar y continuar después →</a></aside>
</div>
@endsection
