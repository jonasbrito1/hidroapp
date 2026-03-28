<?php
/**
 * Traduções e Localizações PT-BR
 * HidroApp - Sistema de Gestão de Manutenção
 */

// ============================================
// CONFIGURAÇÃO DE LOCALE PT-BR
// ============================================

// Definir timezone para Brasil
date_default_timezone_set('America/Sao_Paulo');

// Definir locale para PT-BR
setlocale(LC_ALL, 'pt_BR.UTF-8', 'pt_BR', 'portuguese', 'ptb');
setlocale(LC_TIME, 'pt_BR.UTF-8', 'pt_BR', 'portuguese');
setlocale(LC_NUMERIC, 'pt_BR.UTF-8', 'pt_BR', 'portuguese');

// ============================================
// TRADUÇÕES DE STATUS
// ============================================

$status_traducao = [
    // Status de Equipamentos
    'ativo' => 'Ativo',
    'inativo' => 'Inativo',
    'manutencao' => 'Em Manutenção',

    // Status de Manutenções
    'agendada' => 'Agendada',
    'em_andamento' => 'Em Andamento',
    'concluida' => 'Concluída',
    'cancelada' => 'Cancelada',

    // Status de Usuários
    'active' => 'Ativo',
    'inactive' => 'Inativo',
    'suspended' => 'Suspenso',
];

// ============================================
// TRADUÇÕES DE TIPOS
// ============================================

$tipo_traducao = [
    // Tipos de Equipamento
    'bebedouro' => 'Bebedouro',
    'ducha' => 'Ducha',
    'ambos' => 'Ambos',

    // Tipos de Manutenção
    'preventiva' => 'Preventiva',
    'corretiva' => 'Corretiva',

    // Tipos de Usuário
    'admin' => 'Administrador',
    'tecnico' => 'Técnico',
    'usuario' => 'Usuário',

    // Categorias de Manutenção
    'limpeza' => 'Limpeza',
    'manutencao' => 'Manutenção',
    'instalacao' => 'Instalação',
    'inspecao' => 'Inspeção',
    'reparo' => 'Reparo',
    'outro' => 'Outro',

    // Categorias de Materiais
    'filtro' => 'Filtro',
    'peca' => 'Peça',
    'consumivel' => 'Consumível',
    'ferramenta' => 'Ferramenta',
    'quimico' => 'Químico',

    // Prioridades
    'baixa' => 'Baixa',
    'media' => 'Média',
    'alta' => 'Alta',
    'urgente' => 'Urgente',

    // Tipos de Foto
    'antes' => 'Antes',
    'durante' => 'Durante',
    'depois' => 'Depois',
    'problema' => 'Problema',
    'solucao' => 'Solução',
    'geral' => 'Geral',
    'detalhes' => 'Detalhes',
    'localizacao' => 'Localização',
];

// ============================================
// MENSAGENS DO SISTEMA
// ============================================

$mensagens_sistema = [
    // Sucesso
    'success_create' => 'Registro criado com sucesso!',
    'success_update' => 'Registro atualizado com sucesso!',
    'success_delete' => 'Registro excluído com sucesso!',
    'success_login' => 'Login realizado com sucesso!',
    'success_logout' => 'Logout realizado com sucesso!',

    // Erros
    'error_create' => 'Erro ao criar registro.',
    'error_update' => 'Erro ao atualizar registro.',
    'error_delete' => 'Erro ao excluir registro.',
    'error_login' => 'Usuário ou senha inválidos.',
    'error_permission' => 'Você não tem permissão para realizar esta ação.',
    'error_not_found' => 'Registro não encontrado.',
    'error_database' => 'Erro ao acessar o banco de dados.',

    // Validações
    'validation_required' => 'Este campo é obrigatório.',
    'validation_email' => 'Email inválido.',
    'validation_min_length' => 'Mínimo de caracteres não atingido.',
    'validation_max_length' => 'Máximo de caracteres excedido.',
    'validation_unique' => 'Este valor já está cadastrado.',

    // Confirmações
    'confirm_delete' => 'Tem certeza que deseja excluir este registro?',
    'confirm_cancel' => 'Tem certeza que deseja cancelar?',
];

// ============================================
// LABELS E CAMPOS
// ============================================

