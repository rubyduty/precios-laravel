<?php

use App\Http\Controllers\PriceController;
use Illuminate\Support\Facades\Route;

// Calculadora (formulario)
Route::get('/', [PriceController::class, 'index'])->name('precios.index');

// API JSON que usa el formulario (también se puede probar con Postman / curl)
Route::post('/precios/convertir', [PriceController::class, 'conversion'])->name('precios.convertir');
Route::post('/precios/descuento', [PriceController::class, 'descuento'])->name('precios.descuento');
