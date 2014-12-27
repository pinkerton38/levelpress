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

    function tryGetSize($value)
    {
        global $availableSizes;

        $result = null;

        foreach ($availableSizes as $reference => $applicants) {
            if ($value == $reference) {
                $result = $reference;
                break;
            }
            foreach ($applicants as $applicant) {
                if ($value == $applicant) {
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

    function tryGetType($value)
    {
        global $availableTypes;

        foreach ($availableTypes as $availableType) {
            $availableType = trim($availableType);
            if (strtolower($availableType) == strtolower($value)) {
                return $availableType;
            }
        }

        return null;
    }

    function tryGetColor($value)
    {
        global $availableColors;

        foreach ($availableColors as $availableColor) {
            $availableColor = trim($availableColor);
            if (strtolower($availableColor) == strtolower($value)) {
                return $value;
            }
        }

        return $value;
    }

    function cmpSizes($a, $b)
    {
        global $availableSizes;

        if ($a == $b) {
            return 0;
        }

        foreach (array_keys($availableSizes) as $pos => $reference) {
            if ($a == $reference) {
                $a = $pos;
                break;
            }
        }

        foreach (array_keys($availableSizes) as $pos => $reference) {
            if ($b == $reference) {
                $b = $pos;
                break;
            }
        }

        return ($a < $b) ? -1 : 1;
    }

    $file = fopen($_FILES['csv']['tmp_name'], 'r+');
    if (!$file) {
        throw new Exception('Could not open the uploaded file.');
    }

    $d = fread($file, filesize($_FILES['csv']['tmp_name']));
    if (strpos($d, "\r") !== false) {
        $d = str_replace("\r", "\n", $d);
        fseek($file, 0);
        $a = fwrite($file, $d);
        fclose($file);
        $file = fopen($_FILES['csv']['tmp_name'], 'r');
        if (!$file) {
            throw new Exception('Could not open the converted file.');
        }
    } else {
        fseek($file, 0);
    }

    $data = array();
    $i = 0;
    $passedRowCount = 0;
    while ($row = fgetcsv($file)) {
        $name = null;
        $size = null;
        $type = null;
        $color = null;

        if ($row[0] == 'product_title') {
            $passedRowCount++;
            continue;
        }

        $name = trim($row[0]);

        $complexColumn = explode('/', $row[1]);
        foreach ($complexColumn as $complexItem) {
            $complexItem = strtolower(trim($complexItem));

            if ($size === null) {
                $size = tryGetSize($complexItem);
                if ($size !== null) {
                    continue;
                }
            }

            if ($type === null) {
                $type = tryGetType($complexItem);
                if ($type !== null) {
                    continue;
                }
            }

            if ($color === null) {
                $color = tryGetColor($complexItem);
                if ($color !== null) {
                    continue;
                }
            }
        }

        // попытка найти тип в названии
        if ($type === null) {
            foreach ($availableTypes as $availableType) {
                if (strpos($data[$i]['name'], $availableType) !== false) {
                    $type = $availableType;
                    break;
                }
            }
            if (!$type) {
                $type = 'T-Shirt';
            }
        }

        if ($color === null) {
            $color = 'undefined';
        }

        if (!$name or !$type or !$color or !$size) {
            $passedRowCount++;
            continue;
        }

        $data[$i]['name'] = $name;
        $data[$i]['size'] = $size;
        $data[$i]['type'] = $type;
        $data[$i]['color'] = $color;
        $data[$i]['quantity'] = trim($row[3]);
        $data[$i]['price'] = trim($row[4]);
        $i++;
    }
    $productCount = count($data);

    $sizes = array();
    $dataByName = array();
    foreach ($data as $row) {
        $sizes[] = $row['size'];
        $dataByName[$row['name']][$row['type']][$row['color']][$row['size']] = $row['quantity'];
    }

    $dataByType = array();
    foreach ($data as $row) {
        if (!isset($dataByType[$row['type']][$row['color']][$row['size']])) {
            $dataByType[$row['type']][$row['color']][$row['size']] = 0;
        }
        $dataByType[$row['type']][$row['color']][$row['size']] += (int)$row['quantity'];
    }

    unset($data);

    $sizes = array_unique($sizes);
    usort($sizes, 'cmpSizes');

    $header .= '<br><table class="table">';
    $header .= '<tr>';
    $header .= '<td class="bold" style="width: 30%;">Product</td>';
    foreach ($sizes as $size) {
        $header .= '<td class="bold">' . strtoupper($size) . '</td>';
    }
    $header .= '<td class="bold">total</td>';
    $header .= '</tr>';
    $header .= '</table>';

    $headHtml = '<div id="logo">
                <img src="img/logo.jpg">
            </div>
            <div id="date">Report: ' . date('m/d/Y H:i', time()) . '</div>
            <div id="summary">Number of products: ' . $productCount . '</div>
            <div id="summary">Number of erroneous lines: ' . $passedRowCount . '</div>';

    $htmlByType = '';
    foreach ($dataByType as $type => $row) {
        $htmlByType .= '<br><table class="table">';

        $htmlByType .= '<tr>';
        $htmlByType .= '<td class="product-name" colspan="' . count($sizes) . '">' . $type . '</td>';
        $htmlByType .= '</tr>';

        $htmlByType .= '<tr>';
        $htmlByType .= '<td class="bold" style="width: 30%;"></td>';
        foreach ($sizes as $size) {
            $htmlByType .= '<td class="bold">' . strtoupper($size) . '</td>';
        }
        $htmlByType .= '<td class="bold">total</td>';
        $htmlByType .= '</tr>';

        $colors = array_keys($row);
        foreach ($colors as $color) {
            $htmlByType .= '<tr>';
            $htmlByType .= '<td class="product-color">' . $color . '</td>';
            $total = 0;
            foreach ($sizes as $size) {
                $quantity = isset($dataByType[$type][$color][$size]) ? $dataByType[$type][$color][$size] : '0';
                $total += (int)$quantity;
                $htmlByType .= '<td>' . $quantity . '</td>';
            }
            $htmlByType .= '<td class="bold">' . $total . '</td>';
            $htmlByType .= '</tr>';
        }
        $htmlByType .= '</table>';
    }

    $htmlByName .= '<br><table class="table">';
    $htmlByName .= '<tr>';
    $htmlByName .= '<td class="bold" style="width: 30%;">Product</td>';
    foreach ($sizes as $size) {
        $htmlByName .= '<td class="bold">' . strtoupper($size) . '</td>';
    }
    $htmlByName .= '<td class="bold">total</td>';
    $htmlByName .= '</tr>';

    foreach ($dataByName as $name => $row) {
        $htmlByName .= '<tr>';
        $htmlByName .= '<td class="product-name" colspan="' . count($sizes) . '">' . $name . '</td>';
        $htmlByName .= '</tr>';
        $types = array_keys($row);
        foreach ($types as $type) {
            $htmlByName .= '<tr>';
            $htmlByName .= '<td class="product-type" colspan="' . count($sizes) . '">' . $type . '</td>';
            $htmlByName .= '</tr>';

            $colors = array_keys($dataByName[$name][$type]);
            foreach ($colors as $color) {
                $htmlByName .= '<tr>';
                $htmlByName .= '<td class="product-color">' . $color . '</td>';
                $total = 0;
                foreach ($sizes as $size) {
                    $quantity = isset($dataByName[$name][$type][$color][$size]) ? $dataByName[$name][$type][$color][$size] : '0';
                    $total += (int)$quantity;
                    $htmlByName .= '<td>' . $quantity . '</td>';
                }
                $htmlByName .= '<td class="bold">' . $total . '</td>';
                $htmlByName .= '</tr>';
            }
        }
    }
    $htmlByName .= '</table>';

    $log = 'log-' . date('d-m-Y_H-m-s', time()) . '.txt';
    writeLog($log, 'Number of imported rows: ' . $productCount);
    writeLog($log, 'Written to PDF: ' . $productCount);
    writeLog($log, 'Number of erroneous lines: ' . $passedRowCount);


    require 'mpdf/mpdf.php';

    $mpdf = new mPDF('', 'A4', '', '', 8, 8, 12, 8, 0, 0);

    $css = file_get_contents('css/pdf.css');
    $mpdf->WriteHTML($css, 1);

    $mpdf->AddPage();
    $mpdf->WriteHTML($headHtml, 2);
    $mpdf->WriteHTML($htmlByType, 2);

    $mpdf->AddPage();
    $mpdf->SetHTMLHeader($header);
    $mpdf->WriteHTML($htmlByName, 2);

    $filename = $_FILES['csv']['name'] . '.pdf';
    $mpdf->Output($filename, 'D');
} catch (Exception $e) {
    session_start();
    $_SESSION['error'] = '<strong>Error!</strong> ' . $e->getMessage();

    header('Location: ' . $_SERVER['HTTP_REFERER']);
}
?>