<?php
echo "============================================\n";
echo "🚀 Instalador Automático do mPDF via PHP\n";
echo "============================================\n";

// Caminho do projeto
$projeto = __DIR__ . '/projeto_mpdf';

if (!is_dir($projeto)) {
    mkdir($projeto, 0777, true);
    echo "📁 Pasta do projeto criada em: $projeto\n";
} else {
    echo "ℹ️ Pasta já existe: $projeto\n";
}

// Verificar Composer
echo "🔍 Verificando Composer...\n";
exec('composer --version', $output, $retorno);
if ($retorno !== 0) {
    die("❌ Composer não encontrado. Instale o Composer antes de prosseguir. Acesse: https://getcomposer.org/\n");
}
echo "✅ Composer encontrado: " . implode(' ', $output) . "\n";

// Gerar composer.json
$composerJson = <<<JSON
{
    "require": {
        "mpdf/mpdf": "^8.2"
    }
}
JSON;

file_put_contents("$projeto/composer.json", $composerJson);
echo "📝 Arquivo composer.json criado.\n";

// Rodar composer install
echo "📦 Instalando mPDF...\n";
chdir($projeto);
exec('composer install', $outputInstall);
echo implode("\n", $outputInstall) . "\n";

if (!file_exists("$projeto/vendor/autoload.php")) {
    die("❌ Falha na instalação do mPDF. Verifique os erros acima.\n");
}
echo "✅ mPDF instalado com sucesso!\n";

// Gerar arquivo de teste
$indexPhp = <<<PHP
<?php
require __DIR__ . '/vendor/autoload.php';

\$mpdf = new \Mpdf\Mpdf();
\$mpdf->WriteHTML('<h1>🚀 Teste do mPDF funcionando!</h1><p>PDF gerado com sucesso.</p>');
\$mpdf->Output('teste.pdf', 'I');
?>
PHP;

file_put_contents("$projeto/index.php", $indexPhp);
echo "📝 Arquivo index.php de teste criado.\n";

// Mensagem final
echo "============================================\n";
echo "✅ Instalação concluída!\n";
echo "📂 Acesse a pasta: $projeto\n";
echo "🔗 Execute no terminal: php -S localhost:8000\n";
echo "🌐 Depois acesse: http://localhost:8000/index.php\n";
echo "============================================\n";
?>
