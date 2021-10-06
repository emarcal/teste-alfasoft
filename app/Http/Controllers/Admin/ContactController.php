<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests;

use App\Models\Contact;
use Illuminate\Http\Request;

class ContactController extends Controller
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
            $contact = Contact::where('name', 'LIKE', "%$keyword%")->whereNull('deleted_at')
                ->orWhere('contact', 'LIKE', "%$keyword%")->whereNull('deleted_at')
                ->orWhere('email', 'LIKE', "%$keyword%")->whereNull('deleted_at')
                ->latest()->paginate($perPage);
        } else {
            $contact = Contact::whereNull('deleted_at')->paginate($perPage);
        }

        return view('admin.contact.index', compact('contact'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\View\View
     */
    public function create()
    {
        return view('admin.contact.create');
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
        $this->validate($request, [
			'name' => 'required|string|min:5',
			'contact' => 'required|numeric',
			'email' => 'required|email|unique:contacts'
		]);
        $requestData = $request->all();
        
        Contact::create($requestData);

        return redirect('admin/contact')->with('flash_message', 'Contact added!');
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
        $contact = Contact::findOrFail($id);

        return view('admin.contact.show', compact('contact'));
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
        $contact = Contact::findOrFail($id);

        return view('admin.contact.edit', compact('contact'));
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
        $this->validate($request, [
			'name' => 'required|string|min:5',
			'contact' => 'required|numeric',
			'email' => 'required|email'
		]);
        $requestData = $request->all();
        
        $contact = Contact::findOrFail($id);
        $contact->update($requestData);

        return redirect('admin/contact')->with('flash_message', 'Contact updated!');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     *
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function trash(Request $request)
    {
        $keyword = $request->get('search');
        $perPage = 25;

        if (!empty($keyword)) {
            $contact = Contact::where('name', 'LIKE', "%$keyword%")->whereNotNull('deleted_at')
                ->orWhere('contact', 'LIKE', "%$keyword%")->whereNotNull('deleted_at')
                ->orWhere('email', 'LIKE', "%$keyword%")->whereNotNull('deleted_at')
                ->latest()->paginate($perPage);
        } else {
            $contact = Contact::whereNotNull('deleted_at')->paginate($perPage);
        }

        return view('admin.contact.recovery', compact('contact'));
    }
    public function destroy($id)
    {
        $delete = Contact::where('id',$id)->first();
        $delete->deleted_at = NOW();
        $delete->save();
        //Contact::destroy($id);
        return redirect('admin/contact')->with('flash_message', 'Contact deleted!');
    }
    public function recovery($id)
    {
        $delete = Contact::where('id',$id)->first();
        $delete->deleted_at = NULL;
        $delete->save();
        //Contact::destroy($id);
        return redirect('admin/contact')->with('flash_message', 'Contact deleted!');
    }
}
