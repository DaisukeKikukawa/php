<?php
$array = explode(',', $_GET['array']);

$array_number = count($array);

// 修正はここから
for ($i = 0; $i < $array_number; $i++) {
  for ($j = 1; $j < $array_number; $j++) {
    if ($array[$j - 1] > $array[$j]) {
      $temp = $array[$j];
      $array[$j] = $array[$j - 1];
      $array[$j - 1] = $temp;
    }
  }
}
// 修正はここまで

echo "<pre>";
print_r($array);
echo "</pre>";
