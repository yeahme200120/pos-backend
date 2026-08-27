<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Empresa;

class EmpresaSeeder extends Seeder
{
    public function run(): void
    {
        Empresa::firstOrCreate(
            ['nombre' => 'Mi Empresa POS'],
            [
                'logo' => null,
                'colores' => json_encode(
                    [
                        "primary" => "#1E293B",
                        "secondary" => "#10B981",
                        "accent" => "#3B82F6",
                        "background" => "#F8FAFC",
                        "text" => "#0F172A"
                    ]
                ),
                'direccion' => 'Calle Falsa 123, Ciudad',
                'telefono' => '555-1234',
                'email_contacto' => 'admin@miempresa.com',
                'rfc' => 'ABCD123456XYZ',
                'activo' => true,
            ]
        );
    }
}
