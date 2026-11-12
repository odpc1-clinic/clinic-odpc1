<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Http\Requests;

use App\Models\Vistit;
use Illuminate\Http\Request;

class VistitController extends Controller
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
            $vistit = Vistit::where('patient_id', 'LIKE', "%$keyword%")
                ->orWhere('know_from', 'LIKE', "%$keyword%")
                ->orWhere('send_from', 'LIKE', "%$keyword%")
                ->orWhere('other_from', 'LIKE', "%$keyword%")
                ->orWhere('reason_sti', 'LIKE', "%$keyword%")
                ->orWhere('reason_sti_other', 'LIKE', "%$keyword%")
                ->orWhere('reason_vct', 'LIKE', "%$keyword%")
                ->orWhere('\reason_vct_other', 'LIKE', "%$keyword%")
                ->orWhere('sti_hostory', 'LIKE', "%$keyword%")
                ->orWhere('contraceptive_method', 'LIKE', "%$keyword%")
                ->orWhere('LMP', 'LIKE', "%$keyword%")
                ->orWhere('symptom', 'LIKE', "%$keyword%")
                ->orWhere('diagnosis', 'LIKE', "%$keyword%")
                ->orWhere('\term_syphilis', 'LIKE', "%$keyword%")
                ->orWhere('disease_state', 'LIKE', "%$keyword%")
                ->orWhere('treatment', 'LIKE', "%$keyword%")
                ->orWhere('disease_state_other', 'LIKE', "%$keyword%")
                ->orWhere('consultation', 'LIKE', "%$keyword%")
                ->orWhere('hiv_sti_test', 'LIKE', "%$keyword%")
                ->orWhere('hiv_sti_test_date', 'LIKE', "%$keyword%")
                ->orWhere('hiv_sti_test_resule', 'LIKE', "%$keyword%")
                ->orWhere('\touch_tracing', 'LIKE', "%$keyword%")
                ->orWhere('touch_tracing_fail', 'LIKE', "%$keyword%")
                ->orWhere('provide_condom_site', 'LIKE', "%$keyword%")
                ->orWhere('provide_condom_quantity', 'LIKE', "%$keyword%")
                ->orWhere('provide_lubricant_quantity', 'LIKE', "%$keyword%")
                ->orWhere('appointment', 'LIKE', "%$keyword%")
                ->orWhere('appointment_reason', 'LIKE', "%$keyword%")
                ->latest()->paginate($perPage);
        } else {
            $vistit = Vistit::latest()->paginate($perPage);
        }

        return view('backend.vistit.index', compact('vistit'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\View\View
     */
    public function create()
    {
        return view('backend.vistit.create');
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
        
        Vistit::create($requestData);

        return redirect('backend/vistit')->with('flash_message', 'Vistit added!');
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
        $vistit = Vistit::findOrFail($id);

        return view('backend.vistit.show', compact('vistit'));
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
        $vistit = Vistit::findOrFail($id);

        return view('backend.vistit.edit', compact('vistit'));
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
        
        $vistit = Vistit::findOrFail($id);
        $vistit->update($requestData);

        return redirect('backend/vistit')->with('flash_message', 'Vistit updated!');
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
        Vistit::destroy($id);

        return redirect('backend/vistit')->with('flash_message', 'Vistit deleted!');
    }
}
