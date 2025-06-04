<?php
// index.php - Página principal
require_once 'config.php';
$pdo = conectarDB();

// Listar recognições existentes
$stmt = $pdo->query("SELECT id, rai, data_hora_fato, status FROM recognicoes ORDER BY id DESC");
$recognicoes = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Recognição Visuográfica</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="manifest" href="manifest.json">
    <style>
        body { 
            background-color: #f8f9fa;
            padding-bottom: 60px;
        }
        .navbar {
            background-color: #1a237e !important;
        }
        .btn-nova {
            position: fixed;
            bottom: 20px;
            right: 20px;
            border-radius: 50%;
            width: 60px;
            height: 60px;
            font-size: 24px;
            z-index: 1000;
        }
        .card {
            margin-bottom: 10px;
            cursor: pointer;
            transition: all 0.3s;
        }
        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .status-rascunho {
            background-color: #ffc107;
            color: #000;
        }
        .status-finalizado {
            background-color: #28a745;
            color: #fff;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-dark">
        <div class="container-fluid">
            <span class="navbar-brand mb-0 h1">Recognição Visuográfica</span>
            <span class="navbar-text text-white">
                <span id="status-conexao"></span>
            </span>
        </div>
    </nav>

    <div class="container mt-3">
        <h4>Recognições Cadastradas</h4>
        
        <?php if (empty($recognicoes)): ?>
            <div class="alert alert-info">
                Nenhuma recognição cadastrada. Clique no botão + para criar uma nova.
            </div>
        <?php else: ?>
<?php foreach ($recognicoes as $rec): ?>
    <div class="card" style="position: relative;">
        <div class="card-body">
            <div class="row">
                <div class="col-8" onclick="window.location='formulario.php?id=<?= $rec['id'] ?>'" style="cursor: pointer;">
                    <h6 class="card-title mb-1">RAI: <?= $rec['rai'] ?: 'Não informado' ?></h6>
                    <p class="card-text mb-0">
                        <small>Data do Fato: <?= $rec['data_hora_fato'] ? date('d/m/Y H:i', strtotime($rec['data_hora_fato'])) : 'Não informado' ?></small>
                    </p>
                </div>
                <div class="col-4 text-end">
                    <span class="badge status-<?= $rec['status'] ?>">
                        <?= ucfirst($rec['status']) ?>
                    </span>
                    <div class="mt-2">
                        <a href="gerar_pdf.php?id=<?= $rec['id'] ?>" target="_blank" 
                           class="btn btn-sm btn-info" 
                           title="Imprimir/PDF"
                           onclick="event.stopPropagation();">
                            🖨️
                        </a>
                        <button class="btn btn-sm btn-danger" 
                                title="Excluir"
                                onclick="event.stopPropagation(); excluirRecognicao(<?= $rec['id'] ?>, '<?= htmlspecialchars($rec['rai'] ?: 'S/N', ENT_QUOTES) ?>')">
                            🗑️
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>
        <?php endif; ?>
    </div>

    <button class="btn btn-primary btn-nova" onclick="novaRecognicao()">
        +
    </button>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <?= gerarScriptOffline() ?>
    
    <script>
    // Criar nova recognição
    function novaRecognicao() {
        fetch('api.php?action=nova_recognicao', {
            method: 'POST'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                window.location.href = 'formulario.php?id=' + data.id;
            } else {
                alert('Erro ao criar nova recognição');
            }
        })
        .catch(error => {
            // Se estiver offline, criar ID temporário
            const tempId = 'temp_' + Date.now();
            salvarOffline('recognicao_' + tempId, {
                id: tempId,
                created_at: new Date().toISOString(),
                status: 'rascunho'
            });
            window.location.href = 'formulario.php?id=' + tempId;
        });
    }
    
    // Indicador de status de conexão
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
    
    // Registrar Service Worker para funcionar offline
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('sw.js');
    }
    </script>
    
    <script>
    // Função para excluir recognição
    function excluirRecognicao(id, rai) {
        if (confirm(`Tem certeza que deseja excluir a recognição RAI: ${rai}?\n\nEsta ação não pode ser desfeita!`)) {
            fetch('api.php?action=excluir_recognicao', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ id: id })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Recognição excluída com sucesso!');
                    location.reload();
                } else {
                    alert('Erro ao excluir: ' + data.message);
                }
            })
            .catch(error => {
                alert('Erro ao excluir recognição: ' + error);
            });
        }
    }
</script>

<!-- Substitua o registro do Service Worker no final do index.php por este código: -->

