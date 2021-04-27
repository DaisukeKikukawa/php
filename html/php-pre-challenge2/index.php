<?php
$array = explode(',', $_GET['array']);

// 修正はここから
for ($i = 0; $i < count($array); $i++) {
  for ($n = 1; $n < count($array); $n++) {
    if ($array[$n - 1] > $array[$n]) {
      $temp = $array[$n];
      $array[$n] = $array[$n - 1];
      $array[$n - 1] = $temp;
    }
  }
}
// 修正はここまで

echo "<pre>";
print_r($array);
echo "</pre>";
