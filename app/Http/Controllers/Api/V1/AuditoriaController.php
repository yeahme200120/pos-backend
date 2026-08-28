<?php
// app/Http/Controllers/Api/V1/AuditoriaController.php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\LogAuditoria;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class AuditoriaController extends Controller
{
    public function index(Request $request)
    {
        $empresaId = $request->user()->empresa_id;

        $query = LogAuditoria::where('empresa_id', $empresaId)
            ->with('usuario');

        // Filtros
        if ($request->usuario_id) {
            $query->where('usuario_id', $request->usuario_id);
        }

        if ($request->accion) {
            $query->where('accion', 'LIKE', "%{$request->accion}%");
        }

        if ($request->tabla) {
            $query->where('tabla', $request->tabla);
        }

        if ($request->fecha_desde) {
            $query->whereDate('created_at', '>=', $request->fecha_desde);
        }

        if ($request->fecha_hasta) {
            $query->whereDate('created_at', '<=', $request->fecha_hasta);
        }

        $logs = $query->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 50);

        // Resumen
        $resumen = [
            'total' => LogAuditoria::where('empresa_id', $empresaId)->count(),
            'hoy' => LogAuditoria::where('empresa_id', $empresaId)
                ->whereDate('created_at', now()->toDateString())
                ->count(),
            'acciones' => LogAuditoria::where('empresa_id', $empresaId)
                ->select('accion', DB::raw('count(*) as total'))
                ->groupBy('accion')
                ->get(),
        ];

        return response()->json([
            'data' => $logs,
            'resumen' => $resumen,
        ]);
    }

    public function show($id, Request $request)
    {
        $empresaId = $request->user()->empresa_id;

        $log = LogAuditoria::where('empresa_id', $empresaId)
            ->with('usuario')
            ->findOrFail($id);

        return response()->json($log);
    }

    public function exportar(Request $request)
    {
        $empresaId = $request->user()->empresa_id;

        $query = LogAuditoria::where('empresa_id', $empresaId)
            ->with('usuario');

        if ($request->fecha_desde) {
            $query->whereDate('created_at', '>=', $request->fecha_desde);
        }

        if ($request->fecha_hasta) {
            $query->whereDate('created_at', '<=', $request->fecha_hasta);
        }

        $logs = $query->orderBy('created_at', 'desc')->get();

        // Generar CSV
        $filename = 'auditoria_' . now()->format('Y-m-d_H-i-s') . '.csv';
        $path = storage_path('app/public/exports/' . $filename);

        if (!Storage::disk('public')->exists('exports')) {
            Storage::disk('public')->makeDirectory('exports');
        }

        $file = fopen($path, 'w');
        fputcsv($file, [
            'ID', 'Usuario', 'Acción', 'Tabla', 'Registro ID',
            'Datos Antes', 'Datos Después', 'IP', 'Fecha'
        ]);

        foreach ($logs as $log) {
            fputcsv($file, [
                $log->id,
                $log->usuario->name ?? 'N/A',
                $log->accion,
                $log->tabla,
                $log->registro_id,
                $log->datos_antes ? json_encode($log->datos_antes) : '',
                $log->datos_despues ? json_encode($log->datos_despues) : '',
                $log->ip ?? '',
                $log->created_at->format('d/m/Y H:i:s'),
            ]);
        }

        fclose($file);

        return response()->json([
            'message' => 'Exportación completada',
            'url' => asset('storage/exports/' . $filename),
            'filename' => $filename,
        ]);
    }
}