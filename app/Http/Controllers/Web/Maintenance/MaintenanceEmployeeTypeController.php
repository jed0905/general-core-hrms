<?php

namespace App\Http\Controllers\Web\Maintenance;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MaintenanceEmployeeTypeController extends Controller
{
    public function index(){
        return Inertia::render('app/Maintenance/EmployeeTypes/Index');
    }

    public function create(){
        return Inertia::render('app/Maintenance/EmployeeTypes/Create');
    }

    public function store(Request $request){

    }

    public function edit(){
        /* Get employee type by id */
        return Inertia::render('app/Maintenance/EmployeeTypes/Edit');
    }

    public function update(Request $request, String $id){

    }

    public function destroy(String $id){

    }
    
    
}
