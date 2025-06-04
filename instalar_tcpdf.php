<?php
// instalar_tcpdf.php - Instalação e configuração do TCPDF
set_time_limit(300);

function executarComando($comando) {
    $output = [];
    $return_var = 0;
    exec($comando . ' 2>&1', $output, $return_var);
    return [
        'output' => $output,
        'return_code' => $return_var,
        'success' => $return_var === 0
    ];
}

echo "<h2>📚 Instalação do TCPDF</h2>";
echo "<p><strong>TCPDF</strong> é uma biblioteca PHP pura - não precisa de binários externos!</p>";

// Verificar se Composer está disponível
echo "<h3>1. Verificando Composer:</h3>";
$composer_check = executarComando('composer --version');

if ($composer_check['success']) {
    echo "<p>✅ Composer encontrado:</p>";
    echo "<pre>" . implode("\n", $composer_check['output']) . "</pre>";
} else {
    echo "<p>❌ Composer não encontrado. Tentando instalar...</p>";
    instalarComposer();
}

// Verificar se já está instalado
echo "<h3>2. Verificando TCPDF:</h3>";
if (file_exists(__DIR__ . '/vendor/tecnickcom/tcpdf/tcpdf.php')) {
    echo "<p>✅ TCPDF já está instalado!</p>";
    
    // Verificar versão
    require_once __DIR__ . '/vendor/tecnickcom/tcpdf/tcpdf.php';
    echo "<p>Versão: " . TCPDF_STATIC::getTCPDFVersion() . "</p>";
    
    // Pular para teste
    testarTCPDF();
    exit;
}

// Verificar se existe composer.json
echo "<h3>3. Configurando composer.json:</h3>";
if (!file_exists(__DIR__ . '/composer.json')) {
    echo "<p>📝 Criando composer.json...</p>";
    criarComposerJson();
}

// Instalar TCPDF
echo "<h3>4. Instalando TCPDF:</h3>";
echo "<p>⏳ Executando: <code>composer require tecnickcom/tcpdf</code></p>";

$install_result = executarComando('cd ' . __DIR__ . ' && composer require tecnickcom/tcpdf');

if ($install_result['success']) {
    echo "<p>✅ TCPDF instalado com sucesso!</p>";
    echo "<p>📦 Dependências instaladas:</p>";
    echo "<pre>" . implode("\n", array_slice($install_result['output'], -10)) . "</pre>";
    
    // Testar instalação
    testarTCPDF();
    
} else {
    echo "<p>❌ Falha na instalação:</p>";
    echo "<pre>" . implode("\n", $install_result['output']) . "</pre>";
    
    // Tentar instalação manual
    echo "<h4>🔄 Tentando instalação manual...</h4>";
    instalarTCPDFManual();
}

function instalarComposer() {
    echo "<p>📥 Baixando Composer...</p>";
    
    $download_result = executarComando('cd ' . __DIR__ . ' && curl -sS https://getcomposer.org/installer | php');
    
    if ($download_result['success'] && file_exists(__DIR__ . '/composer.phar')) {
        echo "<p>✅ Composer baixado com sucesso!</p>";
        
        // Criar alias para composer
        $composer_script = '#!/bin/bash' . "\n" . 'php ' . __DIR__ . '/composer.phar "$@"';
        file_put_contents(__DIR__ . '/composer', $composer_script);
        chmod(__DIR__ . '/composer', 0755);
        
        echo "<p>✅ Composer configurado. Use: <code>./composer</code></p>";
    } else {
        echo "<p>❌ Falha ao baixar Composer:</p>";
        echo "<pre>" . implode("\n", $download_result['output']) . "</pre>";
        
        // Tentar com wget
        echo "<p>🔄 Tentando com wget...</p>";
        executarComando('cd ' . __DIR__ . ' && wget https://getcomposer.org/composer.phar');
        
        if (file_exists(__DIR__ . '/composer.phar')) {
            echo "<p>✅ Composer baixado com wget!</p>";
        }
    }
}

function criarComposerJson() {
    $composer_config = [
        "name" => "pcgo/sistema-recognicao",
        "description" => "Sistema de Recognição Visuográfica - PCGO",
        "type" => "project",
        "require" => [
            "php" => ">=7.4",
            "tecnickcom/tcpdf" => "^6.6"
        ],
        "autoload" => [
            "psr-4" => [
                "App\\" => "classes/"
            ]
        ]
    ];
    
    file_put_contents(__DIR__ . '/composer.json', json_encode($composer_config, JSON_PRETTY_PRINT));
    echo "<p>✅ composer.json criado</p>";
}

