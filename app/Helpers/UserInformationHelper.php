<?php

  namespace App\Helpers;

  class UserInformationHelper{
    public static function getUser(){
      $user = auth()->user();
      return $user;
    }

    public static function getInitials(){
      $user = auth()->user();
      if (!$user) {
        return null;
      }

      $personalInfo = $user->employee?->personalInformation;
      if (!$personalInfo) {
        return null;
      }

      $initials = $personalInfo->firstname[0].$personalInfo->lastname[0];

      return $initials;
    }

    public static function getFullName(){
      $user = auth()->user();
      if (!$user) {
        return null;
      }

      $personalInfo = $user->employee?->personalInformation;
      if (!$personalInfo) {
        return null;
      }

      $fullName = $personalInfo->firstname . ' ' . $personalInfo->lastname;

      return $fullName;
    }

    public static function getUserOperatingUnit(){
      $user = auth()->user();
      if (!$user) {
        return null;
      }

      $operatingUnit = $user->employee?->operatingUnit;
      if (!$operatingUnit) {
        return null;
      }

      return $operatingUnit;
    }
  }
