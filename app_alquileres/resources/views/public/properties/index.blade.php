<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Propiedades públicas en Costa Rica | App Arrendamientos</title>
    <meta name="description" content="Encuentra propiedades públicas para hogar, hospedaje o eventos. Filtra por precio mensual equivalente y ubicación exacta en Costa Rica.">
    <meta name="robots" content="index,follow,max-image-preview:large">
    <link rel="canonical" href="{{ route('public.properties.index') }}">

    <meta property="og:type" content="website">
    <meta property="og:site_name" content="App Arrendamientos">
    <meta property="og:title" content="Propiedades públicas en Costa Rica">
    <meta property="og:description" content="Listado público de propiedades con filtros por ubicación, servicio y precio mensual equivalente.">
    <meta property="og:url" content="{{ route('public.properties.index') }}">
    <meta property="og:locale" content="es_CR">

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="Propiedades públicas en Costa Rica">
    <meta name="twitter:description" content="Explora propiedades públicas con filtros por ubicación, tipo de servicio y precio mensual equivalente.">

    <link rel="preconnect" href="https://cdnjs.cloudflare.com">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" integrity="sha512-Kc323vGBEqzTmouAECnVceyQqyqdsSiqLQISBL29aUW4U/M7pSPA/gEUZQqv1cwx4OnYxTxve5UMg5GT6L4JJg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <style>
        :root { color-scheme: light; }
        body { margin: 0; font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif; background: #e9edf3; color: #3d5f7c; }
        .container { width: min(1300px, 92vw); margin: 2rem auto; }
        .header { margin-bottom: 1.5rem; }
        .header h1 { margin: 0 0 .5rem 0; font-size: 2rem; }
        .header p { margin: 0; color: #546d84; }
        .filters { background: #fff; border-radius: 14px; padding: 1rem; margin-bottom: 1.2rem; box-shadow: 0 3px 15px rgba(48,75,103,.08); }
        .filters form { display: grid; gap: .8rem; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); align-items: end; }
        .input-group { display: flex; flex-direction: column; gap: .35rem; }
        label { font-size: .9rem; font-weight: 600; }
        input, select, button { border: 1px solid #cad6e2; border-radius: 10px; padding: .55rem .7rem; font-size: .95rem; }
        button { background: #2f5f8f; color: #fff; border: none; cursor: pointer; }
        .btn-clear { display: inline-block; text-align: center; text-decoration: none; background: #eef2f7; color: #315474; }

        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 1.2rem; }
        .card { background: #f5f7fa; border-radius: 14px; overflow: hidden; border: 1px solid #dae3ee; display: flex; flex-direction: column; }
        .card-header { padding: 1.2rem 1.2rem .6rem; }
        .card-title { margin: 0 0 .4rem; font-size: 2rem; line-height: 1.1; }
        .status { display: inline-block; color: #fff; font-weight: 600; border-radius: 6px; padding: .25rem .6rem; font-size: .95rem; }
        .status-available { background: #2f8f59; }
        .status-occupied { background: #3e5cc7; }
        .status-disabled { background: #7a7f87; }
        .photo-wrap { aspect-ratio: 16/10; background: #f0f2f5; }
        .photo-wrap img { width: 100%; height: 100%; object-fit: cover; }
        .card-body { padding: 1rem 1.2rem 1.2rem; }
        .meta { margin: .2rem 0; font-size: 1.1rem; }
        .features { margin-top: .9rem; display: grid; grid-template-columns: repeat(4, 1fr); row-gap: .6rem; column-gap: .4rem; font-size: 1.05rem; }
        .price { margin-top: .9rem; font-weight: 700; color: #234662; }
        .empty { background: #fff; border-radius: 12px; padding: 1.2rem; border: 1px solid #d4deea; }
        .pagination { margin-top: 1rem; }
        .pagination nav { display: flex; justify-content: center; }
        .pagination svg { width: 16px; }
    </style>
</head>
<body>
<main class="container">
    <header class="header">
        <h1>Propiedades públicas disponibles</h1>
        <p>Filtra por servicio, ubicación y precio mensual equivalente (incluye conversiones desde hora o día).</p>
    </header>

    <section class="filters" aria-label="Filtros de búsqueda">
        <form method="GET" action="{{ route('public.properties.index') }}">
            <div class="input-group">
                <label for="min_monthly_price">Precio mensual mínimo (₡)</label>
                <input type="number" id="min_monthly_price" name="min_monthly_price" min="0" step="0.01" value="{{ request('min_monthly_price') }}">
            </div>
            <div class="input-group">
                <label for="max_monthly_price">Precio mensual máximo (₡)</label>
                <input type="number" id="max_monthly_price" name="max_monthly_price" min="0" step="0.01" value="{{ request('max_monthly_price') }}">
            </div>
            <div class="input-group">
                <label for="location_province">Provincia</label>
                <select id="location_province" name="location_province">
                    <option value="">Todas</option>
                    @foreach($provinceOptions as $province)
                        <option value="{{ $province }}" @selected(request('location_province') === $province)>{{ $province }}</option>
                    @endforeach
                </select>
            </div>
            <div class="input-group">
                <label for="location_canton">Cantón</label>
                <select id="location_canton" name="location_canton">
                    <option value="">Todos</option>
                    @foreach($cantonOptions as $canton)
                        <option value="{{ $canton }}" @selected(request('location_canton') === $canton)>{{ $canton }}</option>
                    @endforeach
                </select>
            </div>
            <div class="input-group">
                <label for="location_district">Distrito</label>
                <select id="location_district" name="location_district">
                    <option value="">Todos</option>
                    @foreach($districtOptions as $district)
                        <option value="{{ $district }}" @selected(request('location_district') === $district)>{{ $district }}</option>
                    @endforeach
                </select>
            </div>
            <div class="input-group">
                <label for="service_type">Servicio</label>
                <select id="service_type" name="service_type">
                    <option value="">Todos</option>
                    @foreach($serviceTypeLabels as $value => $label)
                        <option value="{{ $value }}" @selected(request('service_type') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="input-group">
                <button type="submit">Aplicar filtros</button>
            </div>
            <div class="input-group">
                <a class="btn-clear" href="{{ route('public.properties.index') }}">Limpiar</a>
            </div>
        </form>
    </section>

    <section class="grid" aria-label="Listado de propiedades públicas">
        @forelse($properties as $property)
            @php
                $cover = $property->photos->first();
                $statusKey = $property->status;
                $statusLabel = $statusLabels[$statusKey] ?? $statusKey;
                $statusClass = $statusClasses[$statusKey] ?? 'status-disabled';
                $serviceLabel = $serviceTypeLabels[$property->service_type] ?? $property->service_type;
            @endphp
            <article class="card">
                <div class="card-header">
                    <h2 class="card-title">{{ $property->name }}</h2>
                    <span class="status {{ $statusClass }}">{{ $statusLabel }}</span>
                </div>
                <div class="photo-wrap">
                    <img src="{{ $cover ? asset('storage/' . $cover->path) : asset('storage/photos_properties/photoDefault_property.png') }}"
                         alt="{{ $cover?->caption ?: 'Imagen de ' . $property->name }}"
                         loading="lazy">
                </div>
                <div class="card-body">
                    <p class="meta"><i class="fa-solid fa-location-dot"></i> {{ $property->location_district }}, {{ $property->location_canton }}, {{ $property->location_province }}</p>
                    <p class="meta"><i class="fa-regular fa-house"></i> {{ $serviceLabel }}</p>
                    <div class="features">
                        <span><i class="fa-solid fa-bed" title="Habitaciones o camas"></i> {{ $property->rooms }}</span>
                        <span><i class="fa-solid fa-couch" title="Salas comunes"></i> {{ $property->living_rooms }}</span>
                        <span><i class="fa-solid fa-kitchen-set" title="Cocinas"></i> {{ $property->kitchens }}</span>
                        <span><i class="fa-solid fa-bath" title="Baños"></i> {{ $property->bathrooms }}</span>
                        <span><i class="fa-solid fa-car" title="Capacidad de vehículos"></i> {{ $property->garages_capacity }}</span>
                        <span><i class="fa-solid fa-jug-detergent" title="Objetos incluidos"></i> {{ count($property->included_objects ?? []) }}</span>
                        <span><i class="fa-solid fa-tree" title="Patios y/o zonas verdes"></i> {{ $property->yards }}</span>
                    </div>
                    <p class="price">Precio mensual equivalente: ₡{{ number_format($property->monthly_price, 2, '.', ',') }}</p>
                </div>
            </article>
        @empty
            <div class="empty">
                No se encontraron propiedades públicas con los filtros seleccionados.
            </div>
        @endforelse
    </section>

    <section class="pagination" aria-label="Paginación de resultados">
        {{ $properties->links() }}
    </section>
</main>

<script type="application/ld+json">
{!! json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'ItemList',
    'name' => 'Propiedades públicas en App Arrendamientos',
    'url' => route('public.properties.index'),
    'numberOfItems' => $properties->count(),
    'itemListElement' => $properties->values()->map(function ($property, $index) {
        return [
            '@type' => 'ListItem',
            'position' => $index + 1,
            'name' => $property->name,
            'description' => $property->description,
            'address' => [
                '@type' => 'PostalAddress',
                'addressRegion' => $property->location_province,
                'addressLocality' => $property->location_canton,
                'streetAddress' => $property->location_district,
                'addressCountry' => 'CR',
            ],
            'offers' => [
                '@type' => 'Offer',
                'priceCurrency' => 'CRC',
                'price' => number_format($property->monthly_price, 2, '.', ''),
                'availability' => $property->status === 'available'
                    ? 'https://schema.org/InStock'
                    : 'https://schema.org/LimitedAvailability',
            ],
        ];
    })->all(),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) !!}
</script>
</body>
</html>
