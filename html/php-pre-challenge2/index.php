<?php
$array = explode(',', $_GET['array']);

$array_length = count($array);

// 修正はここから
for ($i = 0; $i < $array_length-1; $i++) {
  for ($j = 1; $j < $array_length-$i; $j++) {
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
