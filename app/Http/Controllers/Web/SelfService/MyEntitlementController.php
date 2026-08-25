<?php

namespace App\Http\Controllers\Web\SelfService;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MyEntitlementController extends Controller
{
    public function index(){
        // dd('Hello World');
        return Inertia::render('app/SelfService/MyLeaves/SelfServiceEntitlement');
    }
}
