<?php
if (!isset($_POST['convert'])) {
    die('Error 403. Доступ запрещен.');
}

if (!isset($_FILES['csv'])) {
    die('Error 403. Доступ запрещен. Нет файла.');
}

/** @var array $complexColumn Составная колонка */
$complexColumn = array(
    0 => 'size',
    1 => 'type',
    2 => 'color',
);

$defaultType = 'T-Shirt';

/** @var array $typesInName Типы, которые встречаются в названиях */
$typesInName = array(
    'T-Shirt'
);

$file = fopen($_FILES['csv']['tmp_name'], 'r');
$data = array();
$i = 0;

while ($row = fgetcsv($file)) {
    if ($row[0] == 'product_title') {
        echo 'pass=' . $i;
        continue;
    }

    $data[$i]['name'] = trim($row[0]);

    $complexColumn = explode('/', $row[1]);
    $data[$i]['size'] = trim($complexColumn[0]);
    if (count($complexColumn) < 3) {
        $data[$i]['color'] = trim($complexColumn[1]);
        $find = false;
        foreach ($typesInName as $typeInName) {
            if (strpos($row[0], $typeInName) !== false) {
                $data[$i]['type'] = $typeInName;
                $find = true;
                break;
            }
        }
        if (!$find) {
            $data[$i]['type'] = $defaultType;
        }
    } else {
        $data[$i]['type'] = trim($complexColumn[1]);
        $data[$i]['color'] = trim($complexColumn[2]);
    }

    $data[$i]['quantity'] = trim($row[3]);
    $data[$i]['price'] = trim($row[4]);
    $i++;
}

require 'mpdf/mpdf.php';

$html = '<table>';
?>