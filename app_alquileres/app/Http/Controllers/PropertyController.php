<?php

namespace App\Http\Controllers;

use App\Models\Property;
use App\Models\PropertyPhoto;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class PropertyController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $lessor = $user?->lessor;

        $properties = collect();

        if ($lessor) {
            $properties = Property::with(['photos' => function ($query) {
                $query->orderBy('position');
            }])
                ->where('lessor_id', $lessor->id)
                ->get();
        }

        return view('admin.properties.index', [
            'locationData' => $this->locationData(),
            'properties' => $properties,
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
                'available' => 'bg-success',
                'occupied' => 'bg-primary',
                'disabled' => 'bg-secondary',
            ],
        ]);
    }

    public function register()
    {
        $user = Auth::user();
        $lessor = $user?->lessor;

        if (!$lessor) {
            return redirect()
                ->route('admin.properties.index')
                ->withErrors(['lessor' => 'Debe completar su perfil de arrendador antes de editar una propiedad.']);
        }

        return view('admin.properties.register', [
            'locationData' => $this->locationData(),
            'phone_contact' => $lessor->phone,
            'email_contact' => $user->email
        ]);
    }

    public function store(Request $request)
    {
        // 1) Validación (sin merge / sin filtrado manual)
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'service_type' => ['required', Rule::in(['home', 'lodging', 'event'])],
            'description' => ['nullable', 'string'],

            'location_province' => ['required', Rule::in(['Cartago', 'San José', 'Alajuela', 'Heredia', 'Limón', 'Puntarenas', 'Guanacaste'])],
            'location_canton' => ['required', 'string', 'max:255'],
            'location_district' => ['required', 'string', 'max:255'],
            'location_text' => ['required', 'string', 'max:255'],

            'rooms' => ['nullable', 'integer', 'min:0'],
            'living_rooms' => ['nullable', 'integer', 'min:0'],
            'kitchens' => ['nullable', 'integer', 'min:0'],
            'bathrooms' => ['nullable', 'integer', 'min:0'],
            'yards' => ['nullable', 'integer', 'min:0'],
            'garages_capacity' => ['nullable', 'integer', 'min:0'],

            'materials' => ['nullable', 'json'],
            'included_objects' => ['nullable', 'json'],
            'price' => ['required', 'numeric', 'min:0'],
            'price_mode' => ['required', Rule::in(['perHour', 'perDay', 'perMonth'])],
            'isSharedPhone' => ['required', 'boolean'],
            'isSharedEmail' => ['required', 'boolean'],

            'status' => ['required', Rule::in(['available', 'occupied', 'disabled'])],
            'is_public' => ['nullable', 'boolean'],

            // Photos: permitir filas vacías (se ignoran si no hay archivo)
            'photos' => ['nullable', 'array'],
            'photos.*.file' => ['nullable', 'image', 'max:5120'],
            'photos.*.position' => ['required_with:photos.*.file', 'integer', 'min:1'],
            'photos.*.caption' => ['required_with:photos.*.file', 'string', 'max:255'],
            'photos.*.taken_at' => ['nullable', 'date_format:Y-m-d\TH:i'],
        ]);

        $user = $request->user();
        $lessor = $user?->lessor;

        if (!$lessor) {
            return back()
                ->withErrors(['lessor' => 'Debe completar su perfil de arrendador antes de registrar una propiedad.'])
                ->withInput();
        }

        // 2) Decodificar JSONs
        $materials = json_decode($validated['materials'] ?? '[]', true);
        $includedObjects = json_decode($validated['included_objects'] ?? '[]', true);

        $materials = is_array($materials) ? $materials : [];
        $includedObjects = is_array($includedObjects) ? $includedObjects : [];


        // 3) Reindexar fotos para evitar huecos (por filas eliminadas en frontend)
        $photosInput = array_values($request->input('photos', []));

        return DB::transaction(function () use ($validated, $materials, $includedObjects, $lessor, $user, $request, $photosInput) {
            $isPublic = $validated['status'] === 'available' && $request->boolean('is_public');

            // 4) Crear propiedad
            $property = Property::create([
                'lessor_id' => $lessor->id,
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'location_text' => $validated['location_text'],
                'location_province' => $validated['location_province'],
                'location_canton' => $validated['location_canton'],
                'location_district' => $validated['location_district'],
                'service_type' => $validated['service_type'],

                'rooms' => $validated['rooms'] ?? 0,
                'living_rooms' => $validated['living_rooms'] ?? 0,
                'kitchens' => $validated['kitchens'] ?? 0,
                'bathrooms' => $validated['bathrooms'] ?? 0,
                'yards' => $validated['yards'] ?? 0,
                'garages_capacity' => $validated['garages_capacity'] ?? 0,

                'included_objects' => $includedObjects,
                'materials' => $materials,
                'price' => $validated['price'],
                'price_mode' => $validated['price_mode'],
                'isSharedPhone' => $request->boolean('isSharedPhone'),
                'isSharedEmail' => $request->boolean('isSharedEmail'),

                'status' => $validated['status'],
                'is_public' => $isPublic,
            ]);

            // 5) Guardar fotos (archivo + caption + taken_at del MISMO índice)
            foreach ($photosInput as $i => $photo) {

                if (!$request->hasFile("photos.$i.file")) {
                    continue; // ignora filas sin archivo
                }

                $file = $request->file("photos.$i.file");
                $path = $file->store('photos_properties', 'public');

                $takenAt = !empty($photo['taken_at'])
                    ? Carbon::createFromFormat('Y-m-d\TH:i', $photo['taken_at'])
                    : null;

                PropertyPhoto::create([
                    'property_id' => $property->id,
                    'path' => $path,
                    'position' => (int)($photo['position'] ?? ($i + 1)),
                    'caption' => (string)($photo['caption'] ?? ''),
                    'taken_at' => $takenAt,
                    'created_by_user_id' => $user?->id,
                ]);
            }

            return redirect()
                ->route('admin.properties.index')
                ->with('success', 'Propiedad registrada correctamente.');
        });
    }

    public function edit(Request $request, int $id_prop)
    {
        $user = $request->user();
        $lessor = $user?->lessor;

        if (!$lessor) {
            return redirect()
                ->route('admin.properties.index')
                ->withErrors(['lessor' => 'Debe completar su perfil de arrendador antes de editar una propiedad.']);
        }

        $property = Property::with(['photos' => function ($query) {
            $query->orderBy('position');
        }])
            ->where('lessor_id', $lessor->id)
            ->where('id', $id_prop)
            ->firstOrFail();

        return view('admin.properties.edit', [
            'locationData' => $this->locationData(),
            'property' => $property,
            'hasLockedAgreement' => $property->agreements()->whereIn('status', ['accepted', 'canceling'])->exists(),
            'phone_contact' => $lessor->phone,
            'email_contact' => $user->email
        ]);
    }

    public function update(int $id_prop, Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'service_type' => ['required', Rule::in(['home', 'lodging', 'event'])],
            'description' => ['nullable', 'string'],

            'location_province' => ['required', Rule::in(['Cartago', 'San José', 'Alajuela', 'Heredia', 'Limón', 'Puntarenas', 'Guanacaste'])],
            'location_canton' => ['required', 'string', 'max:255'],
            'location_district' => ['required', 'string', 'max:255'],
            'location_text' => ['required', 'string', 'max:255'],

            'rooms' => ['nullable', 'integer', 'min:0'],
            'living_rooms' => ['nullable', 'integer', 'min:0'],
            'kitchens' => ['nullable', 'integer', 'min:0'],
            'bathrooms' => ['nullable', 'integer', 'min:0'],
            'yards' => ['nullable', 'integer', 'min:0'],
            'garages_capacity' => ['nullable', 'integer', 'min:0'],

            'materials' => ['nullable', 'json'],
            'included_objects' => ['nullable', 'json'],
            'price' => ['required', 'numeric', 'min:0'],
            'price_mode' => ['required', Rule::in(['perHour', 'perDay', 'perMonth'])],
            'isSharedPhone' => ['required', 'boolean'],
            'isSharedEmail' => ['required', 'boolean'],

            'status' => ['required', Rule::in(['available', 'occupied', 'disabled'])],
            'is_public' => ['nullable', 'boolean'],

            'photos' => ['nullable', 'array'],
            'photos.*.id' => ['nullable', 'integer', 'exists:propertyphotos,id'],
            'photos.*.file' => ['nullable', 'image', 'max:5120', 'required_without:photos.*.id'],
            'photos.*.position' => ['required', 'integer', 'min:1'],
            'photos.*.caption' => ['required', 'string', 'max:255'],
            'photos.*.taken_at' => ['nullable', 'date_format:Y-m-d\TH:i'],
        ]);

        $user = $request->user();
        $lessor = $user?->lessor;

        if (!$lessor) {
            return redirect()
                ->route('admin.properties.index')
                ->withErrors(['lessor' => 'Debe completar su perfil de arrendador antes de editar una propiedad.']);
        }

        $property = Property::with('photos')
            ->where('lessor_id', $lessor->id)
            ->where('id', $id_prop)
            ->firstOrFail();

        $hasLockedAgreement = $property->agreements()
            ->whereIn('status', ['accepted', 'canceling'])
            ->exists();

        if ($hasLockedAgreement && $validated['status'] !== 'occupied') {
            return back()
                ->withErrors(['status' => 'Si la propiedad tiene un contrato activo o en cancelación, su estado debe ser "Ocupada".'])
                ->withInput();
        }

        $materials = json_decode($validated['materials'] ?? '[]', true);
        $includedObjects = json_decode($validated['included_objects'] ?? '[]', true);

        $materials = is_array($materials) ? $materials : [];
        $includedObjects = is_array($includedObjects) ? $includedObjects : [];
        $isPublic = $validated['status'] === 'available' && $request->boolean('is_public');

        $photosInput = array_values($request->input('photos', []));
        $existingPhotos = $property->photos->keyBy('id');

        return DB::transaction(function () use ($property, $validated, $materials, $includedObjects, $isPublic, $photosInput, $existingPhotos, $request, $user) {
            $property->update([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'location_text' => $validated['location_text'],
                'location_province' => $validated['location_province'],
                'location_canton' => $validated['location_canton'],
                'location_district' => $validated['location_district'],
                'service_type' => $validated['service_type'],

                'rooms' => $validated['rooms'] ?? 0,
                'living_rooms' => $validated['living_rooms'] ?? 0,
                'kitchens' => $validated['kitchens'] ?? 0,
                'bathrooms' => $validated['bathrooms'] ?? 0,
                'yards' => $validated['yards'] ?? 0,
                'garages_capacity' => $validated['garages_capacity'] ?? 0,

                'included_objects' => $includedObjects,
                'materials' => $materials,
                'price' => $validated['price'],
                'price_mode' => $validated['price_mode'],
                'isSharedPhone' => $request->boolean('isSharedPhone'),
                'isSharedEmail' => $request->boolean('isSharedEmail'),

                'status' => $hasLockedAgreement ? 'occupied' : $validated['status'],
                'is_public' => $isPublic,
            ]);

            $submittedIds = [];

            foreach ($photosInput as $i => $photo) {
                $photoId = $photo['id'] ?? null;
                $takenAt = !empty($photo['taken_at'])
                    ? Carbon::createFromFormat('Y-m-d\TH:i', $photo['taken_at'])
                    : null;

                if ($photoId) {
                    $existingPhoto = $existingPhotos->get($photoId);

                    if (!$existingPhoto) {
                        abort(403);
                    }

                    $updateData = [
                        'position' => (int)($photo['position'] ?? ($i + 1)),
                        'caption' => (string)($photo['caption'] ?? ''),
                        'taken_at' => $takenAt,
                    ];

                    if ($request->hasFile("photos.$i.file")) {
                        $file = $request->file("photos.$i.file");
                        $path = $file->store('photos_properties', 'public');
                        if ($existingPhoto->path) {
                            Storage::disk('public')->delete($existingPhoto->path);
                        }
                        $updateData['path'] = $path;
                        $updateData['created_by_user_id'] = $user?->id;
                    }

                    $existingPhoto->update($updateData);
                    $submittedIds[] = $photoId;
                } else {
                    if (!$request->hasFile("photos.$i.file")) {
                        continue;
                    }

                    $file = $request->file("photos.$i.file");
                    $path = $file->store('photos_properties', 'public');

                    PropertyPhoto::create([
                        'property_id' => $property->id,
                        'path' => $path,
                        'position' => (int)($photo['position'] ?? ($i + 1)),
                        'caption' => (string)($photo['caption'] ?? ''),
                        'taken_at' => $takenAt,
                        'created_by_user_id' => $user?->id,
                    ]);
                }
            }

            $photosToDelete = $existingPhotos->except($submittedIds);
            foreach ($photosToDelete as $photo) {
                if ($photo->path) {
                    Storage::disk('public')->delete($photo->path);
                }
                $photo->delete();
            }

            return redirect()
                ->route('admin.properties.index')
                ->with('success', 'Propiedad actualizada correctamente.');
        });
    }

    public function delete(int $id_prop, Request $request)
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'size:4'],
        ], [
            'token.required' => 'Debe ingresar el token de confirmación para eliminar la propiedad.',
            'token.size' => 'El token de confirmación debe tener 4 caracteres.',
        ]);

        $user = $request->user();
        $lessor = $user?->lessor;

        if (!$lessor) {
            return redirect()
                ->route('admin.properties.index')
                ->withErrors(['lessor' => 'Debe completar su perfil de arrendador antes de eliminar una propiedad.']);
        }

        $property = Property::with('photos')
            ->where('lessor_id', $lessor->id)
            ->where('id', $id_prop)
            ->firstOrFail();

        if ($property->status === 'occupied') {
            return back()->withErrors([
                'delete' => 'No se puede eliminar una propiedad ocupada.',
            ]);
        }

        // TODO: Reemplazar por token dinámico enviado al correo del arrendador.
        $expectedToken = '1234';
        if ($validated['token'] !== $expectedToken) {
            return back()
                ->withErrors(['token' => 'El token de confirmación es inválido.'])
                ->withInput();
        }

        return DB::transaction(function () use ($property) {
            foreach ($property->photos as $photo) {
                if ($photo->path) {
                    Storage::disk('public')->delete($photo->path);
                }
            }

            $property->photos()->delete();
            $property->delete();

            return redirect()
                ->route('admin.properties.index')
                ->with('success', 'Propiedad eliminada correctamente.');
        });
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

            'San José' => [
                'San José' => [
                    'Carmen','Merced','Hospital','Catedral','Zapote','San Francisco de Dos Ríos',
                    'Uruca','Mata Redonda','Pavas','Hatillo','San Sebastián'
                ],
                'Escazú' => ['Escazú','San Antonio','San Rafael'],
                'Desamparados' => [
                    'Desamparados','San Miguel','San Juan de Dios','San Rafael Arriba',
                    'San Antonio','Frailes','Patarrá','San Cristóbal','Rosario',
                    'Damas','San Rafael Abajo','Gravilias','Los Guido'
                ],
                'Puriscal' => [
                    'Santiago','Mercedes Sur','Barbacoas','Grifo Alto',
                    'San Rafael','Candelarita','Desamparaditos','San Antonio','Chires'
                ],
                'Tarrazú' => ['San Marcos','San Lorenzo','San Carlos'],
                'Aserrí' => [
                    'Aserrí','Tarbaca','Vuelta de Jorco','San Gabriel',
                    'Legua','Monterrey','Salitrillos'
                ],
                'Mora' => ['Colón','Guayabo','Tabarcia','Piedras Negras','Picagres','Jaris'],
                'Goicoechea' => [
                    'Guadalupe','San Francisco','Calle Blancos',
                    'Mata de Plátano','Ipís','Rancho Redondo','Purral'
                ],
                'Santa Ana' => ['Santa Ana','Salitral','Pozos','Uruca','Piedades','Brasil'],
                'Alajuelita' => [
                    'Alajuelita','San Josecito','San Antonio','Concepción','San Felipe'
                ],
                'Vázquez de Coronado' => [
                    'San Isidro','San Rafael','Dulce Nombre de Jesús',
                    'Patalillo','Cascajal'
                ],
                'Acosta' => ['San Ignacio','Guaitil','Palmichal','Cangrejal','Sabanillas'],
                'Tibás' => ['San Juan','Cinco Esquinas','Anselmo Llorente','León XIII','Colima'],
                'Moravia' => ['San Vicente','San Jerónimo','La Trinidad'],
                'Montes de Oca' => ['San Pedro','Sabanilla','Mercedes','San Rafael'],
                'Turrubares' => ['San Pablo','San Pedro','San Juan de Mata','San Luis','Carara'],
                'Dota' => ['Santa María','Jardín','Copey'],
                'Curridabat' => ['Curridabat','Granadilla','Sánchez','Tirrases'],
                'Pérez Zeledón' => [
                    'San Isidro de El General','El General','Daniel Flores','Rivas',
                    'San Pedro','Platanares','Pejibaye','Cajón','Barú','Río Nuevo',
                    'Páramo','La Amistad'
                ],
                'León Cortés Castro' => ['San Pablo','San Andrés','Llano Bonito','San Isidro','Santa Cruz','San Antonio']
            ],

            'Alajuela' => [
                'Alajuela' => [
                    'Alajuela','San José','Carrizal','San Antonio',
                    'Guácima','San Isidro','Sabanilla','San Rafael',
                    'Río Segundo','Desamparados','Turrúcares','Tambor',
                    'Garita','Sarapiquí'
                ],
                'San Ramón' => [
                    'San Ramón','Santiago','San Juan','Piedades Norte',
                    'Piedades Sur','San Rafael','San Isidro','Ángeles',
                    'Alfaro','Volio','Concepción','Zapotal','Peñas Blancas'
                ],
                'Grecia' => ['Grecia','San Isidro','San José','San Roque','Tacares','Puente de Piedra','Bolívar'],
                'San Mateo' => ['San Mateo','Desmonte','Jesús María','Labrador'],
                'Atenas' => ['Atenas','Jesús','Mercedes','San Isidro','Concepción','San José','Santa Eulalia','Escobal'],
                'Naranjo' => ['Naranjo','San Miguel','San José','Cirrí Sur','San Jerónimo','San Juan','El Rosario','Palmitos'],
                'Palmares' => ['Palmares','Zaragoza','Buenos Aires','Santiago','Candelaria','Esquipulas','La Granja'],
                'Poás' => ['San Pedro','San Juan','San Rafael','Carrillos','Sabana Redonda'],
                'Orotina' => ['Orotina','El Mastate','Hacienda Vieja','Coyolar','La Ceiba'],
                'San Carlos' => [
                    'Quesada','Florencia','Buenavista','Aguas Zarcas','Venecia','Pital',
                    'La Fortuna','La Tigra','La Palmera','Venado','Cutris',
                    'Monterrey','Pocosol'
                ],
                'Zarcero' => ['Zarcero','Laguna','Tapezco','Guadalupe','Palmira','Zapote','Brisas'],
                'Sarchí' => ['Sarchí Norte','Sarchí Sur','Toro Amarillo','San Pedro','Rodríguez'],
                'Upala' => [
                    'Upala','Aguas Claras','San José','Bijagua','Delicias',
                    'Dos Ríos','Yolillal','Canalete'
                ],
                'Los Chiles' => ['Los Chiles','Caño Negro','El Amparo','San Jorge'],
                'Guatuso' => ['San Rafael','Buenavista','Cote','Katira']
            ],

            'Heredia' => [
                'Heredia' => ['Heredia','Mercedes','San Francisco','Ulloa','Varablanca'],
                'Barva' => ['Barva','San Pedro','San Pablo','San Roque','Santa Lucía','San José de la Montaña'],
                'Santo Domingo' => [
                    'Santo Domingo','San Vicente','San Miguel',
                    'Paracito','Santo Tomás','Santa Rosa','Tures','Pará'
                ],
                'Santa Bárbara' => ['Santa Bárbara','San Pedro','San Juan','Jesús','Santo Domingo','Purabá'],
                'San Rafael' => ['San Rafael','San Josecito','Santiago','Ángeles','Concepción'],
                'San Isidro' => ['San Isidro','San José','Concepción','San Francisco'],
                'Belén' => ['San Antonio','La Ribera','La Asunción'],
                'Flores' => ['San Joaquín','Barrantes','Llorente'],
                'San Pablo' => ['San Pablo','Rincón de Sabanilla'],
                'Sarapiquí' => [
                    'Puerto Viejo','La Virgen','Horquetas',
                    'Llanuras del Gaspar','Cureña'
                ]
            ],

            'Guanacaste' => [
                'Liberia' => ['Liberia','Cañas Dulces','Mayorga','Nacascolo','Curubandé'],
                'Nicoya' => ['Nicoya','Mansión','San Antonio','Quebrada Honda','Sámara','Nosara','Belén de Nosarita'],
                'Santa Cruz' => ['Santa Cruz','Bolsón','Veintisiete de Abril','Tempate','Cartagena','Cuajiniquil','Diría','Cabo Velas','Tamarindo'],
                'Bagaces' => ['Bagaces','La Fortuna','Mogote','Río Naranjo'],
                'Carrillo' => ['Filadelfia','Palmira','Sardinal','Belén'],
                'Cañas' => ['Cañas','Palmira','San Miguel','Bebedero','Porozal'],
                'Abangares' => ['Las Juntas','Sierra','San Juan','Colorado'],
                'Tilarán' => ['Tilarán','Quebrada Grande','Tronadora','Santa Rosa','Líbano','Tierras Morenas','Arenal','Cabeceras'],
                'Nandayure' => ['Carmona','Santa Rita','Zapotal','San Pablo','Porvenir','Bejuco'],
                'La Cruz' => ['La Cruz','Santa Cecilia','La Garita','Santa Elena'],
                'Hojancha' => ['Hojancha','Monte Romo','Puerto Carrillo','Huacas','Matambú']
            ],

            'Puntarenas' => [
                'Puntarenas' => [
                    'Puntarenas','Pitahaya','Chomes','Lepanto','Paquera',
                    'Manzanillo','Guacimal','Barranca','Monte Verde',
                    'Isla del Coco','Cóbano','Chacarita','Chira','Acapulco','El Roble','Arancibia'
                ],
                'Esparza' => ['Espíritu Santo','San Juan Grande','Macacona','San Rafael','San Jerónimo'],
                'Buenos Aires' => [
                    'Buenos Aires','Volcán','Potrero Grande','Boruca',
                    'Pilas','Colinas','Chánguena','Biolley','Brunka'
                ],
                'Montes de Oro' => ['Miramar','La Unión','San Isidro'],
                'Osa' => ['Puerto Cortés','Palmar','Sierpe','Bahía Ballena','Piedras Blancas','Bahía Drake'],
                'Quepos' => ['Quepos','Savegre','Naranjito'],
                'Golfito' => ['Golfito','Puerto Jiménez','Guaycará','Pavón'],
                'Coto Brus' => ['San Vito','Sabalito','Agua Buena','Limoncito','Pittier','Gutiérrez Braun'],
                'Parrita' => ['Parrita'],
                'Corredores' => ['Corredor','La Cuesta','Paso Canoas','Laurel'],
                'Garabito' => ['Jacó','Tárcoles']
            ],

            'Limón' => [
                'Limón' => ['Limón','Valle La Estrella','Río Blanco','Matama'],
                'Pococí' => ['Guápiles','Jiménez','Rita','Roxana','Cariari','Colorado','La Colonia'],
                'Siquirres' => ['Siquirres','Pacuarito','Florida','Germania','El Cairo','Alegría'],
                'Talamanca' => ['Bratsi','Sixaola','Cahuita','Telire'],
                'Matina' => ['Matina','Batán','Carrandi'],
                'Guácimo' => ['Guácimo','Mercedes','Pocora','Río Jiménez','Duacarí']
            ]
        ];
    }
}
