<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Http\Requests;

use App\Models\ContactPerson;
use Illuminate\Http\Request;

class ContactPersonController extends Controller
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
            $contactperson = ContactPerson::where('date', 'LIKE', "%$keyword%")
                ->orWhere('name', 'LIKE', "%$keyword%")
                ->orWhere('surname', 'LIKE', "%$keyword%")
                ->orWhere('sex', 'LIKE', "%$keyword%")
                ->orWhere('sex_worker_ty', 'LIKE', "%$keyword%")
                ->orWhere('husband_wife_ty', 'LIKE', "%$keyword%")
                ->orWhere('regular_partner_ty', 'LIKE', "%$keyword%")
                ->orWhere('temporary_partner_ty', 'LIKE', "%$keyword%")
                ->orWhere('vagina_mt', 'LIKE', "%$keyword%")
                ->orWhere('mouth_mt', 'LIKE', "%$keyword%")
                ->orWhere('penis_mt', 'LIKE', "%$keyword%")
                ->orWhere('anus_mt', 'LIKE', "%$keyword%")
                ->orWhere('use_cd', 'LIKE', "%$keyword%")
                ->orWhere('unuse_cd', 'LIKE', "%$keyword%")
                ->orWhere('brea_slip_cd', 'LIKE', "%$keyword%")
                ->orWhere('unbrea_slip_cd', 'LIKE', "%$keyword%")
                ->latest()->paginate($perPage);
        } else {
            $contactperson = ContactPerson::latest()->paginate($perPage);
        }

        return view('backend.contact-person.index', compact('contactperson'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\View\View
     */
    public function create()
    {
        return view('backend.contact-person.create');
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
        
        ContactPerson::create($requestData);

        return redirect('backend/contact-person')->with('flash_message', 'ContactPerson added!');
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
        $contactperson = ContactPerson::findOrFail($id);

        return view('backend.contact-person.show', compact('contactperson'));
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
        $contactperson = ContactPerson::findOrFail($id);

        return view('backend.contact-person.edit', compact('contactperson'));
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
        
        $contactperson = ContactPerson::findOrFail($id);
        $contactperson->update($requestData);

        return redirect('backend/contact-person')->with('flash_message', 'ContactPerson updated!');
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
        ContactPerson::destroy($id);

        return redirect('backend/contact-person')->with('flash_message', 'ContactPerson deleted!');
    }
}
