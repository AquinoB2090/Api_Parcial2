"""Prueba HTTPS con registros temporales; requiere el .env local de la misma base.

Uso: python scripts/smoke_deployed_api.py https://APP.azurewebsites.net --php C:/xampp/php/php.exe
No imprime tokens. Limpia únicamente las publicaciones creadas por esta ejecución.
"""
import argparse
import base64
import concurrent.futures
import datetime as dt
import json
from pathlib import Path
import subprocess
import time
import urllib.error
import urllib.request
from urllib.parse import urlsplit
import uuid

parser = argparse.ArgumentParser(description=__doc__)
parser.add_argument('base_url')
parser.add_argument('--php', default='php')
args = parser.parse_args()
base = args.base_url.rstrip('/')
assert base.startswith('https://')
root = Path(__file__).resolve().parent.parent
marker = 'api-smoke-' + uuid.uuid4().hex
tokens, vehicles, photos, auctions = [], [], {}, []


def request(method, path, data=None, token=None, expected=200, content_type='application/json'):
    headers = {'Accept': 'application/json', 'Content-Type': content_type}
    if token:
        headers['Authorization'] = 'Bearer ' + token
    body = data if isinstance(data, bytes) else (json.dumps(data).encode() if data is not None else None)
    req = urllib.request.Request(base + path, data=body, headers=headers, method=method)
    try:
        response = urllib.request.urlopen(req, timeout=45)
    except urllib.error.HTTPError as error:
        response = error
    with response:
        raw = response.read()
        if response.status != expected:
            # No incluir cuerpos que pudieran contener tokens o datos de la configuración.
            problem = json.loads(raw).get('error', {}) if 'json' in response.headers.get('Content-Type', '') else {}
            raise AssertionError(f'{method} {path}: HTTP {response.status}, esperado {expected}; {problem.get("codigo", "")} {problem.get("mensaje", "")}')
        return json.loads(raw) if raw and 'json' in response.headers.get('Content-Type', '') else raw


def fixture(mode, vehicle):
    result = subprocess.run([args.php, str(root / 'scripts/deployed_fixture.php'), mode, marker, str(vehicle)],
                            capture_output=True, text=True, cwd=root)
    if result.returncode:
        raise RuntimeError(f'No se pudo {mode} del registro temporal {vehicle}; revisar conexión local a la misma base.')
    return json.loads(result.stdout)


def stream(token, auction):
    req = urllib.request.Request(base + f'/api/subastas/{auction}/eventos', headers={
        'Accept': 'text/event-stream', 'Authorization': 'Bearer ' + token})
    events, payloads, name = [], [], ''
    with urllib.request.urlopen(req, timeout=35) as response:
        assert response.status == 200
        assert response.headers['Content-Type'].startswith('text/event-stream')
        for line in response:
            line = line.decode().strip()
            if line.startswith('event: '):
                name = line[7:]
                events.append(name)
            if line.startswith('data: '):
                payloads.append((name, json.loads(line[6:])))
    serialized = json.dumps(payloads)
    for forbidden in ['IdUsuario', 'IdGanador', 'correo', 'PasswordHash', '@subastas.test']:
        assert forbidden not in serialized, 'Identidad expuesta en SSE'
    return events, payloads


def pause_until(timestamp):
    remaining = timestamp - time.time()
    if remaining > 0:
        time.sleep(remaining)


