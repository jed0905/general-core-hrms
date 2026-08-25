<?php

namespace App\Http\Controllers\Web\SelfService;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SelfServiceDashboard extends Controller
{
    public function index(){
        return Inertia::render('app/SelfService/Dashboard');
    }
}
