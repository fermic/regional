<?php
// gerar_pdf_tcpdf.php - Versão SIMPLES e FUNCIONAL
require_once 'config.php';

// Verificar se TCPDF está disponível
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
} elseif (file_exists(__DIR__ . '/vendor/tecnickcom/tcpdf/tcpdf.php')) {
    require_once __DIR__ . '/vendor/tecnickcom/tcpdf/tcpdf.php';
} else {
    die('TCPDF não está instalado. Execute instalar_tcpdf.php primeiro.');
}

$id = $_GET['id'] ?? 0;
if (!$id) {
    die("ID não informado");
}

try {
    $pdo = conectarDB();
    
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
    
    // Buscar VEÍCULOS separadamente (CORRIGIDO)
    $stmt = $pdo->prepare("
        SELECT v.descricao 
        FROM meios_empregados me
        JOIN veiculos v ON me.item_id = v.id
        WHERE me.recognicao_id = ? AND me.tipo = 'veiculo'
    ");
    $stmt->execute([$id]);
    $veiculos_empregados = $stmt->fetchAll();
    
    // Buscar ARMAS separadamente (CORRIGIDO)
    $stmt = $pdo->prepare("
        SELECT a.descricao 
        FROM meios_empregados me
        JOIN armas a ON me.item_id = a.id
        WHERE me.recognicao_id = ? AND me.tipo = 'arma'
    ");
    $stmt->execute([$id]);
    $armas_empregadas = $stmt->fetchAll();
    
    // DEBUG
    error_log("Veículos encontrados: " . print_r($veiculos_empregados, true));
    error_log("Armas encontradas: " . print_r($armas_empregadas, true));
    
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
    
    // Funções helper
    date_default_timezone_set('America/Sao_Paulo');
    
    function formatarData($data) {
        return $data ? date('d/m/Y H:i', strtotime($data)) : '-';
    }
    
    function formatarDataNascimento($data) {
        return $data ? date('d/m/Y', strtotime($data)) : '-';
    }
    
    function limparTexto($texto) {
        return $texto ?: '-';
    }
    
    // Criar PDF
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    
    // Configurações do documento
    $pdf->SetCreator('Sistema PCGO');
    $pdf->SetAuthor('Polícia Civil de Goiás - GIH');
    $pdf->SetTitle('Recognição Visuográfica - RAI ' . ($recognicao['rai'] ?: 'S/N'));
    
    // Configurar cabeçalho personalizado SIMPLES
    $pdf->SetHeaderData('', 0, 'RECOGNIÇÃO VISUOGRÁFICA - PCGO', 
        'RAI: ' . ($recognicao['rai'] ?: 'NÃO INFORMADO'), 
        array(26, 35, 126), array(26, 35, 126));
    $pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, 'B', 9));
    
    // Configurar rodapé
    $pdf->setFooterData(array(0,64,0), array(0,64,128));
    $pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', 8));
    
    
// Definir margens
$pdf->SetMargins(PDF_MARGIN_LEFT, 20, PDF_MARGIN_RIGHT); // Reduzir margem superior para 10 (ou menos)
$pdf->SetHeaderMargin(3);  // Diminuir espaço do cabeçalho
$pdf->SetFooterMargin(PDF_MARGIN_FOOTER);


    
    $pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);
    $pdf->SetFont('helvetica', '', 10);
    
    
    // Adicionar primeira página
    $pdf->AddPage();
    
    // CONTEÚDO HTML PRINCIPAL
    $html = gerarHTMLSimples($recognicao, $policiais_preservacao, $vitimas, $autores, $testemunhas, $veiculos_empregados, $armas_empregadas);
    $pdf->writeHTML($html, true, false, true, false, '');
    
    // MAPA (se houver coordenadas)
    if ($recognicao['latitude'] && $recognicao['longitude']) {
        $pdf->AddPage();
        adicionarSecaoMapa($pdf, $recognicao);
    }
    
    // FOTOS (se houver)
    if (!empty($fotos)) {
        $pdf->AddPage();
        adicionarSecaoRegistroFotografico($pdf, count($fotos));
        
        foreach ($fotos as $index => $foto) {
            $pdf->AddPage();
            adicionarFoto($pdf, $foto, $index + 1, count($fotos));
        }
    }
    
    // EQUIPE RESPONSÁVEL
    $pdf->AddPage();
    adicionarSecaoEquipeResponsavel($pdf, $equipe_responsavel);
    
    // Output
    $acao = $_GET['acao'] ?? 'download';
    
    if ($acao === 'salvar') {
        $nome_arquivo = 'Recognicao_' . ($recognicao['rai'] ? preg_replace('/[^A-Za-z0-9\-]/', '_', $recognicao['rai']) : $id) . '_' . date('Y-m-d_H-i-s') . '.pdf';
        
        if (!is_dir(__DIR__ . '/uploads/pdfs')) {
            mkdir(__DIR__ . '/uploads/pdfs', 0755, true);
        }
        
        $caminho_completo = __DIR__ . '/uploads/pdfs/' . $nome_arquivo;
        $pdf->Output($caminho_completo, 'F');
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'arquivo' => $caminho_completo,
            'nome' => $nome_arquivo,
            'url' => 'uploads/pdfs/' . $nome_arquivo
        ]);
    } else {
        $nome_arquivo = 'Recognicao_' . ($recognicao['rai'] ? preg_replace('/[^A-Za-z0-9\-]/', '_', $recognicao['rai']) : $id) . '.pdf';
        $pdf->Output($nome_arquivo, 'D');
    }
    
} catch (Exception $e) {
    if ($_GET['acao'] === 'salvar') {
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    } else {
        die("Erro: " . $e->getMessage());
    }
}

