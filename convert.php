<?php
require 'Converter.php';

try {
    if (!isset($_POST['convert'])) {
        throw new Exception('Access denied.');
    }

    if (!isset($_FILES['csv']) or !$_FILES['csv']['size']) {
        throw new Exception('File is empty or absent.');
    }

    $converter = new Converter($_FILES['csv']);
    $converter->pdf();

} catch (Exception $e) {
    session_start();

    $_SESSION['error'] = '<strong>Error!</strong> ' . $e->getMessage();

    header('Location: ' . $_SERVER['HTTP_REFERER']);
}