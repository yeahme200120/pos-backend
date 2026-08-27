<!-- resources/views/tickets/venta.blade.php -->
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Ticket #{{ $venta->folio }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Courier New', monospace;
            font-size: 10px;
            width: 80mm;
            margin: 0 auto;
            padding: 5px;
        }
        .header { text-align: center; margin-bottom: 10px; }
        .empresa-nombre { font-size: 14px; font-weight: bold; }
        .empresa-direccion { font-size: 9px; }
        .separator { border-top: 1px dashed #000; margin: 5px 0; }
        .productos { width: 100%; }
        .productos td { padding: 2px 0; }
        .total { font-weight: bold; font-size: 12px; }
        .footer { text-align: center; margin-top: 10px; }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        @if($empresa->logo)
            <img src="{{ public_path('storage/' . $empresa->logo) }}" width="100" />
        @endif
        <div class="empresa-nombre">{{ $empresa->nombre }}</div>
        <div class="empresa-direccion">{{ $empresa->direccion }}</div>
        <div class="empresa-direccion">Tel: {{ $empresa->telefono }}</div>
        <div class="empresa-direccion">RFC: {{ $empresa->rfc }}</div>
    </div>

    <div class="separator"></div>

    <!-- Datos de la venta -->
    <div>
        <strong>TICKET #{{ $venta->folio }}</strong><br>
        Fecha: {{ $venta->fecha->format('d/m/Y H:i:s') }}<br>
        Vendedor: {{ $venta->usuario->name }}<br>
        Cliente: {{ $venta->cliente->nombre ?? 'Cliente genérico' }}
    </div>

    <div class="separator"></div>

    <!-- Productos -->
    <table class="productos">
        <thead>
            <tr>
                <th align="left">Cant</th>
                <th align="left">Producto</th>
                <th align="right">Precio</th>
                <th align="right">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @foreach($venta->detalles as $detalle)
            <tr>
                <td>{{ $detalle->cantidad }}</td>
                <td>{{ $detalle->producto->nombre }}</td>
                <td align="right">${{ number_format($detalle->precio_unitario, 2) }}</td>
                <td align="right">${{ number_format($detalle->subtotal, 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="separator"></div>

    <!-- Totales -->
    <div style="text-align: right;">
        <div>Subtotal: ${{ number_format($venta->subtotal, 2) }}</div>
        @if($venta->descuento > 0)
        <div>Descuento: -${{ number_format($venta->descuento, 2) }}</div>
        @endif
        @if($venta->impuesto > 0)
        <div>Impuesto {{ $venta->impuesto }}%: ${{ number_format(($venta->subtotal - $venta->descuento) * ($venta->impuesto / 100), 2) }}</div>
        @endif
        <div class="total">TOTAL: ${{ number_format($venta->total, 2) }}</div>
    </div>

    <div class="separator"></div>

    <!-- Pagos -->
    <div>
        <strong>Pagos:</strong><br>
        @foreach($venta->pagos as $pago)
        {{ $pago->forma_pago }}: ${{ number_format($pago->monto, 2) }}<br>
        @endforeach
    </div>

    <!-- QR -->
    @if($config->mostrar_qr ?? true)
    <div style="text-align: center; margin-top: 10px;">
        <img src="data:image/png;base64,{{ base64_encode(QrCode::format('png')->size(100)->generate($venta->uuid)) }}" />
    </div>
    @endif

    <div class="separator"></div>

    <!-- Footer -->
    <div class="footer">
        {{ $empresa->leyenda_ticket ?? '¡Gracias por su compra!' }}
        <br>
        {{ $config->pie_pagina ?? '' }}
        <br>
        <small>Ticket generado: {{ now()->format('d/m/Y H:i:s') }}</small>
    </div>
</body>
</html>