function renderTituloSecao($numero, $titulo) {
    return '
    <table width="100%" cellpadding="5" cellspacing="0">
        <tr>
            <td style="
                background-color: #e3f2fd;
                border-left: 5px solid #1a237e;
                color: #1a237e;
                font-weight: bold;
                font-size: 11pt;
                padding: 15px 15px 15px 15px; /* top right bottom left */
            ">
                ' . htmlspecialchars($numero) . ') ' . htmlspecialchars($titulo) . '
            </td>
        </tr>
    </table>
    ';
}


function gerarHTMLSimples($recognicao, $policiais_preservacao, $vitimas, $autores, $testemunhas, $veiculos_empregados, $armas_empregadas) {
    ob_start();
    ?>
    <style>
        /* CSS SIMPLES E FUNCIONAL */
        body { 
            font-family: helvetica; 
            font-size: 10pt; 
            line-height: 1.4; 
            color: #333; 
        }
        
        .titulo-principal {
            text-align: center;
            margin-bottom: 20px;
            padding: 15px;
            background: #f0f0f0;
            border: 2px solid #1a237e;
        }
        
        .titulo-principal h1 {
            color: #1a237e;
            font-size: 14pt;
            font-weight: bold;
            margin: 0 0 5px 0;
        }
        
        .titulo-principal h2 {
            color: #1a237e;
            font-size: 11pt;
            margin: 0;
        }
        
        .rai-destaque {
            background: #1a237e;
            color: white;
            padding: 10px;
            text-align: center;
            font-weight: bold;
            margin: 15px 0;
            font-size: 12pt;
        }
        
        .secao {
            margin-bottom: 20px;
            border: 1px solid #ddd;
        }
        



    
        .tabela-info {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
        }
        
        .tabela-info td {
            padding: 6px 10px;
            border-bottom: 1px solid #ddd;
            vertical-align: top;
        }
        
        .tabela-info td:first-child {
            background: #f5f5f5;
            font-weight: bold;
            width: 30%;
        }
        
        .tabela-meios {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
            border: 1px solid #ddd;
        }
        
        .tabela-meios th {
            background: #1a237e !important;
            color: white !important;
            padding: 8px;
            text-align: center;
            font-weight: bold;
            border: 1px solid #ddd;
        }
        
        .tabela-meios td {
            padding: 8px;
            text-align: center;
            border: 1px solid #ddd;
        }
        
        .tabela-meios tr:nth-child(even) {
            background: #f9f9f9;
        }
        
        .pessoa-card {
            border: 1px solid #ddd;
            margin: 8px 0;
            page-break-inside: avoid;
        }
        
        .pessoa-titulo {
            background: #e0e0e0;
            padding: 6px 15px;
            font-weight: bold;
            border-bottom: 1px solid #ddd;
        }
        
        .pessoa-conteudo {
            padding: 2px 10px 5px 10px;
            margin: 0;
        }
        
        .coluna:last-child {
            padding-right: 0;
        }
        
        .campo {
            margin: 1px 0;
            font-size: 9pt;
            line-height: 1.2;
        }
        
        .campo-label {
            font-weight: bold;
            display: inline-block;
            width: 38%;
            color: #444;
        }
        
        .campo-valor {
            display: inline-block;
            width: 60%;
            color: #222;
        }
        
.situacao-fatal {
    background-color: #d32f2f !important;  /* Vermelho intenso */
    color: white !important;               /* Cor do texto branca */
    padding: 4px 8px !important;           /* Padding para dar destaque */
    border-radius: 4px !important;         /* Bordas arredondadas */
    font-weight: bold !important;          /* Texto em negrito */
    text-transform: uppercase !important;  /* Letras maiúsculas */
    display: inline-block !important;      /* Para respeitar padding e width */
    min-width: 80px !important;            /* Largura mínima */
    text-align: center !important;         /* Centralizar texto */
}

.situacao-sobrevivente {
    background-color: #2E7D32 !important;  /* Verde escuro */
    color: white !important;               /* Cor do texto branca */
    padding: 4px 8px !important;           /* Padding para dar destaque */
    border-radius: 4px !important;         /* Bordas arredondadas */
    font-weight: bold !important;          /* Texto em negrito */
    text-transform: uppercase !important;  /* Letras maiúsculas */
    display: inline-block !important;      /* Para respeitar padding e width */
    min-width: 80px !important;            /* Largura mínima */
    text-align: center !important;         /* Centralizar texto */
}

/* Adicionar para garantir que o texto seja branco */
.situacao-fatal,
.situacao-sobrevivente {
    color: white !important;
}
        .relato {
            background: #f9f9f9;
            padding: 15px;
            border: 1px solid #ddd;
            text-align: justify;
            line-height: 1.6;
            text-indent: 2em;
        }
        
        .lista-simples {
            margin: 10px 0;
            padding-left: 20px;
        }
        
        .lista-simples li {
            margin: 3px 0;
        }
        
        .campo-completo {
            margin: 4px 0 2px 0;
            padding: 6px 8px;
            background: #f9f9f9;
            font-size: 9pt;
        }
        
        .campo-completo .label {
            font-weight: bold;
            color: #444;
            display: block;
            margin-bottom: 3px;
        }
    </style>
    
<!-- CABEÇALHO SIMPLES COM LOGO -->
<div class="titulo-principal">
    <img src="imagens/logo_pcgo.png" alt="Logo PCGO" style="height: 80px; display: block; margin: 0 auto 10px auto;">
    
    <h1>POLÍCIA CIVIL DO ESTADO DE GOIÁS</h1>
    <h2>Grupo de Investigação de Homicídios (GIH)</h2>
    <h2>RECOGNIÇÃO VISUOGRÁFICA</h2>
</div>

    
    <div class="rai-destaque">
        RAI Nº <?= htmlspecialchars($recognicao['rai'] ?: 'NÃO INFORMADO') ?>
    </div>
    
    <!-- 1) INFORMAÇÕES GERAIS -->
