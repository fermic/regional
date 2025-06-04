<?php
require __DIR__ . '/vendor/autoload.php';

$mpdf = new \Mpdf\Mpdf();
$mpdf->WriteHTML('<h1>🚀 Teste do mPDF funcionando!</h1><p>PDF gerado com sucesso.</p>');
$mpdf->Output('teste.pdf', 'I');
?>