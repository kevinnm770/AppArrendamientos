<?php

namespace App\Http\Controllers;

use App\Models\Property;
use Illuminate\Http\Request;

class PublicPropertyController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'min_price' => ['nullable', 'numeric', 'min:0'],
            'max_price' => ['nullable', 'numeric', 'min:0'],
            'service_type' => ['nullable', 'in:home,commercial'],
            'location_province' => ['nullable', 'string', 'max:255'],
            'location_canton' => ['nullable', 'string', 'max:255'],
            'location_district' => ['nullable', 'string', 'max:255'],
        ]);

        $baseQuery = Property::query()
            ->with(['photos' => function ($query) {
                $query->orderBy('position');
            }])
            ->where('is_public', true);

        if (!empty($validated['service_type'])) {
            $baseQuery->where('service_type', $validated['service_type']);
        }

        if (!empty($validated['location_province'])) {
            $baseQuery->where('location_province', $validated['location_province']);
        }

        if (!empty($validated['location_canton'])) {
            $baseQuery->where('location_canton', $validated['location_canton']);
        }

        if (!empty($validated['location_district'])) {
            $baseQuery->where('location_district', $validated['location_district']);
        }

        if (isset($validated['min_price'])) {
            $baseQuery->where('price', '>=', $validated['min_price']);
        }

        if (isset($validated['max_price'])) {
            $baseQuery->where('price', '<=', $validated['max_price']);
        }

        $properties = $baseQuery
            ->orderByDesc('created_at')
            ->paginate(12)
            ->withQueryString();

        $locationOptions = Property::query()
            ->where('is_public', true)
            ->select('location_province', 'location_canton', 'location_district')
            ->distinct()
            ->orderBy('location_province')
            ->orderBy('location_canton')
            ->orderBy('location_district')
            ->get();

        $provinceOptions = $locationOptions->pluck('location_province')->unique()->values();

        $cantonOptions = $locationOptions
            ->when(!empty($validated['location_province']), function ($collection) use ($validated) {
                return $collection->where('location_province', $validated['location_province']);
            })
            ->pluck('location_canton')
            ->unique()
            ->values();

        $districtOptions = $locationOptions
            ->when(!empty($validated['location_province']), function ($collection) use ($validated) {
                return $collection->where('location_province', $validated['location_province']);
            })
            ->when(!empty($validated['location_canton']), function ($collection) use ($validated) {
                return $collection->where('location_canton', $validated['location_canton']);
            })
            ->pluck('location_district')
            ->unique()
            ->values();

        return view('public.rentals.index', [
            'properties' => $properties,
            'provinceOptions' => $provinceOptions,
            'cantonOptions' => $cantonOptions,
            'districtOptions' => $districtOptions,
            'serviceTypeLabels' => [
                'home' => 'Hogar',
                'commercial' => 'Comercial',
            ],
            'statusLabels' => [
                'available' => 'Disponible',
                'occupied' => 'Ocupada',
                'disabled' => 'Deshabilitada',
            ],
            'statusClasses' => [
                'available' => 'status-available',
                'occupied' => 'status-occupied',
                'disabled' => 'status-disabled',
            ],
        ]);
    }
}
