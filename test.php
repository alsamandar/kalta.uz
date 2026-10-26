<?php

$input = "apple banana orange apple";

$words = [];
$word = '';
for ($i = 0; $i < strlen($input); $i++) {
    if ($input[$i] != ' ') {
        $word .= $input[$i];
    } else {
        $words[] = $word;
        $word = '';
    }
// $words[] = $word;
}
$words[] = $word;

// $wordCount = 0;
$wordCount = [];

foreach ($words as $word) {
    $wordCount[$word] = isset($wordCount[$word]) ? $wordCount[$word] + 1 : 1;
}

foreach ($wordCount as $word => $count) {
    echo "$word - $count\n";
}
