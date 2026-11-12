<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Calculadora de precios · PriceController</title>
    <style>
        :root { --bg: #f4f6fb; --card: #fff; --ink: #1c2333; --muted: #667085; --line: #e3e7ef; --blue: #2f5bea; --green: #128a54; --red: #c0392b; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: "Segoe UI", system-ui, sans-serif; background: var(--bg); color: var(--ink); }
        header { background: #1c2333; color: #fff; padding: 1.6rem 1rem; text-align: center; }
        header h1 { margin: 0 0 .3rem; font-size: 1.7rem; }
        header p { margin: 0; color: #c5cbe0; }
        main { max-width: 960px; margin: 2rem auto; padding: 0 1rem; display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem; }
        .card { background: var(--card); border: 1px solid var(--line); border-radius: 14px; padding: 1.5rem; box-shadow: 0 6px 20px -14px rgba(0,0,0,.35); }
        h2 { margin: 0 0 1rem; font-size: 1.2rem; }
        label { display: block; font-weight: 600; font-size: .9rem; margin: .8rem 0 .3rem; }
        input, select { width: 100%; padding: .6rem .7rem; border: 1px solid var(--line); border-radius: 8px; font: inherit; }
        .row { display: grid; grid-template-columns: 1fr auto 1fr; gap: .5rem; align-items: end; }
        .swap { padding: .55rem .7rem; border: 1px solid var(--line); background: #fff; border-radius: 8px; cursor: pointer; font-size: 1rem; }
        button.go { margin-top: 1.1rem; width: 100%; padding: .7rem; border: 0; border-radius: 8px; background: var(--blue); color: #fff; font-weight: 600; font-size: 1rem; cursor: pointer; }
        .result { margin-top: 1rem; padding: .9rem 1rem; border-radius: 10px; background: #eef2ff; min-height: 3rem; }
        .result b { font-size: 1.5rem; display: block; }
        .result.ok { background: #e7f6ee; color: var(--green); }
        .result.err { background: #fdecea; color: var(--red); }
        .note { color: var(--muted); font-size: .85rem; margin-top: .8rem; }
        footer { text-align: center; color: var(--muted); font-size: .85rem; padding: 1rem 0 2rem; }
        code { background: #eef0f6; padding: .1rem .35rem; border-radius: 4px; }
    </style>
</head>
<body>
<header>
    <h1>💶 Calculadora de precios ⇄ 💵</h1>
    <p>Conversión EUR ↔ USD y descuentos con <code style="background:#2b3550;color:#fff">PriceController</code> · 1 € = {{ number_format($tasa, 4, ',', '.') }} $</p>
</header>

<main>
    <section class="card">
        <h2>Conversión de monedas</h2>
        <form id="form-conversion">
            <label for="importe">Importe</label>
            <input type="number" id="importe" name="importe" min="0" step="0.01" value="100" required>
            <div class="row">
                <div>
                    <label for="de">De</label>
                    <select id="de" name="de"><option value="EUR">Euros (€)</option><option value="USD">Dólares ($)</option></select>
                </div>
                <button type="button" class="swap" id="swap" title="Intercambiar monedas">⇄</button>
                <div>
                    <label for="a">A</label>
                    <select id="a" name="a"><option value="USD">Dólares ($)</option><option value="EUR">Euros (€)</option></select>
                </div>
            </div>
            <button class="go" type="submit">Convertir</button>
        </form>
        <div class="result" id="res-conversion" aria-live="polite">El resultado aparecerá aquí.</div>
        <p class="note">Llama a <code>POST /precios/convertir</code> → <code>convertir()</code>.</p>
    </section>

    <section class="card">
        <h2>Calcular descuento</h2>
        <form id="form-descuento">
            <label for="precio">Precio original (€)</label>
            <input type="number" id="precio" name="precio" min="0" step="0.01" value="80" required>
            <label for="porcentaje">Descuento (%)</label>
            <input type="number" id="porcentaje" name="porcentaje" min="0" max="100" step="0.01" value="25" required>
            <button class="go" type="submit">Aplicar descuento</button>
        </form>
        <div class="result" id="res-descuento" aria-live="polite">El resultado aparecerá aquí.</div>
        <p class="note">Llama a <code>POST /precios/descuento</code> → <code>calcularDescuento()</code> e <code>importeDescuento()</code>.</p>
    </section>
</main>

<footer>Práctica de tests unitarios en Laravel · 2º ASIR</footer>

<script>
    const token = document.querySelector('meta[name="csrf-token"]').content;
    const fmt = (n, m) => new Intl.NumberFormat('es-ES', { style: 'currency', currency: m }).format(n);

    async function enviar(url, form, caja, pintar) {
        caja.className = 'result';
        caja.textContent = 'Calculando…';
        const r = await fetch(url, {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': token },
            body: new FormData(form),
        });
        const datos = await r.json();
        if (!r.ok) {
            caja.className = 'result err';
            caja.textContent = Object.values(datos.errors || {}).flat().join(' ') || 'Error en el cálculo.';
            return;
        }
        caja.className = 'result ok';
        caja.innerHTML = pintar(datos);
    }

    document.getElementById('form-conversion').addEventListener('submit', e => {
        e.preventDefault();
        enviar('{{ route('precios.convertir') }}', e.target, document.getElementById('res-conversion'),
            d => `<b>${fmt(d.resultado, d.a)}</b>${fmt(d.importe, d.de)} → ${d.a} (1 € = ${d.tasa_eur_usd} $)`);
    });

    document.getElementById('form-descuento').addEventListener('submit', e => {
        e.preventDefault();
        enviar('{{ route('precios.descuento') }}', e.target, document.getElementById('res-descuento'),
            d => `<b>${fmt(d.precio_final, 'EUR')}</b>Antes ${fmt(d.precio, 'EUR')} · Ahorras ${fmt(d.ahorro, 'EUR')} (${d.porcentaje} %)`);
    });

    document.getElementById('swap').addEventListener('click', () => {
        const de = document.getElementById('de'), a = document.getElementById('a');
        [de.value, a.value] = [a.value, de.value];
    });
</script>
</body>
</html>
