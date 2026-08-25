<?php

namespace App\Contracts\Filters;

interface Filterable
{
  /**
     * Transforms model query based on request
     * 
     * @return mixed;
     */
    public static function get();
}