<?php
// gerar_pdf.php - Gera PDF da recognição
require_once 'config.php';
$pdo = conectarDB();

$id = $_GET['id'] ?? 0;

if (!$id) {
    die("ID não informado");
}

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
$recognicao = $stmt->fetch();

if (!$recognicao) {
    die("Recognição não encontrada");
}

// Buscar dados relacionados
$stmt = $pdo->prepare("SELECT * FROM policiais_preservacao WHERE recognicao_id = ?");
$stmt->execute([$id]);
$policiais_preservacao = $stmt->fetchAll();

$stmt = $pdo->prepare("
    SELECT v.*, pv.descricao as posicao_descricao 
    FROM vitimas v 
    LEFT JOIN posicao_vitima pv ON v.posicao_vitima_id = pv.id 
    WHERE v.recognicao_id = ?
");
$stmt->execute([$id]);
$vitimas = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT * FROM autores WHERE recognicao_id = ?");
$stmt->execute([$id]);
$autores = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT * FROM testemunhas WHERE recognicao_id = ?");
$stmt->execute([$id]);
$testemunhas = $stmt->fetchAll();

// Buscar meios empregados
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
$meios_empregados = $stmt->fetchAll();

// Buscar equipe responsável
$stmt = $pdo->prepare("
    SELECT pg.nome, pg.matricula 
    FROM equipe_responsavel er
    JOIN policiais_gih pg ON er.policial_id = pg.id
    WHERE er.recognicao_id = ?
");
$stmt->execute([$id]);
$equipe_responsavel = $stmt->fetchAll();

// Buscar fotos
$stmt = $pdo->prepare("SELECT * FROM fotos WHERE recognicao_id = ? ORDER BY id");
$stmt->execute([$id]);
$fotos = $stmt->fetchAll();

// Função helper para formatar data
date_default_timezone_set('America/Sao_Paulo'); // GMT-3

function formatarData($data) {
    return $data ? date('d/m/Y H:i', strtotime($data)) : '-';
}

function formatarDataNascimento($data) {
    return $data ? date('d/m/Y', strtotime($data)) : '-';
}

function limparTexto($texto) {
    return $texto ?: '-';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recognição Visuográfica - RAI <?= htmlspecialchars($recognicao['rai'] ?: 'S/N') ?></title>
    <style>
        @media print {
            * {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                color-adjust: exact !important;
            }
            
            @page {
                size: A4;
                margin: 15mm 15mm 20mm 15mm;
            }
            
            .no-print {
                display: none !important;
            }
            
            .page-break {
                page-break-after: always;
            }
            
            .avoid-break {
                page-break-inside: avoid;
            }
            
            body {
                margin: 0;
                padding: 0;
            }
            

        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            font-size: 11pt;
            line-height: 1.6;
            color: #333;
            background: white;
            margin: 0;
            padding: 20px;
        }
        
        .header {
            margin-bottom: 30px;
            border-bottom: 3px solid #1a237e;
            padding-bottom: 20px;
        }
        
        .header-content {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 15px;
            position: relative;
        }
        
        .logo-container {
            position: absolute;
            left: 0;
            top: 0;
        }
        
        .logo {
            height: 80px;
            width: auto;
        }
        
        .header-text {
            text-align: center;
            padding: 0 100px; /* Espaço para o logo */
        }
        
        .header h1 {
            margin: 0;
            color: #1a237e;
            font-size: 16pt;
            font-weight: 700;
            letter-spacing: 1px;
        }
        
        .header h2 {
            margin: 5px 0 0 0;
            color: #666;
            font-size: 14pt;
            font-weight: 400;
        }
        
        .header h3 {
            margin: 5px 0 0 0;
            color: #444;
            font-size: 12pt;
            font-weight: 600;
        }
        
        .rai-info {
            text-align: center;
            background: #f0f0f0;
            padding: 8px;
            border-radius: 5px;
            font-size: 13pt;
            font-weight: 600;
            color: #1a237e;
            margin-top: 10px;
        }
        
        .section {
            margin-bottom: 25px;
            background: #fff;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            overflow: hidden;
            page-break-inside: avoid;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .section-title {
            background: linear-gradient(135deg, #1a237e 0%, #283593 100%);
            color: white;
            padding: 10px 20px;
            font-weight: 600;
            font-size: 13pt;
            letter-spacing: 0.5px;
        }
        
        .section-content {
            padding: 20px;
        }
        
        .info-grid {
            display: table;
            width: 100%;
            border-collapse: collapse;
        }
        
        .info-row {
            display: table-row;
        }
        
        .info-row:nth-child(odd) {
            background-color: #f8f9fa;
        }
        
        .info-label, .info-value {
            display: table-cell;
            padding: 8px 15px;
            vertical-align: top;
            border-bottom: 1px solid #e9ecef;
        }
        
        .info-label {
            font-weight: 600;
            color: #555;
            width: 35%;
        }
        
        .info-value {
            color: #222;
            word-wrap: break-word;
        }
        
        .info-row:last-child .info-label,
        .info-row:last-child .info-value {
            border-bottom: none;
        }
        
        .two-columns {
            display: flex;
            gap: 30px;
            margin-bottom: 15px;
        }
        
        .two-columns .column {
            flex: 1;
        }
        
        .subsection {
            margin: 15px 0;
            padding: 15px;
            background: #f8f9fa;
            border-left: 4px solid #1a237e;
            border-radius: 0 5px 5px 0;
        }
        
        .subsection-title {
            font-weight: 600;
            color: #1a237e;
            margin-bottom: 10px;
            font-size: 12pt;
        }
        
        .pessoa-card {
            margin-bottom: 20px;
            padding: 20px;
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        
        .pessoa-header {
            background: #f0f4f8;
            margin: -20px -20px 15px -20px;
            padding: 12px 20px;
            border-bottom: 2px solid #1a237e;
            font-weight: 600;
            color: #1a237e;
            font-size: 12pt;
        }
        
        .field-inline {
            display: flex;
            align-items: baseline;
            margin-bottom: 8px;
            line-height: 1.4;
        }
        
        .field-inline label {
            font-weight: 600;
            color: #555;
            margin-right: 8px;
            flex-shrink: 0;
        }
        
        .field-inline span {
            color: #222;
        }
        
        .text-block {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
            white-space: pre-wrap;
            word-wrap: break-word;
            line-height: 1.6;
        }
        
        .list-clean {
            list-style: none;
            padding: 0;
            margin: 10px 0;
        }
        
        .list-clean li {
            padding: 5px 0 5px 20px;
            position: relative;
        }
        
        .list-clean li:before {
            content: "▸";
            position: absolute;
            left: 0;
            color: #1a237e;
            font-weight: bold;
        }
        
        .foto-section {
            margin-top: 30px;
        }
        
        .foto-container {
            page-break-inside: avoid;
            text-align: center;
            margin-bottom: 30px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .foto-container.page-break {
            page-break-after: always;
        }
        
        .foto-container:last-child {
            page-break-after: avoid;
        }
        
        .foto-container img {
            max-width: 90%;
            max-height: 600px;
            margin: 20px auto;
            display: block;
            border: 2px solid #ddd;
            border-radius: 5px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .foto-header {
            font-size: 14pt;
            font-weight: 600;
            color: #1a237e;
            margin-bottom: 15px;
        }
        
        .foto-descricao {
            text-align: center;
            font-style: italic;
            color: #666;
            margin-top: 10px;
            font-size: 10pt;
        }
        
        .btn-print {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #1a237e;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14pt;
            font-weight: 600;
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
            z-index: 1000;
            transition: all 0.3s ease;
        }
        
        .btn-print:hover {
            background: #283593;
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0,0,0,0.3);
        }
        
        .footer {
            margin-top: 50px;
            padding-top: 20px;
            border-top: 2px solid #e0e0e0;
            text-align: center;
            color: #666;
            font-size: 10pt;
        }
        

        @media screen {
            body {
                max-width: 900px;
                margin: 0 auto;
                padding: 60px 20px 20px 20px;
                background: #f5f5f5;
            }
            
            .section {
                margin-bottom: 30px;
            }
        }
        
        .historico-text {
    text-align: justify;
    text-indent: 2em;
    line-height: 1.8;
}
    </style>
</head>
<body>
    <button class="btn-print no-print" onclick="window.print()">🖨️ Imprimir / Salvar PDF</button>
    
    <div class="header">
        <div class="header-content">
            <div class="logo-container">
                <img src="https://gestao.seg.br/imagens/logo_pcgo.png" alt="Logo PCGO" class="logo">
            </div>
            <div class="header-text">
                <h1>POLÍCIA CIVIL DO ESTADO DE GOIÁS</h1>
                <h3>Grupo de Investigação de Homicídios (GIH)</h3>
                <h2>RECOGNIÇÃO VISUOGRÁFICA</h2>
            </div>
        </div>
        <div class="rai-info">
            RAI Nº <?= htmlspecialchars($recognicao['rai'] ?: 'NÃO INFORMADO') ?>
        </div>
    </div>
    
    <!-- INFORMAÇÕES GERAIS -->
    <div class="section">
        <div class="section-title">INFORMAÇÕES GERAIS</div>
        <div class="section-content">
            <div class="info-grid">
                <div class="info-row">
                    <div class="info-label">Data/Hora do Acionamento</div>
                    <div class="info-value"><?= formatarData($recognicao['data_hora_acionamento']) ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Data/Hora do Fato</div>
                    <div class="info-value"><?= formatarData($recognicao['data_hora_fato']) ?> - <?= limparTexto($recognicao['dia_semana']) ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Natureza da Ocorrência</div>
                    <div class="info-value"><?= limparTexto($recognicao['natureza']) ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Endereço do Fato</div>
                    <div class="info-value"><?= limparTexto($recognicao['endereco_fato']) ?></div>
                </div>
                <?php if ($recognicao['latitude'] && $recognicao['longitude']): ?>
                <div class="info-row">
                    <div class="info-label">Coordenadas Geográficas</div>
                    <div class="info-value">Latitude: <?= $recognicao['latitude'] ?> | Longitude: <?= $recognicao['longitude'] ?></div>
                </div>
                <?php endif; ?>
                <div class="info-row">
                    <div class="info-label">Preservação do Local</div>
                    <div class="info-value"><?= limparTexto($recognicao['preservacao_local']) ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Perito Responsável</div>
                    <div class="info-value">
                        <?= limparTexto($recognicao['perito_nome']) ?>
                        <?php if ($recognicao['perito_matricula']): ?>
                        (Matrícula: <?= htmlspecialchars($recognicao['perito_matricula']) ?>)
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <?php if (!empty($policiais_preservacao)): ?>
            <div class="subsection">
                <div class="subsection-title">Policiais que Preservaram o Local</div>
                <ul class="list-clean">
                    <?php foreach ($policiais_preservacao as $policial): ?>
                    <li>
                        <?= htmlspecialchars($policial['nome']) ?>
                        <?php if ($policial['matricula']): ?>
                        - Matrícula: <?= htmlspecialchars($policial['matricula']) ?>
                        <?php endif; ?>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- LOCAL DO FATO -->
    <div class="section">
        <div class="section-title">LOCAL DO FATO</div>
        <div class="section-content">
            <div class="info-grid">
                <div class="info-row">
                    <div class="info-label">Tipo de Local</div>
                    <div class="info-value">
                        <?= limparTexto($recognicao['tipo_local']) ?>
                        <?php if ($recognicao['tipo_local'] == 'Externo' && $recognicao['local_externo']): ?>
                        - <?= htmlspecialchars($recognicao['local_externo']) ?>
                        <?php elseif ($recognicao['tipo_local'] == 'Interno' && $recognicao['local_interno']): ?>
                        - <?= htmlspecialchars($recognicao['local_interno']) ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="info-row">
                    <div class="info-label">Tipo de Piso</div>
                    <div class="info-value"><?= limparTexto($recognicao['tipo_piso']) ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Disposição dos Objetos</div>
                    <div class="info-value"><?= limparTexto($recognicao['disposicao_objetos']) ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Condições de Higiene</div>
                    <div class="info-value"><?= limparTexto($recognicao['condicoes_higiene']) ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Câmeras de Segurança</div>
                    <div class="info-value"><?= limparTexto($recognicao['cameras_monitoramento']) ?></div>
                </div>
            </div>
            
            <?php if ($recognicao['objetos_recolhidos']): ?>
            <div class="subsection">
                <div class="subsection-title">Objetos Recolhidos (inclusive pela Perícia)</div>
                <div class="text-block"><?= htmlspecialchars($recognicao['objetos_recolhidos']) ?></div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- MEIOS EMPREGADOS -->
    <?php if (!empty($meios_empregados)): ?>
    <div class="section">
        <div class="section-title">MEIOS EMPREGADOS</div>
        <div class="section-content">
            <?php 
            $veiculos = array_filter($meios_empregados, fn($m) => $m['tipo'] == 'veiculo');
            $armas = array_filter($meios_empregados, fn($m) => $m['tipo'] == 'arma');
            ?>
            
            <div class="two-columns">
                <?php if (!empty($veiculos)): ?>
                <div class="column">
                    <div class="subsection-title">Veículos Utilizados</div>
                    <ul class="list-clean">
                        <?php foreach ($veiculos as $veiculo): ?>
                        <li><?= htmlspecialchars($veiculo['descricao']) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($armas)): ?>
                <div class="column">
                    <div class="subsection-title">Armas Empregadas</div>
                    <ul class="list-clean">
                        <?php foreach ($armas as $arma): ?>
                        <li><?= htmlspecialchars($arma['descricao']) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- VÍTIMAS -->
    <?php if (!empty($vitimas)): ?>
    <div class="section">
        <div class="section-title">VÍTIMAS</div>
        <div class="section-content">
            <?php foreach ($vitimas as $index => $vitima): ?>
            <div class="pessoa-card">
                <div class="pessoa-header">Vítima <?= $index + 1 ?></div>
                
                <div class="two-columns">
                    <div class="column">
                        <div class="field-inline">
                            <label>Nome:</label>
                            <span><?= limparTexto($vitima['nome']) ?></span>
                        </div>
                        <div class="field-inline">
                            <label>Data de Nascimento:</label>
                            <span><?= formatarDataNascimento($vitima['data_nascimento']) ?></span>
                        </div>
                        <div class="field-inline">
                            <label>CPF:</label>
                            <span><?= limparTexto($vitima['cpf']) ?></span>
                        </div>
                        <div class="field-inline">
                            <label>RG:</label>
                            <span><?= limparTexto($vitima['rg']) ?></span>
                        </div>
                        <div class="field-inline">
                            <label>Cor:</label>
                            <span><?= limparTexto($vitima['cor']) ?></span>
                        </div>
                        <div class="field-inline">
                            <label>Profissão:</label>
                            <span><?= limparTexto($vitima['profissao']) ?></span>
                        </div>
                    </div>
                    <div class="column">
                        <div class="field-inline">
                            <label>Nacionalidade:</label>
                            <span><?= limparTexto($vitima['nacionalidade']) ?></span>
                        </div>
                        <div class="field-inline">
                            <label>Naturalidade:</label>
                            <span><?= limparTexto($vitima['naturalidade']) ?></span>
                        </div>
                        <div class="field-inline">
                            <label>Pai:</label>
                            <span><?= limparTexto($vitima['pai']) ?></span>
                        </div>
                        <div class="field-inline">
                            <label>Mãe:</label>
                            <span><?= limparTexto($vitima['mae']) ?></span>
                        </div>
                        <div class="field-inline">
                            <label>Situação:</label>
                            <span style="font-weight: 600; color: <?= $vitima['situacao'] == 'FATAL' ? '#d32f2f' : '#388e3c' ?>">
                                <?= limparTexto($vitima['situacao']) ?>
                            </span>
                        </div>
                        <?php if ($vitima['situacao'] == 'FATAL' && $vitima['posicao_descricao']): ?>
                        <div class="field-inline">
                            <label>Posição:</label>
                            <span><?= htmlspecialchars($vitima['posicao_descricao']) ?></span>
                        </div>
                        <?php elseif ($vitima['situacao'] == 'SOBREVIVENTE' && $vitima['hospital_socorrido']): ?>
                        <div class="field-inline">
                            <label>Hospital:</label>
                            <span><?= htmlspecialchars($vitima['hospital_socorrido']) ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="field-inline">
                    <label>Endereço:</label>
                    <span><?= limparTexto($vitima['endereco']) ?></span>
                </div>
                
                <?php if ($vitima['lesoes_apresentadas']): ?>
                <div style="margin-top: 10px;">
                    <label style="font-weight: 600; color: #555;">Lesões Apresentadas:</label>
                    <div class="text-block"><?= htmlspecialchars($vitima['lesoes_apresentadas']) ?></div>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- AUTORES -->
    <?php if (!empty($autores)): ?>
    <div class="section">
        <div class="section-title">SUPOSTA AUTORIA</div>
        <div class="section-content">
            <?php foreach ($autores as $index => $autor): ?>
            <div class="pessoa-card">
                <div class="pessoa-header">Autor <?= $index + 1 ?></div>
                
                <div class="two-columns">
                    <div class="column">
                        <div class="field-inline">
                            <label>Nome:</label>
                            <span><?= limparTexto($autor['nome']) ?></span>
                        </div>
                        <div class="field-inline">
                            <label>Data de Nascimento:</label>
                            <span><?= formatarDataNascimento($autor['data_nascimento']) ?></span>
                        </div>
                        <div class="field-inline">
                            <label>CPF:</label>
                            <span><?= limparTexto($autor['cpf']) ?></span>
                        </div>
                        <div class="field-inline">
                            <label>RG:</label>
                            <span><?= limparTexto($autor['rg']) ?></span>
                        </div>
                        <div class="field-inline">
                            <label>Cor:</label>
                            <span><?= limparTexto($autor['cor']) ?></span>
                        </div>
                        <div class="field-inline">
                            <label>Profissão:</label>
                            <span><?= limparTexto($autor['profissao']) ?></span>
                        </div>
                    </div>
                    <div class="column">
                        <div class="field-inline">
                            <label>Nacionalidade:</label>
                            <span><?= limparTexto($autor['nacionalidade']) ?></span>
                        </div>
                        <div class="field-inline">
                            <label>Naturalidade:</label>
                            <span><?= limparTexto($autor['naturalidade']) ?></span>
                        </div>
                        <div class="field-inline">
                            <label>Pai:</label>
                            <span><?= limparTexto($autor['pai']) ?></span>
                        </div>
                        <div class="field-inline">
                            <label>Mãe:</label>
                            <span><?= limparTexto($autor['mae']) ?></span>
                        </div>
                    </div>
                </div>
                
                <div class="field-inline">
                    <label>Endereço:</label>
                    <span><?= limparTexto($autor['endereco']) ?></span>
                </div>
                
                <?php if ($autor['caracteristicas']): ?>
                <div style="margin-top: 10px;">
                    <label style="font-weight: 600; color: #555;">Características/Sinais Particulares:</label>
                    <div class="text-block"><?= htmlspecialchars($autor['caracteristicas']) ?></div>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- TESTEMUNHAS -->
    <?php if (!empty($testemunhas)): ?>
    <div class="section">
        <div class="section-title">TESTEMUNHAS</div>
        <div class="section-content">
            <?php foreach ($testemunhas as $index => $testemunha): ?>
            <div class="pessoa-card">
                <div class="pessoa-header">Testemunha <?= $index + 1 ?></div>
                
                <div class="two-columns">
                    <div class="column">
                        <div class="field-inline">
                            <label>Nome:</label>
                            <span><?= limparTexto($testemunha['nome']) ?></span>
                        </div>
                        <div class="field-inline">
                            <label>Data de Nascimento:</label>
                            <span><?= formatarDataNascimento($testemunha['data_nascimento']) ?></span>
                        </div>
                        <div class="field-inline">
                            <label>CPF:</label>
                            <span><?= limparTexto($testemunha['cpf']) ?></span>
                        </div>
                        <div class="field-inline">
                            <label>RG:</label>
                            <span><?= limparTexto($testemunha['rg']) ?></span>
                        </div>
                        <div class="field-inline">
                            <label>Telefone:</label>
                            <span><?= limparTexto($testemunha['telefone']) ?></span>
                        </div>
                    </div>
                    <div class="column">
                        <div class="field-inline">
                            <label>Nacionalidade:</label>
                            <span><?= limparTexto($testemunha['nacionalidade']) ?></span>
                        </div>
                        <div class="field-inline">
                            <label>Naturalidade:</label>
                            <span><?= limparTexto($testemunha['naturalidade']) ?></span>
                        </div>
                        <div class="field-inline">
                            <label>Profissão:</label>
                            <span><?= limparTexto($testemunha['profissao']) ?></span>
                        </div>
                        <div class="field-inline">
                            <label>Pai:</label>
                            <span><?= limparTexto($testemunha['pai']) ?></span>
                        </div>
                        <div class="field-inline">
                            <label>Mãe:</label>
                            <span><?= limparTexto($testemunha['mae']) ?></span>
                        </div>
                    </div>
                </div>
                
                <div class="field-inline">
                    <label>Endereço:</label>
                    <span><?= limparTexto($testemunha['endereco']) ?></span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- HISTÓRICO -->
    <?php if ($recognicao['historico']): ?>
    <div class="section">
        <div class="section-title">HISTÓRICO - RELATÓRIO DAS INFORMAÇÕES COLHIDAS NO LOCAL</div>
        <div class="section-content">
    <div class="section-content">
        <p style="text-align: justify; line-height: 1.8;">
            <?= nl2br(htmlspecialchars($recognicao['historico'])) ?>
        </p>
    </div>
        </div>
    </div>
    <!-- MAPA DO LOCAL -->
    <?php if ($recognicao['latitude'] && $recognicao['longitude']): ?>
    <div class="section page-break">
        <div class="section-title">MAPA DO LOCAL DO FATO</div>
        <div class="section-content">
            <div style="text-align: center;">
                <?php 
                $lat = $recognicao['latitude'];
                $lon = $recognicao['longitude'];
                $zoom = 16; // Reduzido de 17 para 16
                
                // Geoapify API configurada com sua chave
                $geoapifyKey = "c8077ec067d344d7a9de40f6e06fd9c9";
                $geoapifyUrl = "https://maps.geoapify.com/v1/staticmap?style=osm-bright&width=400&height=350&center=lonlat:{$lon},{$lat}&zoom={$zoom}&marker=lonlat:{$lon},{$lat};color:%23ff0000;size:large;type:awesome&apiKey={$geoapifyKey}";
                ?>
                
                <!-- Layout compacto com mapa e informações lado a lado -->
                <div style="display: flex; gap: 20px; align-items: flex-start; margin: 20px 0;">
                    <!-- Mapa -->
                    <div style="flex: 1; min-width: 400px; height: 350px; position: relative; border: 2px solid #ddd; border-radius: 5px; overflow: hidden; background: #f5f5f5;">
                        <!-- Mapa Geoapify com marcador preciso -->
                        <img src="<?= $geoapifyUrl ?>" 
                             alt="Mapa do local" 
                             style="width: 100%; height: 100%; object-fit: cover;"
                             onerror="this.style.display='none'; document.getElementById('mapFallback').style.display='flex';">
                        
                        <!-- Fallback caso a API falhe -->
                        <div id="mapFallback" style="display: none; width: 100%; height: 100%; flex-direction: column; justify-content: center; align-items: center; background: linear-gradient(45deg, #e8f5e9 25%, #f1f8e9 25%, #f1f8e9 50%, #e8f5e9 50%, #e8f5e9 75%, #f1f8e9 75%, #f1f8e9); background-size: 20px 20px;">
                            <div style="background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); text-align: center;">
                                <div style="font-size: 24pt; color: #ff0000; margin-bottom: 10px;">📍</div>
                                <div style="font-size: 11pt; font-weight: bold; color: #333;">Local do Fato</div>
                                <div style="font-size: 10pt; color: #666; margin-top: 5px;">
                                    <?= number_format($lat, 6) ?>° <?= $lat < 0 ? 'S' : 'N' ?><br>
                                    <?= number_format(abs($lon), 6) ?>° <?= $lon < 0 ? 'O' : 'L' ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Informações e QR Code -->
                    <div style="flex: 0 0 auto; width: 180px;"> <!-- Reduzido de 220px para 180px -->
                        <!-- QR Code -->
                        <div style="background: #f8f9fa; border-radius: 8px; padding: 12px; text-align: center; margin-bottom: 12px;"> <!-- Padding reduzido -->
                            <?php 
                            // Link direto para o Google Maps com marcador
                            $googleMapsUrl = "https://maps.google.com/maps?q={$lat},{$lon}&ll={$lat},{$lon}&z={$zoom}";
                            // Alternativa: Link para o Google Maps com direções
                            $googleMapsDirections = "https://www.google.com/maps/dir/?api=1&destination={$lat},{$lon}";
                            
                            $qrCodeUrl = "https://api.qrserver.com/v1/create-qr-code/?size=120x120&data=" . urlencode($googleMapsUrl); // Reduzido de 150x150
                            ?>
                            <img src="<?= $qrCodeUrl ?>" 
                                 alt="QR Code para Google Maps" 
                                 style="width: 120px; height: 120px; display: block; margin: 0 auto 8px;"> <!-- QR menor -->
                            <p style="font-size: 9pt; color: #333; margin: 0; font-weight: 600;"> <!-- Fonte menor -->
                                Google Maps
                            </p>
                            <p style="font-size: 7pt; color: #666; margin: 2px 0 0 0;"> <!-- Fonte menor -->
                                Escaneie para navegar
                            </p>
                        </div>
                        
                        <!-- Coordenadas compactas -->
                        <div style="background: #fff; border: 1px solid #e0e0e0; border-radius: 5px; padding: 8px; font-size: 8pt;"> <!-- Padding e fonte menores -->
                            <div style="margin-bottom: 6px;">
                                <strong>Coordenadas GPS:</strong>
                            </div>
                            <div style="color: #333; line-height: 1.3;"> <!-- Line-height reduzido -->
                                <strong>Lat:</strong> <?= number_format($lat, 6) ?>° <?= $lat < 0 ? 'S' : 'N' ?><br>
                                <strong>Lon:</strong> <?= number_format(abs($lon), 6) ?>° <?= $lon < 0 ? 'O' : 'L' ?>
                            </div>
                            <div style="margin-top: 6px; padding-top: 6px; border-top: 1px solid #e0e0e0;">
                                <small style="color: #666; font-size: 7pt;"> <!-- Fonte ainda menor -->
                                    Precisão: ±5 metros<br>
                                    Sistema: WGS84
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Endereço em linha separada -->
                <div style="background: #f8f9fa; border-radius: 5px; padding: 12px 20px; margin-top: 15px;">
                    <strong style="color: #555;">Endereço do Fato:</strong> 
                    <span style="color: #222;"><?= htmlspecialchars($recognicao['endereco_fato'] ?: 'Não informado') ?></span>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <?php endif; ?>
    
    <!-- FOTOS -->
    
    <!-- FOTOS -->
    <?php if (!empty($fotos)): ?>
    <div class="section page-break">
        <div class="section-title">REGISTRO FOTOGRÁFICO</div>
        <div class="section-content">
            <p style="text-align: center; font-size: 12pt; margin: 20px 0;">
                Total de <strong><?= count($fotos) ?></strong> fotografia(s) anexada(s) ao presente documento
            </p>
        </div>
    </div>
    
    <?php foreach ($fotos as $index => $foto): ?>
    <div class="foto-container page-break">
        <div class="foto-header">
            Fotografia <?= $index + 1 ?> de <?= count($fotos) ?>
        </div>
        <?php if (file_exists($foto['arquivo'])): ?>
            <img src="<?= htmlspecialchars($foto['arquivo']) ?>" alt="Foto <?= $index + 1 ?>">
            <div class="foto-descricao"><?= htmlspecialchars($foto['descricao']) ?></div>
        <?php else: ?>
            <p style="text-align: center; color: #d32f2f; font-weight: 600;">
                ⚠️ Arquivo de imagem não encontrado no servidor
            </p>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
    
    <!-- EQUIPE RESPONSÁVEL -->
    <div class="section avoid-break" style="page-break-before: always;">
        <div class="section-title">EQUIPE RESPONSÁVEL PELO PREENCHIMENTO</div>
        <div class="section-content">
            <?php if (!empty($equipe_responsavel)): ?>
                <div class="info-grid">
                    <?php foreach ($equipe_responsavel as $index => $policial): ?>
                    <div class="info-row">
                        <div class="info-label">Policial <?= $index + 1 ?></div>
                        <div class="info-value">
                            <?= htmlspecialchars($policial['nome']) ?>
                            <?php if ($policial['matricula']): ?>
                            - Matrícula: <?= htmlspecialchars($policial['matricula']) ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p style="text-align: center; color: #666;">Informação não disponível</p>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- RODAPÉ -->
    <div class="footer">
        <p><strong>Documento gerado em:</strong> <?= date('d/m/Y \à\s H:i:s') ?></p>
        <p>Sistema de Recognição Visuográfica - Polícia Civil do Estado de Goiás</p>
        <p style="font-size: 9pt; margin-top: 10px;">Este documento é parte integrante do procedimento investigativo</p>
    </div>
    
    <script>
    // Auto print quando acessado com parâmetro print=1
    if (window.location.search.includes('print=1')) {
        window.onload = function() {
            window.print();
        };
    }
    </script>
</body>
</html>
<?php return $recognicao; ?>
