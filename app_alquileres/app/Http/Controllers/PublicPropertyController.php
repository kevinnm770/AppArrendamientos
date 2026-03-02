<?php

namespace App\Http\Controllers;

use App\Models\Property;
use Illuminate\Http\Request;

class PublicPropertyController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'min_monthly_price' => ['nullable', 'numeric', 'min:0'],
            'max_monthly_price' => ['nullable', 'numeric', 'min:0'],
            'service_type' => ['nullable', 'in:home,lodging,event'],
            'location_province' => ['nullable', 'string', 'max:255'],
            'location_canton' => ['nullable', 'string', 'max:255'],
            'location_district' => ['nullable', 'string', 'max:255'],
        ]);

        $monthlyPriceSql = "CASE
            WHEN price_mode = 'perHour' THEN price * 24 * 30
            WHEN price_mode = 'perDay' THEN price * 30
            ELSE price
        END";

        $baseQuery = Property::query()
            ->with(['photos' => function ($query) {
                $query->orderBy('position');
            }])
            ->select('properties.*')
            ->selectRaw("{$monthlyPriceSql} as monthly_price")
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

        if (isset($validated['min_monthly_price'])) {
            $baseQuery->whereRaw("{$monthlyPriceSql} >= ?", [$validated['min_monthly_price']]);
        }

        if (isset($validated['max_monthly_price'])) {
            $baseQuery->whereRaw("{$monthlyPriceSql} <= ?", [$validated['max_monthly_price']]);
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

        return view('public.properties.index', [
            'properties' => $properties,
            'provinceOptions' => $provinceOptions,
            'cantonOptions' => $cantonOptions,
            'districtOptions' => $districtOptions,
            'serviceTypeLabels' => [
                'home' => 'Hogar',
                'lodging' => 'Hospedaje',
                'event' => 'Evento',
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
