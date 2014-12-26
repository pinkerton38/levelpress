<?php
function writeLog($filename, $data)
{
    $f = fopen($filename, 'a+');
    if (!$f) {
        throw new Exception('Error writing to the log file. Check the permissions.');
    }

    fwrite($f, $data . PHP_EOL);
    fclose($f);
}

try {
    if (!isset($_POST['convert'])) {
        throw new Exception('Access denied.');
    }

    if (!isset($_FILES['csv']) or !$_FILES['csv']['size']) {
        throw new Exception('File is empty or absent.');
    }

    if ($_FILES['csv']['type'] != 'text/csv') {
        throw new Exception('Invalid file format: only supported format csv.');
    }


    $defaultType = 'T-Shirt';

    /** @var array $typesInName Типы, которые встречаются в названиях */
    $typesInName = array(
        'T-Shirt'
    );

    global $rules;
    $rules = array(
        's' => array('small'),
        'm' => array('medium'),
        'l' => array('large'),
        'xl' => array('x-large', 'x-l', 'x large'),
        '2xl' => array('2x-large', '2x-l', '2x large'),
        '3xl' => array('3x-large', '3x-l', '3x large'),
        '4xl' => array('4x-large', '4x-l', '4x large'),
        '5xl' => array('5x-large', '5x-l', '5x large')
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
        throw new Exception('Could not open the uploaded file.');
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
        $processed[$row['name']][$row['type']][$row['color']][$row['size']] = $row['quantity'];
    }
    unset($data);

    $sizes = array_unique($sizes);
    usort($sizes, 'cmpSizes');

    $html = '
    <div id="logo">
        <img src="img/logo.jpg">
    </div>
    <div id="date">Report: ' . date('m/d/Y H:i', time()) . '</div>
    <div id="summary">Number of products: ' . $productCount . '</div>
    <div id="summary">Number of erroneous lines: ' . $passedRowCount . '</div>';

    $log = 'log-' . date('d-m-Y_H-m-s', time()) . '.txt';
    writeLog($log, 'Number of imported rows: ' . $productCount);
    writeLog($log, 'Written to PDF: ' . $productCount);
    writeLog($log, 'Number of erroneous lines: ' . $passedRowCount);

    $html .= '<br><table class="table">';

    $html .= '<tr>';
    $html .= '<td class="bold">Product</td>';
    foreach ($sizes as $size) {
        $html .= '<td class="bold">' . strtoupper($size) . '</td>';
    }
    $html .= '<td class="bold">total</td>';
    $html .= '</tr>';

    foreach ($processed as $name => $row) {
        $html .= '<tr>';
        $html .= '<td class="product-name" colspan="' . count($sizes) . '">' . $name . '</td>';
        $html .= '</tr>';
        $types = array_keys($row);
        foreach ($types as $type) {
            $html .= '<tr>';
            $html .= '<td class="product-type" colspan="' . count($sizes) . '">' . $type . '</td>';
            $html .= '</tr>';

            $colors = array_keys($processed[$name][$type]);
            foreach ($colors as $color) {
                $html .= '<tr>';
                $html .= '<td class="product-color">' . $color . '</td>';
                $total = 0;
                foreach ($sizes as $size) {
                    $quantity = isset($processed[$name][$type][$color][$size]) ? $processed[$name][$type][$color][$size] : '';
                    $total += (int)$quantity;
                    $html .= '<td>' . $quantity . '</td>';
                }
                $html .= '<td class="bold">' . $total . '</td>';
                $html .= '</tr>';
            }
        }
    }

    $html .= '</table>';

    require 'mpdf/mpdf.php';

    $mpdf = new mPDF('', 'A4', '', '', 8, 8, 8, 8, 0, 0);

    $css = file_get_contents('css/pdf.css');
    $mpdf->WriteHTML($css, 1);

    $mpdf->WriteHTML($html, 2);

    $filename = $_FILES['csv']['name'] . '.pdf';
    $mpdf->Output($filename, 'D');
} catch (Exception $e) {
    session_start();
    $_SESSION['error'] = '<strong>Error!</strong> ' . $e->getMessage();

    header('Location: ' . $_SERVER['HTTP_REFERER']);
}
?>