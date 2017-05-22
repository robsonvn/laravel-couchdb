<?php

namespace Robsonvn\CouchDB\Helpers;

class Arr
{
    public static function array_diff_recursive($arr1, $arr2, $keep_order = false)
    {
        $outputDiff = [];

        foreach ($arr1 as $key => $value) {
            //if the key exists in the second array, recursively call this function
          //if it is an array, otherwise check if the value is in arr2
          if (array_key_exists($key, $arr2)) {
              if (is_array($value)) {
                  $is_sequencial = (is_array($value) and array_keys($value) === range(0, count($value) - 1));

                  $recursiveDiff = self::array_diff_recursive($value, $arr2[$key]);

                  if (count($recursiveDiff)) {
                      //if is a sequencial array, reset array index
                      if ($is_sequencial && !$keep_order) {
                          $recursiveDiff = array_values($recursiveDiff);
                      }
                      $outputDiff[$key] = $recursiveDiff;
                  } else {
                      //if is a assoc array keep value as array even if empty
                    if (is_string($key)) {
                        $outputDiff[$key] = [];
                    }
                  }
              } elseif (!in_array($value, $arr2)) {
                  $outputDiff[$key] = $value;
              }
          }
          //if the key is not in the second array, check if the value is in
          //the second array (this is a quirk of how array_diff works)
          elseif (!in_array($value, $arr2)) {
              $outputDiff[$key] = $value;
          }
        }

        return $outputDiff;
    }
}
