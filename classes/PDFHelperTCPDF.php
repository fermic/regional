<?php
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
?>