<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'pages.inventory', ['page' => 'inventory', 'title' => 'Encuentra tu próximo vehículo']);
Route::view('/login', 'pages.auth', ['page' => 'login', 'title' => 'Iniciar sesión']);
Route::view('/registro', 'pages.auth', ['page' => 'register', 'title' => 'Crear una cuenta']);
Route::view('/mis-vehiculos', 'pages.mine', ['page' => 'mine', 'title' => 'Mis vehículos']);
Route::view('/publicar', 'pages.editor', ['page' => 'editor', 'title' => 'Publicar un vehículo', 'vehicleId' => '']);
Route::get('/vehiculos/{id}/editar', fn (int $id) => view('pages.editor', ['page' => 'editor', 'title' => 'Editar vehículo', 'vehicleId' => $id]))->whereNumber('id');
Route::get('/vehiculos/{id}', fn (int $id) => view('pages.auction', ['page' => 'auction', 'title' => 'Detalle del vehículo', 'vehicleId' => $id]))->whereNumber('id');
Route::view('/mis-pujas', 'pages.bids', ['page' => 'bids', 'title' => 'Mis pujas']);
Route::view('/notificaciones', 'pages.notifications', ['page' => 'notifications', 'title' => 'Notificaciones']);
