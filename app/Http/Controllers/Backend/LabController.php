<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Http\Requests;

use App\Models\Lab;
use Illuminate\Http\Request;

class LabController extends Controller
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
            $lab = Lab::where('patient_id', 'LIKE', "%$keyword%")
                ->orWhere('visit_id', 'LIKE', "%$keyword%")
                ->orWhere('report_by', 'LIKE', "%$keyword%")
                ->orWhere('approve_by', 'LIKE', "%$keyword%")
                ->orWhere('collected_date', 'LIKE', "%$keyword%")
                ->orWhere('report_date', 'LIKE', "%$keyword%")
                ->orWhere('LN', 'LIKE', "%$keyword%")
                ->latest()->paginate($perPage);
        } else {
            $lab = Lab::latest()->paginate($perPage);
        }

        return view('backend.lab.index', compact('lab'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\View\View
     */
    public function create()
    {
        return view('backend.lab.create');
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
        
        Lab::create($requestData);

        return redirect('backend/lab')->with('flash_message', 'Lab added!');
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
        $lab = Lab::findOrFail($id);

        return view('backend.lab.show', compact('lab'));
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
        $lab = Lab::findOrFail($id);

        return view('backend.lab.edit', compact('lab'));
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
        
        $lab = Lab::findOrFail($id);
        $lab->update($requestData);

        return redirect('backend/lab')->with('flash_message', 'Lab updated!');
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
        Lab::destroy($id);

        return redirect('backend/lab')->with('flash_message', 'Lab deleted!');
    }
}
