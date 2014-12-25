
<link href="css/pdf.css" rel="stylesheet">

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
$sizes = array();
$processed = array();
foreach ($data as $row) {
    $sizes[] = $row['size'];
    $processed[$row['name']][$row['color']][$row['size']] = $row['quantity'];
}

$sizes = array_unique($sizes);


$html = '
    <div id="logo">
        <img src="img/logo.jpg">
    </div>
    <div id="date">' . date('d.m.Y H:i:s', time()) . '</div>
    <div id="summary">Количество товаров: ' . count($data) . ' шт.</div>';

$html .= '<br><table class="table">';

$html .= '<tr>';
$html .= '<td class="bold">Товар</td>';
foreach ($sizes as $size) {
    $html .= '<td>' . $size . '</td>';
}
$html .= '</tr>';

foreach ($processed as $name => $row) {
    $html .= '<tr>';
    $html .= '<td class="product-name" colspan="'.count($sizes).'">' . $name . '</td>';
    $html .= '</tr>';
    $colors = array_keys($row);
    foreach ($colors as $color) {
        $html .= '<tr>';
        $html .= '<td class="product-color">' . $color . '</td>';
        foreach ($sizes as $size) {
            $quantity = isset($processed[$name][$color][$size]) ? $processed[$name][$color][$size] : '0';
            $html .= '<td>' . $quantity . '</td>';
        }
        $html .= '</tr>';
    }
}

$html .= '</table>';

//echo $html;

require 'mpdf/mpdf.php';

$mpdf = new mPDF('','A4','','',8,8,8,8,0,0);

$css = file_get_contents('css/pdf.css');
$mpdf->WriteHTML($css, 1);

$mpdf->WriteHTML($html, 2);

$filename = $_FILES['csv']['name'] . '.pdf';
$mpdf->Output($filename, 'I');
?>