<table class="tabela-secao" width="100%">
    <tr>
        <td>
            <?= renderTituloSecao(1, 'Informações Gerais'); ?><br><br>

            <table class="tabela-info" width="100%" style="table-layout: fixed;">

                <tr>
                    <td style="width: 35%; background: #f5f5f5; font-weight: bold;">Data/Hora do Acionamento</td>
                    <td style="width: 65%;"><?= formatarData($recognicao['data_hora_acionamento']) ?></td>
                </tr>
                <tr>
                    <td style="width: 35%; background: #f5f5f5; font-weight: bold;">Data/Hora do Fato</td>
                    <td style="width: 65%;"><?= formatarData($recognicao['data_hora_fato']) ?></td>
                </tr>
                <tr>
                    <td style="width: 35%; background: #f5f5f5; font-weight: bold;">Natureza da Ocorrência</td>
                    <td style="width: 65%;"><?= limparTexto($recognicao['natureza']) ?></td>
                </tr>
                <tr>
                    <td style="width: 35%; background: #f5f5f5; font-weight: bold;">Endereço do Fato</td>
                    <td style="width: 65%;"><?= limparTexto($recognicao['endereco_fato']) ?></td>
                </tr>
                <tr>
                    <td style="width: 35%; background: #f5f5f5; font-weight: bold;">Preservação do Local</td>
                    <td style="width: 65%;"><?= limparTexto($recognicao['preservacao_local']) ?></td>
                </tr>

            </table>
            
