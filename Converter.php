<?php

class Converter
{
    private $_file = null;
    private $_log = null;
    private $_data = array();

    private $_availableSizes;
    private $_availableColors;
    private $_availableTypes;
    private $_skippedWordsInType;

    // @fixme подмать, как их убрать из переменных объекта
    private $_unrecognizedRows = array();
    private $_validRowCount = 0;
    private $_rowCount = 0;
    private $_productCount = 0;
    private $_sizes = array();
    private $_totalByGroups = array();

    private $_groups = array(
        'PRESS' => 5, # >=5
        'DIGITAL' => 0, #,>= 0
    );

    /**
     * @param $file array
     * @throws Exception
     */
    public function __construct($file)
    {
        $this->_log = 'log-' . date('d-m-Y_H-m-s', time()) . '.txt';

        $this->_availableSizes = json_decode(file_get_contents('config/sizes.json'));
        if ($this->_availableSizes === null) {
            throw new Exception('Invalid sizes.json file.');
        }
        $this->_availableSizes = (array)$this->_availableSizes;

        $this->_availableColors = json_decode(file_get_contents('config/colors.json'));
        if ($this->_availableColors === null) {
            throw new Exception('Invalid colors.json file.');
        }
        $this->_availableColors = (array)$this->_availableColors;

        $this->_availableTypes = json_decode(file_get_contents('config/types.json'));
        if ($this->_availableTypes === null) {
            throw new Exception('Invalid types.json file.');
        }
        $this->_availableTypes = (array)$this->_availableTypes;

        $this->_skippedWordsInType = json_decode(file_get_contents('config/skipped_words_in_type.json'));
        if ($this->_skippedWordsInType === null) {
            throw new Exception('Invalid skipped_words_in_type.json file.');
        }
        $this->_skippedWordsInType = (array)$this->_skippedWordsInType;

        if ($file['type'] != 'text/csv') {
            throw new Exception('Invalid file format: only supported format csv.');
        }

        $file = fopen($file['tmp_name'], 'r+');
        if (!$file) {
            throw new Exception('Could not open the uploaded file.');
        }

        $d = fread($file, filesize($file['tmp_name']));
        if (strpos($d, "\r") !== false) {
            $d = str_replace("\r", "\n", $d);
            fseek($file, 0);
            fwrite($file, $d);
            fclose($file);
            $file = fopen($file['tmp_name'], 'r');
            if (!$file) {
                throw new Exception('Could not open the converted file.');
            }
        } else {
            fseek($file, 0);
        }

        $this->_file = $file;

        $this->_data = $this->_parse();
    }

    public function pdf()
    {
        require 'mpdf/mpdf.php';

        $mpdf = new mPDF('', 'A4', '', '', 8, 8, 12, 8, 0, 0);

        $css = file_get_contents('css/pdf.css');
        $mpdf->WriteHTML($css, 1);

        // @fixme нужно вызывать раньше чем _headHtml !!!
        $htmlByName = $this->_htmlByName();

        $mpdf->AddPage();
        $mpdf->WriteHTML($this->_headHtml(), 2);
        $mpdf->WriteHTML($this->_htmlByType(), 2);

        foreach ($htmlByName as $data) {
            $mpdf->AddPage();
            $mpdf->SetHTMLHeader($this->_headerHtml());
            $mpdf->WriteHTML($data, 2);
            $mpdf->SetHTMLHeader('');
        }

        if (count($this->_unrecognizedRows)) {
            $mpdf->SetHTMLHeader('');
            $mpdf->AddPage();
            $mpdf->WriteHTML($this->_unrecognizedRowsHtml(), 2);
        }

        $filename = $_FILES['csv']['name'] . '.pdf';
        $mpdf->Output($filename, 'D');
    }

