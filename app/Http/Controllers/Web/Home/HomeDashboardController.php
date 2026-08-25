<?php

namespace App\Http\Controllers\Web\Home;

use App\Helpers\PermissionHelper;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

class HomeDashboardController extends Controller
{
    public function index()
    {

        return Inertia::render('app/Modules/Index', [
            'modules' => PermissionHelper::getPermissions(),
        ]);
    }
}
