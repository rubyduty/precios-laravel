<?php

namespace Tests\Unit;

use App\Http\Controllers\PriceController;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitarios de PriceController.
 *
 * Son unitarios "de verdad": no arrancan Laravel ni hacen peticiones HTTP,
 * solo crean el controlador con un tipo de cambio fijo (1 € = 1,08 $)
 * y comprueban el resultado de cada función.
 *
 * Generado con: php artisan make:test PriceControllerTest --unit
 * Ejecutar:     php artisan test --filter=PriceControllerTest
 */
class PriceControllerTest extends TestCase
{
    private const TASA = 1.08;

    private PriceController $precios;

    protected function setUp(): void
    {
        parent::setUp();
        $this->precios = new PriceController(self::TASA);
    }

    // ── Conversión EUR → USD ────────────────────────────────────────────

    #[Test]
    public function convierte_100_euros_a_dolares(): void
    {
        $this->assertSame(108.0, $this->precios->eurosADolares(100));
    }

    /** [euros, dólares esperados] */
    public static function eurosYDolares(): array
    {
        return [
            'cero' => [0, 0.0],
            'un euro' => [1, 1.08],
            'con céntimos' => [19.99, 21.59],    // 21,5892 → 21,59
            'importe grande' => [2500, 2700.0],
            'redondeo hacia arriba' => [0.05, 0.05], // 0,054 → 0,05
        ];
    }

    #[Test]
    #[DataProvider('eurosYDolares')]
    public function euros_a_dolares_con_varios_importes(float $euros, float $esperado): void
    {
        $this->assertSame($esperado, $this->precios->eurosADolares($euros));
    }

    // ── Conversión USD → EUR ────────────────────────────────────────────

    #[Test]
    public function convierte_108_dolares_a_100_euros(): void
    {
        $this->assertSame(100.0, $this->precios->dolaresAEuros(108));
    }

    /** [dólares, euros esperados] */
    public static function dolaresYEuros(): array
    {
        return [
            'cero' => [0, 0.0],
            'un dólar' => [1, 0.93],           // 0,9259… → 0,93
            'cincuenta dólares' => [50, 46.3], // 46,296… → 46,30
            'importe grande' => [5400, 5000.0],
        ];
    }

    #[Test]
    #[DataProvider('dolaresYEuros')]
    public function dolares_a_euros_con_varios_importes(float $dolares, float $esperado): void
    {
        $this->assertSame($esperado, $this->precios->dolaresAEuros($dolares));
    }

    #[Test]
    public function ida_y_vuelta_devuelve_el_importe_original(): void
    {
        $dolares = $this->precios->eurosADolares(250);

        $this->assertSame(250.0, $this->precios->dolaresAEuros($dolares));
    }

    #[Test]
    public function convertir_funciona_en_los_dos_sentidos_y_sin_distinguir_mayusculas(): void
    {
        $this->assertSame(108.0, $this->precios->convertir(100, 'EUR', 'USD'));
        $this->assertSame(100.0, $this->precios->convertir(108, 'usd', 'eur'));
        $this->assertSame(42.5, $this->precios->convertir(42.5, 'EUR', 'EUR'));
    }

    #[Test]
    public function el_tipo_de_cambio_se_puede_cambiar(): void
    {
        $otra = new PriceController(1.25);

        $this->assertSame(1.25, $otra->getTasa());
        $this->assertSame(125.0, $otra->eurosADolares(100));
        $this->assertSame(80.0, $otra->dolaresAEuros(100));
    }

    // ── Descuentos ──────────────────────────────────────────────────────

    #[Test]
    public function aplica_un_25_por_ciento_de_descuento(): void
    {
        $this->assertSame(60.0, $this->precios->calcularDescuento(80, 25));
        $this->assertSame(20.0, $this->precios->importeDescuento(80, 25));
    }

    /** [precio, % descuento, precio final esperado, ahorro esperado] */
    public static function descuentos(): array
    {
        return [
            'sin descuento' => [50, 0, 50.0, 0.0],
            'mitad de precio' => [50, 50, 25.0, 25.0],
            'gratis (100 %)' => [50, 100, 0.0, 50.0],
            '10 % de 19,99' => [19.99, 10, 17.99, 2.0],      // ahorro 1,999 → 2,00
            'porcentaje con decimales' => [200, 12.5, 175.0, 25.0],
            'precio cero' => [0, 30, 0.0, 0.0],
            'rebajas 70 %' => [89.95, 70, 26.98, 62.97],     // 62,965 → 62,97
        ];
    }

    #[Test]
    #[DataProvider('descuentos')]
    public function calcula_precio_final_y_ahorro(float $precio, float $porcentaje, float $final, float $ahorro): void
    {
        $this->assertSame($final, $this->precios->calcularDescuento($precio, $porcentaje));
        $this->assertSame($ahorro, $this->precios->importeDescuento($precio, $porcentaje));
    }

    #[Test]
    public function precio_final_mas_ahorro_es_el_precio_original(): void
    {
        foreach ([[120, 15], [9.99, 33], [1000, 7.5]] as [$precio, $porcentaje]) {
            $suma = $this->precios->calcularDescuento($precio, $porcentaje) + $this->precios->importeDescuento($precio, $porcentaje);
            $this->assertEqualsWithDelta($precio, $suma, 0.001);
        }
    }

    #[Test]
    public function descuento_en_euros_y_luego_conversion_a_dolares(): void
    {
        // 200 € con un 20 % → 160 € → 172,80 $
        $final = $this->precios->calcularDescuento(200, 20);

        $this->assertSame(172.8, $this->precios->eurosADolares($final));
    }

    // ── Errores ─────────────────────────────────────────────────────────

    #[Test]
    public function no_acepta_importes_negativos_al_convertir(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('no puede ser negativo');

        $this->precios->eurosADolares(-5);
    }

    #[Test]
    public function no_acepta_dolares_negativos(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->precios->dolaresAEuros(-0.01);
    }

    #[Test]
    public function no_acepta_monedas_desconocidas(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Moneda no soportada: GBP');

        $this->precios->convertir(10, 'EUR', 'GBP');
    }

    #[Test]
    public function no_acepta_un_tipo_de_cambio_cero_o_negativo(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new PriceController(0);
    }

    /** [porcentaje inválido] */
    public static function porcentajesInvalidos(): array
    {
        return ['negativo' => [-1], 'más de 100' => [100.01], 'muy alto' => [250]];
    }

    #[Test]
    #[DataProvider('porcentajesInvalidos')]
    public function no_acepta_porcentajes_fuera_de_0_a_100(float $porcentaje): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('entre 0 y 100');

        $this->precios->calcularDescuento(100, $porcentaje);
    }

    #[Test]
    public function no_acepta_precios_negativos_en_el_descuento(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->precios->calcularDescuento(-20, 10);
    }
}