    private function _dataByName()
    {
        $dataByName = array();
        foreach ($this->_data as $row) {
            if (!isset($dataByName[$row['name']][$row['type']][$row['color']][$row['size']])) {
                $dataByName[$row['name']][$row['type']][$row['color']][$row['size']] = 0;
            }
            $dataByName[$row['name']][$row['type']][$row['color']][$row['size']] += (int)$row['quantity'];
        }

        return $dataByName;
    }

    private function _dataByType()
    {
        $dataByType = array();
        foreach ($this->_data as $row) {
            if (!isset($dataByType[$row['type']][$row['color']][$row['size']])) {
                $dataByType[$row['type']][$row['color']][$row['size']] = 0;
            }
            $dataByType[$row['type']][$row['color']][$row['size']] += (int)$row['quantity'];
        }

        return $dataByType;
    }

    private function _headerHtml()
    {
        $header = '<br><table class="table">';
        $header .= '<tr>';
        $header .= '<td class="bold" style="width: 30%;">Product</td>';
        foreach ($this->_sizes as $size) {
            $header .= '<td class="bold">' . strtoupper($size) . '</td>';
        }
        $header .= '<td class="bold">total</td>';
        $header .= '</tr>';
        $header .= '</table>';

        return $header;
    }

    private function _headHtml()
    {
        $headHtml = '<div id="logo"><img src="img/logo.jpg"></div>';
        $headHtml .= '<div>' . (isset($_POST['name']) ? htmlspecialchars($_POST['name']) : '') . '</div>';
        $headHtml .= '<div id="date">Report: ' . date('m/d/Y H:i', time()) . '</div>';
        $headHtml .= '<div id="summary">Number of products: ' . $this->_productCount . '</div>';
        $headHtml .= '<div id="summary">Number of erroneous lines: ' . count($this->_unrecognizedRows) . '</div>';

        foreach ($this->_groups as $group => $limit) {
            if (!isset($this->_totalByGroups[$group]))
                continue;

            $headHtml .= '<div id="summary">' . $group . ': ' . $this->_totalByGroups[$group] . '</div>';
        }

        return $headHtml;
    }

    private function _unrecognizedRowsHtml()
    {
        $unrecognizedHtml = '';
        if (count($this->_unrecognizedRows) > 0) {
            $unrecognizedHtml = '<h3>Unrecognized rows:</h3>';
            $unrecognizedHtml .= '<table class="table">';
            foreach ($this->_unrecognizedRows as $unrecognizedRow) {
                $unrecognizedHtml .= '<tr>';
                $unrecognizedHtml .= '<td>' . $unrecognizedRow . '</td>';
                $unrecognizedHtml .= '</tr>';
            }
            $unrecognizedHtml .= '</table>';
        }

        return $unrecognizedHtml;
    }

