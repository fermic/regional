<?php
// formulario.php - Formulário principal
require_once 'config.php';
$pdo = conectarDB();

$id = $_GET['id'] ?? null;
$recognicao = null;
$policiais_preservacao = [];
$vitimas = [];
$autores = [];
$testemunhas = [];
$meios_empregados = [];
$equipe_responsavel = [];

if ($id && !str_starts_with($id, 'temp_')) {
    $stmt = $pdo->prepare("SELECT * FROM recognicoes WHERE id = ?");
    $stmt->execute([$id]);
    $recognicao = $stmt->fetch();
    
    if ($recognicao) {
        // Carregar policiais de preservação
        $stmt = $pdo->prepare("SELECT * FROM policiais_preservacao WHERE recognicao_id = ?");
        $stmt->execute([$id]);
        $policiais_preservacao = $stmt->fetchAll();
        
        // Carregar vítimas
        $stmt = $pdo->prepare("SELECT * FROM vitimas WHERE recognicao_id = ?");
        $stmt->execute([$id]);
        $vitimas = $stmt->fetchAll();
        
        // Carregar autores
        $stmt = $pdo->prepare("SELECT * FROM autores WHERE recognicao_id = ?");
        $stmt->execute([$id]);
        $autores = $stmt->fetchAll();
        
        // Carregar testemunhas
        $stmt = $pdo->prepare("SELECT * FROM testemunhas WHERE recognicao_id = ?");
        $stmt->execute([$id]);
        $testemunhas = $stmt->fetchAll();
        
        // Carregar meios empregados
        $stmt = $pdo->prepare("SELECT * FROM meios_empregados WHERE recognicao_id = ?");
        $stmt->execute([$id]);
        $meios_empregados = $stmt->fetchAll();
        
        // Carregar equipe responsável
        $stmt = $pdo->prepare("SELECT policial_id FROM equipe_responsavel WHERE recognicao_id = ?");
        $stmt->execute([$id]);
        $equipe_responsavel = $stmt->fetchAll();
    }
}

// Carregar dados das tabelas auxiliares
$naturezas = $pdo->query("SELECT * FROM naturezas")->fetchAll();
$forca_policial = $pdo->query("SELECT * FROM forca_policial")->fetchAll();
$peritos = $pdo->query("SELECT * FROM peritos")->fetchAll();
$local_externo = $pdo->query("SELECT * FROM local_externo")->fetchAll();
$local_interno = $pdo->query("SELECT * FROM local_interno")->fetchAll();
$tipo_piso = $pdo->query("SELECT * FROM tipo_piso")->fetchAll();
$disposicao_objetos = $pdo->query("SELECT * FROM disposicao_objetos")->fetchAll();
$condicoes_higiene = $pdo->query("SELECT * FROM condicoes_higiene")->fetchAll();
// Carregar fotos existentes se houver
$fotos_existentes = [];
if ($id && !str_starts_with($id, 'temp_')) {
    $stmt = $pdo->prepare("SELECT * FROM fotos WHERE recognicao_id = ? ORDER BY id");
    $stmt->execute([$id]);
    $fotos_existentes = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Formulário de Recognição Visuográfica</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
    <style>
        body { 
            background-color: #f8f9fa;
            padding-bottom: 80px;
        }
        .navbar {
            background-color: #1a237e !important;
        }
        .section-header {
            background-color: #e3f2fd;
            padding: 10px 15px;
            margin: 20px -15px 15px -15px;
            font-weight: bold;
            color: #1a237e;
        }
        .btn-salvar {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 1000;
        }
        .btn-adicionar {
            margin-top: 10px;
        }
        .item-dinamico {
            background-color: #f8f9fa;
            padding: 15px;
            margin-bottom: 15px;
            border-radius: 5px;
            position: relative;
        }
        .btn-remover {
            position: absolute;
            top: 5px;
            right: 5px;
        }
        #status-save {
            position: fixed;
            bottom: 80px;
            right: 20px;
            z-index: 1000;
        }
        #error-log {
            position: fixed;
            bottom: 120px;
            right: 20px;
            z-index: 1000;
            max-width: 400px;
        }
        .foto-preview {
            position: relative;
        }
        .btn-remover-foto {
            position: absolute;
            top: 5px;
            right: 5px;
            background: rgba(255,0,0,0.7);
            color: white;
            border: none;
            border-radius: 50%;
            width: 25px;
            height: 25px;
            cursor: pointer;
        }
        
            #status-save.alert-warning {
        background-color: #fff3cd;
        border-color: #ffeaa7;
        color: #856404;
    }
    
    #status-save.alert-success {
        background-color: #d4edda;
        border-color: #c3e6cb;
        color: #155724;
    }
    </style>
