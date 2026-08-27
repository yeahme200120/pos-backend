<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            EmpresaSeeder::class,               // 1. Empresa (base)
            UnidadMedidaSeeder::class,          // 2. Unidades (necesarias para productos)
            CategoriaSeeder::class,
            UserSeeder::class,                  // 3. Usuarios (dependen de empresa)
            ProductoSeeder::class,              // 4. Productos (dependen de empresa y unidades)
            ClienteSeeder::class,               // 5. Clientes (dependen de empresa)
            FormaPagoSeeder::class,             // 6. Formas de pago (dependen de empresa)
            ImpuestoSeeder::class,              // 7. Impuestos (dependen de empresa)
            ConfiguracionTicketSeeder::class,   // 8. Configuración (depende de empresa)
        ]);
    }
}
