<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Pruebas de las rutas que usan PriceController (petición HTTP completa).
 * El tipo de cambio se fija a 1,08 para que los resultados no dependan del .env.
 */
class PriceApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['precios.tasa_eur_usd' => 1.08]);
    }

    #[Test]
    public function la_calculadora_se_muestra(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Calculadora de precios')
            ->assertSee('1 € = 1,0800 $', false);
    }

    #[Test]
    public function convierte_euros_a_dolares_por_la_api(): void
    {
        $this->postJson('/precios/convertir', ['importe' => 100, 'de' => 'EUR', 'a' => 'USD'])
            ->assertOk()
            ->assertJson(['resultado' => 108, 'de' => 'EUR', 'a' => 'USD', 'tasa_eur_usd' => 1.08]);
    }

    #[Test]
    public function convierte_dolares_a_euros_por_la_api(): void
    {
        $this->postJson('/precios/convertir', ['importe' => 54, 'de' => 'USD', 'a' => 'EUR'])
            ->assertOk()
            ->assertJsonPath('resultado', 50);
    }

    #[Test]
    public function calcula_el_descuento_por_la_api(): void
    {
        $this->postJson('/precios/descuento', ['precio' => 80, 'porcentaje' => 25])
            ->assertOk()
            ->assertJson(['precio' => 80, 'ahorro' => 20, 'precio_final' => 60]);
    }

    #[Test]
    public function valida_los_datos_de_la_conversion(): void
    {
        $this->postJson('/precios/convertir', ['importe' => -3, 'de' => 'EUR', 'a' => 'GBP'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['importe', 'a']);

        $this->postJson('/precios/convertir', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['importe', 'de', 'a']);
    }

    #[Test]
    public function valida_los_datos_del_descuento(): void
    {
        $this->postJson('/precios/descuento', ['precio' => 'abc', 'porcentaje' => 150])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['precio', 'porcentaje']);
    }

    #[Test]
    public function los_errores_salen_en_espanol(): void
    {
        $this->postJson('/precios/descuento', ['precio' => 10, 'porcentaje' => 150])
            ->assertJsonPath('errors.porcentaje.0', 'El campo porcentaje debe estar entre 0 y 100.');
    }

    #[Test]
    public function el_tipo_de_cambio_sale_de_la_configuracion(): void
    {
        config(['precios.tasa_eur_usd' => 1.25]);

        $this->postJson('/precios/convertir', ['importe' => 100, 'de' => 'EUR', 'a' => 'USD'])
            ->assertJsonPath('resultado', 125);
    }
}
