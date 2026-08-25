<?php

namespace App\Helpers;

class LocatorHelper
{

	public static function getPublicIpAddress()
	{
		// return file_get_contents('https://ip.seeip.org');
		// return file_get_contents('http://ipecho.net/plain');
		return null;
	}

	public static function getLocalIpAddress()
	{
		return request()->ip();
	}

	public static function getUserAgent()
	{
		return request()->header('user-agent');
	}
}
