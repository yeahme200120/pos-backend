<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\FormaPago;
use App\Models\Empresa;

class FormaPagoSeeder extends Seeder
{
    public function run(): void
    {
        $empresa = Empresa::first();

        if (!$empresa) {
            $this->command->error('❌ No hay empresa. Ejecuta EmpresaSeeder primero.');
            return;
        }

        $formas = ['Efectivo', 'Tarjeta Crédito', 'Tarjeta Débito', 'Transferencia'];

        foreach ($formas as $nombre) {
            FormaPago::firstOrCreate(
                ['nombre' => $nombre, 'empresa_id' => $empresa->id],
                ['activo' => true]
            );
        }

        $this->command->info('✅ Formas de pago creadas.');
    }
}