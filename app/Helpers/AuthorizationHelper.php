<?php

 namespace App\Helpers;

use Illuminate\Support\Facades\Auth;

 class AuthorizationHelper
 {
  public static function isAuthorized()
  {
   $user = Auth::user();
   if (!$user) {
    return false;
   }

   if ($user->employee->employeeDesignations()->whereHas('designation', function($query) {
    $query->whereIn('name', ['Chancellor', 'Executive Director', 'University President']);
   })->exists()) {
    return true;
   }

   return false; 
  } 
 }
