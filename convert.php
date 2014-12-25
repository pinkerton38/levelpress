<?php
function writeLog($filename, $data)
{
    $f = fopen($filename, 'a+');
    if (!$f) {
        throw new Exception('Ошибка записи лог файла. Проверьте права доступа.');
    }

    fwrite($f, $data . PHP_EOL);
    fclose($f);
}

try {
    if (!isset($_POST['convert'])) {
        throw new Exception('Доступ запрещен.');
    }

    if (!isset($_FILES['csv']) or !$_FILES['csv']['size']) {
        throw new Exception('Нет файла.');
    }

    if ($_FILES['csv']['type'] != 'text/csv') {
        throw new Exception('Неверный формат файла: поддерживается только формат csv.');
    }


    $defaultType = 'T-Shirt';

    /** @var array $typesInName Типы, которые встречаются в названиях */
    $typesInName = array(
        'T-Shirt'
    );

    global $rules;
    $rules = array(
        'small' => array('s'),
        'medium' => array('m'),
        'large' => array('l'),
        'x-large' => array('xl', 'x-l', 'x large'),
        '2x-large' => array('2xl', '2x-l', '2x large'),
        '3x-large' => array('3xl', '3x-l', '3x large'),
        '4x-large' => array('4xl', '4x-l', '4x large'),
        '5x-large' => array('5xl', '5x-l', '5x large')
    );

    function processSize($size)
    {
        global $rules;

        $result = null;

        foreach ($rules as $reference => $applicants) {
            if ($size == $reference) {
                $result = $reference;
                break;
            }
            foreach ($applicants as $applicant) {
                if ($size == $applicant) {
                    $result = $reference;
                    break;
                }
            }
            if ($result) {
                break;
            }
        }

        return $result;
    }

    function cmpSizes($a, $b)
    {
        global $rules;

        if ($a == $b) {
            return 0;
        }

        foreach (array_keys($rules) as $pos => $reference) {
            if ($a == $reference) {
                $a = $pos;
                break;
            }
        }

        foreach (array_keys($rules) as $pos => $reference) {
            if ($b == $reference) {
                $b = $pos;
                break;
            }
        }

        return ($a < $b) ? -1 : 1;
    }

    $file = fopen($_FILES['csv']['tmp_name'], 'r');
    if (!$file) {
        throw new Exception('Не удалось открыть загруженный файл.');
    }

    $data = array();
    $i = 0;
    $passedRowCount = 0;
    while ($row = fgetcsv($file)) {
        if ($row[0] == 'product_title') {
            $passedRowCount++;
            continue;
        }

        $data[$i]['name'] = trim($row[0]);

        $complexColumn = explode('/', $row[1]);

        $size = processSize(strtolower(trim($complexColumn[0])));
        if (!$size) {
            $passedRowCount++;
            continue;
        }
        $data[$i]['size'] = $size;
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
    $productCount = count($data);

    $sizes = array();
    $processed = array();
    foreach ($data as $row) {
        $sizes[] = $row['size'];
        $processed[$row['name']][$row['color']][$row['size']] = $row['quantity'];
    }
    unset($data);

    $sizes = array_unique($sizes);
    usort($sizes, 'cmpSizes');

    $html = '
    <div id="logo">
        <img src="img/logo.jpg">
    </div>
    <div id="date">' . date('d.m.Y H:i:s', time()) . '</div>
    <div id="summary">Количество товаров: ' . $productCount . ' шт.</div>
    <div id="summary">Пропущено строк: ' . $passedRowCount . ' шт.</div>';

    $log = 'log-' . date('d-m-Y_H-m-s', time()) . '.txt';
    writeLog($log, 'Импортировано строк: ' . $productCount);
    writeLog($log, 'Записано в PDF: ' . $productCount);
    writeLog($log, 'Пропущено строк: ' . $passedRowCount);

    $html .= '<br><table class="table">';

    $html .= '<tr>';
    $html .= '<td class="bold">Товар</td>';
    foreach ($sizes as $size) {
        $html .= '<td>' . $size . '</td>';
    }
    $html .= '</tr>';

    foreach ($processed as $name => $row) {
        $html .= '<tr>';
        $html .= '<td class="product-name" colspan="' . count($sizes) . '">' . $name . '</td>';
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

    require 'mpdf/mpdf.php';

    $mpdf = new mPDF('', 'A4', '', '', 8, 8, 8, 8, 0, 0);

    $css = file_get_contents('css/pdf.css');
    $mpdf->WriteHTML($css, 1);

    $mpdf->WriteHTML($html, 2);

    $filename = $_FILES['csv']['name'] . '.pdf';
    $mpdf->Output($filename, 'I');
} catch (Exception $e) {
    session_start();
    $_SESSION['error'] = '<strong>Ошибка!</strong> ' . $e->getMessage();

    header('Location: ' . $_SERVER['HTTP_REFERER']);
}
?>