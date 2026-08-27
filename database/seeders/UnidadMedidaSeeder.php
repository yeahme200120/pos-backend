<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\UnidadMedida;
use App\Models\Empresa;

class UnidadMedidaSeeder extends Seeder
{
    public function run(): void
    {
        $empresa = Empresa::first();

        if (!$empresa) {
            $this->command->error('❌ Ejecuta EmpresaSeeder primero.');
            return;
        }

        $unidades = [
            // Peso
            ['nombre' => 'Kilogramo', 'abreviatura' => 'kg', 'tipo' => 'peso', 'fraccionable' => true, 'factor_conversion' => 1],
            ['nombre' => 'Gramo', 'abreviatura' => 'g', 'tipo' => 'peso', 'fraccionable' => true, 'factor_conversion' => 0.001],
            ['nombre' => 'Libra', 'abreviatura' => 'lb', 'tipo' => 'peso', 'fraccionable' => true, 'factor_conversion' => 0.453592],
            // Volumen
            ['nombre' => 'Litro', 'abreviatura' => 'l', 'tipo' => 'volumen', 'fraccionable' => true, 'factor_conversion' => 1],
            ['nombre' => 'Mililitro', 'abreviatura' => 'ml', 'tipo' => 'volumen', 'fraccionable' => true, 'factor_conversion' => 0.001],
            // Unidades
            ['nombre' => 'Pieza', 'abreviatura' => 'pza', 'tipo' => 'unidad', 'fraccionable' => false, 'factor_conversion' => 1],
            ['nombre' => 'Docena', 'abreviatura' => 'doc', 'tipo' => 'unidad', 'fraccionable' => false, 'factor_conversion' => 12],
            ['nombre' => 'Pollo Entero', 'abreviatura' => 'ent', 'tipo' => 'unidad', 'fraccionable' => false, 'factor_conversion' => 1],
            ['nombre' => 'Medio Pollo', 'abreviatura' => 'med', 'tipo' => 'unidad', 'fraccionable' => false, 'factor_conversion' => 0.5],
        ];

        foreach ($unidades as $data) {
            UnidadMedida::firstOrCreate(
                ['nombre' => $data['nombre'], 'empresa_id' => $empresa->id],
                array_merge($data, ['empresa_id' => $empresa->id, 'activo' => true])
            );
        }

        $this->command->info('✅ Unidades de medida creadas.');
    }
}