<?php
require_once 'config.php';

$pdo = conectarDB();

$id = $_GET['id'] ?? 0;
if (!$id) {
    die("ID não informado");
}

// 🔎 Buscar dados principais
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

// 🔎 Buscar dados relacionados
function fetchAll($sql, $param) {
    global $pdo;
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$param]);
    return $stmt->fetchAll();
}

$policiais_preservacao = fetchAll("SELECT * FROM policiais_preservacao WHERE recognicao_id = ?", $id);

$vitimas = fetchAll("
    SELECT v.*, pv.descricao as posicao_descricao 
    FROM vitimas v 
    LEFT JOIN posicao_vitima pv ON v.posicao_vitima_id = pv.id 
    WHERE v.recognicao_id = ?", $id);

$autores = fetchAll("SELECT * FROM autores WHERE recognicao_id = ?", $id);

$testemunhas = fetchAll("SELECT * FROM testemunhas WHERE recognicao_id = ?", $id);

$meios_empregados = fetchAll("
    SELECT me.tipo, 
           CASE 
               WHEN me.tipo = 'veiculo' THEN v.descricao
               WHEN me.tipo = 'arma' THEN a.descricao
           END as descricao
    FROM meios_empregados me
    LEFT JOIN veiculos v ON me.tipo = 'veiculo' AND me.item_id = v.id
    LEFT JOIN armas a ON me.tipo = 'arma' AND me.item_id = a.id
    WHERE me.recognicao_id = ?", $id);

$equipe_responsavel = fetchAll("
    SELECT pg.nome, pg.matricula 
    FROM equipe_responsavel er
    JOIN policiais_gih pg ON er.policial_id = pg.id
    WHERE er.recognicao_id = ?", $id);

$fotos = fetchAll("SELECT * FROM fotos WHERE recognicao_id = ? ORDER BY id", $id);

// 🕓 Funções auxiliares
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
?>
