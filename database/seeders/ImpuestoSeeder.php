<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Impuesto;
use App\Models\Empresa;

class ImpuestoSeeder extends Seeder
{
    public function run(): void
    {
        $empresa = Empresa::first();

        if (!$empresa) {
            $this->command->error('❌ No hay empresa. Ejecuta EmpresaSeeder primero.');
            return;
        }

        $impuestos = [
            ['nombre' => 'IVA 16%', 'valor' => 16],
            ['nombre' => 'IVA 8%', 'valor' => 8],
            ['nombre' => 'Sin IVA', 'valor' => 0],
        ];

        foreach ($impuestos as $imp) {
            Impuesto::firstOrCreate(
                ['nombre' => $imp['nombre'], 'empresa_id' => $empresa->id],
                array_merge($imp, ['activo' => true])
            );
        }

        $this->command->info('✅ Impuestos creados.');
    }
}