<table class="tabela-info" width="100%" style="table-layout: fixed;">
    <tr>
        <td style="width: 35%; background: #f5f5f5; font-weight: bold;">Perito Responsável</td>
        <td style="width: 65%;"><?= limparTexto($recognicao['perito_nome']) ?></td>
    </tr>
</table>


            <br><br>

            <?php if (!empty($policiais_preservacao)): ?>
                <table width="100%" cellpadding="6" cellspacing="0" style="border: 1px solid #ddd; margin-top: 10px;">
                    <tr>
                        <td>
                            <strong>Policiais que preservaram o local:</strong>
                            <ul class="lista-simples">
                                <?php foreach ($policiais_preservacao as $policial): ?>
                                    <li>
                                        <?= htmlspecialchars($policial['nome']) ?>
                                        <?php if ($policial['matricula']): ?>
                                            - Mat: <?= $policial['matricula'] ?>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </td>
                    </tr>
                </table>
            <?php endif; ?>
        </td>
    </tr>
</table>


    <!-- 2) INFORMAÇÕES DO LOCAL -->
<br><br><br>
<table class="tabela-secao">
    <tr>
        <td>
            <?= renderTituloSecao(2, 'Informações do Local'); ?><br><br>
<table class="tabela-info">
    <tr>
        <td>Tipo de Local</td>
        <td>
            <?= limparTexto($recognicao['tipo_local']) ?>
            <?php if ($recognicao['tipo_local'] === 'Externo' && $recognicao['local_externo']): ?>
                - <?= limparTexto($recognicao['local_externo']) ?>
            <?php elseif ($recognicao['tipo_local'] === 'Interno' && $recognicao['local_interno']): ?>
                - <?= limparTexto($recognicao['local_interno']) ?>
            <?php endif; ?>
        </td>
    </tr>
</table>

            <table class="tabela-info">

                <tr>
                    <td>Tipo de Piso</td>
                    <td><?= limparTexto($recognicao['tipo_piso']) ?></td>
                </tr>
                <tr>
                    <td>Disposição dos Objetos</td>
                    <td><?= limparTexto($recognicao['disposicao_objetos']) ?></td>
                </tr>
                <tr>
                    <td>Condições de Higiene</td>
                    <td><?= limparTexto($recognicao['condicoes_higiene']) ?></td>
                </tr>
                <tr>
                    <td>Câmeras de Segurança</td>
                    <td><?= limparTexto($recognicao['cameras_monitoramento']) ?></td>
                </tr>
            </table>
<br><br>
            <?php if ($recognicao['objetos_recolhidos']): ?>
                <table width="100%" cellpadding="8" cellspacing="0" style="border:1px solid #ddd; margin-top:10px;">
                    <tr>
                        <td>
                            <strong>Objetos Recolhidos (inclusive pela Perícia):</strong><br><br>
                            <?= nl2br(htmlspecialchars($recognicao['objetos_recolhidos'])) ?>
                        </td>
                    </tr>
                </table>
            <?php endif; ?>
        </td>
    </tr>
</table>

            

    
    <!-- MEIOS EMPREGADOS - SEÇÃO SEPARADA -->
