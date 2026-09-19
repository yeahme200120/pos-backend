<?php

namespace Database\Seeders;

use App\Models\Empresa;
use App\Models\FormaPago;
use Illuminate\Database\Seeder;

class FormaPagoSeeder extends Seeder
{
    public function run(): void
    {
        $empresa = Empresa::first();

        if (!$empresa) {
            $this->command->error('❌ No hay empresa. Ejecuta EmpresaSeeder primero.');
            return;
        }

        $formas = [
            'Efectivo',
            'Tarjeta Crédito',
            'Tarjeta Débito',
            'Transferencia',
        ];

        foreach ($formas as $nombre) {
            FormaPago::firstOrCreate(
                [
                    'nombre' => $nombre,
                    'empresa_id' => $empresa->id,
                ],
                [
                    'empresa_id' => $empresa->id,
                    'activo' => true,
                ]
            );
        }

        $this->command->info('✅ Formas de pago creadas.');
    }
}