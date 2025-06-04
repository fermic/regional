<?php
// diagnostico.php - Verificar sistema e wkhtmltopdf
echo "<h2>🔍 Diagnóstico do Sistema</h2>";

echo "<h3>1. Informações do Sistema:</h3>";
echo "Sistema Operacional: " . PHP_OS . "<br>";
echo "Versão PHP: " . PHP_VERSION . "<br>";
echo "SAPI: " . php_sapi_name() . "<br>";
echo "Servidor: " . $_SERVER['SERVER_SOFTWARE'] . "<br>";

echo "<h3>2. Verificar wkhtmltopdf:</h3>";

// Testar se wkhtmltopdf está no PATH
$output = [];
$return_var = 0;
exec('which wkhtmltopdf 2>&1', $output, $return_var);

if ($return_var === 0) {
    echo "✅ wkhtmltopdf encontrado em: " . implode('', $output) . "<br>";
    
    // Verificar versão
    $version_output = [];
    exec('wkhtmltopdf --version 2>&1', $version_output, $version_return);
    echo "Versão: " . implode(' ', $version_output) . "<br>";
} else {
    echo "❌ wkhtmltopdf NÃO encontrado<br>";
    echo "Saída: " . implode(' ', $output) . "<br>";
}

echo "<h3>3. Verificar Comandos Disponíveis:</h3>";

// Testar comandos básicos
$comandos = ['ls', 'pwd', 'whoami', 'apt', 'yum', 'wget', 'curl'];
foreach ($comandos as $cmd) {
    $test_output = [];
    $test_return = 0;
    exec("which $cmd 2>&1", $test_output, $test_return);
    
    if ($test_return === 0) {
        echo "✅ $cmd: " . implode('', $test_output) . "<br>";
    } else {
        echo "❌ $cmd: não encontrado<br>";
    }
}

echo "<h3>4. Verificar Distribuição Linux:</h3>";
$distro_output = [];
exec('cat /etc/os-release 2>&1', $distro_output, $distro_return);
if ($distro_return === 0) {
    echo "<pre>" . implode("\n", $distro_output) . "</pre>";
} else {
    echo "Não foi possível identificar a distribuição<br>";
}

echo "<h3>5. Verificar Usuário Atual:</h3>";
$user_output = [];
exec('whoami 2>&1', $user_output, $user_return);
echo "Usuário: " . implode('', $user_output) . "<br>";

$id_output = [];
exec('id 2>&1', $id_output, $id_return);
echo "ID completo: " . implode('', $id_output) . "<br>";

echo "<h3>6. Testar Instalação Simples:</h3>";
echo '<button onclick="testarInstalacao()">🔧 Tentar Instalar wkhtmltopdf</button>';
echo '<div id="resultado"></div>';

?>

<script>
function testarInstalacao() {
    const resultado = document.getElementById('resultado');
    resultado.innerHTML = '<p>⏳ Testando instalação...</p>';
    
    fetch('instalar_wkhtmltopdf.php')
        .then(response => response.text())
        .then(data => {
            resultado.innerHTML = data;
        })
        .catch(error => {
            resultado.innerHTML = '<p>❌ Erro: ' + error + '</p>';
        });
}
</script>