<br><br><br>
<table class="tabela-secao">
    <tr>
        <td>
            <?= renderTituloSecao(3, 'Meios Empregados'); ?><br><br>

            <?php 
            $max_items = max(count($veiculos_empregados), count($armas_empregadas), 1);
            ?>

            <table class="tabela-meios" width="100%" cellpadding="6" cellspacing="0" border="1" style="border-collapse: collapse;">
                <thead>
                    <tr>
                        <th style="width: 50%; background-color: #1a237e; color: white; padding: 8px;">VEÍCULO UTILIZADO</th>
                        <th style="width: 50%; background-color: #1a237e; color: white; padding: 8px;">ARMA EMPREGADA</th>
                    </tr>
                </thead>
                <tbody>
                    <?php for ($i = 0; $i < $max_items; $i++): ?>
                        <tr>
                            <td style="padding: 8px; text-align: center;">
                                <?php if (isset($veiculos_empregados[$i])): ?>
                                    <?= htmlspecialchars($veiculos_empregados[$i]['descricao']) ?>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td style="padding: 8px; text-align: center;">
                                <?php if (isset($armas_empregadas[$i])): ?>
                                    <?= htmlspecialchars($armas_empregadas[$i]['descricao']) ?>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endfor; ?>
                </tbody>
            </table>
        </td>
    </tr>
</table>

    
    <!-- 4) VÍTIMAS -->
<br><br><br>
<table class="tabela-secao">
    <tr>
        <td>
            <?= renderTituloSecao(4, 'Vítima(s)'); ?><br><br>

            <?php foreach ($vitimas as $index => $vitima): ?>
                <table width="100%" cellpadding="6" cellspacing="0" border="1" style="border-collapse: collapse; margin-bottom: 10px;">
                    <tr style="background-color: #f0f0f0;">
                        <td colspan="2" style="padding: 8px; font-weight: bold;">
                            Vítima <?= $index + 1 ?>
                        </td>
                    </tr>
                    <tr>
                        <td style="width: 50%; vertical-align: top; padding: 8px;">
                            <strong>Nome:</strong> <?= limparTexto($vitima['nome']) ?><br>
                            <strong>Data Nascimento:</strong> <?= formatarDataNascimento($vitima['data_nascimento']) ?><br>
                            <strong>CPF:</strong> <?= limparTexto($vitima['cpf']) ?><br>
                            <strong>RG:</strong> <?= limparTexto($vitima['rg']) ?><br>
                            <strong>Cor:</strong> <?= limparTexto($vitima['cor']) ?><br>
                            <strong>Profissão:</strong> <?= limparTexto($vitima['profissao']) ?>
                        </td>
                        <td style="width: 50%; vertical-align: top; padding: 8px;">
                            <strong>Nacionalidade:</strong> <?= limparTexto($vitima['nacionalidade']) ?><br>
                            <strong>Naturalidade:</strong> <?= limparTexto($vitima['naturalidade']) ?><br>
                            <strong>Pai:</strong> <?= limparTexto($vitima['pai']) ?><br>
                            <strong>Mãe:</strong> <?= limparTexto($vitima['mae']) ?><br>
                            <strong>Situação:</strong> 
                            <span style="
                                display: inline-block;
                                background-color: <?= $vitima['situacao'] === 'FATAL' ? '#d32f2f' : '#2E7D32' ?>;
                                color: white;
                                padding: 4px 8px;
                                border-radius: 4px;
                                font-weight: bold;
                                text-transform: uppercase;
                            ">
                                <?= htmlspecialchars($vitima['situacao']) ?>
                            </span><br>

                            <?php if ($vitima['situacao'] == 'FATAL' && $vitima['posicao_descricao']): ?>
                                <strong>Posição:</strong> <?= limparTexto($vitima['posicao_descricao']) ?><br>
                            <?php elseif ($vitima['situacao'] == 'SOBREVIVENTE' && $vitima['hospital_socorrido']): ?>
                                <strong>Hospital:</strong> <?= limparTexto($vitima['hospital_socorrido']) ?><br>
                            <?php endif; ?>
                        </td>
                    </tr>

                    <?php if ($vitima['endereco']): ?>
                        <tr>
                            <td colspan="2" style="padding: 8px;">
                                <strong>Endereço:</strong><br>
                                <?= limparTexto($vitima['endereco']) ?>
                            </td>
                        </tr>
                    <?php endif; ?>

                    <?php if ($vitima['lesoes_apresentadas']): ?>
                        <tr>
                            <td colspan="2" style="padding: 8px;">
                                <strong>Lesões Apresentadas:</strong><br>
                                <?= nl2br(htmlspecialchars($vitima['lesoes_apresentadas'])) ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </table>
            <?php endforeach; ?>
        </td>
    </tr>
