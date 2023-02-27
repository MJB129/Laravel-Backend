<?php

namespace App\Common;

use App\Models\Setting;
use App\Models\Storage as StorageInfo;

class CommonUtils
{
  public static function getSetting()
  {
    $settings = Setting::all();
    return $settings[0];
  }

  public static function getDefaultStorage()
  {
    $settings = Setting::all();
    $setting = $settings[0];
    if (!$setting->storage_id) {
      return null;
    }
    $storage = StorageInfo::find($setting->storage_id);
    return $storage;
  }
}