<script>
    // Registrar Service Worker para funcionar offline
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function() {
            navigator.serviceWorker.register('sw.js')
                .then(function(registration) {
                    console.log('Service Worker registrado com sucesso:', registration.scope);
                    
                    // Forçar atualização se houver nova versão
                    registration.addEventListener('updatefound', function() {
                        const newWorker = registration.installing;
                        newWorker.addEventListener('statechange', function() {
                            if (newWorker.state === 'activated') {
                                console.log('Service Worker atualizado!');
                                // Recarregar para usar nova versão
                                if (confirm('Nova versão disponível! Deseja atualizar?')) {
                                    window.location.reload();
                                }
                            }
                        });
                    });
                })
                .catch(function(error) {
                    console.error('Erro ao registrar Service Worker:', error);
                });
        });
        
        // Escutar mensagens do Service Worker
        navigator.serviceWorker.addEventListener('message', event => {
            console.log('Mensagem do Service Worker:', event.data);
            if (event.data.type === 'sync-status') {
                atualizarStatusConexao();
            }
        });
    } else {
        console.warn('Service Worker não é suportado neste navegador');
    }
    
    // Criar nova recognição (atualizada para funcionar offline)
    function novaRecognicao() {
        if (isOnline()) {
            // Online: criar no servidor
            fetch('api.php?action=nova_recognicao', {
                method: 'POST'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.href = 'formulario.php?id=' + data.id;
                } else {
                    alert('Erro ao criar nova recognição');
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                criarRecognicaoOffline();
            });
        } else {
            // Offline: criar localmente
            criarRecognicaoOffline();
        }
    }
    
    function criarRecognicaoOffline() {
        const tempId = 'temp_' + Date.now();
        
        // Salvar recognição inicial
        const recognicaoInicial = {
            recognicao_id: tempId,
            created_at: new Date().toISOString(),
            status: 'rascunho',
            rai: '',
            data_hora_acionamento: '',
            data_hora_fato: '',
            dia_semana: '',
            natureza_id: '',
            endereco_fato: '',
            geolocalizacao: '',
            preservacao_local_id: '',
            perito_id: '',
            tipo_local: '',
            local_externo_id: '',
            local_interno_id: '',
            tipo_piso_id: '',
            disposicao_objetos_id: '',
            condicoes_higiene_id: '',
            cameras_monitoramento: '',
            objetos_recolhidos: '',
            policiais_preservacao: [],
            vitimas: [],
            autores: [],
            testemunhas: [],
            veiculos_empregados: [],
            armas_empregadas: [],
            historico: '',
            policial1_id: '',
            policial2_id: '',
            fotos: []
        };
        
        // Salvar no localStorage
        salvarOffline('recognicao_' + tempId, recognicaoInicial);
        
        // Adicionar à lista de recognições offline
        let recognicoesOffline = JSON.parse(localStorage.getItem('recognicoes_offline') || '[]');
        if (!recognicoesOffline.includes(tempId)) {
            recognicoesOffline.push(tempId);
            localStorage.setItem('recognicoes_offline', JSON.stringify(recognicoesOffline));
        }
        
        // Ir para o formulário
        window.location.href = 'formulario.php?id=' + tempId;
    }
    
    // Listar recognições offline na página inicial
    function listarRecognicoesOffline() {
        const recognicoesOffline = JSON.parse(localStorage.getItem('recognicoes_offline') || '[]');
        
        if (recognicoesOffline.length > 0 && !isOnline()) {
            const container = document.querySelector('.container');
            let html = '<div class="alert alert-warning mt-3"><strong>Recognições Offline:</strong></div>';
            
            recognicoesOffline.forEach(id => {
                const dados = recuperarOffline('recognicao_' + id);
                if (dados) {
                    html += `
                        <div class="card mb-2" onclick="window.location='formulario.php?id=${id}'">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-8">
                                        <h6 class="card-title mb-1">
                                            <span class="badge bg-warning">Offline</span>
                                            RAI: ${dados.rai || 'Não informado'}
                                        </h6>
                                        <p class="card-text mb-0">
                                            <small>ID Temporário: ${id}</small>
                                        </p>
                                    </div>
                                    <div class="col-4 text-end">
                                        <span class="badge bg-secondary">Não sincronizado</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                }
            });
            
            // Inserir após a lista de recognições online
            const cardContainer = container.querySelector('h4').nextElementSibling;
            if (cardContainer) {
                cardContainer.insertAdjacentHTML('afterend', html);
            }
        }
    }
    
    // Executar ao carregar a página
    window.addEventListener('load', function() {
        listarRecognicoesOffline();
    });
</script>

</body>
</html>