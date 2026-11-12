<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Http\Requests;

use App\Models\LabBlood;
use Illuminate\Http\Request;

class LabBloodController extends Controller
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
            $labblood = LabBlood::where('patient_id', 'LIKE', "%$keyword%")
                ->orWhere('visit_id', 'LIKE', "%$keyword%")
                ->orWhere('report_by', 'LIKE', "%$keyword%")
                ->orWhere('approve_by', 'LIKE', "%$keyword%")
                ->orWhere('report_date', 'LIKE', "%$keyword%")
                ->orWhere('LN', 'LIKE', "%$keyword%")
                ->orWhere('hiv', 'LIKE', "%$keyword%")
                ->orWhere('syphilis', 'LIKE', "%$keyword%")
                ->orWhere('rpr', 'LIKE', "%$keyword%")
                ->orWhere('pcr_specimen', 'LIKE', "%$keyword%")
                ->orWhere('pcr_result', 'LIKE', "%$keyword%")
                ->latest()->paginate($perPage);
        } else {
            $labblood = LabBlood::latest()->paginate($perPage);
        }

        return view('backend.lab-blood.index', compact('labblood'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\View\View
     */
    public function create()
    {
        return view('backend.lab-blood.create');
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
        
        LabBlood::create($requestData);

        return redirect('backend/lab-blood')->with('flash_message', 'LabBlood added!');
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
        $labblood = LabBlood::findOrFail($id);

        return view('backend.lab-blood.show', compact('labblood'));
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
        $labblood = LabBlood::findOrFail($id);

        return view('backend.lab-blood.edit', compact('labblood'));
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
        
        $labblood = LabBlood::findOrFail($id);
        $labblood->update($requestData);

        return redirect('backend/lab-blood')->with('flash_message', 'LabBlood updated!');
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
        LabBlood::destroy($id);

        return redirect('backend/lab-blood')->with('flash_message', 'LabBlood deleted!');
    }
}