</table>


    

<!-- 5) SUPOSTA AUTORIA -->
<br><br><br>
    <table class="tabela-secao">
        <tr>
            <td>
                <?= renderTituloSecao(5, 'Suposta Autoria'); ?><br><br>

                <?php foreach ($autores as $index => $autor): ?>
                    <table width="100%" cellpadding="6" cellspacing="0" border="1" style="border-collapse: collapse; margin-bottom: 10px;">
                        <tr style="background-color: #f0f0f0;">
                            <td colspan="2" style="padding: 8px; font-weight: bold;">
                                Autor <?= $index + 1 ?>
                            </td>
                        </tr>
                        <tr>
                            <td style="width: 50%; vertical-align: top; padding: 8px;">
                                <strong>Nome:</strong> <?= limparTexto($autor['nome']) ?><br>
                                <strong>Data Nascimento:</strong> <?= formatarDataNascimento($autor['data_nascimento']) ?><br>
                                <strong>CPF:</strong> <?= limparTexto($autor['cpf']) ?><br>
                                <strong>RG:</strong> <?= limparTexto($autor['rg']) ?><br>
                                <strong>Cor:</strong> <?= limparTexto($autor['cor']) ?><br>
                                <strong>Profissão:</strong> <?= limparTexto($autor['profissao']) ?>
                            </td>
                            <td style="width: 50%; vertical-align: top; padding: 8px;">
                                <strong>Nacionalidade:</strong> <?= limparTexto($autor['nacionalidade']) ?><br>
                                <strong>Naturalidade:</strong> <?= limparTexto($autor['naturalidade']) ?><br>
                                <strong>Pai:</strong> <?= limparTexto($autor['pai']) ?><br>
                                <strong>Mãe:</strong> <?= limparTexto($autor['mae']) ?>
                            </td>
                        </tr>

                        <?php if (!empty($autor['endereco'])): ?>
                            <tr>
                                <td colspan="2" style="padding: 8px;">
                                    <strong>Endereço:</strong><br>
                                    <?= limparTexto($autor['endereco']) ?>
                                </td>
                            </tr>
                        <?php endif; ?>

                        <?php if (!empty($autor['caracteristicas'])): ?>
                            <tr>
                                <td colspan="2" style="padding: 8px;">
                                    <strong>Características/Sinais Particulares:</strong><br>
                                    <?= nl2br(htmlspecialchars($autor['caracteristicas'])) ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </table>
                <?php endforeach; ?>
            </td>
        </tr>
    </table>





    
    <!-- 6) TESTEMUNHAS -->
<br><br><br>
<?php if (!empty($testemunhas)): ?>
    <table class="tabela-secao">
        <tr>
            <td>
                <?= renderTituloSecao(6, 'Testemunhas'); ?><br><br>

                <?php foreach ($testemunhas as $index => $testemunha): ?>
                    <table width="100%" cellpadding="6" cellspacing="0" border="1" style="border-collapse: collapse; margin-bottom: 10px;">
                        <tr style="background-color: #f0f0f0;">
                            <td colspan="2" style="padding: 8px; font-weight: bold;">
                                Testemunha <?= $index + 1 ?>
                            </td>
                        </tr>
                        <tr>
                            <td style="width: 50%; vertical-align: top; padding: 8px;">
                                <strong>Nome:</strong> <?= limparTexto($testemunha['nome']) ?><br>
                                <strong>Data Nascimento:</strong> <?= formatarDataNascimento($testemunha['data_nascimento']) ?><br>
                                <strong>CPF:</strong> <?= limparTexto($testemunha['cpf']) ?><br>
                                <strong>RG:</strong> <?= limparTexto($testemunha['rg']) ?><br>
                                <strong>Telefone:</strong> <?= limparTexto($testemunha['telefone']) ?><br>
                                <strong>Profissão:</strong> <?= limparTexto($testemunha['profissao']) ?>
                            </td>
                            <td style="width: 50%; vertical-align: top; padding: 8px;">
                                <strong>Nacionalidade:</strong> <?= limparTexto($testemunha['nacionalidade']) ?><br>
                                <strong>Naturalidade:</strong> <?= limparTexto($testemunha['naturalidade']) ?><br>
                                <strong>Pai:</strong> <?= limparTexto($testemunha['pai']) ?><br>
                                <strong>Mãe:</strong> <?= limparTexto($testemunha['mae']) ?><br>
                            </td>
                        </tr>

                        <?php if ($testemunha['endereco']): ?>
                            <tr>
                                <td colspan="2" style="padding: 8px;">
                                    <strong>Endereço:</strong><br>
                                    <?= limparTexto($testemunha['endereco']) ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </table><br><br>
                <?php endforeach; ?>
            </td>
        </tr>
    </table>
