<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Venta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Barryvdh\DomPDF\Facade\Pdf;

class ReportShareController extends Controller
{
    public function dailyShare(Request $request)
    {
        abort_unless(in_array($request->user()->rol, ['admin', 'superadmin'], true), 403);
        $data = $request->validate([
            'destinatario' => 'required|email:rfc,dns|max:255',
            'fecha' => 'nullable|date_format:Y-m-d',
            'formato' => 'nullable|in:pdf',
        ]);
        $fecha = $data['fecha'] ?? now()->toDateString();
        $ventas = Venta::where('empresa_id', $request->user()->empresa_id)->whereDate('fecha', $fecha)->where('estado', 'pagado')->get();
        $total = $ventas->sum('total');
        $html = '<h1>Reporte diario POS</h1><p>Fecha: ' . e($fecha) . '</p><p>Ventas: ' . $ventas->count() . '</p><p>Total: $' . number_format($total, 2) . '</p><table width="100%" border="1" cellspacing="0" cellpadding="5"><tr><th>Folio</th><th>Fecha</th><th>Total</th></tr>';
        foreach ($ventas as $venta) $html .= '<tr><td>' . e($venta->folio) . '</td><td>' . e($venta->fecha->format('Y-m-d H:i:s')) . '</td><td>$' . number_format($venta->total, 2) . '</td></tr>';
        $html .= '</table>';
        $pdf = Pdf::loadHTML($html)->output();
        Mail::raw("Reporte diario {$fecha}. Ventas: {$ventas->count()}. Total: " . number_format($total, 2), function ($mail) use ($data, $fecha, $pdf) {
            $mail->to($data['destinatario'])->subject("Reporte diario POS {$fecha}")
                ->attachData($pdf, "reporte-diario-{$fecha}.pdf", ['mime' => 'application/pdf']);
        });
        return response()->json(['message' => 'Reporte enviado por correo.', 'fecha' => $fecha]);
    }
}