    private function _htmlByName()
    {
        $htmlByGroup = array();

        $dataByName = $this->_dataByName();
        foreach ($dataByName as $name => $row) {
            $htmlByName = '<tr>';
            $htmlByName .= '<td class="product-name" colspan="' . count($this->_sizes) . '">' . $name . '</td>';
            $htmlByName .= '</tr>';
            $totalName = 0;
            $types = array_keys($row);
            foreach ($types as $type) {
                $htmlByName .= '<tr>';
                $htmlByName .= '<td class="product-type" colspan="' . count($this->_sizes) . '">' . $type . '</td>';
                $htmlByName .= '</tr>';

                $totalColumn = 0;
                $colors = array_keys($dataByName[$name][$type]);
                foreach ($colors as $color) {
                    $htmlByName .= '<tr>';
                    $htmlByName .= '<td class="product-color">' . $color . '</td>';
                    $total = 0;
                    foreach ($this->_sizes as $size) {
                        $quantity = isset($dataByName[$name][$type][$color][$size]) ? $dataByName[$name][$type][$color][$size] : '0';
                        $total += (int)$quantity;
                        $htmlByName .= '<td>' . $quantity . '</td>';
                    }
                    $totalColumn += $total;
                    $htmlByName .= '<td class="bold">' . $total . '</td>';
                    $htmlByName .= '</tr>';
                }
                $totalName += $totalColumn;
                $htmlByName .= '<tr class="no-border">';
                $htmlByName .= '<td colspan="' . (count($this->_sizes) + 1) . '"></td>';
                $htmlByName .= '<td class="bold">' . $totalColumn . '</td>';
                $htmlByName .= '</tr>';
            }

            foreach ($this->_groups as $group => $limit) {
                if ($totalName > $limit) {
                    $htmlByGroup[$group] .= $htmlByName;
                    $this->_totalByGroups[$group] += $totalName;
                    break;
                }
            }
        }

        $html = array();
        foreach ($this->_groups as $group => $limit) {
            if (!isset($htmlByGroup[$group]))
                continue;

            $html[$group] .= '<br><table class="table">';

            $html[$group] .= '<tr>';
            $html[$group] .= '<td class="product-name" style="text-align: center;" colspan="' . count($this->_sizes) . '">==' . $group . '==</td>';
            $html[$group] .= '</tr>';

            $html[$group] .= '<tr>';
            $html[$group] .= '<td class="bold" style="width: 30%;">Product</td>';
            foreach ($this->_sizes as $size) {
                $html[$group] .= '<td class="bold">' . strtoupper($size) . '</td>';
            }
            $html[$group] .= '<td class="bold">total</td>';
            $html[$group] .= '</tr>';

            $html[$group] .= $htmlByGroup[$group];

            $html[$group] .= '</table>';
        }

        asort($html);

        return $html;
    }

    private function _htmlByType()
    {
        $dataByType = $this->_dataByType();

        $htmlByType = '';
        foreach ($dataByType as $type => $row) {
            $htmlByType .= '<br><table class="table">';

            $htmlByType .= '<tr>';
            $htmlByType .= '<td class="product-name" colspan="' . count($this->_sizes) . '">' . $type . '</td>';
            $htmlByType .= '</tr>';

            $htmlByType .= '<tr>';
            $htmlByType .= '<td class="bold" style="width: 30%;"></td>';
            foreach ($this->_sizes as $size) {
                $htmlByType .= '<td class="bold">' . strtoupper($size) . '</td>';
            }
            $htmlByType .= '<td class="bold">total</td>';
            $htmlByType .= '</tr>';

            $colors = array_keys($row);
            $totalColumn = 0;
            foreach ($colors as $color) {
                $htmlByType .= '<tr>';
                $htmlByType .= '<td class="product-color">' . $color . '</td>';
                $total = 0;
                foreach ($this->_sizes as $size) {
                    $quantity = isset($dataByType[$type][$color][$size]) ? $dataByType[$type][$color][$size] : 0;
                    $total += (int)$quantity;
                    $htmlByType .= '<td>' . $quantity . '</td>';
                }
                $totalColumn += $total;
                $htmlByType .= '<td class="bold">' . $total . '</td>';
                $htmlByType .= '</tr>';
            }
            $htmlByType .= '<tr class="no-border">';
            $htmlByType .= '<td colspan="' . (count($this->_sizes) + 1) . '"></td>';
            $htmlByType .= '<td class="bold">' . $totalColumn . '</td>';
            $htmlByType .= '</tr>';

            $htmlByType .= '</table>';
        }

        return $htmlByType;
    }