</head>
<body>
    <nav class="navbar navbar-dark">
        <div class="container-fluid">
            <a href="index.php" class="navbar-brand">← Voltar</a>
            <span class="navbar-text text-white">
                <span id="status-conexao"></span>
            </span>
        </div>
    </nav>

    <div class="container mt-3">
        <form id="formRecognicao">
            <input type="hidden" id="recognicao_id" value="<?= $id ?>">
            
            <!-- INFORMAÇÕES GERAIS -->
            <div class="section-header">INFORMAÇÕES GERAIS</div>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">RAI</label>
                    <input type="text" class="form-control" id="rai" value="<?= $recognicao['rai'] ?? '' ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Data e hora do acionamento</label>
                    <input type="datetime-local" class="form-control" id="data_hora_acionamento" 
                           value="<?= $recognicao && $recognicao['data_hora_acionamento'] ? date('Y-m-d\TH:i', strtotime($recognicao['data_hora_acionamento'])) : '' ?>">
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Data e hora do fato (aproximado)</label>
                    <input type="datetime-local" class="form-control" id="data_hora_fato" 
                           value="<?= $recognicao && $recognicao['data_hora_fato'] ? date('Y-m-d\TH:i', strtotime($recognicao['data_hora_fato'])) : '' ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Dia da Semana</label>
                    <select class="form-select" id="dia_semana">
                        <option value="">Selecione...</option>
                        <option value="Segunda-feira" <?= ($recognicao && $recognicao['dia_semana'] == 'Segunda-feira') ? 'selected' : '' ?>>Segunda-feira</option>
                        <option value="Terça-feira" <?= ($recognicao && $recognicao['dia_semana'] == 'Terça-feira') ? 'selected' : '' ?>>Terça-feira</option>
                        <option value="Quarta-feira" <?= ($recognicao && $recognicao['dia_semana'] == 'Quarta-feira') ? 'selected' : '' ?>>Quarta-feira</option>
                        <option value="Quinta-feira" <?= ($recognicao && $recognicao['dia_semana'] == 'Quinta-feira') ? 'selected' : '' ?>>Quinta-feira</option>
                        <option value="Sexta-feira" <?= ($recognicao && $recognicao['dia_semana'] == 'Sexta-feira') ? 'selected' : '' ?>>Sexta-feira</option>
                        <option value="Sábado" <?= ($recognicao && $recognicao['dia_semana'] == 'Sábado') ? 'selected' : '' ?>>Sábado</option>
                        <option value="Domingo" <?= ($recognicao && $recognicao['dia_semana'] == 'Domingo') ? 'selected' : '' ?>>Domingo</option>
                    </select>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-12 mb-3">
                    <label class="form-label">Natureza da Ocorrência</label>
                    <select class="form-select" id="natureza_id">
                        <option value="">Selecione...</option>
                        <?php foreach ($naturezas as $nat): ?>
                            <option value="<?= $nat['id'] ?>" <?= ($recognicao && $recognicao['natureza_id'] == $nat['id']) ? 'selected' : '' ?>><?= $nat['descricao'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="mb-3">
                <label class="form-label">Endereço do Fato</label>
                <textarea class="form-control" id="endereco_fato" rows="2"><?= $recognicao['endereco_fato'] ?? '' ?></textarea>
            </div>
            
            <div class="row">
                <div class="col-md-12 mb-3">
                    <label class="form-label">Geolocalização do Local</label>
                    <div class="input-group">
                        <input type="text" class="form-control" id="geolocalizacao" 
                               placeholder="Digite as coordenadas (ex: Lat: -16.6799, Long: -49.2550) ou clique no botão"
                               value="<?= ($recognicao && $recognicao['latitude'] && $recognicao['longitude']) ? 'Lat: ' . $recognicao['latitude'] . ', Long: ' . $recognicao['longitude'] : '' ?>">
                        <button class="btn btn-primary" type="button" onclick="obterLocalizacao()">
                            📍 Obter Localização Atual
                        </button>
                        <button class="btn btn-secondary" type="button" onclick="limparLocalizacao()">
                            ✖️ Limpar
                        </button>
                    </div>
                    <small class="text-muted">Você pode digitar manualmente ou obter automaticamente sua localização</small>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-12 mb-3">
                    <label class="form-label">Preservação do Local</label>
                    <select class="form-select" id="preservacao_local_id">
                        <option value="">Selecione...</option>
                        <?php foreach ($forca_policial as $fp): ?>
                            <option value="<?= $fp['id'] ?>" <?= ($recognicao && $recognicao['preservacao_local_id'] == $fp['id']) ? 'selected' : '' ?>><?= $fp['descricao'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="mb-3">
                <label class="form-label">Policiais que preservaram o local</label>
                <div id="policiais_preservacao"></div>
                <button type="button" class="btn btn-sm btn-primary btn-adicionar" onclick="adicionarPolicialPreservacao()">
                    + Adicionar Policial
                </button>
            </div>
            
            <div class="mb-3">
                <label class="form-label">Perito Responsável pelo exame in loco</label>
                <select class="form-select select2-perito" id="perito_id">
                    <option value="">Selecione ou digite para cadastrar...</option>
                    <?php foreach ($peritos as $perito): ?>
                        <option value="<?= $perito['id'] ?>" <?= ($recognicao && $recognicao['perito_id'] == $perito['id']) ? 'selected' : '' ?>><?= $perito['nome'] ?> - <?= $perito['matricula'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <!-- LOCAL DO FATO -->
            <div class="section-header">LOCAL DO FATO</div>
            
            <div class="mb-3">
                <label class="form-label">Tipo de Local</label>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="tipo_local" id="tipo_local_externo" value="Externo"
                           <?= ($recognicao && $recognicao['tipo_local'] == 'Externo') ? 'checked' : '' ?>
                           onchange="alterarTipoLocal()">
                    <label class="form-check-label" for="tipo_local_externo">Externo</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="tipo_local" id="tipo_local_interno" value="Interno"
                           <?= ($recognicao && $recognicao['tipo_local'] == 'Interno') ? 'checked' : '' ?>
                           onchange="alterarTipoLocal()">
                    <label class="form-check-label" for="tipo_local_interno">Interno</label>
                </div>
            </div>
            
            <div class="mb-3" id="div_local_externo" style="display:<?= ($recognicao && $recognicao['tipo_local'] == 'Externo') ? 'block' : 'none' ?>;">
                <label class="form-label">Local Externo</label>
                <select class="form-select" id="local_externo_id">
                    <option value="">Selecione...</option>
                    <?php foreach ($local_externo as $le): ?>
                        <option value="<?= $le['id'] ?>" <?= ($recognicao && $recognicao['local_externo_id'] == $le['id']) ? 'selected' : '' ?>><?= $le['descricao'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="mb-3" id="div_local_interno" style="display:<?= ($recognicao && $recognicao['tipo_local'] == 'Interno') ? 'block' : 'none' ?>;">
                <label class="form-label">Local Interno</label>
                <select class="form-select" id="local_interno_id">
                    <option value="">Selecione...</option>
                    <?php foreach ($local_interno as $li): ?>
                        <option value="<?= $li['id'] ?>" <?= ($recognicao && $recognicao['local_interno_id'] == $li['id']) ? 'selected' : '' ?>><?= $li['descricao'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Tipo de Piso</label>
                    <select class="form-select" id="tipo_piso_id">
                        <option value="">Selecione...</option>
                        <?php foreach ($tipo_piso as $tp): ?>
                            <option value="<?= $tp['id'] ?>" <?= ($recognicao && $recognicao['tipo_piso_id'] == $tp['id']) ? 'selected' : '' ?>><?= $tp['descricao'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Disposição dos Objetos</label>
                    <select class="form-select" id="disposicao_objetos_id">
                        <option value="">Selecione...</option>
                        <?php foreach ($disposicao_objetos as $do): ?>
                            <option value="<?= $do['id'] ?>" <?= ($recognicao && $recognicao['disposicao_objetos_id'] == $do['id']) ? 'selected' : '' ?>><?= $do['descricao'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Condições de Higiene</label>
                    <select class="form-select" id="condicoes_higiene_id">
                        <option value="">Selecione...</option>
                        <?php foreach ($condicoes_higiene as $ch): ?>
                            <option value="<?= $ch['id'] ?>" <?= ($recognicao && $recognicao['condicoes_higiene_id'] == $ch['id']) ? 'selected' : '' ?>><?= $ch['descricao'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Câmeras de Monitoramento</label>
                    <select class="form-select" id="cameras_monitoramento">
                        <option value="">Selecione...</option>
                        <option value="Existente" <?= ($recognicao && $recognicao['cameras_monitoramento'] == 'Existente') ? 'selected' : '' ?>>Existente</option>
                        <option value="Inexistente" <?= ($recognicao && $recognicao['cameras_monitoramento'] == 'Inexistente') ? 'selected' : '' ?>>Inexistente</option>
                    </select>
                </div>
            </div>
            
            <div class="mb-3">
                <label class="form-label">Objetos Recolhidos - Inclusive pela Perícia</label>
                <textarea class="form-control" id="objetos_recolhidos" rows="3"><?= $recognicao['objetos_recolhidos'] ?? '' ?></textarea>
            </div>
            
            <!-- VÍTIMAS -->
            <div class="section-header">VÍTIMAS</div>
            
            <div id="vitimas_container"></div>
            <button type="button" class="btn btn-sm btn-primary btn-adicionar" onclick="adicionarVitima()">
                + Adicionar Vítima
            </button>
            
            <!-- SUPOSTA AUTORIA -->
            <div class="section-header">SUPOSTA AUTORIA</div>
            
            <div id="autores_container"></div>
            <button type="button" class="btn btn-sm btn-primary btn-adicionar" onclick="adicionarAutor()">
                + Adicionar Autor
            </button>
            
            <!-- MEIOS EMPREGADOS -->
            <div class="section-header">MEIOS EMPREGADOS</div>
            
            <div class="mb-3">
                <label class="form-label">Veículo Empregado</label>
                <select class="form-select select2-multiple" id="veiculos_empregados" multiple>
                    <?php 
                    $veiculos = $pdo->query("SELECT * FROM veiculos")->fetchAll();
                    $veiculos_selecionados = array_filter($meios_empregados, fn($m) => $m['tipo'] == 'veiculo');
                    $veiculos_ids = array_column($veiculos_selecionados, 'item_id');
                    foreach ($veiculos as $veiculo): 
                    ?>
                        <option value="<?= $veiculo['id'] ?>" <?= in_array($veiculo['id'], $veiculos_ids) ? 'selected' : '' ?>><?= $veiculo['descricao'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="mb-3">
                <label class="form-label">Arma Empregada</label>
                <select class="form-select select2-multiple" id="armas_empregadas" multiple>
                    <?php 
                    $armas = $pdo->query("SELECT * FROM armas")->fetchAll();
                    $armas_selecionadas = array_filter($meios_empregados, fn($m) => $m['tipo'] == 'arma');
                    $armas_ids = array_column($armas_selecionadas, 'item_id');
                    foreach ($armas as $arma): 
                    ?>
                        <option value="<?= $arma['id'] ?>" <?= in_array($arma['id'], $armas_ids) ? 'selected' : '' ?>><?= $arma['descricao'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <!-- TESTEMUNHAS -->
            <div class="section-header">TESTEMUNHAS</div>
            
            <div id="testemunhas_container"></div>
            <button type="button" class="btn btn-sm btn-primary btn-adicionar" onclick="adicionarTestemunha()">
                + Adicionar Testemunha
            </button>
            
            <!-- HISTÓRICO -->
            <div class="section-header">HISTÓRICO</div>
            
            <div class="mb-3">
                <label class="form-label">Relatório acerca das informações colhidas no local</label>
                <textarea class="form-control" id="historico" rows="5"><?= $recognicao['historico'] ?? '' ?></textarea>
            </div>
            
            <!-- FOTOS -->
            <div class="section-header">FOTOS</div>
            
            <?php if (!empty($fotos_existentes)): ?>
            <div class="mb-3">
                <label class="form-label">Fotos já salvas:</label>
                <div class="row" id="fotos_existentes">
                    <?php foreach ($fotos_existentes as $foto): ?>
                    <div class="col-md-3 mb-3" id="foto_existente_<?= $foto['id'] ?>">
                        <div class="card">
                            <img src="<?= htmlspecialchars($foto['arquivo']) ?>" 
                                 class="card-img-top" 
                                 style="height: 150px; object-fit: cover; cursor: pointer;"
                                 onclick="window.open('<?= htmlspecialchars($foto['arquivo']) ?>', '_blank')">
                            <div class="card-body p-2">
                                <small class="text-muted d-block"><?= htmlspecialchars($foto['descricao']) ?></small>
                                <button type="button" class="btn btn-danger btn-sm w-100 mt-1"
                                        onclick="excluirFotoExistente(<?= $foto['id'] ?>)">
                                    🗑️ Excluir
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
             <div class="mb-3">
                <label class="form-label">Adicionar novas imagens:</label>
                <div class="input-group mb-2">
                    <input type="file" class="form-control" id="fotos" multiple accept="image/*">
                    <button class="btn btn-primary" type="button" onclick="abrirCamera()">
                        📷 Câmera
                    </button>
                    <button class="btn btn-info" type="button" onclick="abrirGaleria()">
                        🖼️ Galeria
                    </button>
                    <?php if ($id && !str_starts_with($id, 'temp_')): ?>
                    <a href="ver_fotos.php?id=<?= $id ?>" class="btn btn-secondary" target="_blank">
                        👁️ Ver Todas
                    </a>
                    <?php endif; ?>
                </div>
                <small class="text-muted">Você pode tirar fotos ou selecionar da galeria. As novas fotos serão adicionadas às existentes ao salvar</small>
                <div id="preview_fotos" class="mt-3 row"></div>
                
                <!-- Inputs ocultos para câmera e galeria -->
                <input type="file" id="camera_input" accept="image/*" capture="camera" style="display:none;">
                <input type="file" id="galeria_input" accept="image/*" multiple style="display:none;">
            </div>
            
            <!-- EQUIPE RESPONSÁVEL -->
            <div class="section-header">EQUIPE RESPONSÁVEL PELO PREENCHIMENTO</div>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Primeiro Policial</label>
                    <select class="form-select select2-policial" id="policial1_id">
                        <option value="">Selecione...</option>
                        <?php 
                        $policiais_gih = $pdo->query("SELECT * FROM policiais_gih")->fetchAll();
                        $policial1_id = isset($equipe_responsavel[0]) ? $equipe_responsavel[0]['policial_id'] : null;
                        foreach ($policiais_gih as $policial): 
                        ?>
                            <option value="<?= $policial['id'] ?>" <?= ($policial1_id == $policial['id']) ? 'selected' : '' ?>><?= $policial['nome'] ?> - <?= $policial['matricula'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Segundo Policial</label>
                    <select class="form-select select2-policial" id="policial2_id">
                        <option value="">Selecione...</option>
                        <?php 
                        $policial2_id = isset($equipe_responsavel[1]) ? $equipe_responsavel[1]['policial_id'] : null;
                        foreach ($policiais_gih as $policial): 
                        ?>
                            <option value="<?= $policial['id'] ?>" <?= ($policial2_id == $policial['id']) ? 'selected' : '' ?>><?= $policial['nome'] ?> - <?= $policial['matricula'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
        </form>
    </div>
    
    <div id="status-save" class="alert alert-success" style="display:none;">
        Dados salvos automaticamente
    </div>
    
    <div id="error-log" class="alert alert-danger" style="display:none;">
        <strong>Erro:</strong> <span id="error-message"></span>
    </div>
    
<div style="position: fixed; bottom: 20px; right: 20px; z-index: 1000; display: flex; gap: 10px;">
    <button class="btn btn-success" onclick="salvarRascunho(true)">
        💾 Salvar
    </button>
    
    <?php if ($id && !str_starts_with($id, 'temp_')): ?>
    <a href="gerar_pdf_mpdf.php?id=<?= $id ?>" target="_blank" class="btn btn-primary">
        🖨️ Imprimir PDF
    </a>
    <?php endif; ?>
</div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <?= gerarScriptOffline() ?>
    
    <script>
    // Inicializar Select2
    $(document).ready(function() {
        $('.select2-perito').select2({
            theme: 'bootstrap-5',
            tags: true,
            createTag: function (params) {
                return {
                    id: 'novo_' + params.term,
                    text: params.term + ' (Novo)',
                    newOption: true
                }
            }
        });
        
        $('.select2-multiple').select2({
            theme: 'bootstrap-5',
            placeholder: 'Selecione uma ou mais opções'
        });
        
        $('.select2-policial').select2({
            theme: 'bootstrap-5',
            tags: true,
            createTag: function (params) {
                return {
                    id: 'novo_policial_' + params.term,
                    text: params.term + ' (Novo)',
                    newOption: true
                }
            }
        });
        
        // Carregar posições de vítima
        <?php 
        $posicoes_vitima = $pdo->query("SELECT * FROM posicao_vitima")->fetchAll();
        ?>
        window.posicoesVitima = <?= json_encode($posicoes_vitima) ?>;
    });
    
    // Obter localização
    function obterLocalizacao() {
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(function(position) {
                const lat = position.coords.latitude;
                const lng = position.coords.longitude;
                document.getElementById('geolocalizacao').value = `Lat: ${lat}, Long: ${lng}`;
            }, function(error) {
                alert('Erro ao obter localização: ' + error.message);
            });
        } else {
            alert('Geolocalização não suportada pelo navegador');
        }
    }
    
    // Alterar tipo de local
    function alterarTipoLocal() {
        const tipoLocal = document.querySelector('input[name="tipo_local"]:checked')?.value;
        document.getElementById('div_local_externo').style.display = tipoLocal === 'Externo' ? 'block' : 'none';
        document.getElementById('div_local_interno').style.display = tipoLocal === 'Interno' ? 'block' : 'none';
    }
    
    // Adicionar policial de preservação
    let contadorPolicialPreservacao = 0;
    function adicionarPolicialPreservacao() {
        contadorPolicialPreservacao++;
        const html = `
            <div class="item-dinamico" id="policial_preservacao_${contadorPolicialPreservacao}">
                <button type="button" class="btn btn-sm btn-danger btn-remover" 
                        onclick="removerItem('policial_preservacao_${contadorPolicialPreservacao}')">×</button>
                <div class="row">
                    <div class="col-md-6">
                        <input type="text" class="form-control" placeholder="Nome" 
                               name="policial_preservacao_nome[]">
                    </div>
                    <div class="col-md-6">
                        <input type="text" class="form-control" placeholder="Matrícula" 
                               name="policial_preservacao_matricula[]">
                    </div>
                </div>
            </div>
        `;
        document.getElementById('policiais_preservacao').insertAdjacentHTML('beforeend', html);
    }
    
    // Adicionar vítima
    let contadorVitima = 0;
    function adicionarVitima() {
        contadorVitima++;
        const html = `
            <div class="item-dinamico" id="vitima_${contadorVitima}">
                <button type="button" class="btn btn-sm btn-danger btn-remover" 
                        onclick="removerItem('vitima_${contadorVitima}')">×</button>
                <h6>Vítima ${contadorVitima}</h6>
                <div class="row">
                    <div class="col-md-6 mb-2">
                        <input type="text" class="form-control" placeholder="Nome" name="vitima_nome[]">
                    </div>
                    <div class="col-md-6 mb-2">
                        <input type="date" class="form-control" placeholder="Data de Nascimento" name="vitima_data_nascimento[]">
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-4 mb-2">
                        <input type="text" class="form-control" placeholder="Cor" name="vitima_cor[]">
                    </div>
                    <div class="col-md-4 mb-2">
                        <input type="text" class="form-control" placeholder="Nacionalidade" name="vitima_nacionalidade[]">
                    </div>
                    <div class="col-md-4 mb-2">
                        <input type="text" class="form-control" placeholder="Naturalidade" name="vitima_naturalidade[]">
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-4 mb-2">
                        <input type="text" class="form-control" placeholder="Profissão" name="vitima_profissao[]">
                    </div>
                    <div class="col-md-4 mb-2">
                        <input type="text" class="form-control" placeholder="CPF" name="vitima_cpf[]">
                    </div>
                    <div class="col-md-4 mb-2">
                        <input type="text" class="form-control" placeholder="RG" name="vitima_rg[]">
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-2">
                        <input type="text" class="form-control" placeholder="Nome do Pai" name="vitima_pai[]">
                    </div>
                    <div class="col-md-6 mb-2">
                        <input type="text" class="form-control" placeholder="Nome da Mãe" name="vitima_mae[]">
                    </div>
                </div>
                <div class="mb-2">
                    <textarea class="form-control" placeholder="Endereço" name="vitima_endereco[]" rows="2"></textarea>
                </div>
                <div class="mb-2">
                    <label>Situação:</label>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="vitima_situacao_${contadorVitima}" 
                               value="FATAL" onchange="alterarSituacaoVitima(${contadorVitima})">
                        <label class="form-check-label">Fatal</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="vitima_situacao_${contadorVitima}" 
                               value="SOBREVIVENTE" onchange="alterarSituacaoVitima(${contadorVitima})">
                        <label class="form-check-label">Sobrevivente</label>
                    </div>
                </div>
                <div id="vitima_situacao_campos_${contadorVitima}" style="display:none;">
                    <div class="mb-2" id="vitima_posicao_${contadorVitima}" style="display:none;">
                        <select class="form-select" name="vitima_posicao[]">
                            <option value="">Selecione a posição da vítima...</option>
                            ${window.posicoesVitima.map(p => `<option value="${p.id}">${p.descricao}</option>`).join('')}
                        </select>
                    </div>
                    <div class="mb-2" id="vitima_hospital_${contadorVitima}" style="display:none;">
                        <input type="text" class="form-control" placeholder="Hospital para onde foi socorrida" name="vitima_hospital[]">
                    </div>
                </div>
                <div class="mb-2">
                    <textarea class="form-control" placeholder="Lesões apresentadas" name="vitima_lesoes[]" rows="2"></textarea>
                </div>
            </div>
        `;
        document.getElementById('vitimas_container').insertAdjacentHTML('beforeend', html);
    }
    
    // Alterar situação da vítima
    function alterarSituacaoVitima(num) {
        const situacao = document.querySelector(`input[name="vitima_situacao_${num}"]:checked`)?.value;
        const camposSituacao = document.getElementById(`vitima_situacao_campos_${num}`);
        const posicaoDiv = document.getElementById(`vitima_posicao_${num}`);
        const hospitalDiv = document.getElementById(`vitima_hospital_${num}`);
        
        if (situacao) {
            camposSituacao.style.display = 'block';
            if (situacao === 'FATAL') {
                posicaoDiv.style.display = 'block';
                hospitalDiv.style.display = 'none';
            } else {
                posicaoDiv.style.display = 'none';
                hospitalDiv.style.display = 'block';
            }
        } else {
            camposSituacao.style.display = 'none';
        }
    }
    
    // Adicionar autor
    let contadorAutor = 0;
    function adicionarAutor() {
        contadorAutor++;
        const html = `
            <div class="item-dinamico" id="autor_${contadorAutor}">
                <button type="button" class="btn btn-sm btn-danger btn-remover" 
                        onclick="removerItem('autor_${contadorAutor}')">×</button>
                <h6>Autor ${contadorAutor}</h6>
                <div class="row">
                    <div class="col-md-6 mb-2">
                        <input type="text" class="form-control" placeholder="Nome" name="autor_nome[]">
                    </div>
                    <div class="col-md-6 mb-2">
                        <input type="date" class="form-control" placeholder="Data de Nascimento" name="autor_data_nascimento[]">
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-4 mb-2">
                        <input type="text" class="form-control" placeholder="Cor" name="autor_cor[]">
                    </div>
                    <div class="col-md-4 mb-2">
                        <input type="text" class="form-control" placeholder="Nacionalidade" name="autor_nacionalidade[]">
                    </div>
                    <div class="col-md-4 mb-2">
                        <input type="text" class="form-control" placeholder="Naturalidade" name="autor_naturalidade[]">
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-4 mb-2">
                        <input type="text" class="form-control" placeholder="Profissão" name="autor_profissao[]">
                    </div>
                    <div class="col-md-4 mb-2">
                        <input type="text" class="form-control" placeholder="CPF" name="autor_cpf[]">
                    </div>
                    <div class="col-md-4 mb-2">
                        <input type="text" class="form-control" placeholder="RG" name="autor_rg[]">
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-2">
                        <input type="text" class="form-control" placeholder="Nome do Pai" name="autor_pai[]">
                    </div>
                    <div class="col-md-6 mb-2">
                        <input type="text" class="form-control" placeholder="Nome da Mãe" name="autor_mae[]">
                    </div>
                </div>
                <div class="mb-2">
                    <textarea class="form-control" placeholder="Endereço" name="autor_endereco[]" rows="2"></textarea>
                </div>
                <div class="mb-2">
                    <textarea class="form-control" placeholder="Sinais característicos (se autor ignorado)" 
                              name="autor_caracteristicas[]" rows="2"></textarea>
                </div>
            </div>
        `;
        document.getElementById('autores_container').insertAdjacentHTML('beforeend', html);
    }
    
    // Adicionar testemunha
    let contadorTestemunha = 0;
    function adicionarTestemunha() {
        contadorTestemunha++;
        const html = `
            <div class="item-dinamico" id="testemunha_${contadorTestemunha}">
                <button type="button" class="btn btn-sm btn-danger btn-remover" 
                        onclick="removerItem('testemunha_${contadorTestemunha}')">×</button>
                <h6>Testemunha ${contadorTestemunha}</h6>
                <div class="row">
                    <div class="col-md-6 mb-2">
                        <input type="text" class="form-control" placeholder="Nome" name="testemunha_nome[]">
                    </div>
                    <div class="col-md-6 mb-2">
                        <input type="date" class="form-control" placeholder="Data de Nascimento" name="testemunha_data_nascimento[]">
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-2">
                        <input type="text" class="form-control" placeholder="Nacionalidade" name="testemunha_nacionalidade[]">
                    </div>
                    <div class="col-md-6 mb-2">
                        <input type="text" class="form-control" placeholder="Naturalidade" name="testemunha_naturalidade[]">
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-4 mb-2">
                        <input type="text" class="form-control" placeholder="Profissão" name="testemunha_profissao[]">
                    </div>
                    <div class="col-md-4 mb-2">
                        <input type="text" class="form-control" placeholder="CPF" name="testemunha_cpf[]">
                    </div>
                    <div class="col-md-4 mb-2">
                        <input type="text" class="form-control" placeholder="RG" name="testemunha_rg[]">
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-2">
                        <input type="text" class="form-control" placeholder="Nome do Pai" name="testemunha_pai[]">
                    </div>
                    <div class="col-md-6 mb-2">
                        <input type="text" class="form-control" placeholder="Nome da Mãe" name="testemunha_mae[]">
                    </div>
                </div>
                <div class="mb-2">
                    <textarea class="form-control" placeholder="Endereço" name="testemunha_endereco[]" rows="2"></textarea>
                </div>
                <div class="mb-2">
                    <input type="text" class="form-control" placeholder="Telefone" name="testemunha_telefone[]">
                </div>
            </div>
        `;
        document.getElementById('testemunhas_container').insertAdjacentHTML('beforeend', html);
    }
    
    // Array para armazenar fotos
    let fotosArray = [];
    
    // Preview de fotos
    document.getElementById('fotos').addEventListener('change', function(e) {
        console.log('Arquivos selecionados:', e.target.files.length);
        adicionarFotosAoArray(e.target.files);
    });
    
    // Camera input
    document.getElementById('camera_input').addEventListener('change', function(e) {
        console.log('Foto da câmera:', e.target.files.length);
        if (e.target.files.length > 0) {
            adicionarFotosAoArray(e.target.files);
        }
    });
    
    // Adicionar fotos ao array
    // Função para comprimir imagem
    function comprimirImagem(file, maxWidth, maxHeight, quality) {
        return new Promise((resolve, reject) => {
            const reader = new FileReader();
            reader.readAsDataURL(file);
            
            reader.onload = function(e) {
                const img = new Image();
                img.src = e.target.result;
                
                img.onload = function() {
                    const canvas = document.createElement('canvas');
                    let width = img.width;
                    let height = img.height;
                    
                    // Calcular novo tamanho mantendo proporção
                    if (width > height) {
                        if (width > maxWidth) {
                            height = Math.round((height * maxWidth) / width);
                            width = maxWidth;
                        }
                    } else {
                        if (height > maxHeight) {
                            width = Math.round((width * maxHeight) / height);
                            height = maxHeight;
                        }
                    }
                    
                    canvas.width = width;
                    canvas.height = height;
                    
                    const ctx = canvas.getContext('2d');
                    ctx.drawImage(img, 0, 0, width, height);
                    
                    // Comprimir para JPEG com qualidade especificada
                    const compressedDataUrl = canvas.toDataURL('image/jpeg', quality);
                    
                    resolve({
                        nome: file.name,
                        tipo: 'image/jpeg',
                        tamanho: Math.round(compressedDataUrl.length * 0.75), // Estimativa do tamanho
                        dados: compressedDataUrl,
                        original_size: file.size,
                        compressed_size: compressedDataUrl.length
                    });
                };
                
                img.onerror = reject;
            };
            
            reader.onerror = reject;
        });
    }
    
    // Adicionar fotos ao array com compressão
    async function adicionarFotosAoArray(files) {
        const maxWidth = 1200;  // Largura máxima
        const maxHeight = 1200; // Altura máxima
        const quality = 0.7;    // Qualidade JPEG (0.7 = 70%)
        
        // Verificar espaço disponível
        const espacoUsado = calcularEspacoUsado();
        const espacoLimite = 4 * 1024 * 1024; // 4MB limite seguro (localStorage tem ~5-10MB)
        
        if (espacoUsado > espacoLimite) {
            mostrarErro('Espaço insuficiente! Delete algumas fotos antigas ou sincronize os dados.');
            return;
        }
        
        // Mostrar loading
        mostrarStatusSave('Processando fotos...', 'info');
        
        for (const file of Array.from(files)) {
            try {
                console.log(`Processando: ${file.name} (${(file.size / 1024 / 1024).toFixed(2)}MB)`);
                
                // Comprimir imagem
                const fotoComprimida = await comprimirImagem(file, maxWidth, maxHeight, quality);
                
                console.log(`Comprimido: ${(fotoComprimida.compressed_size / 1024 / 1024).toFixed(2)}MB`);
                console.log(`Redução: ${Math.round((1 - fotoComprimida.compressed_size / file.size) * 100)}%`);
                
                // Verificar se ainda cabe
                const novoEspaco = espacoUsado + fotoComprimida.compressed_size;
                if (novoEspaco > espacoLimite) {
                    mostrarErro(`Limite de armazenamento atingido! Conseguiu adicionar ${fotosArray.length} fotos.`);
                    break;
                }
                
                fotosArray.push(fotoComprimida);
                atualizarPreviewFotos();
                
            } catch (error) {
                console.error('Erro ao processar foto:', error);
                mostrarErro('Erro ao processar imagem: ' + file.name);
            }
        }
        
        mostrarStatusSave(`${fotosArray.length} fotos prontas para salvar`, 'success');
        mostrarEspacoDisponivel();
    }
    
    // Calcular espaço usado no localStorage
    function calcularEspacoUsado() {
        let total = 0;
        for (let key in localStorage) {
            if (localStorage.hasOwnProperty(key)) {
                total += localStorage[key].length + key.length;
            }
        }
        return total;
    }
    
    // Mostrar espaço disponível
    function mostrarEspacoDisponivel() {
        const usado = calcularEspacoUsado();
        const limite = 5 * 1024 * 1024; // 5MB
        const porcentagem = Math.round((usado / limite) * 100);
        
        console.log(`Espaço usado: ${(usado / 1024 / 1024).toFixed(2)}MB de 5MB (${porcentagem}%)`);
        
        // Mostrar aviso se estiver ficando cheio
        if (porcentagem > 80) {
            mostrarErro(`Atenção: ${porcentagem}% do espaço usado. Considere sincronizar os dados.`);
        }
    }
    
    // Limpar dados antigos (emergência)
    function limparDadosAntigos() {
        if (confirm('Isso removerá TODAS as recognições offline não sincronizadas. Continuar?')) {
            const recognicoesOffline = JSON.parse(localStorage.getItem('recognicoes_offline') || '[]');
            
            recognicoesOffline.forEach(id => {
                localStorage.removeItem('recognicao_' + id);
            });
            
            localStorage.removeItem('recognicoes_offline');
            alert('Dados limpos. Espaço liberado!');
            location.reload();
        }
    }
    
    // Adicionar botão de emergência para limpar cache (apenas se necessário)
    if (!isOnline()) {
        const espacoUsado = calcularEspacoUsado();
        if (espacoUsado > 4 * 1024 * 1024) {
            const btnLimpar = document.createElement('button');
            btnLimpar.className = 'btn btn-warning btn-sm';
            btnLimpar.textContent = '⚠️ Limpar Cache Local';
            btnLimpar.onclick = limparDadosAntigos;
            btnLimpar.style.position = 'fixed';
            btnLimpar.style.bottom = '80px';
            btnLimpar.style.right = '20px';
            btnLimpar.style.zIndex = '999';
            document.body.appendChild(btnLimpar);
        }
    }
    
    // Atualizar preview das fotos
    function atualizarPreviewFotos() {
        const preview = document.getElementById('preview_fotos');
        preview.innerHTML = '';
        
        fotosArray.forEach((foto, index) => {
            const col = document.createElement('div');
            col.className = 'col-md-3 mb-3';
            col.innerHTML = `
                <div class="foto-preview">
                    <img src="${foto.dados}" class="img-fluid rounded" alt="Foto ${index + 1}">
                    <button type="button" class="btn-remover-foto" onclick="removerFoto(${index})">×</button>
                    <small class="d-block text-center mt-1">Foto ${index + 1}</small>
                </div>
            `;
            preview.appendChild(col);
        });
    }
    
    // Remover foto
    function removerFoto(index) {
        fotosArray.splice(index, 1);
        atualizarPreviewFotos();
    }
    
    // Abrir câmera
    function abrirCamera() {
        document.getElementById('camera_input').click();
    }
    
    // Salvar rascunho

    // Função para salvar rascunho (online e offline)
    function salvarRascunho(mostrarMensagem = false) {
        try {
            const recognicaoId = document.getElementById('recognicao_id').value || 'temp_' + Date.now();
            
            // Se não tinha ID, gerar um temporário
            if (!document.getElementById('recognicao_id').value) {
                document.getElementById('recognicao_id').value = recognicaoId;
            }
            
            // Debug: verificar fotos
            console.log('Fotos no array:', fotosArray);
            console.log('Quantidade de fotos:', fotosArray.length);
            console.log('Está online?', isOnline());
            
            const dados = {
                recognicao_id: recognicaoId,
                rai: document.getElementById('rai').value || '',
                data_hora_acionamento: document.getElementById('data_hora_acionamento').value || '',
                data_hora_fato: document.getElementById('data_hora_fato').value || '',
                dia_semana: document.getElementById('dia_semana').value || '',
                natureza_id: document.getElementById('natureza_id').value || '',
                endereco_fato: document.getElementById('endereco_fato').value || '',
                geolocalizacao: document.getElementById('geolocalizacao').value || '',
                preservacao_local_id: document.getElementById('preservacao_local_id').value || '',
                perito_id: document.getElementById('perito_id').value || '',
                tipo_local: document.querySelector('input[name="tipo_local"]:checked')?.value || '',
                local_externo_id: document.getElementById('local_externo_id').value || '',
                local_interno_id: document.getElementById('local_interno_id').value || '',
                tipo_piso_id: document.getElementById('tipo_piso_id').value || '',
                disposicao_objetos_id: document.getElementById('disposicao_objetos_id').value || '',
                condicoes_higiene_id: document.getElementById('condicoes_higiene_id').value || '',
                cameras_monitoramento: document.getElementById('cameras_monitoramento').value || '',
                objetos_recolhidos: document.getElementById('objetos_recolhidos').value || '',
                policiais_preservacao: obterPoliciaisPreservacao(),
                vitimas: obterVitimas(),
                autores: obterAutores(),
                testemunhas: obterTestemunhas(),
                veiculos_empregados: $('#veiculos_empregados').val() || [],
                armas_empregadas: $('#armas_empregadas').val() || [],
                historico: document.getElementById('historico').value || '',
                policial1_id: document.getElementById('policial1_id').value || '',
                policial2_id: document.getElementById('policial2_id').value || '',
                fotos: fotosArray
            };
            
            console.log('Dados sendo salvos:', dados);
            
            if (isOnline()) {
                // Salvar no servidor
                console.log('Salvando online...');
                fetch('api.php?action=salvar_rascunho', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify(dados)
                })
                .then(response => {
                    console.log('Response status:', response.status);
                    const contentType = response.headers.get("content-type");
                    if (contentType && contentType.indexOf("application/json") !== -1) {
                        return response.json();
                    } else {
                        return response.text().then(text => {
                            console.error('Resposta não é JSON:', text);
                            throw new Error('Resposta não é JSON: ' + text.substring(0, 200));
                        });
                    }
                })
                .then(data => {
                    console.log('Resposta da API:', data);
                    if (data.success) {
                        if (mostrarMensagem) {
                            mostrarStatusSave('Dados salvos no servidor!');
                        }
                        // Atualizar ID se foi criado novo
                        if (data.id && recognicaoId.startsWith('temp_')) {
                            document.getElementById('recognicao_id').value = data.id;
                            history.replaceState(null, null, 'formulario.php?id=' + data.id);
                            
                            // Remover dados offline após salvar com sucesso
                            localStorage.removeItem('recognicao_' + recognicaoId);
                        }
                        
                        // Limpar array de fotos após salvar com sucesso
                        if (data.fotos_salvas > 0) {
                            console.log('Fotos salvas com sucesso:', data.fotos_salvas);
                            fotosArray = [];
                            atualizarPreviewFotos();
                            
                            // Recarregar página para mostrar fotos salvas
                            setTimeout(() => {
                                location.reload();
                            }, 1000);
                        }
                        
                        esconderErro();
                    } else {
                        mostrarErro(data.message || 'Erro ao salvar');
                    }
                })
                .catch(error => {
                    console.error('Erro ao salvar online:', error);
                    // Se falhar online, salvar offline
                    salvarOffline('recognicao_' + recognicaoId, dados);
                    if (mostrarMensagem) {
                        mostrarStatusSave('Erro de conexão. Dados salvos localmente!', 'warning');
                    }
                });
            } else {
                // Salvar offline
                console.log('Salvando offline...');
                salvarOffline('recognicao_' + recognicaoId, dados);
                if (mostrarMensagem) {
                    mostrarStatusSave('Sem conexão. Dados salvos localmente!', 'warning');
                }
                
                // Adicionar à lista de recognições offline se não existir
                let recognicoesOffline = JSON.parse(localStorage.getItem('recognicoes_offline') || '[]');
                if (!recognicoesOffline.includes(recognicaoId)) {
                    recognicoesOffline.push(recognicaoId);
                    localStorage.setItem('recognicoes_offline', JSON.stringify(recognicoesOffline));
                }
            }
        } catch (error) {
            console.error('Erro ao preparar dados:', error);
            mostrarErro('Erro ao preparar dados: ' + error.message);
        }
    }
    
    // Mostrar status de salvamento
    function mostrarStatusSave(mensagem = 'Dados salvos!', tipo = 'success') {
        const status = document.getElementById('status-save');
        status.className = `alert alert-${tipo}`;
        status.textContent = mensagem;
        status.style.display = 'block';
        setTimeout(() => {
            status.style.display = 'none';
        }, 3000);
    }
    
    // Função para sincronizar dados quando voltar online
    function sincronizarDadosOffline() {
        if (!isOnline()) return;
        
        const recognicoesOffline = JSON.parse(localStorage.getItem('recognicoes_offline') || '[]');
        
        recognicoesOffline.forEach(recognicaoId => {
            const dados = recuperarOffline('recognicao_' + recognicaoId);
            if (dados) {
                console.log('Sincronizando recognição offline:', recognicaoId);
                
                fetch('api.php?action=salvar_rascunho', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify(dados)
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        console.log('Recognição sincronizada:', recognicaoId);
                        // Remover do localStorage após sincronizar
                        localStorage.removeItem('recognicao_' + recognicaoId);
                        
                        // Atualizar lista de recognições offline
                        const novaLista = recognicoesOffline.filter(id => id !== recognicaoId);
                        localStorage.setItem('recognicoes_offline', JSON.stringify(novaLista));
                        
                        // Se for a recognição atual, atualizar ID
                        if (document.getElementById('recognicao_id').value === recognicaoId && data.id) {
                            document.getElementById('recognicao_id').value = data.id;
                            history.replaceState(null, null, 'formulario.php?id=' + data.id);
                        }
                    }
                })
                .catch(error => {
                    console.error('Erro ao sincronizar:', error);
                });
            }
        });
    }
    
    // Adicionar listener para quando voltar online
    window.addEventListener('online', function() {
        console.log('Conexão restaurada! Sincronizando dados...');
        setTimeout(sincronizarDadosOffline, 2000); // Aguardar 2 segundos antes de sincronizar
    });
    
    // Verificar se há dados offline ao carregar a página
    window.addEventListener('load', function() {
        if (isOnline()) {
            sincronizarDadosOffline();
        }
    });


    
    // Obter policiais de preservação
    function obterPoliciaisPreservacao() {
        const policiais = [];
        const nomes = document.querySelectorAll('input[name="policial_preservacao_nome[]"]');
        const matriculas = document.querySelectorAll('input[name="policial_preservacao_matricula[]"]');
        
        for (let i = 0; i < nomes.length; i++) {
            if (nomes[i].value || matriculas[i].value) {
                policiais.push({
                    nome: nomes[i].value,
                    matricula: matriculas[i].value
                });
            }
        }
        return policiais;
    }
    
    // Obter vítimas
    function obterVitimas() {
        const vitimas = [];
        const containers = document.querySelectorAll('[id^="vitima_"]:not([id*="_situacao_"]):not([id*="_posicao_"]):not([id*="_hospital_"])');
        
        containers.forEach((container, index) => {
            const num = container.id.split('_')[1];
            const vitima = {
                nome: container.querySelector('input[name="vitima_nome[]"]')?.value,
                data_nascimento: container.querySelector('input[name="vitima_data_nascimento[]"]')?.value,
                cor: container.querySelector('input[name="vitima_cor[]"]')?.value,
                nacionalidade: container.querySelector('input[name="vitima_nacionalidade[]"]')?.value,
                naturalidade: container.querySelector('input[name="vitima_naturalidade[]"]')?.value,
                profissao: container.querySelector('input[name="vitima_profissao[]"]')?.value,
                cpf: container.querySelector('input[name="vitima_cpf[]"]')?.value,
                rg: container.querySelector('input[name="vitima_rg[]"]')?.value,
                pai: container.querySelector('input[name="vitima_pai[]"]')?.value,
                mae: container.querySelector('input[name="vitima_mae[]"]')?.value,
                endereco: container.querySelector('textarea[name="vitima_endereco[]"]')?.value,
                situacao: document.querySelector(`input[name="vitima_situacao_${num}"]:checked`)?.value,
                posicao_vitima_id: container.querySelector('select[name="vitima_posicao[]"]')?.value,
                hospital_socorrido: container.querySelector('input[name="vitima_hospital[]"]')?.value,
                lesoes_apresentadas: container.querySelector('textarea[name="vitima_lesoes[]"]')?.value
            };
            
            if (vitima.nome || vitima.cpf) {
                vitimas.push(vitima);
            }
        });
        
        return vitimas;
    }
    
    // Obter autores
    function obterAutores() {
        const autores = [];
        const containers = document.querySelectorAll('[id^="autor_"]');
        
        containers.forEach(container => {
            const autor = {
                nome: container.querySelector('input[name="autor_nome[]"]')?.value,
                data_nascimento: container.querySelector('input[name="autor_data_nascimento[]"]')?.value,
                cor: container.querySelector('input[name="autor_cor[]"]')?.value,
                nacionalidade: container.querySelector('input[name="autor_nacionalidade[]"]')?.value,
                naturalidade: container.querySelector('input[name="autor_naturalidade[]"]')?.value,
                profissao: container.querySelector('input[name="autor_profissao[]"]')?.value,
                cpf: container.querySelector('input[name="autor_cpf[]"]')?.value,
                rg: container.querySelector('input[name="autor_rg[]"]')?.value,
                pai: container.querySelector('input[name="autor_pai[]"]')?.value,
                mae: container.querySelector('input[name="autor_mae[]"]')?.value,
                endereco: container.querySelector('textarea[name="autor_endereco[]"]')?.value,
                caracteristicas: container.querySelector('textarea[name="autor_caracteristicas[]"]')?.value
            };
            
            if (autor.nome || autor.caracteristicas) {
                autores.push(autor);
            }
        });
        
        return autores;
    }
    
    // Obter testemunhas
    function obterTestemunhas() {
        const testemunhas = [];
        const containers = document.querySelectorAll('[id^="testemunha_"]');
        
        containers.forEach(container => {
            const testemunha = {
                nome: container.querySelector('input[name="testemunha_nome[]"]')?.value,
                data_nascimento: container.querySelector('input[name="testemunha_data_nascimento[]"]')?.value,
                nacionalidade: container.querySelector('input[name="testemunha_nacionalidade[]"]')?.value,
                naturalidade: container.querySelector('input[name="testemunha_naturalidade[]"]')?.value,
                profissao: container.querySelector('input[name="testemunha_profissao[]"]')?.value,
                cpf: container.querySelector('input[name="testemunha_cpf[]"]')?.value,
                rg: container.querySelector('input[name="testemunha_rg[]"]')?.value,
                pai: container.querySelector('input[name="testemunha_pai[]"]')?.value,
                mae: container.querySelector('input[name="testemunha_mae[]"]')?.value,
                endereco: container.querySelector('textarea[name="testemunha_endereco[]"]')?.value,
                telefone: container.querySelector('input[name="testemunha_telefone[]"]')?.value
            };
            
            if (testemunha.nome || testemunha.cpf) {
                testemunhas.push(testemunha);
            }
        });
        
        return testemunhas;
    }
    
    // Mostrar status de salvamento
    function mostrarStatusSave() {
        const status = document.getElementById('status-save');
        status.style.display = 'block';
        setTimeout(() => {
            status.style.display = 'none';
        }, 3000);
    }
    
    // Mostrar erro
    function mostrarErro(mensagem) {
        const errorLog = document.getElementById('error-log');
        const errorMessage = document.getElementById('error-message');
        errorMessage.textContent = mensagem;
        errorLog.style.display = 'block';
        setTimeout(() => {
            errorLog.style.display = 'none';
        }, 10000);
    }
    
    // Esconder erro
    function esconderErro() {
        document.getElementById('error-log').style.display = 'none';
    }
    
    // Atualizar status de conexão
    function atualizarStatusConexao() {
        const status = document.getElementById('status-conexao');
        if (isOnline()) {
            status.innerHTML = '<span class="badge bg-success">Online</span>';
        } else {
            status.innerHTML = '<span class="badge bg-danger">Offline</span>';
        }
    }
    
    setInterval(atualizarStatusConexao, 1000);
    atualizarStatusConexao();
    

    // Carregar dados se existirem
    window.onload = function() {
        const recognicaoId = document.getElementById('recognicao_id').value;
        
        console.log('Carregando recognição:', recognicaoId);
        
        // Se for um ID temporário, carregar do localStorage
        if (recognicaoId && recognicaoId.startsWith('temp_')) {
            console.log('Carregando dados offline...');
            const dados = recuperarOffline('recognicao_' + recognicaoId);
            if (dados) {
                carregarDadosFormulario(dados);
            }
        } else if (recognicaoId) {
            // Carregar dados do PHP se houver
            console.log('Carregando dados do servidor...');
            
            <?php if (!empty($policiais_preservacao)): ?>
            // Carregar policiais de preservação
            console.log('Carregando <?= count($policiais_preservacao) ?> policiais de preservação');
            <?php foreach ($policiais_preservacao as $index => $policial): ?>
            setTimeout(() => {
                adicionarPolicialPreservacao();
                setTimeout(() => {
                    const containers = document.querySelectorAll('[id^="policial_preservacao_"]');
                    const container = containers[containers.length - 1];
                    if (container) {
                        container.querySelector('input[name="policial_preservacao_nome[]"]').value = '<?= addslashes($policial['nome'] ?? '') ?>';
                        container.querySelector('input[name="policial_preservacao_matricula[]"]').value = '<?= addslashes($policial['matricula'] ?? '') ?>';
                    }
                }, 100);
            }, <?= $index * 200 ?>);
            <?php endforeach; ?>
            <?php endif; ?>
            
            <?php if (!empty($vitimas)): ?>
            // Carregar vítimas
            console.log('Carregando <?= count($vitimas) ?> vítimas');
            <?php foreach ($vitimas as $index => $vitima): ?>
            setTimeout(() => {
                adicionarVitima();
                setTimeout(() => {
                    const containers = document.querySelectorAll('[id^="vitima_"]:not([id*="_situacao_"]):not([id*="_posicao_"]):not([id*="_hospital_"])');
                    const container = containers[containers.length - 1];
                    if (container) {
                        const num = container.id.split('_')[1];
                        console.log('Preenchendo vítima', num);
                        
                        // Preencher campos básicos
                        const campos = {
                            'vitima_nome[]': '<?= addslashes($vitima['nome'] ?? '') ?>',
                            'vitima_data_nascimento[]': '<?= $vitima['data_nascimento'] ?? '' ?>',
                            'vitima_cor[]': '<?= addslashes($vitima['cor'] ?? '') ?>',
                            'vitima_nacionalidade[]': '<?= addslashes($vitima['nacionalidade'] ?? '') ?>',
                            'vitima_naturalidade[]': '<?= addslashes($vitima['naturalidade'] ?? '') ?>',
                            'vitima_profissao[]': '<?= addslashes($vitima['profissao'] ?? '') ?>',
                            'vitima_cpf[]': '<?= addslashes($vitima['cpf'] ?? '') ?>',
                            'vitima_rg[]': '<?= addslashes($vitima['rg'] ?? '') ?>',
                            'vitima_pai[]': '<?= addslashes($vitima['pai'] ?? '') ?>',
                            'vitima_mae[]': '<?= addslashes($vitima['mae'] ?? '') ?>',
                            'vitima_endereco[]': '<?= addslashes($vitima['endereco'] ?? '') ?>',
                            'vitima_lesoes[]': '<?= addslashes($vitima['lesoes_apresentadas'] ?? '') ?>'
                        };
                        
                        // Preencher cada campo
                        for (let [name, value] of Object.entries(campos)) {
                            const input = container.querySelector(`[name="${name}"]`);
                            if (input) {
                                input.value = value;
                            }
                        }
                        
                        <?php if ($vitima['situacao']): ?>
                        // Marcar situação
                        setTimeout(() => {
                            const radioSituacao = document.querySelector(`input[name="vitima_situacao_${num}"][value="<?= $vitima['situacao'] ?>"]`);
                            if (radioSituacao) {
                                radioSituacao.checked = true;
                                alterarSituacaoVitima(num);
                                
                                <?php if ($vitima['situacao'] == 'FATAL' && $vitima['posicao_vitima_id']): ?>
                                setTimeout(() => {
                                    const selectPosicao = container.querySelector('select[name="vitima_posicao[]"]');
                                    if (selectPosicao) {
                                        selectPosicao.value = '<?= $vitima['posicao_vitima_id'] ?>';
                                    }
                                }, 100);
                                <?php elseif ($vitima['situacao'] == 'SOBREVIVENTE' && $vitima['hospital_socorrido']): ?>
                                setTimeout(() => {
                                    const inputHospital = container.querySelector('input[name="vitima_hospital[]"]');
                                    if (inputHospital) {
                                        inputHospital.value = '<?= addslashes($vitima['hospital_socorrido'] ?? '') ?>';
                                    }
                                }, 100);
                                <?php endif; ?>
                            }
                        }, 200);
                        <?php endif; ?>
                    }
                }, 300);
            }, <?= ($index + 1) * 500 ?>);
            <?php endforeach; ?>
            <?php endif; ?>
            
            <?php if (!empty($autores)): ?>
            // Carregar autores
            console.log('Carregando <?= count($autores) ?> autores');
            <?php foreach ($autores as $index => $autor): ?>
            setTimeout(() => {
                adicionarAutor();
                setTimeout(() => {
                    const containers = document.querySelectorAll('[id^="autor_"]');
                    const container = containers[containers.length - 1];
                    if (container) {
                        console.log('Preenchendo autor', container.id);
                        
                        const campos = {
                            'autor_nome[]': '<?= addslashes($autor['nome'] ?? '') ?>',
                            'autor_data_nascimento[]': '<?= $autor['data_nascimento'] ?? '' ?>',
                            'autor_cor[]': '<?= addslashes($autor['cor'] ?? '') ?>',
                            'autor_nacionalidade[]': '<?= addslashes($autor['nacionalidade'] ?? '') ?>',
                            'autor_naturalidade[]': '<?= addslashes($autor['naturalidade'] ?? '') ?>',
                            'autor_profissao[]': '<?= addslashes($autor['profissao'] ?? '') ?>',
                            'autor_cpf[]': '<?= addslashes($autor['cpf'] ?? '') ?>',
                            'autor_rg[]': '<?= addslashes($autor['rg'] ?? '') ?>',
                            'autor_pai[]': '<?= addslashes($autor['pai'] ?? '') ?>',
                            'autor_mae[]': '<?= addslashes($autor['mae'] ?? '') ?>',
                            'autor_endereco[]': '<?= addslashes($autor['endereco'] ?? '') ?>',
                            'autor_caracteristicas[]': '<?= addslashes($autor['caracteristicas'] ?? '') ?>'
                        };
                        
                        for (let [name, value] of Object.entries(campos)) {
                            const input = container.querySelector(`[name="${name}"]`);
                            if (input) {
                                input.value = value;
                            }
                        }
                    }
                }, 300);
            }, <?= ($index + 1) * 500 ?>);
            <?php endforeach; ?>
            <?php endif; ?>
            
            <?php if (!empty($testemunhas)): ?>
            // Carregar testemunhas
            console.log('Carregando <?= count($testemunhas) ?> testemunhas');
            <?php foreach ($testemunhas as $index => $testemunha): ?>
            setTimeout(() => {
                adicionarTestemunha();
                setTimeout(() => {
                    const containers = document.querySelectorAll('[id^="testemunha_"]');
                    const container = containers[containers.length - 1];
                    if (container) {
                        console.log('Preenchendo testemunha', container.id);
                        
                        const campos = {
                            'testemunha_nome[]': '<?= addslashes($testemunha['nome'] ?? '') ?>',
                            'testemunha_data_nascimento[]': '<?= $testemunha['data_nascimento'] ?? '' ?>',
                            'testemunha_nacionalidade[]': '<?= addslashes($testemunha['nacionalidade'] ?? '') ?>',
                            'testemunha_naturalidade[]': '<?= addslashes($testemunha['naturalidade'] ?? '') ?>',
                            'testemunha_profissao[]': '<?= addslashes($testemunha['profissao'] ?? '') ?>',
                            'testemunha_cpf[]': '<?= addslashes($testemunha['cpf'] ?? '') ?>',
                            'testemunha_rg[]': '<?= addslashes($testemunha['rg'] ?? '') ?>',
                            'testemunha_pai[]': '<?= addslashes($testemunha['pai'] ?? '') ?>',
                            'testemunha_mae[]': '<?= addslashes($testemunha['mae'] ?? '') ?>',
                            'testemunha_endereco[]': '<?= addslashes($testemunha['endereco'] ?? '') ?>',
                            'testemunha_telefone[]': '<?= addslashes($testemunha['telefone'] ?? '') ?>'
                        };
                        
                        for (let [name, value] of Object.entries(campos)) {
                            const input = container.querySelector(`[name="${name}"]`);
                            if (input) {
                                input.value = value;
                            }
                        }
                    }
                }, 300);
            }, <?= ($index + 1) * 500 ?>);
            <?php endforeach; ?>
            <?php endif; ?>
        }
        
        // Adicionar listener para debug
        setTimeout(() => {
            console.log('Verificando carregamento:');
            console.log('- Policiais:', document.querySelectorAll('[id^="policial_preservacao_"]').length);
            console.log('- Vítimas:', document.querySelectorAll('[id^="vitima_"]:not([id*="_situacao_"]):not([id*="_posicao_"]):not([id*="_hospital_"])').length);
            console.log('- Autores:', document.querySelectorAll('[id^="autor_"]').length);
            console.log('- Testemunhas:', document.querySelectorAll('[id^="testemunha_"]').length);
        }, 5000);
    };
    
    // Função melhorada para carregar dados do localStorage
    function carregarDadosFormulario(dados) {
        console.log('Carregando dados do formulário:', dados);
        
        // Campos simples
        if (dados.rai) document.getElementById('rai').value = dados.rai;
        if (dados.data_hora_acionamento) document.getElementById('data_hora_acionamento').value = dados.data_hora_acionamento;
        if (dados.data_hora_fato) document.getElementById('data_hora_fato').value = dados.data_hora_fato;
        if (dados.dia_semana) document.getElementById('dia_semana').value = dados.dia_semana;
        if (dados.natureza_id) document.getElementById('natureza_id').value = dados.natureza_id;
        if (dados.endereco_fato) document.getElementById('endereco_fato').value = dados.endereco_fato;
        if (dados.geolocalizacao) document.getElementById('geolocalizacao').value = dados.geolocalizacao;
        if (dados.preservacao_local_id) document.getElementById('preservacao_local_id').value = dados.preservacao_local_id;
        if (dados.perito_id) $('#perito_id').val(dados.perito_id).trigger('change');
        if (dados.tipo_local) {
            document.querySelector(`input[name="tipo_local"][value="${dados.tipo_local}"]`).checked = true;
            alterarTipoLocal();
        }
        if (dados.local_externo_id) document.getElementById('local_externo_id').value = dados.local_externo_id;
        if (dados.local_interno_id) document.getElementById('local_interno_id').value = dados.local_interno_id;
        if (dados.tipo_piso_id) document.getElementById('tipo_piso_id').value = dados.tipo_piso_id;
        if (dados.disposicao_objetos_id) document.getElementById('disposicao_objetos_id').value = dados.disposicao_objetos_id;
        if (dados.condicoes_higiene_id) document.getElementById('condicoes_higiene_id').value = dados.condicoes_higiene_id;
        if (dados.cameras_monitoramento) document.getElementById('cameras_monitoramento').value = dados.cameras_monitoramento;
        if (dados.objetos_recolhidos) document.getElementById('objetos_recolhidos').value = dados.objetos_recolhidos;
        if (dados.historico) document.getElementById('historico').value = dados.historico;
        if (dados.policial1_id) $('#policial1_id').val(dados.policial1_id).trigger('change');
        if (dados.policial2_id) $('#policial2_id').val(dados.policial2_id).trigger('change');
        
        // Meios empregados
        if (dados.veiculos_empregados) $('#veiculos_empregados').val(dados.veiculos_empregados).trigger('change');
        if (dados.armas_empregadas) $('#armas_empregadas').val(dados.armas_empregadas).trigger('change');
        
        // Carregar pessoas envolvidas
        
        // Policiais de preservação
        if (dados.policiais_preservacao && dados.policiais_preservacao.length > 0) {
            dados.policiais_preservacao.forEach((policial, index) => {
                setTimeout(() => {
                    adicionarPolicialPreservacao();
                    setTimeout(() => {
                        const containers = document.querySelectorAll('[id^="policial_preservacao_"]');
                        const container = containers[containers.length - 1];
                        if (container) {
                            container.querySelector('input[name="policial_preservacao_nome[]"]').value = policial.nome || '';
                            container.querySelector('input[name="policial_preservacao_matricula[]"]').value = policial.matricula || '';
                        }
                    }, 100);
                }, index * 200);
            });
        }
        
        // Vítimas
        if (dados.vitimas && dados.vitimas.length > 0) {
            dados.vitimas.forEach((vitima, index) => {
                setTimeout(() => {
                    adicionarVitima();
                    setTimeout(() => {
                        const containers = document.querySelectorAll('[id^="vitima_"]:not([id*="_situacao_"]):not([id*="_posicao_"]):not([id*="_hospital_"])');
                        const container = containers[containers.length - 1];
                        if (container) {
                            const num = container.id.split('_')[1];
                            
                            container.querySelector('input[name="vitima_nome[]"]').value = vitima.nome || '';
                            container.querySelector('input[name="vitima_data_nascimento[]"]').value = vitima.data_nascimento || '';
                            container.querySelector('input[name="vitima_cor[]"]').value = vitima.cor || '';
                            container.querySelector('input[name="vitima_nacionalidade[]"]').value = vitima.nacionalidade || '';
                            container.querySelector('input[name="vitima_naturalidade[]"]').value = vitima.naturalidade || '';
                            container.querySelector('input[name="vitima_profissao[]"]').value = vitima.profissao || '';
                            container.querySelector('input[name="vitima_cpf[]"]').value = vitima.cpf || '';
                            container.querySelector('input[name="vitima_rg[]"]').value = vitima.rg || '';
                            container.querySelector('input[name="vitima_pai[]"]').value = vitima.pai || '';
                            container.querySelector('input[name="vitima_mae[]"]').value = vitima.mae || '';
                            container.querySelector('textarea[name="vitima_endereco[]"]').value = vitima.endereco || '';
                            container.querySelector('textarea[name="vitima_lesoes[]"]').value = vitima.lesoes_apresentadas || '';
                            
                            if (vitima.situacao) {
                                setTimeout(() => {
                                    const radio = document.querySelector(`input[name="vitima_situacao_${num}"][value="${vitima.situacao}"]`);
                                    if (radio) {
                                        radio.checked = true;
                                        alterarSituacaoVitima(num);
                                        
                                        setTimeout(() => {
                                            if (vitima.situacao === 'FATAL' && vitima.posicao_vitima_id) {
                                                container.querySelector('select[name="vitima_posicao[]"]').value = vitima.posicao_vitima_id;
                                            } else if (vitima.situacao === 'SOBREVIVENTE' && vitima.hospital_socorrido) {
                                                container.querySelector('input[name="vitima_hospital[]"]').value = vitima.hospital_socorrido;
                                            }
                                        }, 100);
                                    }
                                }, 200);
                            }
                        }
                    }, 300);
                }, (index + 1) * 500);
            });
        }
        
        // Autores
        if (dados.autores && dados.autores.length > 0) {
            dados.autores.forEach((autor, index) => {
                setTimeout(() => {
                    adicionarAutor();
                    setTimeout(() => {
                        const containers = document.querySelectorAll('[id^="autor_"]');
                        const container = containers[containers.length - 1];
                        if (container) {
                            container.querySelector('input[name="autor_nome[]"]').value = autor.nome || '';
                            container.querySelector('input[name="autor_data_nascimento[]"]').value = autor.data_nascimento || '';
                            container.querySelector('input[name="autor_cor[]"]').value = autor.cor || '';
                            container.querySelector('input[name="autor_nacionalidade[]"]').value = autor.nacionalidade || '';
                            container.querySelector('input[name="autor_naturalidade[]"]').value = autor.naturalidade || '';
                            container.querySelector('input[name="autor_profissao[]"]').value = autor.profissao || '';
                            container.querySelector('input[name="autor_cpf[]"]').value = autor.cpf || '';
                            container.querySelector('input[name="autor_rg[]"]').value = autor.rg || '';
                            container.querySelector('input[name="autor_pai[]"]').value = autor.pai || '';
                            container.querySelector('input[name="autor_mae[]"]').value = autor.mae || '';
                            container.querySelector('textarea[name="autor_endereco[]"]').value = autor.endereco || '';
                            container.querySelector('textarea[name="autor_caracteristicas[]"]').value = autor.caracteristicas || '';
                        }
                    }, 300);
                }, (index + 1) * 500);
            });
        }
        
        // Testemunhas
        if (dados.testemunhas && dados.testemunhas.length > 0) {
            dados.testemunhas.forEach((testemunha, index) => {
                setTimeout(() => {
                    adicionarTestemunha();
                    setTimeout(() => {
                        const containers = document.querySelectorAll('[id^="testemunha_"]');
                        const container = containers[containers.length - 1];
                        if (container) {
                            container.querySelector('input[name="testemunha_nome[]"]').value = testemunha.nome || '';
                            container.querySelector('input[name="testemunha_data_nascimento[]"]').value = testemunha.data_nascimento || '';
                            container.querySelector('input[name="testemunha_nacionalidade[]"]').value = testemunha.nacionalidade || '';
                            container.querySelector('input[name="testemunha_naturalidade[]"]').value = testemunha.naturalidade || '';
                            container.querySelector('input[name="testemunha_profissao[]"]').value = testemunha.profissao || '';
                            container.querySelector('input[name="testemunha_cpf[]"]').value = testemunha.cpf || '';
                            container.querySelector('input[name="testemunha_rg[]"]').value = testemunha.rg || '';
                            container.querySelector('input[name="testemunha_pai[]"]').value = testemunha.pai || '';
                            container.querySelector('input[name="testemunha_mae[]"]').value = testemunha.mae || '';
                            container.querySelector('textarea[name="testemunha_endereco[]"]').value = testemunha.endereco || '';
                            container.querySelector('input[name="testemunha_telefone[]"]').value = testemunha.telefone || '';
                        }
                    }, 300);
                }, (index + 1) * 500);
            });
        }
        
        // Carregar fotos se houver
        if (dados.fotos && dados.fotos.length > 0) {
            fotosArray = dados.fotos;
            atualizarPreviewFotos();
        }
    }

    
    function carregarDadosFormulario(dados) {
        // Implementar carregamento dos dados no formulário
        // Por enquanto só os campos básicos
        if (dados.rai) document.getElementById('rai').value = dados.rai;
        if (dados.data_hora_acionamento) document.getElementById('data_hora_acionamento').value = dados.data_hora_acionamento;
        // ... continuar com os outros campos
    }
    // Função para excluir foto existente
    function excluirFotoExistente(fotoId) {
        if (confirm('Tem certeza que deseja excluir esta foto?')) {
            const formData = new FormData();
            formData.append('foto_id', fotoId);
            
            fetch('api.php?action=excluir_foto', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Remover visualmente
                    document.getElementById('foto_existente_' + fotoId).remove();
                    mostrarStatusSave();
                    
                    // Verificar se ainda há fotos
                    const fotosRestantes = document.querySelectorAll('[id^="foto_existente_"]');
                    if (fotosRestantes.length === 0) {
                        const container = document.getElementById('fotos_existentes');
                        if (container) {
                            container.parentElement.style.display = 'none';
                        }
                    }
                } else {
                    mostrarErro('Erro ao excluir foto: ' + data.message);
                }
            })
            .catch(error => {
                mostrarErro('Erro ao excluir foto: ' + error);
            });
        }
    }
    
    // Função para atualizar Select2 de peritos
    function atualizarSelect2Perito() {
        fetch('api.php?action=listar_peritos')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const select = $('#perito_id');
                    const valorAtual = select.val();
                    
                    // Limpar e recriar opções
                    select.empty();
                    select.append('<option value="">Selecione ou digite para cadastrar...</option>');
                    
                    data.peritos.forEach(perito => {
                        select.append(`<option value="${perito.id}">${perito.nome} - ${perito.matricula}</option>`);
                    });
                    
                    // Restaurar valor selecionado se ainda existir
                    if (valorAtual && !valorAtual.startsWith('novo_')) {
                        select.val(valorAtual).trigger('change');
                    }
                }
            });
    }
    
    // Função para atualizar Select2 de policiais
    function atualizarSelect2Policiais() {
        fetch('api.php?action=listar_policiais_gih')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const select1 = $('#policial1_id');
                    const select2 = $('#policial2_id');
                    const valor1 = select1.val();
                    const valor2 = select2.val();
                    
                    // Atualizar ambos os selects
                    [select1, select2].forEach(select => {
                        select.empty();
                        select.append('<option value="">Selecione...</option>');
                        
                        data.policiais.forEach(policial => {
                            select.append(`<option value="${policial.id}">${policial.nome} - ${policial.matricula}</option>`);
                        });
                    });
                    
                    // Restaurar valores
                    if (valor1 && !valor1.startsWith('novo_policial_')) {
                        select1.val(valor1).trigger('change');
                    }
                    if (valor2 && !valor2.startsWith('novo_policial_')) {
                        select2.val(valor2).trigger('change');
                    }
                }
            });
    }
    </script>
    
    <!-- E adicione esta função JavaScript junto com as outras funções: -->
