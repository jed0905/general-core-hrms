<?php

namespace App\Http\Filters;

use App\Contracts\Filters\Filterable;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class UserFilter implements Filterable
{
    public static function get()
    {

        $direction = 'ASC';
        if (request('direction') && request('direction') === 'Descending') {
            $direction = 'DESC';
        }

        $user = Auth::user();
        $operating_unit_id_of_authenticated_user = optional($user->employee)->operating_unit_id;

        $filter_by_operating_unit = false;

        if (!$user->hasRole('superadmin')) {
            $filter_by_operating_unit = true;
        }


        $employeeSearch = request('employee');
        $roleSearch = request('role');
        $statusSearch = request('account_status');
        $operatingUnitSearch = request('operating_unit');

        $users = User::with('employee.personalInformation', 'roles')

        ->when($filter_by_operating_unit, function ($query) use ($operating_unit_id_of_authenticated_user) {
            $query->whereHas('employee', function ($q) use ($operating_unit_id_of_authenticated_user) {
                $q->where('operating_unit_id', $operating_unit_id_of_authenticated_user);
            });
        })

        // Exclude superadmin users if the authenticated user is not a superadmin
        ->when(!$user->hasRole('superadmin'), function ($query) {
            $query->whereDoesntHave('roles', function ($q) {
                $q->where('name', 'superadmin');
            });
        })

        ->when($employeeSearch, function ($query) use ($employeeSearch) {
            $query->whereHas('employee.personalInformation', function ($q) use ($employeeSearch) {
                $q->where(function ($subQuery) use ($employeeSearch) {
                    $subQuery
                        ->where('firstname', 'LIKE', '%'.$employeeSearch.'%')
                        ->orWhere('middlename', 'LIKE', '%'.$employeeSearch.'%')
                        ->orWhere('lastname', 'LIKE', '%'.$employeeSearch.'%')
                        ->orWhere(DB::raw("CONCAT(lastname, ', ', firstname)"), 'LIKE', '%'.$employeeSearch.'%')
                        ->orWhere(DB::raw("CONCAT(lastname, ' ', firstname)"), 'LIKE', '%'.$employeeSearch.'%')
                        ->orWhere(DB::raw("CONCAT(firstname, ' ', lastname)"), 'LIKE', '%'.$employeeSearch.'%');
                });
            });
        })

        ->when($roleSearch, function ($query) use ($roleSearch) {
            $query->whereHas('roles', function ($q) use ($roleSearch) {
                $q->where('id', $roleSearch);
            });
        })

        ->when($operatingUnitSearch, function ($query) use ($operatingUnitSearch) {
            $query->whereHas('employee', function ($q) use ($operatingUnitSearch) {
                $q->where('operating_unit_id', $operatingUnitSearch);
            });
        })

        ->when($statusSearch, function ($query) use ($statusSearch) {
            $query->where('status', $statusSearch);
        });

        $users = $users->orderBy('id', $direction);
        $users = $users->paginate(request('size', 10));

        return $users;
    }
}