<?php endif; ?>


    
<!-- 7) RELATO -->
<br><br><br>
<?php if ($recognicao['historico']): ?>
    <div style="page-break-before: always;"></div>
    <table class="tabela-secao">
        <tr>
            <td>
                <?= renderTituloSecao(7, 'Relato'); ?> <br><br>
                <table width="100%" cellpadding="8" cellspacing="0" border="1" style="border-collapse: collapse;">
                    <tr>
                        <td style="text-align: justify; line-height: 1.6;">
                            <?= nl2br(htmlspecialchars($recognicao['historico'])) ?>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
<?php endif; ?>




    
    <?php
    return ob_get_clean();
}

function adicionarSecaoMapa($pdf, $recognicao) {
$htmlTituloMapa = renderTituloSecao(8, 'Mapa do Local do Fato');
$pdf->writeHTML($htmlTituloMapa, true, false, true, false, '');
$pdf->Ln(5);

    
    $lat = $recognicao['latitude'];
    $lon = $recognicao['longitude'];
    $zoom = 16;
    
    $geoapifyKey = "c8077ec067d344d7a9de40f6e06fd9c9";
    $geoapifyUrl = "https://maps.geoapify.com/v1/staticmap?style=osm-bright&width=400&height=300&center=lonlat:{$lon},{$lat}&zoom={$zoom}&marker=lonlat:{$lon},{$lat};color:%23ff0000;size:large;type:awesome&apiKey={$geoapifyKey}";
    
    $googleMapsUrl = "https://maps.google.com/maps?q={$lat},{$lon}&ll={$lat},{$lon}&z={$zoom}";
    $qrCodeUrl = "https://api.qrserver.com/v1/create-qr-code/?size=80x80&data=" . urlencode($googleMapsUrl);
    
    try {
        $currentY = $pdf->GetY();
        
        $pdf->Image($geoapifyUrl, 15, $currentY, 120, 90);
        
        $pdf->SetXY(140, $currentY);
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(0, 6, 'Coordenadas GPS:', 0, 1, 'L');
        
        $pdf->SetX(140);
        $pdf->SetFont('helvetica', '', 9);
        $coordText = "Lat: " . number_format($lat, 6) . "°\n";
        $coordText .= "Long: " . number_format($lon, 6) . "°\n\n";
        $coordText .= "Precisão: ±5 metros";
        
        $pdf->MultiCell(55, 4, $coordText, 0, 'L');
        
        $pdf->SetXY(140, $currentY + 40);
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->Cell(0, 4, 'Google Maps:', 0, 1, 'L');
        $pdf->SetX(140);
        $pdf->Image($qrCodeUrl, 140, $pdf->GetY() + 2, 25, 25);
        
        $pdf->SetY($currentY + 95);
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(0, 6, 'Endereço:', 0, 1, 'L');
        $pdf->SetFont('helvetica', '', 9);
        $pdf->MultiCell(0, 5, $recognicao['endereco_fato'] ?: 'Não informado', 0, 'L');
        
    } catch (Exception $e) {
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(0, 6, "Coordenadas: Lat {$lat}, Long {$lon}", 0, 1, 'L');
        $pdf->Cell(0, 6, 'Endereço: ' . ($recognicao['endereco_fato'] ?: 'Não informado'), 0, 1, 'L');
    }
}

function adicionarSecaoRegistroFotografico($pdf, $totalFotos) {
    $htmlTituloFotos = renderTituloSecao(9, 'Registros Fotográficos');
    $pdf->writeHTML($htmlTituloFotos, true, false, true, false, '');
    $pdf->Ln(5);

    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 6, "Total de {$totalFotos} fotografia(s) anexada(s)", 0, 1, 'C');
}


