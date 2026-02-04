<?php

namespace App\Http\Controllers;

use App\Models\Courier;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CourierController extends Controller
{
    public function index(Request $request)
    {
        $query = Courier::query();

        $search = trim((string) $request->query('search', ''));
        if ($search !== '') {
            $terms = preg_split('/\s+/', $search) ?: [];
            $query->where(function ($builder) use ($terms) {
                foreach ($terms as $term) {
                    $builder->where('name', 'like', '%'.$term.'%');
                }
            });
        }

        $levelsParam = $request->query('level');
        if ($levelsParam !== null) {
            $levels = array_filter(
                array_map(
                    static fn ($value) => (int) trim($value),
                    explode(',', (string) $levelsParam)
                ),
                static fn (int $level) => in_array($level, [2, 3], true)
            );

            if ($levels !== []) {
                $query->whereIn('level', $levels);
            }
        }

        $sort = $request->query('sort', 'name');

        if ($sort === 'registered_at') {
            $direction = strtolower((string) $request->query('direction', 'asc')) === 'desc' ? 'desc' : 'asc';
            $query->orderBy('registered_at', $direction);
        } else {
            $query->orderBy('name', 'asc');
        }

        $perPage = (int) $request->query('per_page', 15);
        $perPage = max(1, min($perPage, 100));

        $paginator = $query->paginate($perPage);

        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'count' => $paginator->count(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
                'total' => $paginator->total(),
                'total_pages' => $paginator->lastPage(),
            ],
        ]);
    }

    public function show(Courier $courier)
    {
        return $courier;
    }

    public function store(Request $request)
    {
        $data = $this->validateCourier($request);

        $courier = Courier::create($data);

        return response()->json($courier, 201);
    }

    public function update(Request $request, Courier $courier)
    {
        $data = $this->validateCourier($request, $courier, true);
        $courier->update($data);

        return $courier;
    }

    public function destroy(Courier $courier)
    {
        $courier->delete();

        return response()->noContent();
    }

    private function validateCourier(Request $request, ?Courier $courier = null, bool $partial = false): array
    {
        $phoneUnique = Rule::unique('couriers', 'phone');
        if ($courier) {
            $phoneUnique = $phoneUnique->ignore($courier->id);
        }

        $requiredRule = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'name' => [$requiredRule, 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32', $phoneUnique],
            'email' => ['nullable', 'email', 'max:255'],
            'level' => [$requiredRule, 'integer', 'between:1,5'],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
            'registered_at' => ['sometimes', 'date'],
        ]);
    }
}
