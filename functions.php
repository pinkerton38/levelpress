<?php

function tryGetName($value)
{
    if (!strlen(trim($value))) {
        return null;
    }

    global $skippedWordsInType;

    foreach ($skippedWordsInType as $skippedWord) {
        $value = trim(str_ireplace($skippedWord, '', $value));
    }

    return trim($value);
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
    global $skippedWordsInType;

    foreach ($availableTypes as $availableType) {
        $availableType = trim($availableType);
        if (strtolower($availableType) == strtolower($value)) {
            foreach ($skippedWordsInType as $skippedWord) {
                $availableType = trim(str_ireplace($skippedWord, '', $availableType));
            }
            return $availableType;
        }
    }

    return null;
}

function tryGetColor($value)
{
    global $availableColors;

    $value = strtolower(trim($value));

    $result = null;
    foreach ($availableColors as $reference => $applicants) {
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

function writeLog($filename, $data)
{
    $f = fopen($filename, 'a+');
    if (!$f) {
        throw new Exception('Error writing to the log file. Check the permissions.');
    }

    fwrite($f, $data . PHP_EOL);
    fclose($f);
}