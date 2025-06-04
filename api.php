<?php
// api.php - API para operações AJAX
error_reporting(0);
ini_set('display_errors', 0);

require_once 'config.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $pdo = conectarDB();
    $action = $_GET['action'] ?? '';

    switch ($action) {
        case 'nova_recognicao':
            // Criar nova recognição
            $stmt = $pdo->prepare("INSERT INTO recognicoes (created_at) VALUES (NOW())");
            $stmt->execute();
            $id = $pdo->lastInsertId();
            
            echo json_encode(['success' => true, 'id' => $id]);
            break;
            

        case 'excluir_recognicao':
            // Receber dados JSON
            $dados = json_decode(file_get_contents('php://input'), true);
            $id = $dados['id'] ?? 0;
            
            if (!$id) {
                echo json_encode(['success' => false, 'message' => 'ID não informado']);
                break;
            }
            
            try {
                // Iniciar transação
                $pdo->beginTransaction();
                
                // Buscar e excluir fotos físicas
                $stmt = $pdo->prepare("SELECT arquivo FROM fotos WHERE recognicao_id = ?");
                $stmt->execute([$id]);
                $fotos = $stmt->fetchAll();
                
                foreach ($fotos as $foto) {
                    if (file_exists($foto['arquivo'])) {
                        unlink($foto['arquivo']);
                    }
                }
                
                // Excluir registros relacionados (ordem importante por causa das foreign keys)
                $tabelas = [
                    'fotos',
                    'equipe_responsavel',
                    'meios_empregados',
                    'testemunhas',
                    'autores',
                    'vitimas',
                    'policiais_preservacao'
                ];
                
                foreach ($tabelas as $tabela) {
                    $stmt = $pdo->prepare("DELETE FROM {$tabela} WHERE recognicao_id = ?");
                    $stmt->execute([$id]);
                }
                
                // Por fim, excluir a recognição principal
                $stmt = $pdo->prepare("DELETE FROM recognicoes WHERE id = ?");
                $stmt->execute([$id]);
                
                // Confirmar transação
                $pdo->commit();
                
                echo json_encode(['success' => true, 'message' => 'Recognição excluída com sucesso']);
                
            } catch (Exception $e) {
                // Reverter transação em caso de erro
                $pdo->rollBack();
                echo json_encode(['success' => false, 'message' => 'Erro ao excluir: ' . $e->getMessage()]);
            }
            break;            
            
        case 'salvar_rascunho':
            // Receber dados JSON
            $dados = json_decode(file_get_contents('php://input'), true);
            
            if (!$dados) {
                throw new Exception('Dados inválidos recebidos');
            }
            
            $recognicaoId = $dados['recognicao_id'];
            
            // Verificar se é um ID temporário
            if (strpos($recognicaoId, 'temp_') === 0) {
                // Criar nova recognição
                $stmt = $pdo->prepare("INSERT INTO recognicoes (created_at) VALUES (NOW())");
                $stmt->execute();
                $recognicaoId = $pdo->lastInsertId();
            }
            
            // Extrair coordenadas se houver
            $latitude = null;
            $longitude = null;
            if (!empty($dados['geolocalizacao'])) {
                preg_match('/Lat: ([\-0-9.]+), Long: ([\-0-9.]+)/', $dados['geolocalizacao'], $matches);
                if (count($matches) === 3) {
                    $latitude = $matches[1];
                    $longitude = $matches[2];
                }
            }
            
            // Atualizar recognição principal
            $sql = "UPDATE recognicoes SET 
                    rai = :rai,
                    data_hora_acionamento = :data_hora_acionamento,
                    data_hora_fato = :data_hora_fato,
                    dia_semana = :dia_semana,
                    natureza_id = :natureza_id,
                    endereco_fato = :endereco_fato,
                    latitude = :latitude,
                    longitude = :longitude,
                    preservacao_local_id = :preservacao_local_id,
                    perito_id = :perito_id,
                    tipo_local = :tipo_local,
                    local_externo_id = :local_externo_id,
                    local_interno_id = :local_interno_id,
                    tipo_piso_id = :tipo_piso_id,
                    disposicao_objetos_id = :disposicao_objetos_id,
                    condicoes_higiene_id = :condicoes_higiene_id,
                    cameras_monitoramento = :cameras_monitoramento,
                    objetos_recolhidos = :objetos_recolhidos,
                    historico = :historico,
                    updated_at = NOW()
                    WHERE id = :id";
                    
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':id' => $recognicaoId,
                ':rai' => isset($dados['rai']) ? $dados['rai'] : null,
                ':data_hora_acionamento' => isset($dados['data_hora_acionamento']) && $dados['data_hora_acionamento'] ? $dados['data_hora_acionamento'] : null,
                ':data_hora_fato' => isset($dados['data_hora_fato']) && $dados['data_hora_fato'] ? $dados['data_hora_fato'] : null,
                ':dia_semana' => isset($dados['dia_semana']) ? $dados['dia_semana'] : null,
                ':natureza_id' => isset($dados['natureza_id']) && $dados['natureza_id'] ? $dados['natureza_id'] : null,
                ':endereco_fato' => isset($dados['endereco_fato']) ? $dados['endereco_fato'] : null,
                ':latitude' => $latitude,
                ':longitude' => $longitude,
                ':preservacao_local_id' => isset($dados['preservacao_local_id']) && $dados['preservacao_local_id'] ? $dados['preservacao_local_id'] : null,
                ':perito_id' => isset($dados['perito_id']) ? processar_perito($dados['perito_id'], $pdo) : null,
                ':tipo_local' => isset($dados['tipo_local']) && $dados['tipo_local'] ? $dados['tipo_local'] : null,
                ':local_externo_id' => isset($dados['local_externo_id']) && $dados['local_externo_id'] ? $dados['local_externo_id'] : null,
                ':local_interno_id' => isset($dados['local_interno_id']) && $dados['local_interno_id'] ? $dados['local_interno_id'] : null,
                ':tipo_piso_id' => isset($dados['tipo_piso_id']) && $dados['tipo_piso_id'] ? $dados['tipo_piso_id'] : null,
                ':disposicao_objetos_id' => isset($dados['disposicao_objetos_id']) && $dados['disposicao_objetos_id'] ? $dados['disposicao_objetos_id'] : null,
                ':condicoes_higiene_id' => isset($dados['condicoes_higiene_id']) && $dados['condicoes_higiene_id'] ? $dados['condicoes_higiene_id'] : null,
                ':cameras_monitoramento' => isset($dados['cameras_monitoramento']) && $dados['cameras_monitoramento'] ? $dados['cameras_monitoramento'] : null,
                ':objetos_recolhidos' => isset($dados['objetos_recolhidos']) ? $dados['objetos_recolhidos'] : null,
                ':historico' => isset($dados['historico']) ? $dados['historico'] : null
            ]);
            
            // Salvar policiais de preservação
            if (!empty($dados['policiais_preservacao'])) {
                // Limpar policiais existentes
                $stmt = $pdo->prepare("DELETE FROM policiais_preservacao WHERE recognicao_id = ?");
                $stmt->execute([$recognicaoId]);
                
                // Inserir novos
                $stmt = $pdo->prepare("INSERT INTO policiais_preservacao (recognicao_id, nome, matricula) VALUES (?, ?, ?)");
                foreach ($dados['policiais_preservacao'] as $policial) {
                    if (!empty($policial['nome']) || !empty($policial['matricula'])) {
                        $stmt->execute([$recognicaoId, $policial['nome'], $policial['matricula']]);
                    }
                }
            }
            
            // Salvar vítimas
            if (!empty($dados['vitimas'])) {
                $stmt = $pdo->prepare("DELETE FROM vitimas WHERE recognicao_id = ?");
                $stmt->execute([$recognicaoId]);
                
                $stmt = $pdo->prepare("INSERT INTO vitimas (recognicao_id, nome, data_nascimento, cor, nacionalidade, naturalidade, profissao, cpf, rg, pai, mae, endereco, situacao, posicao_vitima_id, hospital_socorrido, lesoes_apresentadas) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                
                foreach ($dados['vitimas'] as $vitima) {
                    $stmt->execute([
                        $recognicaoId,
                        isset($vitima['nome']) ? $vitima['nome'] : null,
                        isset($vitima['data_nascimento']) && $vitima['data_nascimento'] ? $vitima['data_nascimento'] : null,
                        isset($vitima['cor']) ? $vitima['cor'] : null,
                        isset($vitima['nacionalidade']) ? $vitima['nacionalidade'] : null,
                        isset($vitima['naturalidade']) ? $vitima['naturalidade'] : null,
                        isset($vitima['profissao']) ? $vitima['profissao'] : null,
                        isset($vitima['cpf']) ? $vitima['cpf'] : null,
                        isset($vitima['rg']) ? $vitima['rg'] : null,
                        isset($vitima['pai']) ? $vitima['pai'] : null,
                        isset($vitima['mae']) ? $vitima['mae'] : null,
                        isset($vitima['endereco']) ? $vitima['endereco'] : null,
                        isset($vitima['situacao']) ? $vitima['situacao'] : null,
                        isset($vitima['posicao_vitima_id']) && $vitima['posicao_vitima_id'] ? $vitima['posicao_vitima_id'] : null,
                        isset($vitima['hospital_socorrido']) ? $vitima['hospital_socorrido'] : null,
                        isset($vitima['lesoes_apresentadas']) ? $vitima['lesoes_apresentadas'] : null
                    ]);
                }
            }
            
            // Salvar autores
            if (!empty($dados['autores'])) {
                $stmt = $pdo->prepare("DELETE FROM autores WHERE recognicao_id = ?");
                $stmt->execute([$recognicaoId]);
                
                $stmt = $pdo->prepare("INSERT INTO autores (recognicao_id, nome, data_nascimento, cor, nacionalidade, naturalidade, profissao, cpf, rg, pai, mae, endereco, caracteristicas) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                
                foreach ($dados['autores'] as $autor) {
                    $stmt->execute([
                        $recognicaoId,
                        isset($autor['nome']) ? $autor['nome'] : null,
                        isset($autor['data_nascimento']) && $autor['data_nascimento'] ? $autor['data_nascimento'] : null,
                        isset($autor['cor']) ? $autor['cor'] : null,
                        isset($autor['nacionalidade']) ? $autor['nacionalidade'] : null,
                        isset($autor['naturalidade']) ? $autor['naturalidade'] : null,
                        isset($autor['profissao']) ? $autor['profissao'] : null,
                        isset($autor['cpf']) ? $autor['cpf'] : null,
                        isset($autor['rg']) ? $autor['rg'] : null,
                        isset($autor['pai']) ? $autor['pai'] : null,
                        isset($autor['mae']) ? $autor['mae'] : null,
                        isset($autor['endereco']) ? $autor['endereco'] : null,
                        isset($autor['caracteristicas']) ? $autor['caracteristicas'] : null
                    ]);
                }
            }
            
            // Salvar testemunhas
            if (!empty($dados['testemunhas'])) {
                $stmt = $pdo->prepare("DELETE FROM testemunhas WHERE recognicao_id = ?");
                $stmt->execute([$recognicaoId]);
                
                $stmt = $pdo->prepare("INSERT INTO testemunhas (recognicao_id, nome, data_nascimento, nacionalidade, naturalidade, profissao, cpf, rg, pai, mae, endereco, telefone) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                
                foreach ($dados['testemunhas'] as $testemunha) {
                    $stmt->execute([
                        $recognicaoId,
                        isset($testemunha['nome']) ? $testemunha['nome'] : null,
                        isset($testemunha['data_nascimento']) && $testemunha['data_nascimento'] ? $testemunha['data_nascimento'] : null,
                        isset($testemunha['nacionalidade']) ? $testemunha['nacionalidade'] : null,
                        isset($testemunha['naturalidade']) ? $testemunha['naturalidade'] : null,
                        isset($testemunha['profissao']) ? $testemunha['profissao'] : null,
                        isset($testemunha['cpf']) ? $testemunha['cpf'] : null,
                        isset($testemunha['rg']) ? $testemunha['rg'] : null,
                        isset($testemunha['pai']) ? $testemunha['pai'] : null,
                        isset($testemunha['mae']) ? $testemunha['mae'] : null,
                        isset($testemunha['endereco']) ? $testemunha['endereco'] : null,
                        isset($testemunha['telefone']) ? $testemunha['telefone'] : null
                    ]);
                }
            }
            
            // Salvar meios empregados
            $stmt = $pdo->prepare("DELETE FROM meios_empregados WHERE recognicao_id = ?");
            $stmt->execute([$recognicaoId]);
            
            if (!empty($dados['veiculos_empregados'])) {
                $stmt = $pdo->prepare("INSERT INTO meios_empregados (recognicao_id, tipo, item_id) VALUES (?, 'veiculo', ?)");
                foreach ($dados['veiculos_empregados'] as $veiculo_id) {
                    $stmt->execute([$recognicaoId, $veiculo_id]);
                }
            }
            
            if (!empty($dados['armas_empregadas'])) {
                $stmt = $pdo->prepare("INSERT INTO meios_empregados (recognicao_id, tipo, item_id) VALUES (?, 'arma', ?)");
                foreach ($dados['armas_empregadas'] as $arma_id) {
                    $stmt->execute([$recognicaoId, $arma_id]);
                }
            }
            
            // Salvar equipe responsável
            if (!empty($dados['policial1_id']) || !empty($dados['policial2_id'])) {
                $stmt = $pdo->prepare("DELETE FROM equipe_responsavel WHERE recognicao_id = ?");
                $stmt->execute([$recognicaoId]);
                
                $stmt = $pdo->prepare("INSERT INTO equipe_responsavel (recognicao_id, policial_id) VALUES (?, ?)");
                if (!empty($dados['policial1_id'])) {
                    $policial1_id = processar_policial_gih($dados['policial1_id'], $pdo);
                    if ($policial1_id) {
                        $stmt->execute([$recognicaoId, $policial1_id]);
                    }
                }
                if (!empty($dados['policial2_id'])) {
                    $policial2_id = processar_policial_gih($dados['policial2_id'], $pdo);
                    if ($policial2_id) {
                        $stmt->execute([$recognicaoId, $policial2_id]);
                    }
                }
            }
            
            // Salvar fotos (ADICIONAR, não substituir)
            $fotos_salvas = 0;
            if (!empty($dados['fotos'])) {
                error_log("Processando " . count($dados['fotos']) . " fotos");
                
                // Criar diretório se não existir
                $upload_dir = 'uploads/' . date('Y/m/');
                if (!file_exists($upload_dir)) {
                    if (!mkdir($upload_dir, 0777, true)) {
                        error_log("Erro ao criar diretório: " . $upload_dir);
                        throw new Exception("Erro ao criar diretório de upload");
                    }
                }
                
                // Verificar permissões
                if (!is_writable($upload_dir)) {
                    error_log("Diretório sem permissão de escrita: " . $upload_dir);
                    throw new Exception("Diretório sem permissão de escrita");
                }
                
                // NÃO DELETAR fotos antigas - apenas adicionar novas
                $stmt = $pdo->prepare("INSERT INTO fotos (recognicao_id, arquivo, descricao) VALUES (?, ?, ?)");
                
                foreach ($dados['fotos'] as $index => $foto) {
                    try {
                        // Debug
                        error_log("Processando foto " . ($index + 1));
                        
                        // Extrair dados base64
                        $data = $foto['dados'];
                        if (strpos($data, ',') === false) {
                            error_log("Formato base64 inválido para foto " . ($index + 1));
                            continue;
                        }
                        
                        list($type, $data) = explode(',', $data);
                        $data = base64_decode($data);
                        
                        if ($data === false) {
                            error_log("Erro ao decodificar base64 para foto " . ($index + 1));
                            continue;
                        }
                        
                        // Determinar extensão
                        $extensao = 'jpg'; // padrão
                        if (strpos($type, 'png') !== false) $extensao = 'png';
                        elseif (strpos($type, 'gif') !== false) $extensao = 'gif';
                        elseif (strpos($type, 'jpeg') !== false) $extensao = 'jpg';
                        
                        $nome_arquivo = 'recognicao_' . $recognicaoId . '_' . time() . '_' . ($index + 1) . '.' . $extensao;
                        $caminho_completo = $upload_dir . $nome_arquivo;
                        
                        // Salvar arquivo no servidor
                        $bytes_salvos = file_put_contents($caminho_completo, $data);
                        if ($bytes_salvos !== false) {
                            error_log("Foto salva: " . $caminho_completo . " (" . $bytes_salvos . " bytes)");
                            
                            // Salvar no banco
                            $descricao = 'Foto ' . ($index + 1) . ' - ' . date('d/m/Y H:i:s');
                            $stmt->execute([$recognicaoId, $caminho_completo, $descricao]);
                            $fotos_salvas++;
                        } else {
                            error_log("Erro ao salvar arquivo: " . $caminho_completo);
                        }
                    } catch (Exception $e) {
                        error_log("Erro ao processar foto " . ($index + 1) . ": " . $e->getMessage());
                    }
                }
                
                error_log("Total de fotos salvas: " . $fotos_salvas . " de " . count($dados['fotos']));
            }
            
            echo json_encode([
                'success' => true, 
                'id' => $recognicaoId,
                'fotos_salvas' => $fotos_salvas
            ]);
            break;
            
        case 'excluir_foto':
            // Receber ID da foto
            $foto_id = $_POST['foto_id'] ?? 0;
            
            if ($foto_id) {
                // Buscar arquivo para deletar
                $stmt = $pdo->prepare("SELECT arquivo FROM fotos WHERE id = ?");
                $stmt->execute([$foto_id]);
                $foto = $stmt->fetch();
                
                if ($foto && file_exists($foto['arquivo'])) {
                    // Deletar arquivo físico
                    unlink($foto['arquivo']);
                }
                
                // Deletar do banco
                $stmt = $pdo->prepare("DELETE FROM fotos WHERE id = ?");
                $stmt->execute([$foto_id]);
                
                echo json_encode(['success' => true, 'message' => 'Foto excluída com sucesso']);
            } else {
                echo json_encode(['success' => false, 'message' => 'ID da foto não informado']);
            }
            break;
            
        case 'listar_peritos':
            $stmt = $pdo->query("SELECT id, nome, matricula FROM peritos ORDER BY nome");
            $peritos = $stmt->fetchAll();
            echo json_encode(['success' => true, 'peritos' => $peritos]);
            break;
            
        case 'listar_policiais_gih':
            $stmt = $pdo->query("SELECT id, nome, matricula FROM policiais_gih ORDER BY nome");
            $policiais = $stmt->fetchAll();
            echo json_encode(['success' => true, 'policiais' => $policiais]);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Ação inválida']);
    }
} catch (Exception $e) {
    error_log("Erro na API: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

// Função auxiliar para processar perito
function processar_perito($perito_id, $pdo) {
    if (empty($perito_id)) {
        return null;
    }
    
    // Se for um novo perito
    if (strpos($perito_id, 'novo_') === 0) {
        $nome = str_replace('novo_', '', $perito_id);
        
        // Separar nome e matrícula se houver
        $partes = explode(' - ', $nome);
        $nome_perito = trim($partes[0]);
        $matricula = isset($partes[1]) ? trim($partes[1]) : '';
        
        // Verificar se já existe
        $stmt = $pdo->prepare("SELECT id FROM peritos WHERE nome = ? AND (matricula = ? OR (matricula IS NULL AND ? = ''))");
        $stmt->execute([$nome_perito, $matricula, $matricula]);
        $perito_existente = $stmt->fetch();
        
        if ($perito_existente) {
            return $perito_existente['id'];
        }
        
        // Se não existe, inserir novo perito
        $stmt = $pdo->prepare("INSERT INTO peritos (nome, matricula) VALUES (?, ?)");
        $stmt->execute([$nome_perito, $matricula ?: null]);
        
        return $pdo->lastInsertId();
    }
    
    return $perito_id;
}

// Função auxiliar para processar policial GIH
function processar_policial_gih($policial_id, $pdo) {
    if (empty($policial_id)) {
        return null;
    }
    
    // Se for um novo policial
    if (strpos($policial_id, 'novo_policial_') === 0) {
        $nome = str_replace('novo_policial_', '', $policial_id);
        
        // Separar nome e matrícula se houver
        $partes = explode(' - ', $nome);
        $nome_policial = trim($partes[0]);
        $matricula = isset($partes[1]) ? trim($partes[1]) : '';
        
        // Verificar se já existe
        $stmt = $pdo->prepare("SELECT id FROM policiais_gih WHERE nome = ? AND (matricula = ? OR (matricula IS NULL AND ? = ''))");
        $stmt->execute([$nome_policial, $matricula, $matricula]);
        $policial_existente = $stmt->fetch();
        
        if ($policial_existente) {
            return $policial_existente['id'];
        }
        
        // Se não existe, inserir novo policial
        $stmt = $pdo->prepare("INSERT INTO policiais_gih (nome, matricula) VALUES (?, ?)");
        $stmt->execute([$nome_policial, $matricula ?: null]);
        
        return $pdo->lastInsertId();
    }
    
    return $policial_id;
}