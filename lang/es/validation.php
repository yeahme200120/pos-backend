<?php

return [

    'required' => 'El campo :attribute es obligatorio.',
    'string' => 'El campo :attribute debe ser una cadena de texto.',
    'email' => 'El campo :attribute debe ser un correo electrónico válido.',
    'min' => [
        'string' => 'El campo :attribute debe tener al menos :min caracteres.',
    ],
    'max' => [
        'string' => 'El campo :attribute no puede tener más de :max caracteres.',
    ],
    'confirmed' => 'La confirmación de :attribute no coincide.',
    'nullable' => 'El campo :attribute puede ser nulo.',

    'attributes' => [

        'identificador' => 'número de cliente o correo electrónico',

        'password' => 'contraseña',

        'password_actual' => 'contraseña actual',

        'password_nueva' => 'nueva contraseña',

        'email' => 'correo electrónico',

        'token' => 'token',

        'name' => 'nombre',

        'telefono' => 'teléfono',
    ],
];
