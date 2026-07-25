<?php

namespace Tests\Feature;

use App\Models\Lessor;
use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicPropertiesIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_only_lists_public_properties(): void
    {
        $lessor = $this->createLessor();

        Property::create($this->basePropertyData($lessor->id, [
            'name' => 'Propiedad pública',
            'is_public' => true,
        ]));

        Property::create($this->basePropertyData($lessor->id, [
            'name' => 'Propiedad privada',
            'is_public' => false,
        ]));

        $response = $this->get(route('public.properties.index'));

        $response->assertOk();
        $response->assertSee('Propiedad pública');
        $response->assertDontSee('Propiedad privada');
    }

    public function test_it_filters_by_price_range(): void
    {
        $lessor = $this->createLessor();

        Property::create($this->basePropertyData($lessor->id, [
            'name' => 'Económica',
            'price' => 100000,
            'is_public' => true,
        ]));

        Property::create($this->basePropertyData($lessor->id, [
            'name' => 'Costosa',
            'price' => 900000,
            'is_public' => true,
        ]));

        $response = $this->get(route('public.properties.index', [
            'max_price' => 500000,
        ]));

        $response->assertOk();
        $response->assertSee('Económica');
        $response->assertDontSee('Costosa');
    }

    public function test_it_filters_by_location_and_service_type(): void
    {
        $lessor = $this->createLessor();

        Property::create($this->basePropertyData($lessor->id, [
            'name' => 'Casa Cartago',
            'service_type' => 'home',
            'location_province' => 'Cartago',
            'location_canton' => 'Cartago',
            'location_district' => 'Oriental',
            'is_public' => true,
        ]));

        Property::create($this->basePropertyData($lessor->id, [
            'name' => 'Local San José',
            'service_type' => 'commercial',
            'location_province' => 'San José',
            'location_canton' => 'San José',
            'location_district' => 'Carmen',
            'is_public' => true,
        ]));

        $response = $this->get(route('public.properties.index', [
            'service_type' => 'home',
            'location_province' => 'Cartago',
            'location_canton' => 'Cartago',
            'location_district' => 'Oriental',
        ]));

        $response->assertOk();
        $response->assertSee('Casa Cartago');
        $response->assertDontSee('Local San José');
    }

    private function createLessor(): Lessor
    {
        $user = User::factory()->create();

        return Lessor::create([
            'user_id' => $user->id,
            'legal_name' => 'Lessor Test',
            'id_number' => 'ID-' . uniqid(),
            'phone' => '70000000',
            'address' => 'Dirección prueba',
        ]);
    }

    private function basePropertyData(int $lessorId, array $overrides = []): array
    {
        return array_merge([
            'lessor_id' => $lessorId,
            'name' => 'Propiedad demo',
            'description' => 'Descripción',
            'location_text' => 'Frente al parque',
            'location_province' => 'Cartago',
            'location_canton' => 'Cartago',
            'location_district' => 'Oriental',
            'service_type' => 'home',
            'rooms' => 1,
            'living_rooms' => 1,
            'kitchens' => 1,
            'bathrooms' => 1,
            'yards' => 1,
            'garages_capacity' => 1,
            'included_objects' => ['lavadora'],
            'materials' => ['concreto'],
            'price' => 100000,
            'isSharedPhone' => false,
            'isSharedEmail' => false,
            'status' => 'available',
            'is_public' => true,
        ], $overrides);
    }
}
