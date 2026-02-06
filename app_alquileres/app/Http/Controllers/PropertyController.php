<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PropertyController extends Controller
{
    public function index()
    {
        return view('admin.properties.index', [
            'locationData' => $this->locationData(),
        ]);
    }

    public function register()
    {
        return view('admin.properties.register', [
            'locationData' => $this->locationData(),
        ]);
    }

    private function locationData(): array
    {
        return [
            'Cartago' => [
                'Cartago' => [
                    'Oriental',
                    'Occidental',
                    'Carmen',
                    'San Nicolás',
                    'Aguacaliente o San Francisco',
                    'Guadalupe o Arenilla',
                    'Corralillo',
                    'Tierra Blanca',
                    'Dulce Nombre',
                    'Llano Grande',
                    'Quebradilla',
                ],
                'Paraíso' => [
                    'Paraíso',
                    'Santiago',
                    'Orosi',
                    'Cachí',
                    'Llanos de Santa Lucía',
                ],
                'La Unión' => [
                    'Tres Ríos',
                    'San Diego',
                    'San Juan',
                    'San Rafael',
                    'Concepción',
                    'Dulce Nombre',
                    'San Ramón',
                    'Río Azul',
                ],
                'Jiménez' => [
                    'Juan Viñas',
                    'Tucurrique',
                    'Pejibaye',
                ],
                'Turrialba' => [
                    'Turrialba',
                    'La Suiza',
                    'Peralta',
                    'Santa Cruz',
                    'Santa Teresita',
                    'Pavones',
                    'Tuis',
                    'Tayutic',
                    'Santa Rosa',
                    'Tres Equis',
                    'La Isabel',
                    'Chirripó',
                ],
                'Alvarado' => [
                    'Pacayas',
                    'Cervantes',
                    'Capellades',
                ],
                'Oreamuno' => [
                    'San Rafael',
                    'Cot',
                    'Potrero Cerrado',
                    'Cipreses',
                    'Santa Rosa',
                ],
                'El Guarco' => [
                    'El Tejar',
                    'San Isidro',
                    'Tobosi',
                    'Patio de Agua',
                ],
            ],
        ];
    }
}
