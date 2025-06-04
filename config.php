<?php
// config.php - Configuração do banco de dados
define('DB_HOST', 'localhost');
define('DB_NAME', 'recon');
define('DB_USER', 'recon');
define('DB_PASS', '89@Freitas#fmf');

// Função para conectar ao banco
function conectarDB() {
    try {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]
        );
        return $pdo;
    } catch (PDOException $e) {
        die("Erro de conexão: " . $e->getMessage());
    }
}

// Função para salvar dados offline no localStorage e sincronizar quando online
function gerarScriptOffline() {
    return '
    <script>
    // Verificar conexão
    function isOnline() {
        return navigator.onLine;
    }
    
    // Salvar dados no localStorage
    function salvarOffline(chave, dados) {
        localStorage.setItem(chave, JSON.stringify(dados));
    }
    
    // Recuperar dados do localStorage
    function recuperarOffline(chave) {
        const dados = localStorage.getItem(chave);
        return dados ? JSON.parse(dados) : null;
    }
    
    // Sincronizar dados quando voltar online
    window.addEventListener("online", function() {
        sincronizarDados();
    });
    
    // Auto-save a cada 30 segundos
    setInterval(function() {
        if (typeof salvarRascunho === "function") {
            salvarRascunho();
        }
    }, 30000);
    </script>';
}
?>