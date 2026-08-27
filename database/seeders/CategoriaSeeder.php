<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Categoria;
use App\Models\Empresa;

class CategoriaSeeder extends Seeder
{
    public function run(): void
    {
        $empresa = Empresa::first();

        if (!$empresa) {
            $this->command->error('❌ Ejecuta EmpresaSeeder primero.');
            return;
        }

        $categorias = [
            ['nombre' => 'Bebidas', 'icono' => '☕', 'color' => '#3B82F6'],
            ['nombre' => 'Alimentos', 'icono' => '🍔', 'color' => '#10B981'],
            ['nombre' => 'Postres', 'icono' => '🍰', 'color' => '#EC4899'],
            ['nombre' => 'Snacks', 'icono' => '🍿', 'color' => '#F59E0B'],
            ['nombre' => 'Pollo', 'icono' => '🍗', 'color' => '#EF4444'],
            ['nombre' => 'Bebidas Frías', 'icono' => '🧊', 'color' => '#06B6D4'],
        ];

        foreach ($categorias as $cat) {
            Categoria::firstOrCreate(
                ['nombre' => $cat['nombre'], 'empresa_id' => $empresa->id],
                array_merge($cat, ['empresa_id' => $empresa->id, 'activo' => true])
            );
        }

        $this->command->info('✅ Categorías creadas.');
    }
}