<?php

namespace App\Http\Controllers\Web\SelfService;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MyLeaveReportsController extends Controller
{
    public function index(){
        return Inertia::render('app/SelfService/MyLeaves/SelfServiceReports');
    }
}
