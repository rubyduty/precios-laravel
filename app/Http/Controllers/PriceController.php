<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * Calculadora de precios.
 *
 * Funciones de cálculo (las que prueban los tests unitarios):
 *   - eurosADolares()      €  → $
 *   - dolaresAEuros()      $  → €
 *   - convertir()          cualquier sentido (EUR ↔ USD)
 *   - calcularDescuento()  precio final tras aplicar un % de descuento
 *   - importeDescuento()   cuánto dinero se ahorra con ese %
 *
 * Acciones HTTP (usan las funciones de arriba):
 *   - index()              GET  /                  formulario de la calculadora
 *   - conversion()         POST /precios/convertir  (JSON)
 *   - descuento()          POST /precios/descuento  (JSON)
 */
class PriceController extends Controller
{
    public const EUR = 'EUR';

    public const USD = 'USD';

    /** Decimales con los que se redondean los resultados (céntimos). */
    public const DECIMALES = 2;

    /** Cuántos dólares vale 1 euro. */
    private float $tasa;

    /**
     * @param  float|null  $tasaEurUsd  Si no se indica, se usa config('precios.tasa_eur_usd').
     *                                  Los tests unitarios la pasan a mano para no depender de la configuración.
     */
    public function __construct(?float $tasaEurUsd = null)
    {
        $tasa = $tasaEurUsd ?? (float) config('precios.tasa_eur_usd', 1.08);

        if ($tasa <= 0) {
            throw new InvalidArgumentException('El tipo de cambio debe ser mayor que 0.');
        }

        $this->tasa = $tasa;
    }

    public function getTasa(): float
    {
        return $this->tasa;
    }

    // ── Conversión de monedas ───────────────────────────────────────────

    /** Convierte euros a dólares. Ej.: 100 € con tasa 1,08 → 108,00 $ */
    public function eurosADolares(float $euros): float
    {
        $this->comprobarNoNegativo($euros, 'importe');

        return $this->redondear($euros * $this->tasa);
    }

    /** Convierte dólares a euros. Ej.: 108 $ con tasa 1,08 → 100,00 € */
    public function dolaresAEuros(float $dolares): float
    {
        $this->comprobarNoNegativo($dolares, 'importe');

        return $this->redondear($dolares / $this->tasa);
    }

    /**
     * Convierte un importe entre EUR y USD en cualquier sentido.
     * Si origen y destino son iguales devuelve el mismo importe (redondeado).
     */
    public function convertir(float $importe, string $de, string $a): float
    {
        $de = strtoupper($de);
        $a = strtoupper($a);

        foreach ([$de, $a] as $moneda) {
            if (! in_array($moneda, [self::EUR, self::USD], true)) {
                throw new InvalidArgumentException("Moneda no soportada: {$moneda}. Usa EUR o USD.");
            }
        }

        return match (true) {
            $de === $a => $this->redondear($this->comprobarNoNegativo($importe, 'importe')),
            $de === self::EUR => $this->eurosADolares($importe),
            default => $this->dolaresAEuros($importe),
        };
    }

    // ── Descuentos ──────────────────────────────────────────────────────

    /** Precio final tras aplicar el descuento. Ej.: 80 € con un 25 % → 60,00 € */
    public function calcularDescuento(float $precio, float $porcentaje): float
    {
        return $this->redondear($precio - $this->importeDescuento($precio, $porcentaje));
    }

    /** Dinero que se ahorra. Ej.: 80 € con un 25 % → 20,00 € */
    public function importeDescuento(float $precio, float $porcentaje): float
    {
        $this->comprobarNoNegativo($precio, 'precio');

        if ($porcentaje < 0 || $porcentaje > 100) {
            throw new InvalidArgumentException('El porcentaje de descuento debe estar entre 0 y 100.');
        }

        return $this->redondear($precio * $porcentaje / 100);
    }

    // ── Acciones HTTP ───────────────────────────────────────────────────

    public function index(): View
    {
        return view('precios', ['tasa' => $this->tasa]);
    }

    /** POST /precios/convertir  { importe, de, a } */
    public function conversion(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'importe' => ['required', 'numeric', 'min:0'],
            'de' => ['required', 'in:EUR,USD'],
            'a' => ['required', 'in:EUR,USD'],
        ]);

        $importe = (float) $datos['importe'];

        return response()->json([
            'importe' => $this->redondear($importe),
            'de' => $datos['de'],
            'a' => $datos['a'],
            'tasa_eur_usd' => $this->tasa,
            'resultado' => $this->convertir($importe, $datos['de'], $datos['a']),
        ]);
    }

    /** POST /precios/descuento  { precio, porcentaje } */
    public function descuento(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'precio' => ['required', 'numeric', 'min:0'],
            'porcentaje' => ['required', 'numeric', 'between:0,100'],
        ]);

        $precio = (float) $datos['precio'];
        $porcentaje = (float) $datos['porcentaje'];

        return response()->json([
            'precio' => $this->redondear($precio),
            'porcentaje' => $porcentaje,
            'ahorro' => $this->importeDescuento($precio, $porcentaje),
            'precio_final' => $this->calcularDescuento($precio, $porcentaje),
        ]);
    }

    // ── Ayudantes ───────────────────────────────────────────────────────

    private function comprobarNoNegativo(float $valor, string $campo): float
    {
        if ($valor < 0) {
            throw new InvalidArgumentException("El {$campo} no puede ser negativo.");
        }

        return $valor;
    }

    private function redondear(float $valor): float
    {
        return round($valor, self::DECIMALES);
    }
}