$labels_campos = [
    // Campos Gerais
    'id' => 'ID',
    'nome' => 'Nome',
    'email' => 'E-mail',
    'senha' => 'Senha',
    'telefone' => 'Telefone',
    'cpf' => 'CPF',
    'endereco' => 'Endereço',
    'observacoes' => 'Observações',
    'created_at' => 'Criado em',
    'updated_at' => 'Atualizado em',
    'deleted_at' => 'Excluído em',

    // Equipamentos
    'codigo' => 'Código',
    'tipo' => 'Tipo',
    'localizacao' => 'Localização',
    'latitude' => 'Latitude',
    'longitude' => 'Longitude',
    'marca' => 'Marca',
    'modelo' => 'Modelo',
    'data_instalacao' => 'Data de Instalação',
    'status' => 'Status',
    'google_maps_url' => 'Link Google Maps',

    // Manutenções
    'equipamento_id' => 'Equipamento',
    'tecnico_id' => 'Técnico',
    'tipo_manutencao_id' => 'Tipo de Manutenção',
    'data_agendada' => 'Data Agendada',
    'data_realizada' => 'Data Realizada',
    'data_inicio' => 'Data de Início',
    'problema_relatado' => 'Problema Relatado',
    'solucao_aplicada' => 'Solução Aplicada',
    'custo_total' => 'Custo Total',
    'tempo_execucao' => 'Tempo de Execução',
    'prioridade' => 'Prioridade',
    'descricao' => 'Descrição',

    // Materiais
    'unidade' => 'Unidade',
    'preco_unitario' => 'Preço Unitário',
    'estoque_minimo' => 'Estoque Mínimo',
    'categoria' => 'Categoria',
    'unidade_medida' => 'Unidade de Medida',
    'quantidade' => 'Quantidade',

    // Usuários
    'tipo_usuario' => 'Tipo de Usuário',
    'ativo' => 'Ativo',
    'last_login' => 'Último Login',
    'last_logout' => 'Último Logout',
    'created_by' => 'Criado por',

    // Ações
    'adicionar' => 'Adicionar',
    'editar' => 'Editar',
    'excluir' => 'Excluir',
    'salvar' => 'Salvar',
    'cancelar' => 'Cancelar',
    'buscar' => 'Buscar',
    'filtrar' => 'Filtrar',
    'limpar' => 'Limpar',
    'visualizar' => 'Visualizar',
    'voltar' => 'Voltar',
    'exportar' => 'Exportar',
    'imprimir' => 'Imprimir',
];

// ============================================
// FUNÇÕES DE TRADUÇÃO
// ============================================

/**
 * Traduz status para PT-BR
 */
function traduzirStatus($status) {
    global $status_traducao;
    return $status_traducao[strtolower($status)] ?? ucfirst($status);
}

/**
 * Traduz tipo para PT-BR
 */
function traduzirTipo($tipo) {
    global $tipo_traducao;
    return $tipo_traducao[strtolower($tipo)] ?? ucfirst($tipo);
}

/**
 * Obtém label de campo
 */
function getLabel($campo) {
    global $labels_campos;
    return $labels_campos[strtolower($campo)] ?? ucfirst(str_replace('_', ' ', $campo));
}

/**
 * Obtém mensagem do sistema
 */
function getMensagem($chave, $params = []) {
    global $mensagens_sistema;
    $mensagem = $mensagens_sistema[$chave] ?? $chave;

    // Substituir parâmetros se fornecidos
    foreach ($params as $key => $value) {
        $mensagem = str_replace("{{$key}}", $value, $mensagem);
    }

    return $mensagem;
}

/**
 * Formata data para padrão brasileiro
 */
function formatarData($data, $incluir_hora = false) {
    if (empty($data) || $data == '0000-00-00' || $data == '0000-00-00 00:00:00') {
        return '-';
    }

    try {
        $timestamp = is_numeric($data) ? $data : strtotime($data);

        if ($incluir_hora) {
            return date('d/m/Y H:i', $timestamp);
        } else {
            return date('d/m/Y', $timestamp);
        }
    } catch (Exception $e) {
        return $data;
    }
}

/**
 * Formata data MySQL para input date HTML
 */
function formatarDataInput($data) {
    if (empty($data) || $data == '0000-00-00') {
        return '';
    }

    try {
        $timestamp = strtotime($data);
        return date('Y-m-d', $timestamp);
    } catch (Exception $e) {
        return '';
    }
}

/**
 * Formata valor monetário para PT-BR
 */
function formatarMoeda($valor) {
    return 'R$ ' . number_format((float)$valor, 2, ',', '.');
}

/**
 * Formata número para PT-BR
 */
function formatarNumero($numero, $decimais = 0) {
    return number_format((float)$numero, $decimais, ',', '.');
}

/**
 * Converte data BR para MySQL
 */
function dataParaMySQL($data_br) {
    if (empty($data_br)) {
        return null;
    }

    // Se já está no formato MySQL
    if (preg_match('/^\d{4}-\d{2}-\d{2}/', $data_br)) {
        return $data_br;
    }

    // Formato DD/MM/YYYY
    if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})/', $data_br, $matches)) {
        return "{$matches[3]}-{$matches[2]}-{$matches[1]}";
    }

    return null;
}

/**
 * Obtém badge HTML para status
 */
function getBadgeStatus($status) {
    $classes = [
        'ativo' => 'success',
        'inativo' => 'secondary',
        'manutencao' => 'warning',
        'agendada' => 'info',
        'em_andamento' => 'primary',
        'concluida' => 'success',
        'cancelada' => 'danger',
    ];

    $class = $classes[strtolower($status)] ?? 'secondary';
    $texto = traduzirStatus($status);

    return "<span class='badge bg-{$class}'>{$texto}</span>";
}