try:
    for path in ['/up', '/api/prueba', '/api/vehiculos', '/api/subastas', '/api/catalogos', '/openapi.json']:
        request('GET', path)
    request('GET', '/api/auth/me', expected=401)
    for email, password in [('vendedor@subastas.test', 'Vendedor123!'), ('postor1@subastas.test', 'Postor123!'), ('postor2@subastas.test', 'Postor123!')]:
        data = request('POST', '/api/auth/login', {'correo': email, 'password': password})['data']
        tokens.append(data['token'])
        assert request('GET', '/api/auth/me', token=tokens[-1])['data']['correo'] == email
    print('HTTPS, inventario, autenticación y tres cuentas: OK', flush=True)

    # PNG mínimo, usado solo como archivo de prueba, nunca como fotografía de demostración.
    png = base64.b64decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=')
    boundary = uuid.uuid4().hex
    body = b''.join((f'--{boundary}\r\nContent-Disposition: form-data; name="fotos[]"; filename="test{i}.png"\r\nContent-Type: image/png\r\n\r\n'.encode() + png + b'\r\n') for i in range(5)) + f'--{boundary}--\r\n'.encode()
    for _ in range(2):
        vehicle = request('POST', '/api/vehiculos', {
            'anio': 2020, 'tipo_articulo': 'Prueba temporal', 'marca': 'Prueba API', 'modelo': marker,
            'motor': '2.0', 'transmision': 'Automatica', 'tipo_combustible': 'Gasolina',
            'tren_manejo': 'FWD', 'numero_cilindros': 4, 'estado_danio': 'Verde', 'descripcion': marker,
        }, token=tokens[0], expected=201)['data']['id']
        vehicles.append(vehicle)
        fixture('status', vehicle)  # Comprueba que la base local sea la de la API desplegada.
        photos[vehicle] = request('POST', f'/api/vehiculos/{vehicle}/fotos', body, tokens[0], 201, 'multipart/form-data; boundary=' + boundary)['data']
    start = time.time() + 40
    end = start + 65
    iso = lambda stamp: dt.datetime.fromtimestamp(stamp, dt.timezone.utc).isoformat()
    for vehicle in vehicles:
        auction = request('POST', '/api/subastas', {'id_vehiculo': vehicle, 'monto_base': '20000.00', 'fecha_inicio': iso(start), 'fecha_cierre': iso(end)}, tokens[0], 201)['data']['id']
        auctions.append(auction)
    photo_path = urlsplit(photos[vehicles[0]][0]['url']).path
    assert request('GET', photo_path).startswith(b'\x89PNG')
    print('Creación, cinco fotos por vehículo, lectura pública y programación: OK', flush=True)
    pause_until(start + 1)
    with concurrent.futures.ThreadPoolExecutor(max_workers=2) as executor:
        streams = [executor.submit(stream, token, auctions[0]) for token in tokens[1:]]
        pause_until(start + 1)
        request('POST', f'/api/subastas/{auctions[0]}/pujas', {'monto': '21000.00'}, tokens[1], 201)
        time.sleep(2)
        request('POST', f'/api/subastas/{auctions[0]}/pujas', {'monto': '22000.00'}, tokens[2], 409)
        request('POST', f'/api/subastas/{auctions[0]}/pujas', {'monto': '23100.00'}, tokens[2], 201)
        results = [future.result() for future in streams]
    assert all('PujaActualizada' in events for events, _ in results)
    assert any(payload.get('mi_estado') == 'superado' or payload.get('estado') == 'superado' for _, payload in results[0][1])
    assert any(payload.get('mi_estado') == 'ganando' or payload.get('estado') == 'ganando' for _, payload in results[1][1])
    print('SSE simultáneo, privacidad, incremento mínimo y estados individuales: OK', flush=True)
    # Durante este intervalo no se consulta la API ni se ejecuta synchronize desde este entorno.
    pause_until(end + 8)
    closed = fixture('status', vehicles[0])
    deserted = fixture('status', vehicles[1])
    assert closed['Estado'] == 'Finalizada' and closed['MontoFinal'] == '23100.00', closed
    assert deserted['Estado'] == 'Desierta', deserted
    won = request('GET', '/api/subastas/ganadas', token=tokens[2])
    assert any(s['id'] == auctions[0] for s in won['data'])
    notices = request('GET', '/api/notificaciones', token=tokens[2])
    assert any(n['id_subasta'] == auctions[0] for n in notices['data'])
    print('Cierre sin visitantes, ganador, subasta desierta y notificaciones: OK', flush=True)
finally:
    cleanup_errors = []
    for vehicle in vehicles:
        try:
            fixture('detach', vehicle)
            for photo in photos.get(vehicle, []):
                request('DELETE', f'/api/vehiculos/{vehicle}/fotos/{photo["id"]}', token=tokens[0], expected=204)
            fixture('purge', vehicle)
        except Exception as error:
            cleanup_errors.append(str(error))
    for token in tokens:
        try:
            request('POST', '/api/auth/logout', token=token, expected=204)
        except Exception as error:
            cleanup_errors.append(str(error))
    if cleanup_errors:
        raise RuntimeError('Revisar limpieza de ' + marker + ': ' + '; '.join(cleanup_errors))
    print('Registros, fotos y tokens temporales eliminados.', flush=True)