function instalarTCPDFManual() {
    echo "<p>📥 Baixando TCPDF manualmente...</p>";
    
    $tcpdf_url = "https://github.com/tecnickcom/TCPDF/archive/refs/tags/6.6.2.zip";
    $zip_file = __DIR__ . '/tcpdf.zip';
    
    $download_result = executarComando("wget -O '$zip_file' '$tcpdf_url'");
    
    if ($download_result['success'] && file_exists($zip_file)) {
        echo "<p>✅ TCPDF baixado!</p>";
        
        // Extrair
        echo "<p>📦 Extraindo...</p>";
        $extract_result = executarComando("cd " . __DIR__ . " && unzip -q '$zip_file'");
        
        if ($extract_result['success']) {
            // Mover para vendor
            if (!is_dir(__DIR__ . '/vendor')) {
                mkdir(__DIR__ . '/vendor', 0755, true);
            }
            
            if (!is_dir(__DIR__ . '/vendor/tecnickcom')) {
                mkdir(__DIR__ . '/vendor/tecnickcom', 0755, true);
            }
            
            executarComando("mv " . __DIR__ . "/TCPDF-* " . __DIR__ . "/vendor/tecnickcom/tcpdf");
            
            // Criar autoload simples
            $autoload_content = '<?php
// autoload.php simples para TCPDF
spl_autoload_register(function ($class) {
    if (strpos($class, "TCPDF") === 0) {
        $file = __DIR__ . "/tecnickcom/tcpdf/tcpdf.php";
        if (file_exists($file)) {
            require_once $file;
        }
    }
});
?>';
            
            file_put_contents(__DIR__ . '/vendor/autoload.php', $autoload_content);
            
            echo "<p>✅ TCPDF instalado manualmente!</p>";
            
            unlink($zip_file);
            testarTCPDF();
        } else {
            echo "<p>❌ Falha na extração</p>";
        }
    } else {
        echo "<p>❌ Falha no download manual</p>";
        mostrarInstrucoesManuais();
    }
}

function testarTCPDF() {
    echo "<h3>🧪 Testando TCPDF:</h3>";
    
    try {
        // Incluir TCPDF
        if (file_exists(__DIR__ . '/vendor/autoload.php')) {
            require_once __DIR__ . '/vendor/autoload.php';
        } else {
            require_once __DIR__ . '/vendor/tecnickcom/tcpdf/tcpdf.php';
        }
        
        // Criar PDF teste
        $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        
        // Configurações básicas
        $pdf->SetCreator('Sistema PCGO');
        $pdf->SetAuthor('Polícia Civil de Goiás');
        $pdf->SetTitle('Teste TCPDF');
        $pdf->SetSubject('Teste de funcionalidade');
        
        // Configurar cabeçalho
        $pdf->SetHeaderData('', 0, 'TESTE TCPDF', 'Sistema de Recognição Visuográfica', array(26, 35, 126), array(26, 35, 126));
        $pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
        
        // Configurar rodapé
        $pdf->setFooterData(array(0,64,0), array(0,64,128));
        $pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));
        
        // Margens
        $pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
        $pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
        $pdf->SetFooterMargin(PDF_MARGIN_FOOTER);
        
        // Auto quebra de página
        $pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);
        
        // Fonte padrão
        $pdf->SetFont('helvetica', '', 12);
        
        // Adicionar página
        $pdf->AddPage();
        
        // Conteúdo HTML teste
        $html = '
        <h1 style="color: #1a237e;">🎉 TCPDF Funcionando!</h1>
        <p><strong>Sistema:</strong> ' . PHP_OS . '</p>
        <p><strong>PHP:</strong> ' . PHP_VERSION . '</p>
        <p><strong>Data:</strong> ' . date('d/m/Y H:i:s') . '</p>
        <p><strong>Usuário:</strong> ' . get_current_user() . '</p>
        
        <h2 style="color: #1a237e;">Recursos Testados:</h2>
        <ul>
            <li>✅ Geração de PDF</li>
            <li>✅ Suporte a UTF-8</li>
            <li>✅ HTML e CSS</li>
            <li>✅ Cabeçalho automático</li>
            <li>✅ Rodapé com numeração</li>
        </ul>
        
        <h2 style="color: #1a237e;">Próximos Passos:</h2>
        <p>1. Integrar com o sistema de Recognição</p>
        <p>2. Criar templates personalizados</p>
        <p>3. Configurar cabeçalho da PCGO</p>
        ';
        
        $pdf->writeHTML($html, true, false, true, false, '');
        
        // Salvar PDF teste
        $test_file = __DIR__ . '/teste_tcpdf.pdf';
        $pdf->Output($test_file, 'F');
        
        if (file_exists($test_file)) {
            $file_size = filesize($test_file);
            echo "<p>✅ PDF teste gerado com sucesso!</p>";
            echo "<p>📊 Tamanho: " . number_format($file_size / 1024, 2) . " KB</p>";
            echo "<p><a href='teste_tcpdf.pdf' target='_blank'>📄 Abrir PDF Teste</a></p>";
            
            // Criar implementação para o sistema
            criarImplementacaoTCPDF();
            
        } else {
            echo "<p>❌ Falha ao gerar PDF teste</p>";
        }
        
    } catch (Exception $e) {
        echo "<p>❌ Erro ao testar TCPDF: " . $e->getMessage() . "</p>";
        echo "<p>Stack trace:</p>";
        echo "<pre>" . $e->getTraceAsString() . "</pre>";
    }
}

