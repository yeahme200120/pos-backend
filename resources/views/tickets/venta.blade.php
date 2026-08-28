<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Ticket #{{ $venta->folio }}</title>
    <style>
        @page {
            margin: 0;
            padding: 0;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Courier New', 'Courier', monospace;
            font-size: {{ $config->tamano_fuente ?: 10 }}px;
            line-height: 1.3;
            margin: 0;
            padding: 0;
            background: #fff;
            color: #000;
        }

        /* ✅ CONTAINER PRINCIPAL - Controla el desbordamiento */
        .ticket-wrapper {
            width: {{ $anchoPapel }}pt;
            max-width: {{ $anchoPapel }}pt;
            margin: 0 auto;
            padding: 3px 4px;
            overflow: hidden;
            word-wrap: break-word;
            word-break: break-word;
        }

        .ticket {
            width: 100%;
            max-width: 100%;
            overflow: hidden;
        }

        /* ============================================
           HEADER
        ============================================ */
        .header {
            text-align: center;
            margin-bottom: 4px;
            padding: 0 2px;
            overflow: hidden;
        }

        .logo {
            max-width: 70px;
            max-height: 40px;
            margin-bottom: 2px;
            display: inline-block;
        }

        .empresa-nombre {
            font-size: {{ ($config->tamano_fuente ?: 10) + 2 }}px;
            font-weight: bold;
            text-transform: uppercase;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        .empresa-dato {
            font-size: {{ max(7, ($config->tamano_fuente ?: 10) - 2) }}px;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        .separator {
            border-top: 1px dashed #000;
            margin: 3px 0;
            width: 100%;
            overflow: hidden;
        }

        .separator-doble {
            border-top: 2px solid #000;
            margin: 3px 0;
            width: 100%;
            overflow: hidden;
        }

        /* ============================================
           INFO
        ============================================ */
        .info {
            font-size: {{ max(7, ($config->tamano_fuente ?: 10) - 1) }}px;
            width: 100%;
            overflow: hidden;
        }

        .info-line {
            display: flex;
            justify-content: space-between;
            padding: 1px 0;
            flex-wrap: wrap;
            gap: 2px;
        }

        .info-label {
            font-weight: bold;
        }

        /* ============================================
           TABLA DE PRODUCTOS
        ============================================ */
        .table-container {
            width: 100%;
            overflow: hidden;
            overflow-x: auto;
        }

        .productos {
            width: 100%;
            border-collapse: collapse;
            font-size: {{ max(7, ($config->tamano_fuente ?: 10) - 2) }}px;
            table-layout: fixed;
        }

        .productos th,
        .productos td {
            padding: 1px 2px;
            word-wrap: break-word;
            overflow-wrap: break-word;
            hyphens: auto;
        }

        /* ✅ Columnas con ancho fijo en porcentaje */
        .productos .col-cantidad {
            width: 10%;
            text-align: center;
        }
        .productos .col-producto {
            width: 44%;
            text-align: left;
        }
        .productos .col-precio {
            width: 20%;
            text-align: right;
        }
        .productos .col-subtotal {
            width: 26%;
            text-align: right;
        }

        .productos th {
            font-weight: bold;
            border-bottom: 1px solid #000;
        }

        .producto-nombre {
            font-size: {{ max(6, ($config->tamano_fuente ?: 10) - 3) }}px;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        /* ============================================
           TOTALES
        ============================================ */
        .totales {
            text-align: right;
            font-size: {{ max(7, ($config->tamano_fuente ?: 10) - 1) }}px;
            width: 100%;
            overflow: hidden;
        }

        .totales-line {
            display: flex;
            justify-content: space-between;
            padding: 1px 0;
            flex-wrap: wrap;
            gap: 2px;
        }

        .total {
            font-weight: bold;
            font-size: {{ ($config->tamano_fuente ?: 10) + 1 }}px;
        }

        /* ============================================
           PAGOS
        ============================================ */
        .pagos {
            font-size: {{ max(7, ($config->tamano_fuente ?: 10) - 1) }}px;
            width: 100%;
            overflow: hidden;
        }

        .pagos-line {
            display: flex;
            justify-content: space-between;
            padding: 1px 0;
            flex-wrap: wrap;
            gap: 2px;
        }

        /* ============================================
           QR
        ============================================ */
        .qr {
            text-align: center;
            margin: 4px 0;
            overflow: hidden;
        }

        .qr img {
            width: 70px;
            height: 70px;
            max-width: 100%;
        }

        /* ============================================
           FOOTER
        ============================================ */
        .footer {
            text-align: center;
            margin-top: 4px;
            font-size: {{ max(7, ($config->tamano_fuente ?: 10) - 2) }}px;
            width: 100%;
            overflow: hidden;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        .footer-principal {
            font-weight: bold;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        .fecha-generacion {
            font-size: 6px;
            color: #666;
            margin-top: 2px;
        }

        /* ============================================
           UTILITY
        ============================================ */
        .text-left { text-align: left; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .bold { font-weight: bold; }
        .mt-1 { margin-top: 2px; }
        .mb-1 { margin-bottom: 2px; }
        .clearfix { clear: both; }

        /* ============================================
           RESPONSIVE - Para pantallas muy pequeñas
        ============================================ */
        @media (max-width: 200px) {
            .productos .col-producto { width: 35%; }
            .productos .col-precio { width: 25%; }
            .productos .col-subtotal { width: 30%; }
            
            .productos td, 
            .productos th {
                font-size: 6px;
                padding: 1px 0.5px;
            }
            
            .logo { max-width: 50px; max-height: 30px; }
            .empresa-nombre { font-size: 10px; }
            .empresa-dato { font-size: 6px; }
        }

        @media (max-width: 150px) {
            .productos .col-producto { width: 30%; }
            .productos .col-precio { width: 28%; }
            .productos .col-subtotal { width: 32%; }
            
            .productos td, 
            .productos th {
                font-size: 5px;
                padding: 0.5px;
            }
        }

        /* ============================================
           IMPRESIÓN
        ============================================ */
        @media print {
            body { margin: 0; padding: 0; }
            .no-print { display: none; }
            .ticket-wrapper { margin: 0 auto; }
        }
    </style>
</head>
<body>

<!-- ✅ CONTAINER PRINCIPAL -->
<div class="ticket-wrapper">
    <div class="ticket">
        
        <!-- HEADER -->
        <div class="header">
            @if($config->mostrar_logo && isset($logoPath) && $logoPath && file_exists($logoPath))
                <img src="{{ $logoPath }}" class="logo" alt="Logo" />
            @endif

            @if(($camposVisibles['nombre_negocio'] ?? true))
                <div class="empresa-nombre">{{ $empresa->nombre }}</div>
            @endif

            @if(($camposVisibles['direccion'] ?? true) && $empresa->direccion)
                <div class="empresa-dato">{{ $empresa->direccion }}</div>
            @endif

            @if(($camposVisibles['telefono'] ?? true) && $empresa->telefono)
                <div class="empresa-dato">Tel: {{ $empresa->telefono }}</div>
            @endif

            @if($empresa->rfc)
                <div class="empresa-dato">RFC: {{ $empresa->rfc }}</div>
            @endif
        </div>

        <div class="separator"></div>

        <!-- INFO -->
        <div class="info">
            <div class="info-line">
                <span class="info-label">TICKET #{{ $venta->folio }}</span>
            </div>
            @if(($camposVisibles['fecha'] ?? true))
                <div class="info-line">
                    <span>Fecha: {{ optional($venta->fecha)->format('d/m/Y H:i:s') }}</span>
                </div>
            @endif
            <div class="info-line">
                <span>Vendedor: {{ $venta->usuario->name ?? 'N/A' }}</span>
            </div>
            <div class="info-line">
                <span>Cliente: {{ $venta->cliente->nombre ?? 'Cliente genérico' }}</span>
            </div>
        </div>

        <div class="separator"></div>

        <!-- PRODUCTOS -->
        @if(($camposVisibles['productos'] ?? true))
            <div class="table-container">
                <table class="productos">
                    <thead>
                        <tr>
                            <th class="col-cantidad">Cant</th>
                            <th class="col-producto">Producto</th>
                            <th class="col-precio">Precio</th>
                            <th class="col-subtotal">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($venta->detalles as $detalle)
                            <tr>
                                <td class="col-cantidad">{{ number_format($detalle->cantidad, 0) }}</td>
                                <td class="col-producto producto-nombre">{{ $detalle->producto->nombre ?? 'Producto' }}</td>
                                <td class="col-precio">${{ number_format($detalle->precio_unitario, 2) }}</td>
                                <td class="col-subtotal">${{ number_format($detalle->subtotal, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <div class="separator"></div>

        <!-- TOTALES -->
        @if(($camposVisibles['total'] ?? true))
            <div class="totales">
                <div class="totales-line">
                    <span>Subtotal:</span>
                    <span>${{ number_format($venta->subtotal, 2) }}</span>
                </div>
                @if($venta->descuento > 0)
                    <div class="totales-line">
                        <span>Descuento:</span>
                        <span>-${{ number_format($venta->descuento, 2) }}</span>
                    </div>
                @endif
                @if($venta->impuesto > 0)
                    @php
                        $baseImpuesto = $venta->subtotal - $venta->descuento;
                        $importeImpuesto = $baseImpuesto * ($venta->impuesto / 100);
                    @endphp
                    <div class="totales-line">
                        <span>Impuesto {{ $venta->impuesto }}%:</span>
                        <span>${{ number_format($importeImpuesto, 2) }}</span>
                    </div>
                @endif
                <div class="separator"></div>
                <div class="totales-line total">
                    <span>TOTAL:</span>
                    <span>${{ number_format($venta->total, 2) }}</span>
                </div>
            </div>
        @endif

        <div class="separator"></div>

        <!-- PAGOS -->
        <div class="pagos">
            <div class="bold">Pagos:</div>
            @forelse($venta->pagos as $pago)
                <div class="pagos-line">
                    <span>{{ $pago->forma_pago }}:</span>
                    <span>${{ number_format($pago->monto, 2) }}</span>
                </div>
                @if($pago->cambio > 0)
                    <div class="pagos-line" style="color: #666; font-size: 0.9em;">
                        <span>Cambio:</span>
                        <span>${{ number_format($pago->cambio, 2) }}</span>
                    </div>
                @endif
            @empty
                <div class="pagos-line">
                    <span>Sin pagos registrados</span>
                </div>
            @endforelse
        </div>

        <!-- QR -->
        @if($config->mostrar_qr)
            @php
                $qrContenido = $config->qr_contenido ?: $venta->uuid;
            @endphp
            <div class="qr">
                <img src="data:image/png;base64,{{ base64_encode(QrCode::format('png')->size(70)->margin(0)->generate($qrContenido)) }}" />
            </div>
        @endif

        <div class="separator"></div>

        <!-- FOOTER -->
        <div class="footer">
            @if($config->cabecera)
                <div class="footer-principal">{{ $config->cabecera }}</div>
            @endif
            @if($config->pie_pagina)
                <div>{{ $config->pie_pagina }}</div>
            @endif
            <div class="fecha-generacion">Ticket generado: {{ now()->format('d/m/Y H:i:s') }}</div>
        </div>

    </div>
</div>
<!-- ✅ FIN CONTAINER -->

</body>
</html>