/**
 * Obtém badge HTML para prioridade
 */
function getBadgePrioridade($prioridade) {
    $classes = [
        'baixa' => 'secondary',
        'media' => 'info',
        'alta' => 'warning',
        'urgente' => 'danger',
    ];

    $class = $classes[strtolower($prioridade)] ?? 'secondary';
    $texto = traduzirTipo($prioridade);

    return "<span class='badge bg-{$class}'>{$texto}</span>";
}

/**
 * Obtém badge HTML para tipo de usuário
 */
function getBadgeUsuario($tipo) {
    $classes = [
        'admin' => 'danger',
        'tecnico' => 'primary',
        'usuario' => 'secondary',
    ];

    $class = $classes[strtolower($tipo)] ?? 'secondary';
    $texto = traduzirTipo($tipo);

    return "<span class='badge bg-{$class}'>{$texto}</span>";
}

/**
 * Formata tempo em minutos para horas/minutos
 */
function formatarTempo($minutos) {
    if (empty($minutos) || $minutos == 0) {
        return '-';
    }

    $horas = floor($minutos / 60);
    $mins = $minutos % 60;

    if ($horas > 0 && $mins > 0) {
        return "{$horas}h {$mins}min";
    } elseif ($horas > 0) {
        return "{$horas}h";
    } else {
        return "{$mins}min";
    }
}

/**
 * Formata periodicidade em dias
 */
function formatarPeriodicidade($dias) {
    if (empty($dias) || $dias == 0) {
        return 'Sob demanda';
    }

    if ($dias < 30) {
        return "{$dias} dias";
    } elseif ($dias < 365) {
        $meses = round($dias / 30);
        return $meses == 1 ? "1 mês" : "{$meses} meses";
    } else {
        $anos = round($dias / 365);
        return $anos == 1 ? "1 ano" : "{$anos} anos";
    }
}

/**
 * Traduz dias da semana
 */
function traduzirDiaSemana($dia) {
    $dias = [
        'Monday' => 'Segunda-feira',
        'Tuesday' => 'Terça-feira',
        'Wednesday' => 'Quarta-feira',
        'Thursday' => 'Quinta-feira',
        'Friday' => 'Sexta-feira',
        'Saturday' => 'Sábado',
        'Sunday' => 'Domingo',
    ];

    return $dias[$dia] ?? $dia;
}

/**
 * Traduz meses
 */
function traduzirMes($mes) {
    $meses = [
        'January' => 'Janeiro',
        'February' => 'Fevereiro',
        'March' => 'Março',
        'April' => 'Abril',
        'May' => 'Maio',
        'June' => 'Junho',
        'July' => 'Julho',
        'August' => 'Agosto',
        'September' => 'Setembro',
        'October' => 'Outubro',
        'November' => 'Novembro',
        'December' => 'Dezembro',
    ];

    return $meses[$mes] ?? $mes;
}

/**
 * Formata telefone brasileiro
 */
function formatarTelefone($telefone) {
    if (empty($telefone)) {
        return '-';
    }

    $telefone = preg_replace('/[^0-9]/', '', $telefone);

    if (strlen($telefone) == 11) {
        return '(' . substr($telefone, 0, 2) . ') ' .
               substr($telefone, 2, 5) . '-' .
               substr($telefone, 7);
    } elseif (strlen($telefone) == 10) {
        return '(' . substr($telefone, 0, 2) . ') ' .
               substr($telefone, 2, 4) . '-' .
               substr($telefone, 6);
    }

    return $telefone;
}

/**
 * Formata CPF brasileiro
 */
function formatarCPF($cpf) {
    if (empty($cpf)) {
        return '-';
    }

    $cpf = preg_replace('/[^0-9]/', '', $cpf);

    if (strlen($cpf) == 11) {
        return substr($cpf, 0, 3) . '.' .
               substr($cpf, 3, 3) . '.' .
               substr($cpf, 6, 3) . '-' .
               substr($cpf, 9);
    }

    return $cpf;
}

/**
 * Retorna nome do mês por número
 */
function getNomeMes($numero) {
    $meses = [
        1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março',
        4 => 'Abril', 5 => 'Maio', 6 => 'Junho',
        7 => 'Julho', 8 => 'Agosto', 9 => 'Setembro',
        10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro'
    ];

    return $meses[(int)$numero] ?? '';
}

// ============================================
// CONSTANTES DE FORMATAÇÃO
// ============================================

define('DATA_FORMAT_BR', 'd/m/Y');
define('DATA_HORA_FORMAT_BR', 'd/m/Y H:i');
define('DATA_HORA_SEG_FORMAT_BR', 'd/m/Y H:i:s');
define('HORA_FORMAT_BR', 'H:i');
define('DATA_FORMAT_MYSQL', 'Y-m-d');
define('DATA_HORA_FORMAT_MYSQL', 'Y-m-d H:i:s');

?>