function adicionarFoto($pdf, $foto, $numero, $total) {
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(0, 8, "Fotografia {$numero} de {$total}", 0, 1, 'C');
    $pdf->SetDrawColor(26, 35, 126);
    $pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
    $pdf->Ln(5);
    
    if (file_exists($foto['arquivo'])) {
        try {
            $max_width = 170;
            $max_height = 200;
            
            $imageInfo = getimagesize($foto['arquivo']);
            if ($imageInfo) {
                list($img_width, $img_height) = $imageInfo;
                
                $img_width_mm = $img_width * 0.264583;
                $img_height_mm = $img_height * 0.264583;
                
                $ratio = min($max_width / $img_width_mm, $max_height / $img_height_mm);
                $new_width = $img_width_mm * $ratio;
                $new_height = $img_height_mm * $ratio;
                
                $x = (210 - $new_width) / 2;
                $y = $pdf->GetY();
                
                $pdf->Image($foto['arquivo'], $x, $y, $new_width, $new_height);
                $pdf->SetY($y + $new_height + 10);
            } else {
                $pdf->Image($foto['arquivo'], 20, $pdf->GetY(), 170, 0);
                $pdf->Ln(15);
            }
            
            $pdf->SetFont('helvetica', 'I', 9);
            $pdf->MultiCell(0, 4, $foto['descricao'], 0, 'C');
            
        } catch (Exception $e) {
            $pdf->SetFont('helvetica', '', 10);
            $pdf->Cell(0, 8, 'Erro ao carregar imagem', 0, 1, 'C');
            $pdf->SetFont('helvetica', 'I', 9);
            $pdf->MultiCell(0, 4, $foto['descricao'], 0, 'C');
        }
    } else {
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(0, 8, 'Arquivo não encontrado', 0, 1, 'C');
        $pdf->SetFont('helvetica', 'I', 9);
        $pdf->MultiCell(0, 4, $foto['descricao'], 0, 'C');
    }
}

function adicionarSecaoEquipeResponsavel($pdf, $equipe_responsavel) {
    $pdf->SetFont('helvetica', '', 10); // 🔥 Corrige itálico antes da tabela
    $htmlTitulo = renderTituloSecao(10, 'Equipe Responsável pelo Preenchimento');
    $pdf->writeHTML($htmlTitulo, true, false, true, false, '');
    $pdf->Ln(5);

    

    if (!empty($equipe_responsavel)) {
        $html = '
        <table width="100%" style="border-collapse: collapse; font-size: 10pt;">
        ';
        
        foreach ($equipe_responsavel as $index => $policial) {
            $bg = ($index % 2 == 0) ? '#f8f8f8' : 'white';
            $html .= '<tr>';
            $html .= '<td style="padding: 8px 10px; background: #f0f0f0; font-weight: bold; width: 25%; border: 1px solid #ddd;">Policial ' . ($index + 1) . '</td>';
            $html .= '<td style="padding: 8px 10px; background: ' . $bg . '; border: 1px solid #ddd;">' . htmlspecialchars($policial['nome']);
            if ($policial['matricula']) {
                $html .= ' - Mat: ' . htmlspecialchars($policial['matricula']);
            }
            $html .= '</td>';
            $html .= '</tr>';
        }
        
        $html .= '</table>';
        
        $pdf->writeHTML($html, true, false, true, false, '');
    } else {
        $pdf->SetFont('helvetica', 'I', 9);
        $pdf->Cell(0, 8, 'Informação não disponível', 0, 1, 'C');
        $pdf->SetFont('helvetica', '', 9); // 🔥 Reset após itálico
    }

    $pdf->Ln(15);
    $pdf->SetFont('helvetica', '', 8);
    $pdf->SetTextColor(100, 100, 100);
    
    $rodape = "Documento gerado em " . date('d/m/Y \à\s H:i:s') . "\n";
    $rodape .= "Sistema de Recognição Visuográfica - Polícia Civil do Estado de Goiás";
    
    $pdf->MultiCell(0, 4, $rodape, 0, 'C');
}


?>