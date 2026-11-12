<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Baraear\ThaiAddress\Models\Province;
use Baraear\ThaiAddress\Models\District;
use Baraear\ThaiAddress\Models\SubDistrict;

class AddressLookupController extends Controller
{
    public function provinces(Request $req): JsonResponse
    {
        $q = trim((string)$req->query('q', ''));
        $items = Province::query()
            ->when($q !== '', fn($qer) => $qer->where('name', 'like', "%{$q}%"))
            ->orderBy('name')->limit(50)->get(['id', 'name']);

        return response()->json(['data' => $items]);
    }

    public function districts(Request $req): JsonResponse
    {
        $q = trim((string)$req->query('q', ''));
        $provinceId = $req->query('province');
        $items = District::query()
            ->when($provinceId, fn($qer) => $qer->where('province_id', $provinceId))
            ->when($q !== '', fn($qer) => $qer->where('name', 'like', "%{$q}%"))
            ->orderBy('name')->limit(50)->get(['id', 'name']);

        return response()->json(['data' => $items]);
    }

    public function subDistricts(Request $req): JsonResponse
    {
        $q = trim((string)$req->query('q', ''));
        $districtId = $req->query('district');
        $items = SubDistrict::query()
            ->when($districtId, fn($qer) => $qer->where('district_id', $districtId))
            ->when($q !== '', fn($qer) => $qer->where('name', 'like', "%{$q}%"))
            ->orderBy('name')->limit(50)->get(['id', 'name']);

        return response()->json(['data' => $items]);
    }

    public function postalCode($sub): JsonResponse
    {
        $sub = SubDistrict::query()->find($sub, ['id', 'code']);
        return response()->json(['data' => $sub ? ['code' => $sub->code] : null]);
    }
}
