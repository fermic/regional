<?php
// gerar_pdf_mpdf.php - Geração de PDF completo com mPDF
require_once __DIR__ . '/projeto_mpdf/vendor/autoload.php';
require_once 'config.php';

// Configurações de tratamento de erros
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Configurar timezone
date_default_timezone_set('America/Sao_Paulo');

// Conectar banco de dados
try {
    $pdo = conectarDB();
} catch (Exception $e) {
    die("Erro na conexão com o banco de dados: " . $e->getMessage());
}

// Validar ID da recognição
$id = $_GET['id'] ?? 0;
if (!$id) {
    die("ID não informado");
}

// Funções auxiliares
function formatarData($data) {
    return $data ? date('d/m/Y H:i', strtotime($data)) : '-';
}

function formatarDataNascimento($data) {
    return $data ? date('d/m/Y', strtotime($data)) : '-';
}

function limparTexto($texto) {
    return $texto ?: '-';
}

// Buscar dados da recognição
try {
    // Buscar dados principais
    $stmt = $pdo->prepare("
        SELECT r.*, 
               n.descricao as natureza,
               fp.descricao as preservacao_local,
               p.nome as perito_nome,
               p.matricula as perito_matricula,
               le.descricao as local_externo,
               li.descricao as local_interno,
               tp.descricao as tipo_piso,
               do.descricao as disposicao_objetos,
               ch.descricao as condicoes_higiene
        FROM recognicoes r
        LEFT JOIN naturezas n ON r.natureza_id = n.id
        LEFT JOIN forca_policial fp ON r.preservacao_local_id = fp.id
        LEFT JOIN peritos p ON r.perito_id = p.id
        LEFT JOIN local_externo le ON r.local_externo_id = le.id
        LEFT JOIN local_interno li ON r.local_interno_id = li.id
        LEFT JOIN tipo_piso tp ON r.tipo_piso_id = tp.id
        LEFT JOIN disposicao_objetos do ON r.disposicao_objetos_id = do.id
        LEFT JOIN condicoes_higiene ch ON r.condicoes_higiene_id = ch.id
        WHERE r.id = ?
    ");
    $stmt->execute([$id]);
    $recognicao = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$recognicao) {
        die("Recognição não encontrada");
    }

    // Buscar dados relacionados
    $stmt = $pdo->prepare("SELECT * FROM policiais_preservacao WHERE recognicao_id = ?");
    $stmt->execute([$id]);
    $policiais_preservacao = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("
        SELECT v.*, pv.descricao as posicao_descricao 
        FROM vitimas v 
        LEFT JOIN posicao_vitima pv ON v.posicao_vitima_id = pv.id 
        WHERE v.recognicao_id = ?
    ");
    $stmt->execute([$id]);
    $vitimas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("SELECT * FROM autores WHERE recognicao_id = ?");
    $stmt->execute([$id]);
    $autores = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("SELECT * FROM testemunhas WHERE recognicao_id = ?");
    $stmt->execute([$id]);
    $testemunhas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("
        SELECT me.tipo, 
               CASE 
                   WHEN me.tipo = 'veiculo' THEN v.descricao
                   WHEN me.tipo = 'arma' THEN a.descricao
               END as descricao
        FROM meios_empregados me
        LEFT JOIN veiculos v ON me.tipo = 'veiculo' AND me.item_id = v.id
        LEFT JOIN armas a ON me.tipo = 'arma' AND me.item_id = a.id
        WHERE me.recognicao_id = ?
    ");
    $stmt->execute([$id]);
    $meios_empregados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("
        SELECT pg.nome, pg.matricula 
        FROM equipe_responsavel er
        JOIN policiais_gih pg ON er.policial_id = pg.id
        WHERE er.recognicao_id = ?
    ");
    $stmt->execute([$id]);
    $equipe_responsavel = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("SELECT * FROM fotos WHERE recognicao_id = ? ORDER BY id");
    $stmt->execute([$id]);
    $fotos = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    die("Erro ao buscar dados: " . $e->getMessage());
}

// Gerar HTML para mPDF
$html = '
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Recognição Visuográfica - RAI ' . htmlspecialchars($recognicao['rai'] ?? 'S/N') . '</title>
    <style>
        body { 
            font-family: "DejaVu Sans", sans-serif; 
            font-size: 9pt; 
            line-height: 1.4; 
            color: #333; 
        }
        
        .header {
            text-align: center;
            margin-bottom: 15px;
            border-bottom: 2px solid #1a237e;
            padding-bottom: 10px;
        }
        
        .header h1 {
            margin: 0;
            color: #1a237e;
            font-size: 14pt;
            font-weight: bold;
        }
        
        .header h2 {
            margin: 3px 0 0 0;
            color: #666;
            font-size: 11pt;
        }
        
        .header h3 {
            margin: 3px 0 0 0;
            color: #444;
            font-size: 10pt;
        }
        
        .rai-info {
            background: #1a237e;
            padding: 8px;
            text-align: center;
            font-size: 12pt;
            font-weight: bold;
            color: white;
            margin: 8px 0;
        }
        
        .section { 
            margin-bottom: 15px; 
            page-break-inside: avoid;
        }
        
        .section-title { 
            background-color: #1a237e; 
            color: white; 
            padding: 6px 12px; 
            font-weight: bold; 
            font-size: 10pt;
        }
        
        .section-content { 
            border: 1px solid #ccc; 
            padding: 10px; 
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .info-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .info-table td {
            padding: 5px 8px;
            border: 1px solid #ddd;
            vertical-align: middle;
        }
        
        .info-table td.label {
            background: #f5f5f5;
            font-weight: bold;
            width: 30%;
            color: #555;
        }
        
        .info-table td.value {
            width: 70%;
        }
        
        .two-col-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .two-col-table td {
            width: 50%;
            padding: 0 0;
            vertical-align: middle;
        }
        
        .two-col-table td:first-child {
            padding-left: 0;
        }
        
        .two-col-table td:last-child {
            padding-right: 0;
        }
        
        .full-width-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .full-width-table td {
            padding: 6px 8px;
            border: 1px solid #ddd;
            vertical-align: middle;
        }
        
        .full-width-table td.label {
            background: #f5f5f5;
            font-weight: bold;
            width: 25%;
        }
        
        .subsection {
            margin: 10px 0;
            padding: 10px;
            background: #f8f9fa;
            border-left: 3px solid #1a237e;
        }
        
        .subsection-title {
            font-weight: bold;
            color: #1a237e;
            margin-bottom: 8px;
            font-size: 9pt;
        }
        
        .pessoa-card { 
            border: 1px solid #ccc; 
            margin-bottom: 10px; 
            page-break-inside: avoid;
        }
        
        .pessoa-header {
            background: #f0f0f0;
            padding: 6px 10px;
            font-weight: bold;
            color: #1a237e;
            border-bottom: 1px solid #1a237e;
            font-size: 9pt;
        }
        
        .pessoa-header-fatal {
            background: #ffebee;
            padding: 6px 10px;
            font-weight: bold;
            color: #d32f2f;
            border-bottom: 2px solid #d32f2f;
            font-size: 9pt;
        }
        
        .pessoa-content {
            padding: 8px;
        }
        
        .pessoa-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .pessoa-table td {
            padding: 3px 5px;
            vertical-align: top;
            font-size: 8pt;
        }
        
        .pessoa-table td.label {
            font-weight: bold;
            color: #555;
            width: 35%;
        }
        
        .list-clean {
            list-style: none;
            padding-left: 0;
            margin: 5px 0;
        }
        
        .list-clean li {
            padding: 3px 0;
            padding-left: 15px;
            position: relative;
            font-size: 8pt;
        }
        
        .list-clean li:before {
            content: "•";
            position: absolute;
            left: 0;
            color: #1a237e;
        }
        
        .text-block {
            background: #f8f9fa;
            padding: 10px;
            margin: 8px 0;
            white-space: pre-wrap;
            word-wrap: break-word;
            line-height: 1.5;
            text-align: justify;
            font-size: 8pt;
            border: 1px solid #ddd;
        }
        
        .foto-titulo {
            font-size: 11pt;
            font-weight: bold;
            color: #1a237e;
            text-align: center;
            margin: 15px 0 10px 0;
            padding: 8px;
            background: #f0f0f0;
            border: 1px solid #ddd;
            page-break-after: avoid;
        }
        
        .foto-container {
            text-align: center;
            padding: 10px;
            page-break-after: avoid;
            page-break-inside: avoid;
        }
        
        .foto-container img { 
            max-width: 100%; 
            max-height: 600px;
            margin: 0 auto;
            display: block;
            border: 1px solid #999;
        }
        
        .footer {
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid #ddd;
            text-align: center;
            color: #666;
            font-size: 8pt;
        }
        
        .situacao-fatal {
            background: #d32f2f;
            color: white;
            padding: 4px 12px;
            border-radius: 12px;
            font-weight: bold;
            font-size: 9pt;
            display: inline-block;
            box-shadow: 0 2px 4px rgba(0,0,0,0.3);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .situacao-sobrevivente {
            background: #388e3c;
            color: white;
            padding: 4px 12px;
            border-radius: 12px;
            font-weight: bold;
            font-size: 9pt;
            display: inline-block;
            box-shadow: 0 2px 4px rgba(0,0,0,0.3);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .meios-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .meios-table td {
            padding: 5px 8px;
            border: 1px solid #ddd;
            vertical-align: middle;
        }
        
        .meios-table th {
            background: #1a237e;
            color: white;
            padding: 6px 8px;
            font-weight: bold;
            border: 1px solid #ddd;
            font-size: 9pt;
        }
        
        .page-break {
            page-break-after: always;
        }
    </style>
</head>
<body>';

// CABEÇALHO
$html .= '
    <div class="header">
        <div style="text-align: center; margin-bottom: 15px;">
            <img src="imagens/logo_pcgo.png" style="height: 100px; width: auto;">
        </div>
        <h1>POLÍCIA CIVIL DO ESTADO DE GOIÁS</h1>
        <h3>Grupo de Investigação de Homicídios (GIH)</h3>
        <h2>RECOGNIÇÃO VISUOGRÁFICA</h2>
    </div>
    
    <div class="rai-info">
        RAI Nº ' . htmlspecialchars($recognicao['rai'] ?? 'NÃO INFORMADO') . '
    </div>';

// INFORMAÇÕES GERAIS
$html .= '
    <div class="section">
        <div class="section-title">INFORMAÇÕES GERAIS</div>
        <div class="section-content">
            <table class="two-col-table" style="width: 100%;">
                <tr>
                    <td style="width: 50%; padding-right: 10px;">
                        <table class="info-table">
                            <tr>
                                <td class="label">RAI</td>
                                <td class="value">' . htmlspecialchars($recognicao['rai'] ?? 'NÃO INFORMADO') . '</td>
                            </tr>
                            <tr>
                                <td class="label">Data/Hora Acionamento</td>
                                <td class="value">' . formatarData($recognicao['data_hora_acionamento']) . '</td>
                            </tr>
                            <tr>
                                <td class="label">Data/Hora do Fato</td>
                                <td class="value">' . formatarData($recognicao['data_hora_fato']) . '</td>
                            </tr>
                            <tr>
                                <td class="label">Dia da Semana</td>
                                <td class="value">' . limparTexto($recognicao['dia_semana']) . '</td>
                            </tr>
                        </table>
                    </td>
                    <td style="width: 50%; padding-left: 10px;">
                        <table class="info-table">
                            <tr>
                                <td class="label">Natureza</td>
                                <td class="value">' . limparTexto($recognicao['natureza']) . '</td>
                            </tr>
                            <tr>
                                <td class="label">Preservação Local</td>
                                <td class="value">' . limparTexto($recognicao['preservacao_local']) . '</td>
                            </tr>
                            <tr>
                                <td class="label">Perito Responsável</td>
                                <td class="value">' . limparTexto($recognicao['perito_nome']) . 
                                ($recognicao['perito_matricula'] ? '<br><small>Mat: ' . $recognicao['perito_matricula'] . '</small>' : '') . '</td>
                            </tr>';

if ($recognicao['latitude'] && $recognicao['longitude']) {
    $html .= '
                            <tr>
                                <td class="label">Coordenadas</td>
                                <td class="value">Lat: ' . $recognicao['latitude'] . '<br>Long: ' . $recognicao['longitude'] . '</td>
                            </tr>';
}

$html .= '
                        </table>
                    </td>
                </tr>
            </table>
            
            <table class="info-table" style="margin-top: 10px; width: 100%;">
                <tr>
                    <td class="label" style="width: 15%;">Endereço do Fato</td>
                    <td class="value" style="width: 85%;">' . limparTexto($recognicao['endereco_fato']) . '</td>
                </tr>
            </table>';

if (!empty($policiais_preservacao)) {
    $html .= '
            <div class="subsection">
                <div class="subsection-title">Policiais que Preservaram o Local</div>
                <ul class="list-clean">';
    foreach ($policiais_preservacao as $policial) {
        $html .= '<li>' . htmlspecialchars($policial['nome']) . 
                 ($policial['matricula'] ? ' - Matrícula: ' . $policial['matricula'] : '') . '</li>';
    }
    $html .= '
                </ul>
            </div>';
}

$html .= '
        </div>
    </div>';

// LOCAL DO FATO
$html .= '
    <div class="section">
        <div class="section-title">LOCAL DO FATO</div>
        <div class="section-content">
            <table class="two-col-table" style="width: 100%;">
                <tr>
                    <td style="width: 50%; padding-right: 10px;">
                        <table class="info-table">
                            <tr>
                                <td class="label">Tipo de Local</td>
                                <td class="value">' . limparTexto($recognicao['tipo_local']) . 
                                ($recognicao['tipo_local'] == 'Externo' && $recognicao['local_externo'] ? '<br>' . htmlspecialchars($recognicao['local_externo']) : '') .
                                ($recognicao['tipo_local'] == 'Interno' && $recognicao['local_interno'] ? '<br>' . htmlspecialchars($recognicao['local_interno']) : '') . '</td>
                            </tr>
                            <tr>
                                <td class="label">Tipo de Piso</td>
                                <td class="value">' . limparTexto($recognicao['tipo_piso']) . '</td>
                            </tr>
                            <tr>
                                <td class="label">Disposição Objetos</td>
                                <td class="value">' . limparTexto($recognicao['disposicao_objetos']) . '</td>
                            </tr>
                        </table>
                    </td>
                    <td style="width: 50%; padding-left: 10px;">
                        <table class="info-table">
                            <tr>
                                <td class="label">Condições Higiene</td>
                                <td class="value">' . limparTexto($recognicao['condicoes_higiene']) . '</td>
                            </tr>
                            <tr>
                                <td class="label">Câmeras</td>
                                <td class="value">' . limparTexto($recognicao['cameras_monitoramento']) . '</td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
            
            <div class="subsection">
                <div class="subsection-title">Objetos Recolhidos (inclusive pela Perícia)</div>
                <div class="text-block">' . 
                ($recognicao['objetos_recolhidos'] ? htmlspecialchars($recognicao['objetos_recolhidos']) : 'Nenhum objeto foi recolhido') . 
                '</div>
            </div>
        </div>
    </div>';

// MEIOS EMPREGADOS
$veiculos = array_filter($meios_empregados, fn($m) => $m['tipo'] == 'veiculo');
$armas = array_filter($meios_empregados, fn($m) => $m['tipo'] == 'arma');

if (!empty($veiculos) || !empty($armas)) {
    $html .= '
    <div class="section">
        <div class="section-title">MEIOS EMPREGADOS</div>
        <div class="section-content">
            <table class="meios-table">
                <tr>
                    <th style="width: 50%;">VEÍCULO UTILIZADO</th>
                    <th style="width: 50%;">ARMA EMPREGADA</th>
                </tr>';
    
    $max_items = max(count($veiculos), count($armas));
    $veiculos_arr = array_values($veiculos);
    $armas_arr = array_values($armas);
    
    for ($i = 0; $i < $max_items; $i++) {
        $html .= '<tr>';
        $html .= '<td style="text-align: center;">' . (isset($veiculos_arr[$i]) ? htmlspecialchars($veiculos_arr[$i]['descricao']) : '-') . '</td>';
        $html .= '<td style="text-align: center;">' . (isset($armas_arr[$i]) ? htmlspecialchars($armas_arr[$i]['descricao']) : '-') . '</td>';
        $html .= '</tr>';
    }
    
    $html .= '
            </table>
        </div>
    </div>';
}

// VÍTIMAS
if (!empty($vitimas)) {
    $html .= '
    <div class="section">
        <div class="section-title">VÍTIMAS</div>
        <div class="section-content">';
    
    foreach ($vitimas as $index => $vitima) {
        $isFatal = ($vitima['situacao'] == 'FATAL');
        $headerClass = $isFatal ? 'pessoa-header-fatal' : 'pessoa-header';
        
        $html .= '
            <div class="pessoa-card">
                <div class="' . $headerClass . '">
                    Vítima ' . ($index + 1);
        
        // Adicionar indicador visual se for fatal
        if ($isFatal) {
            $html .= ' <span style="float: right; color: #d32f2f; font-weight: bold; font-size: 8pt;">⚠️ FATAL</span>';
        }
        
        $html .= '</div>
                <div class="pessoa-content">
                    <table class="full-width-table">
                        <tr>
                            <td class="label" style="width: 15%;">Nome</td>
                            <td style="width: 35%;">' . limparTexto($vitima['nome']) . '</td>
                            <td class="label" style="width: 15%;">Nascimento</td>
                            <td style="width: 35%;">' . formatarDataNascimento($vitima['data_nascimento']) . '</td>
                        </tr>
                        <tr>
                            <td class="label">CPF</td>
                            <td>' . limparTexto($vitima['cpf']) . '</td>
                            <td class="label">RG</td>
                            <td>' . limparTexto($vitima['rg']) . '</td>
                        </tr>
                        <tr>
                            <td class="label">Cor</td>
                            <td>' . limparTexto($vitima['cor']) . '</td>
                            <td class="label">Profissão</td>
                            <td>' . limparTexto($vitima['profissao']) . '</td>
                        </tr>
                        <tr>
                            <td class="label">Nacionalidade</td>
                            <td>' . limparTexto($vitima['nacionalidade']) . '</td>
                            <td class="label">Naturalidade</td>
                            <td>' . limparTexto($vitima['naturalidade']) . '</td>
                        </tr>
                        <tr>
                            <td class="label">Pai</td>
                            <td>' . limparTexto($vitima['pai']) . '</td>
                            <td class="label">Mãe</td>
                            <td>' . limparTexto($vitima['mae']) . '</td>
                        </tr>
                        <tr>
                            <td class="label">Situação</td>
                            <td>' . limparTexto($vitima['situacao']) . '</td>';
        
        if ($vitima['situacao'] == 'FATAL' && $vitima['posicao_descricao']) {
            $html .= '
                            <td class="label">Posição</td>
                            <td>' . htmlspecialchars($vitima['posicao_descricao']) . '</td>';
        } elseif ($vitima['situacao'] == 'SOBREVIVENTE' && $vitima['hospital_socorrido']) {
            $html .= '
                            <td class="label">Hospital</td>
                            <td>' . htmlspecialchars($vitima['hospital_socorrido']) . '</td>';
        } else {
            $html .= '
                            <td></td>
                            <td></td>';
        }
        
        $html .= '
                        </tr>
                        <tr>
                            <td class="label">Endereço</td>
                            <td colspan="3">' . limparTexto($vitima['endereco']) . '</td>
                        </tr>';
        
        if ($vitima['lesoes_apresentadas']) {
            $html .= '
                        <tr>
                            <td class="label">Lesões</td>
                            <td colspan="3">' . nl2br(htmlspecialchars($vitima['lesoes_apresentadas'])) . '</td>
                        </tr>';
        }
        
        $html .= '
                    </table>
                </div>
            </div>';
    }
    
    $html .= '
        </div>
    </div>';
}

// AUTORES
if (!empty($autores)) {
    $html .= '
    <div class="section">
        <div class="section-title">SUPOSTA AUTORIA</div>
        <div class="section-content">';
    
    foreach ($autores as $index => $autor) {
        $html .= '
            <div class="pessoa-card">
                <div class="pessoa-header">Autor ' . ($index + 1) . '</div>
                <div class="pessoa-content">
                    <table class="full-width-table">
                        <tr>
                            <td class="label" style="width: 15%;">Nome</td>
                            <td style="width: 35%;">' . limparTexto($autor['nome']) . '</td>
                            <td class="label" style="width: 15%;">Nascimento</td>
                            <td style="width: 35%;">' . formatarDataNascimento($autor['data_nascimento']) . '</td>
                        </tr>
                        <tr>
                            <td class="label">CPF</td>
                            <td>' . limparTexto($autor['cpf']) . '</td>
                            <td class="label">RG</td>
                            <td>' . limparTexto($autor['rg']) . '</td>
                        </tr>
                        <tr>
                            <td class="label">Cor</td>
                            <td>' . limparTexto($autor['cor']) . '</td>
                            <td class="label">Profissão</td>
                            <td>' . limparTexto($autor['profissao']) . '</td>
                        </tr>
                        <tr>
                            <td class="label">Nacionalidade</td>
                            <td>' . limparTexto($autor['nacionalidade']) . '</td>
                            <td class="label">Naturalidade</td>
                            <td>' . limparTexto($autor['naturalidade']) . '</td>
                        </tr>
                        <tr>
                            <td class="label">Pai</td>
                            <td>' . limparTexto($autor['pai']) . '</td>
                            <td class="label">Mãe</td>
                            <td>' . limparTexto($autor['mae']) . '</td>
                        </tr>
                        <tr>
                            <td class="label">Endereço</td>
                            <td colspan="3">' . limparTexto($autor['endereco']) . '</td>
                        </tr>';
        
        if ($autor['caracteristicas']) {
            $html .= '
                        <tr>
                            <td class="label">Características</td>
                            <td colspan="3">' . nl2br(htmlspecialchars($autor['caracteristicas'])) . '</td>
                        </tr>';
        }
        
        $html .= '
                    </table>
                </div>
            </div>';
    }
    
    $html .= '
        </div>
    </div>';
}

// TESTEMUNHAS
if (!empty($testemunhas)) {
    $html .= '
    <div class="section">
        <div class="section-title">TESTEMUNHAS</div>
        <div class="section-content">';
    
    foreach ($testemunhas as $index => $testemunha) {
        $html .= '
            <div class="pessoa-card">
                <div class="pessoa-header">Testemunha ' . ($index + 1) . '</div>
                <div class="pessoa-content">
                    <table class="full-width-table">
                        <tr>
                            <td class="label" style="width: 15%;">Nome</td>
                            <td style="width: 35%;">' . limparTexto($testemunha['nome']) . '</td>
                            <td class="label" style="width: 15%;">Nascimento</td>
                            <td style="width: 35%;">' . formatarDataNascimento($testemunha['data_nascimento']) . '</td>
                        </tr>
                        <tr>
                            <td class="label">CPF</td>
                            <td>' . limparTexto($testemunha['cpf']) . '</td>
                            <td class="label">RG</td>
                            <td>' . limparTexto($testemunha['rg']) . '</td>
                        </tr>
                        <tr>
                            <td class="label">Nacionalidade</td>
                            <td>' . limparTexto($testemunha['nacionalidade']) . '</td>
                            <td class="label">Naturalidade</td>
                            <td>' . limparTexto($testemunha['naturalidade']) . '</td>
                        </tr>
                        <tr>
                            <td class="label">Profissão</td>
                            <td>' . limparTexto($testemunha['profissao']) . '</td>
                            <td class="label">Telefone</td>
                            <td>' . limparTexto($testemunha['telefone']) . '</td>
                        </tr>
                        <tr>
                            <td class="label">Pai</td>
                            <td>' . limparTexto($testemunha['pai']) . '</td>
                            <td class="label">Mãe</td>
                            <td>' . limparTexto($testemunha['mae']) . '</td>
                        </tr>
                        <tr>
                            <td class="label">Endereço</td>
                            <td colspan="3">' . limparTexto($testemunha['endereco']) . '</td>
                        </tr>
                    </table>
                </div>
            </div>';
    }
    
    $html .= '
        </div>
    </div>';
}

// HISTÓRICO
if ($recognicao['historico']) {
    $html .= '
    <div class="section">
        <div class="section-title">HISTÓRICO - RELATÓRIO DAS INFORMAÇÕES COLHIDAS NO LOCAL</div>
        <div class="section-content">
            <div class="text-block">' . nl2br(htmlspecialchars($recognicao['historico'])) . '</div>
        </div>
    </div>';
}

// MAPA DO LOCAL (se houver coordenadas)
if ($recognicao['latitude'] && $recognicao['longitude']) {
    $html .= '
    <div class="page-break"></div>
    <div class="section">
        <div class="section-title">MAPA DO LOCAL DO FATO</div>
        <div class="section-content" style="text-align: center;">
            <div class="mapa-container">';
    
    $lat = $recognicao['latitude'];
    $lon = $recognicao['longitude'];
    $zoom = 16;
    
    // Geoapify API
    $geoapifyKey = "c8077ec067d344d7a9de40f6e06fd9c9";
    $geoapifyUrl = "https://maps.geoapify.com/v1/staticmap?style=osm-bright&width=600&height=400&center=lonlat:{$lon},{$lat}&zoom={$zoom}&marker=lonlat:{$lon},{$lat};color:%23ff0000;size:large;type:awesome&apiKey={$geoapifyKey}";
    
    // Google Maps URL
    $googleMapsUrl = "https://maps.google.com/maps?q={$lat},{$lon}";
    
    // QR Code URL
    $qrCodeUrl = "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=" . urlencode($googleMapsUrl);
    
    $html .= '
                <img src="' . $geoapifyUrl . '" style="max-width: 100%; height: auto; margin-bottom: 15px;">
                
                <table style="width: 500px; margin: 15px auto; text-align: left;">
                    <tr>
                        <td style="width: 150px; text-align: center; vertical-align: top;">
                            <img src="' . $qrCodeUrl . '" style="width: 120px; height: 120px;">
                            <p style="font-size: 8pt; margin-top: 5px;">Escaneie para abrir no Google Maps</p>
                        </td>
                        <td style="width: 350px; padding-left: 20px; vertical-align: top;">
                            <div class="mapa-info" style="text-align: left; margin: 0;">
                                <strong>Coordenadas GPS:</strong><br>
                                Latitude: ' . number_format($lat, 6) . '° ' . ($lat < 0 ? 'S' : 'N') . '<br>
                                Longitude: ' . number_format(abs($lon), 6) . '° ' . ($lon < 0 ? 'O' : 'L') . '<br>
                                <br>
                                <strong>Link Google Maps:</strong><br>
                                <span style="font-size: 8pt; color: #1a237e; word-break: break-all;">' . $googleMapsUrl . '</span><br>
                                <br>
                                <small>Precisão: ±5 metros | Sistema: WGS84</small>
                            </div>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
    </div>';
}

// REGISTRO FOTOGRÁFICO
if (!empty($fotos)) {
    $html .= '
    <div class="section">
        <div class="section-title">REGISTRO FOTOGRÁFICO</div>
        <div class="section-content">
            <p style="text-align: center; font-size: 10pt;">
                Total de <strong>' . count($fotos) . '</strong> fotografia(s) anexada(s) ao presente documento
            </p>
        </div>
    </div>
    <pagebreak />';
    
    foreach ($fotos as $index => $foto) {
        $html .= '
        <div style="text-align: center; margin-top: 20px;">
            <h3 style="color: #1a237e; font-size: 14pt;">Fotografia ' . ($index + 1) . ' de ' . count($fotos) . '</h3>';
        
        if (file_exists($foto['arquivo'])) {
            $html .= '<img src="' . $foto['arquivo'] . '" style="max-width: 90%; max-height: 700px; margin-top: 20px;">';
        } else {
            $html .= '<p style="color: #d32f2f; font-weight: bold; margin-top: 50px;">⚠️ Arquivo de imagem não encontrado no servidor</p>';
        }
        
        $html .= '</div>';
        
        // Quebra de página após cada foto, exceto a última
        if ($index < count($fotos) - 1) {
            $html .= '<pagebreak />';
        }
    }
}

// EQUIPE RESPONSÁVEL
$html .= '
    <pagebreak />
    <div class="section">
        <div class="section-title">EQUIPE RESPONSÁVEL PELO PREENCHIMENTO</div>
        <div class="section-content">';

if (!empty($equipe_responsavel)) {
    $html .= '<table class="full-width-table">';
    foreach ($equipe_responsavel as $index => $policial) {
        $html .= '
            <tr>
                <td class="label">Policial ' . ($index + 1) . '</td>
                <td>' . htmlspecialchars($policial['nome']) . 
                ($policial['matricula'] ? ' - Matrícula: ' . $policial['matricula'] : '') . '</td>
            </tr>';
    }
    $html .= '</table>';
} else {
    $html .= '<p style="text-align: center; color: #666;">Informação não disponível</p>';
}

$html .= '
        </div>
    </div>';

// RODAPÉ
$html .= '
    <div class="footer">
        <p><strong>Documento gerado em:</strong> ' . date('d/m/Y \à\s H:i:s') . '</p>
        <p>Sistema de Recognição Visuográfica - Polícia Civil do Estado de Goiás</p>
        <p style="font-size: 8pt; margin-top: 10px;">Este documento é parte integrante do procedimento investigativo</p>
    </div>';

$html .= '
</body>
</html>';

// Configurar mPDF
try {
    // Criar diretório temporário se não existir
    $tempDir = __DIR__ . '/tmp';
    if (!is_dir($tempDir)) {
        mkdir($tempDir, 0755, true);
    }
    
    $mpdf = new \Mpdf\Mpdf([
        'mode' => 'utf-8',
        'format' => 'A4',
        'margin_left' => 15,
        'margin_right' => 15,
        'margin_top' => 25,
        'margin_bottom' => 25,
        'margin_header' => 10,
        'margin_footer' => 10,
        'default_font' => 'dejavusans',
        'tempDir' => $tempDir
    ]);

    // Configurações adicionais
    $mpdf->SetTitle("Recognição Visuográfica - RAI " . ($recognicao['rai'] ?? 'S/N'));
    $mpdf->SetAuthor("Polícia Civil do Estado de Goiás");
    $mpdf->SetCreator("Sistema de Recognição Visuográfica - GIH");
    $mpdf->SetSubject("Recognição Visuográfica");
    $mpdf->SetKeywords("recognição, visuográfica, polícia civil, goiás, gih");

    // Configurar cabeçalho
    $mpdf->SetHTMLHeader('
        <table width="100%" style="border-bottom: 2px solid #1a237e;">
            <tr>
                <td width="50%" style="text-align: left; font-size: 9pt; color: #1a237e;">
                    <strong>POLÍCIA CIVIL DO ESTADO DE GOIÁS</strong><br>
                    <small>Grupo de Investigação de Homicídios (GIH)</small>
                </td>
                <td width="50%" style="text-align: right; font-size: 9pt; color: #666;">
                    Recognição Visuográfica<br>
                    <strong>RAI Nº ' . htmlspecialchars($recognicao['rai'] ?? 'S/N') . '</strong>
                </td>
            </tr>
        </table>
    ');

    // Configurar rodapé
    $mpdf->SetHTMLFooter('
        <table width="100%" style="border-top: 1px solid #ddd; font-size: 8pt; color: #666;">
            <tr>
                <td width="33%" style="text-align: left;">
                    {DATE d/m/Y H:i}
                </td>
                <td width="33%" style="text-align: center;">
                    Página {PAGENO} de {nb}
                </td>
                <td width="33%" style="text-align: right;">
                    Sistema de Recognição Visuográfica
                </td>
            </tr>
        </table>
    ');

    // Configurações de performance
    $mpdf->showImageErrors = true;
    $mpdf->debug = false;
    $mpdf->allow_output_buffering = true;

    // Adicionar metadados (usando método correto do mPDF)
    $mpdf->SetCreator('Sistema de Recognição Visuográfica - PCGO');
    $mpdf->SetAuthor('Polícia Civil do Estado de Goiás - GIH');
    $mpdf->SetSubject('Recognição Visuográfica - RAI ' . ($recognicao['rai'] ?? 'S/N'));
    $mpdf->SetKeywords('recognição, visuográfica, polícia civil, goiás, gih, rai');

    // Escrever HTML
    $mpdf->WriteHTML($html);

    // Nome do arquivo
    $nomeArquivo = 'Recognicao_' . ($recognicao['rai'] ? preg_replace('/[^A-Za-z0-9\-]/', '_', $recognicao['rai']) : $id) . '_' . date('Y-m-d_His') . '.pdf';
    
    // Verificar se é para salvar ou fazer download
    $acao = isset($_GET['acao']) ? $_GET['acao'] : 'download';
    
    if ($acao === 'salvar') {
        // Salvar no servidor
        if (!is_dir(__DIR__ . '/uploads/pdfs')) {
            mkdir(__DIR__ . '/uploads/pdfs', 0755, true);
        }
        
        $caminhoCompleto = __DIR__ . '/uploads/pdfs/' . $nomeArquivo;
        $mpdf->Output($caminhoCompleto, 'F');
        
        // Retornar JSON com informações do arquivo
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'arquivo' => $caminhoCompleto,
            'nome' => $nomeArquivo,
            'url' => 'uploads/pdfs/' . $nomeArquivo,
            'tamanho' => filesize($caminhoCompleto)
        ]);
    } else {
        // Download direto
        $mpdf->Output($nomeArquivo, 'D');
    }

} catch (Exception $e) {
    if (isset($_GET['acao']) && $_GET['acao'] === 'salvar') {
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    } else {
        die("Erro ao gerar PDF: " . $e->getMessage());
    }
}
?>