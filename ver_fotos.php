<?php
// ver_fotos.php - Visualizar fotos de uma recognição
require_once 'config.php';
$pdo = conectarDB();

$recognicao_id = $_GET['id'] ?? 0;

// Buscar fotos
$stmt = $pdo->prepare("SELECT * FROM fotos WHERE recognicao_id = ? ORDER BY id");
$stmt->execute([$recognicao_id]);
$fotos = $stmt->fetchAll();

// Buscar dados da recognição
$stmt = $pdo->prepare("SELECT r.*, n.descricao as natureza FROM recognicoes r LEFT JOIN naturezas n ON r.natureza_id = n.id WHERE r.id = ?");
$stmt->execute([$recognicao_id]);
$recognicao = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fotos - Recognição <?= $recognicao_id ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .foto-container {
            margin-bottom: 20px;
        }
        .foto-container img {
            width: 100%;
            height: auto;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            cursor: pointer;
        }
        .foto-container img:hover {
            transform: scale(1.02);
            transition: transform 0.2s;
        }
        /* Modal para visualização em tela cheia */
        .modal-img {
            max-width: 100%;
            max-height: 90vh;
            display: block;
            margin: 0 auto;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-dark bg-dark">
        <div class="container-fluid">
            <a href="formulario.php?id=<?= $recognicao_id ?>" class="navbar-brand">← Voltar para Formulário</a>
        </div>
    </nav>

    <div class="container mt-4">
        <h2>Fotos da Recognição #<?= $recognicao_id ?></h2>
        
        <?php if ($recognicao): ?>
        <div class="card mb-4">
            <div class="card-body">
                <p><strong>RAI:</strong> <?= htmlspecialchars($recognicao['rai'] ?: 'Não informado') ?></p>
                <p><strong>Natureza:</strong> <?= htmlspecialchars($recognicao['natureza'] ?: 'Não informado') ?></p>
                <p><strong>Data do Fato:</strong> <?= $recognicao['data_hora_fato'] ? date('d/m/Y H:i', strtotime($recognicao['data_hora_fato'])) : 'Não informado' ?></p>
            </div>
        </div>
        <?php endif; ?>

        <?php if (empty($fotos)): ?>
            <div class="alert alert-info">
                Nenhuma foto encontrada para esta recognição.
            </div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($fotos as $foto): ?>
                    <div class="col-md-4 col-sm-6 foto-container">
                        <div class="card">
                            <?php if (file_exists($foto['arquivo'])): ?>
                                <img src="<?= htmlspecialchars($foto['arquivo']) ?>" 
                                     alt="<?= htmlspecialchars($foto['descricao']) ?>"
                                     class="card-img-top"
                                     onclick="abrirModal('<?= htmlspecialchars($foto['arquivo']) ?>')">
                                <div class="card-body">
                                    <small class="text-muted"><?= htmlspecialchars($foto['descricao']) ?></small>
                                    <button class="btn btn-danger btn-sm float-end" 
                                            onclick="excluirFoto(<?= $foto['id'] ?>, '<?= htmlspecialchars($foto['arquivo']) ?>')">
                                        🗑️ Excluir
                                    </button>
                                </div>
                            <?php else: ?>
                                <div class="card-body">
                                    <p class="text-danger">Arquivo não encontrado: <?= htmlspecialchars($foto['arquivo']) ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Modal para visualização em tela cheia -->
    <div class="modal fade" id="modalFoto" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Visualizar Foto</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <img id="modalImg" src="" class="modal-img">
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function abrirModal(src) {
        document.getElementById('modalImg').src = src;
        new bootstrap.Modal(document.getElementById('modalFoto')).show();
    }
    
    function excluirFoto(fotoId, arquivo) {
        if (confirm('Tem certeza que deseja excluir esta foto?')) {
            // Enviar requisição para excluir
            const formData = new FormData();
            formData.append('foto_id', fotoId);
            
            fetch('api.php?action=excluir_foto', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Foto excluída com sucesso!');
                    location.reload(); // Recarregar página
                } else {
                    alert('Erro ao excluir foto: ' + data.message);
                }
            })
            .catch(error => {
                alert('Erro ao excluir foto: ' + error);
            });
        }
    }
    </script>
</body>
</html>