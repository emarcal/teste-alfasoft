<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Contact;

class HomeController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function welcome()
    {
        $perPage = 25;
        $contact = Contact::whereNull('deleted_at')->paginate($perPage);
        return view('welcome', compact('contact'));

    }

    public function index()
    {
        return view('home');
    }
}
