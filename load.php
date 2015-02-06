<?php
if (!isset($_POST['convert'])) {
    throw new Exception('Access denied.');
}

if (!isset($_FILES['csv']) or !$_FILES['csv']['size']) {
    throw new Exception('File is empty or absent.');
}

if ($_FILES['csv']['type'] != 'text/csv') {
    throw new Exception('Invalid file format: only supported format csv.');
}

global $availableSizes;
$availableSizes = json_decode(file_get_contents('config/sizes.json'));
if ($availableSizes === null) {
    throw new Exception('Invalid sizes.json file.');
}
$availableSizes = (array)$availableSizes;

global $availableColors;
$availableColors = json_decode(file_get_contents('config/colors.json'));
if ($availableColors === null) {
    throw new Exception('Invalid colors.json file.');
}

global $availableTypes;
$availableTypes = json_decode(file_get_contents('config/types.json'));
if ($availableTypes === null) {
    throw new Exception('Invalid types.json file.');
}

global $skippedWordsInType;
$skippedWordsInType = json_decode(file_get_contents('config/skipped_words_in_type.json'));
if ($skippedWordsInType === null) {
    throw new Exception('Invalid skipped_words_in_type.json file.');
}
