<?php

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$token = null;
$request = function (string $method, string $path, array $body = [], ?string $bearer = null) use ($app, $kernel) {
    if ($app->bound('auth')) {
        $app->make('auth')->forgetGuards();
    }
    $headers = ['HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json'];
    if ($bearer) {
        $headers['HTTP_AUTHORIZATION'] = 'Bearer '.$bearer;
    }
    $req = Request::create($path, $method, [], [], [], $headers, json_encode($body));
    $response = $kernel->handle($req);
    $kernel->terminate($req, $response);

    return [$response->getStatusCode(), json_decode($response->getContent(), true)];
};
try {
    foreach (['/api/prueba', '/api/vehiculos', '/api/subastas', '/api/catalogos'] as $path) {
        [$status] = $request('GET', $path);
        if ($status !== 200) {
            throw new RuntimeException('Falló '.$path.': '.$status);
        }
        echo $path.": 200\n";
    }
    [$status,$data] = $request('POST', '/api/auth/login', ['correo' => 'postor1@subastas.test', 'password' => 'Postor123!']);
    if ($status !== 200) {
        throw new RuntimeException('Falló login: '.$status);
    }
    $token = $data['data']['token'];
    [$status,$data] = $request('GET', '/api/auth/me', [], $token);
    if ($status !== 200 || $data['data']['correo'] !== 'postor1@subastas.test') {
        throw new RuntimeException('Falló perfil');
    }
    echo "Login y perfil contra Azure SQL: correctos.\n";
    [$status] = $request('POST', '/api/auth/logout', [], $token);
    if ($status !== 204) {
        throw new RuntimeException('Falló logout');
    }
    $token = null;
    echo "Token temporal revocado.\n";
} finally {
    if ($token) {
        $request('POST', '/api/auth/logout', [], $token);
    }
}
