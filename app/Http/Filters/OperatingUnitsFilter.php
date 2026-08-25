<?php

namespace App\Http\Filters;

use App\Contracts\Filters\Filterable;
use App\Models\OperatingUnit;

class OperatingUnitsFilter implements Filterable
{
  public static function get()
  {
    $direction = 'ASC';
    $sortBy = ['name'];

    $query = OperatingUnit::query();

    foreach($sortBy as $sort){
      $query = $query->orderBy($sort, $direction);
    }

    $query = $query->paginate(request('size', 10))->withQueryString();

    return $query;

  }
}