function criarImplementacaoTCPDF() {
    echo "<h3>🔧 Criando Implementação para o Sistema:</h3>";
    
    // Criar classe PDFHelperTCPDF
    $pdf_helper_content = '<?php
// classes/PDFHelperTCPDF.php - Implementação usando TCPDF
require_once __DIR__ . "/../vendor/autoload.php";

class PDFHelperTCPDF {
    private $uploadsPath;
    
    public function __construct() {
        $this->uploadsPath = __DIR__ . "/../uploads/";
        
        // Criar diretório se não existir
        if (!is_dir($this->uploadsPath . "pdfs/")) {
            mkdir($this->uploadsPath . "pdfs/", 0755, true);
        }
    }
    
    public function gerarRecognicaoPDF($recognicaoId, $dados, $salvarArquivo = false) {
        try {
            // Criar instância TCPDF
            $pdf = new TCPDF("P", "mm", "A4", true, "UTF-8", false);
            
            // Configurações do documento
            $pdf->SetCreator("Sistema PCGO");
            $pdf->SetAuthor("Polícia Civil de Goiás - GIH");
            $pdf->SetTitle("Recognição Visuográfica - RAI " . ($dados["rai"] ?: $recognicaoId));
            $pdf->SetSubject("Recognição Visuográfica");
            
            // Configurar cabeçalho
            $this->configurarCabecalho($pdf, $dados);
            
            // Configurar rodapé
            $this->configurarRodape($pdf);
            
            // Margens
            $pdf->SetMargins(15, 30, 15);
            $pdf->SetHeaderMargin(5);
            $pdf->SetFooterMargin(10);
            
            // Auto quebra de página
            $pdf->SetAutoPageBreak(TRUE, 25);
            
            // Adicionar conteúdo
            $this->adicionarConteudo($pdf, $dados);
            
            if ($salvarArquivo) {
                $nomeArquivo = "Recognicao_" . ($dados["rai"] ? preg_replace("/[^A-Za-z0-9\-]/", "_", $dados["rai"]) : $recognicaoId) . "_" . date("Y-m-d_H-i-s") . ".pdf";
                $caminhoCompleto = $this->uploadsPath . "pdfs/" . $nomeArquivo;
                
                $pdf->Output($caminhoCompleto, "F");
                
                return [
                    "success" => true,
                    "arquivo" => $caminhoCompleto,
                    "nome" => $nomeArquivo,
                    "url" => "uploads/pdfs/" . $nomeArquivo
                ];
            } else {
                $nomeArquivo = "Recognicao_" . ($dados["rai"] ? preg_replace("/[^A-Za-z0-9\-]/", "_", $dados["rai"]) : $recognicaoId) . ".pdf";
                $pdf->Output($nomeArquivo, "D");
                return ["success" => true];
            }
            
        } catch (Exception $e) {
            return [
                "success" => false,
                "error" => $e->getMessage()
            ];
        }
    }
    
    private function configurarCabecalho($pdf, $dados) {
        // Cabeçalho personalizado será implementado na classe filha
        $pdf->SetHeaderData("", 0, "RECOGNIÇÃO VISUOGRÁFICA", "RAI: " . ($dados["rai"] ?: "NÃO INFORMADO"), array(26, 35, 126), array(26, 35, 126));
        $pdf->setHeaderFont(Array("helvetica", "B", 10));
    }
    
    private function configurarRodape($pdf) {
        $pdf->setFooterData(array(0,64,0), array(0,64,128));
        $pdf->setFooterFont(Array("helvetica", "", 8));
    }
    
    private function adicionarConteudo($pdf, $dados) {
        // Fonte padrão
        $pdf->SetFont("helvetica", "", 11);
        
        // Adicionar primeira página
        $pdf->AddPage();
        
        // Título principal
        $html = $this->gerarHTMLCompleto($dados);
        $pdf->writeHTML($html, true, false, true, false, "");
    }
    
    private function gerarHTMLCompleto($dados) {
        ob_start();
        ?>
        <style>
            h1 { color: #1a237e; font-size: 16pt; text-align: center; margin-bottom: 20px; }
            h2 { color: #1a237e; font-size: 14pt; background-color: #e3f2fd; padding: 8px; margin: 20px 0 10px 0; }
            h3 { color: #1a237e; font-size: 12pt; margin: 15px 0 8px 0; }
            .info-table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
            .info-table td { padding: 6px 8px; border-bottom: 1px solid #ddd; vertical-align: top; }
            .info-table td:first-child { background-color: #f5f5f5; font-weight: bold; width: 35%; }
            .pessoa-box { border: 1px solid #ddd; margin-bottom: 15px; page-break-inside: avoid; }
            .pessoa-header { background-color: #e3f2fd; padding: 8px; font-weight: bold; color: #1a237e; }
            .pessoa-content { padding: 10px; }
            .historico { text-align: justify; line-height: 1.6; text-indent: 2em; }
        </style>
        
        <h1>POLÍCIA CIVIL DO ESTADO DE GOIÁS<br>
        Grupo de Investigação de Homicídios (GIH)<br>
        RECOGNIÇÃO VISUOGRÁFICA</h1>
        
        <div style="text-align: center; background-color: #f0f0f0; padding: 10px; margin: 20px 0;">
            <strong>RAI Nº <?= htmlspecialchars($dados["rai"] ?: "NÃO INFORMADO") ?></strong>
        </div>
        
        <h2>1. INFORMAÇÕES GERAIS</h2>
        <table class="info-table">
            <tr>
                <td>Data/Hora do Acionamento</td>
                <td><?= $dados["data_hora_acionamento"] ? date("d/m/Y H:i", strtotime($dados["data_hora_acionamento"])) : "-" ?></td>
            </tr>
            <tr>
                <td>Data/Hora do Fato</td>
                <td><?= $dados["data_hora_fato"] ? date("d/m/Y H:i", strtotime($dados["data_hora_fato"])) : "-" ?> - <?= $dados["dia_semana"] ?: "-" ?></td>
            </tr>
            <tr>
                <td>Natureza da Ocorrência</td>
                <td><?= htmlspecialchars($dados["natureza"] ?: "-") ?></td>
            </tr>
            <tr>
                <td>Endereço do Fato</td>
                <td><?= htmlspecialchars($dados["endereco_fato"] ?: "-") ?></td>
            </tr>
            <?php if (!empty($dados["latitude"]) && !empty($dados["longitude"])): ?>
            <tr>
                <td>Coordenadas GPS</td>
                <td>Lat: <?= $dados["latitude"] ?> | Long: <?= $dados["longitude"] ?></td>
            </tr>
            <?php endif; ?>
        </table>
        
        <?php if (!empty($dados["historico"])): ?>
        <h2>2. HISTÓRICO</h2>
        <div class="historico">
            <?= nl2br(htmlspecialchars($dados["historico"])) ?>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($dados["vitimas"])): ?>
        <h2>3. VÍTIMAS</h2>
        <?php foreach ($dados["vitimas"] as $index => $vitima): ?>
        <div class="pessoa-box">
            <div class="pessoa-header">Vítima <?= $index + 1 ?></div>
            <div class="pessoa-content">
                <table class="info-table">
                    <tr><td>Nome</td><td><?= htmlspecialchars($vitima["nome"] ?: "-") ?></td></tr>
                    <tr><td>CPF</td><td><?= htmlspecialchars($vitima["cpf"] ?: "-") ?></td></tr>
                    <tr><td>Situação</td><td><strong><?= htmlspecialchars($vitima["situacao"] ?: "-") ?></strong></td></tr>
                </table>
                <?php if (!empty($vitima["lesoes_apresentadas"])): ?>
                <p><strong>Lesões:</strong> <?= htmlspecialchars($vitima["lesoes_apresentadas"]) ?></p>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
        
        <?php
        return ob_get_clean();
    }
    
    public function verificarTCPDF() {
        try {
            $version = TCPDF_STATIC::getTCPDFVersion();
            return [
                "disponivel" => true,
                "versao" => $version,
                "tipo" => "TCPDF"
            ];
        } catch (Exception $e) {
            return [
                "disponivel" => false,
                "erro" => $e->getMessage()
            ];
        }
    }
}
?>';
    
    // Salvar classe
    if (!is_dir(__DIR__ . '/classes')) {
        mkdir(__DIR__ . '/classes', 0755, true);
    }
    
    file_put_contents(__DIR__ . '/classes/PDFHelperTCPDF.php', $pdf_helper_content);
    echo "<p>✅ Classe PDFHelperTCPDF criada</p>";
    
    // Criar arquivo de uso
    criarArquivoUso();
}

function criarArquivoUso() {
    $usage_content = '<?php
// gerar_pdf_tcpdf.php - Gerador usando TCPDF
require_once "config.php";
require_once "classes/PDFHelperTCPDF.php";

$id = $_GET["id"] ?? 0;
if (!$id) {
    die("ID não informado");
}

try {
    $pdo = conectarDB();
    
    // Buscar dados da recognição (mesmo SQL do sistema atual)
    $stmt = $pdo->prepare("SELECT * FROM recognicoes WHERE id = ?");
    $stmt->execute([$id]);
    $recognicao = $stmt->fetch();
    
    if (!$recognicao) {
        die("Recognição não encontrada");
    }
    
    // Buscar vítimas
    $stmt = $pdo->prepare("SELECT * FROM vitimas WHERE recognicao_id = ?");
    $stmt->execute([$id]);
    $vitimas = $stmt->fetchAll();
    
    // Preparar dados
    $dados = array_merge($recognicao, [
        "vitimas" => $vitimas
    ]);
    
    // Gerar PDF
    $pdfHelper = new PDFHelperTCPDF();
    
    $acao = $_GET["acao"] ?? "download";
    
    if ($acao === "salvar") {
        $resultado = $pdfHelper->gerarRecognicaoPDF($id, $dados, true);
        header("Content-Type: application/json");
        echo json_encode($resultado);
    } else {
        $resultado = $pdfHelper->gerarRecognicaoPDF($id, $dados, false);
        if (!$resultado["success"]) {
            die("Erro: " . $resultado["error"]);
        }
    }
    
} catch (Exception $e) {
    if ($_GET["acao"] === "salvar") {
        header("Content-Type: application/json");
        http_response_code(500);
        echo json_encode(["success" => false, "error" => $e->getMessage()]);
    } else {
        die("Erro: " . $e->getMessage());
    }
}
?>';
    
    file_put_contents(__DIR__ . '/gerar_pdf_tcpdf.php', $usage_content);
    echo "<p>✅ Arquivo gerar_pdf_tcpdf.php criado</p>";
    
    echo "<h4>🎯 Próximos Passos:</h4>";
    echo "<ol>";
    echo "<li>✅ TCPDF instalado e funcionando</li>";
    echo "<li>✅ Classe PDFHelperTCPDF criada</li>";
    echo "<li>✅ Arquivo gerar_pdf_tcpdf.php criado</li>";
    echo "<li>🔄 Agora modifique o formulario.php para usar TCPDF</li>";
    echo "</ol>";
    
    echo "<h5>Modificação no formulario.php:</h5>";
    echo "<pre>
// Adicionar botão TCPDF:
&lt;a href=\"gerar_pdf_tcpdf.php?id=&lt;?= \$id ?&gt;\" target=\"_blank\" class=\"btn btn-primary\"&gt;
    📄 Gerar PDF (TCPDF)
&lt;/a&gt;
</pre>";
    
    echo "<p><strong>Teste agora:</strong> <a href='gerar_pdf_tcpdf.php?id=1' target='_blank'>Testar com ID 1</a></p>";
}

function mostrarInstrucoesManuais() {
    echo "<h4>📖 Instruções Manuais</h4>";
    echo "<p>Se a instalação automática falhar:</p>";
    echo "<ol>";
    echo "<li>Baixe TCPDF: <a href='https://tcpdf.org/' target='_blank'>https://tcpdf.org/</a></li>";
    echo "<li>Extraia na pasta <code>vendor/tecnickcom/tcpdf/</code></li>";
    echo "<li>Inclua: <code>require_once 'vendor/tecnickcom/tcpdf/tcpdf.php';</code></li>";
    echo "</ol>";
}
?>