    private function _parse()
    {
        if (!$this->_file) {
            return array();
        }

        $data = array();

        while ($row = fgetcsv($this->_file)) {
            $name = null;
            $size = null;
            $type = null;
            $color = null;
            $this->_rowCount++;

            if ($row[0] == 'product_title') {
                $this->_unrecognizedRows[] = implode(', ', array_merge(array($this->_rowCount), $row));
                continue;
            }

            $name = $this->_tryGetName($row[0]);

            $complexColumn = explode('/', $row[1]);
            foreach ($complexColumn as $complexItem) {
                $complexItem = strtolower(trim($complexItem));

                if ($size === null) {
                    $size = $this->_tryGetSize($complexItem);
                    if ($size !== null) {
                        continue;
                    }
                }

                if ($type === null) {
                    $type = $this->_tryGetType($complexItem);
                    if ($type !== null) {
                        continue;
                    }
                }

                if ($color === null) {
                    $color = $this->_tryGetColor($complexItem);
                    if ($color !== null) {
                        continue;
                    }
                }
            }

            // попытка найти тип в названии
            if ($type === null) {
                foreach ($this->_availableTypes as $availableType) {
                    if (stripos($name, $availableType) !== false) {
                        $type = $availableType;
                        break;
                    }
                }
                if (!$type) {
                    $type = 'T-Shirt';
                }
            }

            if ($color === null) {
                $color = 'black';
            }

            if (!$name or !$type or !$color or !$size) {
                $this->_unrecognizedRows[] = implode(', ', array_merge(array($this->_rowCount), $row));
                continue;
            }

            $data[$this->_validRowCount]['name'] = $name;
            $data[$this->_validRowCount]['size'] = $size;
            $data[$this->_validRowCount]['type'] = $type;
            $data[$this->_validRowCount]['color'] = $color;
            $data[$this->_validRowCount]['quantity'] = (int)trim($row[3]);
            $data[$this->_validRowCount]['price'] = trim($row[4]);

            $this->_productCount += $data[$this->_validRowCount]['quantity'];
            $this->_sizes[] = $data[$this->_validRowCount]['size'];

            $this->_validRowCount++;
        }

        $this->_sizes = array_unique($this->_sizes);
        usort($this->_sizes, array('Converter', '_cmpSizes'));

        $this->_log('Number of imported rows: ' . $this->_validRowCount);
        $this->_log('Written to PDF: ' . $this->_validRowCount);
        $this->_log('Number of erroneous lines: ' . count($this->_unrecognizedRows));

        return $data;
    }

    private function _tryGetName($value)
    {
        if (!strlen(trim($value))) {
            return null;
        }

        foreach ($this->_skippedWordsInType as $skippedWord) {
            $value = trim(str_ireplace($skippedWord, '', $value));
        }

        return trim($value);
    }

    private function _tryGetSize($value)
    {
        $result = null;

        foreach ($this->_availableSizes as $reference => $applicants) {
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

    private function _tryGetType($value)
    {
        foreach ($this->_availableTypes as $availableType) {
            $availableType = trim($availableType);
            if (strtolower($availableType) == strtolower($value)) {
                foreach ($this->_skippedWordsInType as $skippedWord) {
                    $availableType = trim(str_ireplace($skippedWord, '', $availableType));
                }
                return $availableType;
            }
        }

        return null;
    }

    private function _tryGetColor($value)
    {
        $value = strtolower(trim($value));

        $result = null;
        foreach ($this->_availableColors as $reference => $applicants) {
            if ($value == strtolower(trim($reference))) {
                $result = $reference;
                break;
            }
            foreach ($applicants as $applicant) {
                if ($value == strtolower(trim($applicant))) {
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

    private function _cmpSizes($a, $b)
    {
        if ($a == $b) {
            return 0;
        }

        foreach (array_keys($this->_availableSizes) as $pos => $reference) {
            if ($a == $reference) {
                $a = $pos;
                break;
            }
        }

        foreach (array_keys($this->_availableSizes) as $pos => $reference) {
            if ($b == $reference) {
                $b = $pos;
                break;
            }
        }

        return ($a < $b) ? -1 : 1;
    }

    private function _log($data)
    {
        $f = fopen($this->_log, 'a+');
        if (!$f) {
            throw new Exception('Error writing to the log file. Check the permissions.');
        }

        fwrite($f, $data . PHP_EOL);
        fclose($f);
    }
}