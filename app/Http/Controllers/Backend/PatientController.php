<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Http\Requests;

use App\Models\Patient;
use Illuminate\Http\Request;

class PatientController extends Controller
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
            $patient = Patient::where('code', 'LIKE', "%$keyword%")
                ->orWhere('prefix', 'LIKE', "%$keyword%")
                ->orWhere('name', 'LIKE', "%$keyword%")
                ->orWhere('surname', 'LIKE', "%$keyword%")
                ->orWhere('sex', 'LIKE', "%$keyword%")
                ->orWhere('first_visit', 'LIKE', "%$keyword%")
                ->orWhere('id_card_number', 'LIKE', "%$keyword%")
                ->orWhere('birth_date', 'LIKE', "%$keyword%")
                ->orWhere('address_id', 'LIKE', "%$keyword%")
                ->orWhere('tel', 'LIKE', "%$keyword%")
                ->orWhere('nationality', 'LIKE', "%$keyword%")
                ->orWhere('education', 'LIKE', "%$keyword%")
                ->orWhere('marital_status', 'LIKE', "%$keyword%")
                ->orWhere('status', 'LIKE', "%$keyword%")
                ->orWhere('occupation', 'LIKE', "%$keyword%")
                ->orWhere('other_occupation', 'LIKE', "%$keyword%")
                ->orWhere('organization', 'LIKE', "%$keyword%")
                ->orWhere('congenital๘disease', 'LIKE', "%$keyword%")
                ->orWhere('drug_allergy', 'LIKE', "%$keyword%")
                ->orWhere('drug_allergy', 'LIKE', "%$keyword%")
                ->latest()->paginate($perPage);
        } else {
            $patient = Patient::latest()->paginate($perPage);
        }

        return view('backend.patient.index', compact('patient'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\View\View
     */
    public function create()
    {
        return view('backend.patient.create');
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
        
        Patient::create($requestData);

        return redirect('backend/patient')->with('flash_message', 'Patient added!');
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
        $patient = Patient::findOrFail($id);

        return view('backend.patient.show', compact('patient'));
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
        $patient = Patient::findOrFail($id);

        return view('backend.patient.edit', compact('patient'));
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
        
        $patient = Patient::findOrFail($id);
        $patient->update($requestData);

        return redirect('backend/patient')->with('flash_message', 'Patient updated!');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     *
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function destroy($id)
    {
        Patient::destroy($id);

        return redirect('backend/patient')->with('flash_message', 'Patient deleted!');
    }
}
