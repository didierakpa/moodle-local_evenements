<?php
require $argv[1] . '/tcpdf_barcodes_2d.php';
set_error_handler(function ($no, $str, $file, $line) { throw new ErrorException($str, 0, $no, $file, $line); });
$roots = ['http://localhost:8000', 'http://127.0.0.1', 'http://localhost', 'https://moodle.smss.fr'];
$fails = 0;
for ($i = 0; $i < 20000; $i++) {
    $url = $roots[$i % 4] . '/local/evenements/scan.php?t=' . bin2hex(random_bytes(20));
    try { (new TCPDF2DBarcode($url, 'QRCODE,M'))->getBarcodeArray(); }
    catch (Throwable $e) { if ($fails++ < 5) echo "FAIL $url @", basename($e->getFile()), ':', $e->getLine(), "\n"; }
}
echo PHP_VERSION, " fails=$fails/20000\n";
