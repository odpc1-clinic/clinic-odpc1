<?php

namespace Baraear\ThaiAddress\Controllers;

use Baraear\ThaiAddress\Models\District;
use Baraear\ThaiAddress\Models\Province;
use Baraear\ThaiAddress\Models\PostalCode;
use Baraear\ThaiAddress\Models\SubDistrict;
use Baraear\ThaiAddress\Resources\DistrictResource;
use Baraear\ThaiAddress\Resources\ProvinceResource;
use Baraear\ThaiAddress\Resources\PostalCodeResource;
use Baraear\ThaiAddress\Resources\SubDistrictResource;
use Baraear\ThaiAddress\Resources\ThaiAddressResource;
use Illuminate\Routing\Controller;
use Illuminate\Http\Request;

class ThaiAddressController extends Controller
{
    public function getAllSubDistricts()
    {
        return SubDistrictResource::collection(SubDistrict::all());
    }

    public function getSubDistrict($id)
    {
        return SubDistrictResource::collection(SubDistrict::findOrFail($id));
    }

    public function searchSubDistrict(Request $request)
    {

         
        $query = $request->input('search');
        $district = $request->input('district');

        $queryObj = SubDistrict::query();
        if($district){
            $queryObj = $queryObj->where('district_id', $district);
        }
        $queryObj = $queryObj->search($query, 'name');
        return SubDistrictResource::collection($queryObj->get());

        // return SubDistrictResource::collection(SubDistrict::query()->search($query, 'name')->get());
    }

    public function getAllDistricts()
    {
        return DistrictResource::collection(District::all());
    }

    public function getDistrict($id)
    {
        return DistrictResource::collection(District::findOrFail($id));
    }

    public function searchDistrict(Request $request)
    {
 
        $query = $request->input('search');
        $province = $request->input('province');

        $queryObj = District::query();
        if($province){
            $queryObj = $queryObj->where('province_id', $province);
        }
        $queryObj = $queryObj->search($query, 'name');
        return DistrictResource::collection($queryObj->get());

        // return DistrictResource::collection(District::query()->where('province_id',24)->search($query, 'name')->get());
    }

    public function getAllProvinces()
    {
        return ProvinceResource::collection(Province::all());
    }

    public function getProvince($id)
    {
        return ProvinceResource::collection(Province::findOrFail($id));
    }

    public function searchProvince(Request $request)
    {
        $query = $request->input('search');
        return ProvinceResource::collection(Province::query()->search($query, 'name')->get());
    }

    public function getAllPostalCodes()
    {
        return PostalCodeResource::collection(PostalCode::all());
    }

    public function getPostalCode($id)
    {
        return PostalCodeResource::collection(PostalCode::findOrFail($id));
    }
    public function getPostal(Request $request)
    {
        $sub_district_id = $request->input('sub_district_id');
        $district_id = $request->input('district_id');
        $province_id = $request->input('province_id');
        if($sub_district_id  && $district_id && $province_id ){
            return PostalCodeResource::collection(PostalCode::where("sub_district_id",$sub_district_id)->where("district_id",$district_id)->where("province_id",$province_id)->get() );
        }
        // return PostalCodeResource::collection(PostalCode::findOrFail($id));
    }

    public function searchPostalCode($query)
    {
        return PostalCodeResource::collection(PostalCode::query()->search($query, 'code')->get());
    }

    public function search($query)
    {
        return ThaiAddressResource::collection(
            PostalCode::query()
                ->whereHas('sub_district', function ($q) use ($query) {
                    $q->search($query, 'name');
                })
                ->orWhereHas('district', function ($q) use ($query) {
                    $q->search($query, 'name');
                })
                ->orWhereHas('province', function ($q) use ($query) {
                    $q->search($query, 'name');
                })
                ->orWhere('code', 'like', "%{$query}%")
                ->get()
        );
    }
}
