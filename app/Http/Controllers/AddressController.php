<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests;

use App\Models\Address;
use App\Models\Visit;
use Illuminate\Http\Request;

class AddressController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\View\View
     */
    public function index(Request $request)
    {
        $keyword = $request->get('search');
        $perPage = 25;

        if (!empty($keyword)) {
            $address = Address::where('house_no', 'LIKE', "%$keyword%")
                ->orWhere('address', 'LIKE', "%$keyword%")
                ->orWhere('sub_district', 'LIKE', "%$keyword%")
                ->orWhere('district', 'LIKE', "%$keyword%")
                ->orWhere('province', 'LIKE', "%$keyword%")
                ->orWhere('zipcode', 'LIKE', "%$keyword%")
                ->latest()->paginate($perPage);
        } else {
            $address = Address::latest()->paginate($perPage);
        }

        return view('address.index', compact('address'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\View\View
     */
    public function create()
    {
        return view('address.create');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function store(Request $request)
    {
        
        $requestData = $request->all();
        
        Address::create($requestData);

        // return redirect('backend/address')->with('flash_message', 'Address added!');
        return redirect(route('patient.edit', $requestData['patient_id']))->with('flash_message', 'Address added!');
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     *
     * @return \Illuminate\View\View
     */
    public function show($id)
    {
        $address = Address::findOrFail($id);

        return view('address.show', compact('address'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     *
     * @return \Illuminate\View\View
     */
    public function edit($id)
    {
        $address = Address::findOrFail($id);

        return view('address.edit', compact('address'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param  int  $id
     *
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function update(Request $request, $id)
    {
        
        $requestData = $request->all();
        
        $address = Address::findOrFail($id);
        $address->update($requestData);

        return redirect(route('patient.edit', $address->patient_id))->with('flash_message', 'Address updated!');


        
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     *
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function destroy(Request $request,$id)
    {
        $requestData = $request->all();
        $address = Address::find($id);
        Address::destroy($id);

        // return redirect('backend/address')->with('flash_message', 'Address deleted!');
        return Visit::next(1,$address->patient_id);
    }


}
