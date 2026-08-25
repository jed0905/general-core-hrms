<?php
 namespace App\Http\Filters;

use App\Contracts\Filters\Filterable;
use App\Models\UniversityActivity;
use Illuminate\Support\Facades\URL;

 class UniversityActivityFilter implements Filterable
 {
  public static function get()
  {
   $direction = 'DESC';
   if(request('direction') == 'Ascending'){
    $direction = 'ASC';
   }
   
   $query = UniversityActivity::query();
   $query->with('operatingUnits');
   $query->when(request('search'), function($query) {
    $query->where('title', 'like', '%' . request('search') . '%')
    ->orWhere('description', 'like', '%' . request('search') . '%')
    ->orWhere('document_control_number', 'like', '%' . request('search') . '%');
   });
   $query->when(request('type'), function($query) {
    $query->where('type', request('type'));
   });
   $query->when(request('start_at'), function($query) {
    $query->where('start_at', request('start_at'));
   });
   $query->when(request('end_at'), function($query) {
    $query->where('end_at', request('end_at'));
   });
   $query->when(request('status'), function($query) {
    $query->where('status', request('status'));
   });
  $query->when(request('operating_unit_ids'), function($query) {
   $query->whereHas('operatingUnits', function($query) {
     $query->whereIn('operating_units.id', request('operating_unit_ids'));
   });
  });

  $query->orderBy('id', $direction);

  return $query->paginate(request('size', 10))->appends(request()->query());
  }
 }