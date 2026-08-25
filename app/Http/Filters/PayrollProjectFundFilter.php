<?php

 namespace App\Http\Filters;

 use App\Contracts\Filters\Filterable;

 class PayrollProjectFundFilter implements Filterable 
 {
  public static function get()
  {
   $direction = ['ASC'];
  } 
 }