<script>
    // Adicione esta função após a função obterLocalizacao()
    
    // Limpar localização
    function limparLocalizacao() {
        document.getElementById('geolocalizacao').value = '';
    }
    
    // Validar formato de coordenadas ao digitar manualmente
    document.getElementById('geolocalizacao').addEventListener('blur', function() {
        const valor = this.value.trim();
        if (valor && !valor.match(/^Lat:\s*-?\d+\.?\d*,\s*Long:\s*-?\d+\.?\d*$/)) {
            // Tentar formatar automaticamente se o usuário digitar apenas números
            const regex = /(-?\d+\.?\d*)[,\s]+(-?\d+\.?\d*)/;
            const match = valor.match(regex);
            if (match) {
                this.value = `Lat: ${match[1]}, Long: ${match[2]}`;
            } else {
                alert('Formato inválido! Use: Lat: -16.6799, Long: -49.2550');
            }
        }
    });
</script>

<script>
    // Abrir câmera (uma foto por vez)
    function abrirCamera() {
        document.getElementById('camera_input').click();
    }
    
    // Abrir galeria (múltiplas fotos)
    function abrirGaleria() {
        document.getElementById('galeria_input').click();
    }
    
    // Adicionar listener para galeria
    document.getElementById('galeria_input').addEventListener('change', function(e) {
        console.log('Fotos da galeria:', e.target.files.length);
        if (e.target.files.length > 0) {
            adicionarFotosAoArray(e.target.files);
        }
    });
</script>

</body>
</html>