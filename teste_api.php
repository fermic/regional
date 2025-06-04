<?php
// teste_api.php - Verificar resposta da API
echo "<h2>Teste da API - Resposta JSON</h2>";

// 1. Teste simples da API
echo "<h3>1. Teste de endpoint simples (listar_peritos):</h3>";

// Usar URL completa ou CURL
$base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]" . dirname($_SERVER['REQUEST_URI']);
$url = $base_url . '/api.php?action=listar_peritos';

echo "URL da API: " . htmlspecialchars($url) . "<br>";

// Tentar com CURL
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: " . $http_code . "<br>";
echo "Resposta raw: <pre>" . htmlspecialchars($response) . "</pre>";

if ($response) {
    $json = json_decode($response, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        echo "✅ JSON válido<br>";
        echo "<pre>" . print_r($json, true) . "</pre>";
    } else {
        echo "❌ JSON inválido: " . json_last_error_msg() . "<br>";
    }
} else {
    echo "❌ Sem resposta da API<br>";
}

// 2. Verificar se api.php existe
echo "<h3>2. Verificar arquivos:</h3>";
if (file_exists('api.php')) {
    echo "✅ api.php existe<br>";
    
    // Verificar início do arquivo
    $content = file_get_contents('api.php');
    $first_chars = substr($content, 0, 5);
    echo "Primeiros caracteres de api.php: " . bin2hex($first_chars) . " (" . htmlspecialchars($first_chars) . ")<br>";
    
    // Deve ser: 3c3f706870 (<?php)
    if (bin2hex($first_chars) !== '3c3f706870') {
        echo "⚠️ api.php pode ter caracteres extras no início!<br>";
    }
} else {
    echo "❌ api.php NÃO existe<br>";
}

// 3. Teste direto incluindo o arquivo
echo "<h3>3. Teste direto da API:</h3>";
try {
    // Simular variáveis GET
    $_GET['action'] = 'listar_peritos';
    
    // Capturar saída
    ob_start();
    include 'api.php';
    $output = ob_get_clean();
    
    echo "Saída direta: <pre>" . htmlspecialchars($output) . "</pre>";
    
    $json = json_decode($output, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        echo "✅ JSON válido no teste direto<br>";
    } else {
        echo "❌ JSON inválido no teste direto: " . json_last_error_msg() . "<br>";
    }
} catch (Exception $e) {
    echo "❌ Erro ao executar api.php: " . $e->getMessage() . "<br>";
}

// 4. Verificar erros PHP
echo "<h3>4. Verificar erros PHP:</h3>";
ini_set('display_errors', 1);
error_reporting(E_ALL);
$error_log = ini_get('error_log');
echo "Log de erros: " . $error_log . "<br>";

// 5. Teste de salvamento
echo "<h3>5. Teste de salvamento (POST):</h3>";
?>

<button onclick="testarSalvamento()">Testar Salvamento</button>
<div id="resultado"></div>

<script>
function testarSalvamento() {
    const dados = {
        recognicao_id: '999999',
        rai: 'TESTE-API',
        fotos: []
    };
    
    console.log('Enviando:', dados);
    
    fetch('api.php?action=salvar_rascunho', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(dados)
    })
    .then(response => {
        console.log('Status:', response.status);
        console.log('Headers:', response.headers);
        return response.text();
    })
    .then(text => {
        console.log('Resposta completa:', text);
        document.getElementById('resultado').innerHTML = 
            '<h4>Resposta:</h4><pre>' + text + '</pre>';
        
        // Mostrar caracteres especiais
        let hex = '';
        for (let i = 0; i < Math.min(text.length, 50); i++) {
            hex += text.charCodeAt(i).toString(16) + ' ';
        }
        console.log('Hex dos primeiros caracteres:', hex);
        
        try {
            const json = JSON.parse(text);
            console.log('JSON parseado:', json);
            document.getElementById('resultado').innerHTML += 
                '<h4>JSON válido:</h4><pre>' + JSON.stringify(json, null, 2) + '</pre>';
        } catch (e) {
            console.error('Erro ao parsear JSON:', e);
            document.getElementById('resultado').innerHTML += 
                '<h4>Erro JSON:</h4><pre>' + e + '</pre>';
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        document.getElementById('resultado').innerHTML = 
            '<h4>Erro:</h4><pre>' + error + '</pre>';
    });
}
</script>