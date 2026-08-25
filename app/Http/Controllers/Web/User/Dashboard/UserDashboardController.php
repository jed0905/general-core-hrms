<?php

namespace App\Http\Controllers\Web\User\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

class UserDashboardController extends Controller
{
    public function index(){
        return Inertia::render('app/User/Dashboard/Index');
    }
}
