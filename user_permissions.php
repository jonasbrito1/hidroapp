<?php
/**
 * Sistema de Permissões do HidroApp
 * Gerencia permissões e controle de acesso por tipo de usuário
 */

class UserPermissions {
    
    // Definição das permissões por tipo de usuário
    private static $permissions = [
        'admin' => [
            'dashboard' => ['view', 'full_stats'],
            'equipamentos' => ['view', 'create', 'edit', 'delete', 'manage'],
            'usuarios' => ['view', 'create', 'edit', 'delete', 'manage', 'reset_password', 'change_type', 'activate', 'deactivate'],
            'cadastro_usuarios' => ['create'],
            'manutencoes' => ['view', 'create', 'edit', 'delete', 'manage'],
            'relatorios' => ['view', 'generate', 'export'],
            'configuracoes' => ['view', 'edit', 'edit_profile', 'system_settings'],
            'logs' => ['view', 'delete'],
            'backup' => ['create', 'restore', 'download']
        ],
        'tecnico' => [
            'dashboard' => ['view', 'full_stats'],
            'equipamentos' => ['view', 'create', 'edit', 'delete', 'manage'],
            // Técnico NÃO tem acesso a gestão de usuários
            'manutencoes' => ['view', 'create', 'edit', 'delete', 'manage'],
            'relatorios' => ['view', 'generate', 'export'],
            'configuracoes' => ['view', 'edit', 'edit_profile', 'system_settings'],
            'logs' => ['view', 'delete'],
            'backup' => ['create', 'restore', 'download']
        ],
        'usuario' => [
            'dashboard' => ['view'],
            'equipamentos' => ['view'],
            'manutencoes' => ['view'],
            'relatorios' => ['view'],
            'configuracoes' => ['view', 'edit_profile']
        ]
    ];
    
    // Páginas que cada tipo de usuário pode acessar
    private static $allowedPages = [
        'admin' => [
            'index.php',
            'equipamentos.php',
            'usuarios.php',
            'manutencoes.php',
            'relatorios.php',
            'configuracoes.php',
            'logs.php',
            'backup.php',
            'profile.php',
            'register.php',
            'user_api.php'
        ],
        'tecnico' => [
            'index.php',
            'equipamentos.php',
            'manutencoes.php',
            'relatorios.php',
            'configuracoes.php',
            'logs.php',
            'backup.php',
            'profile.php'
            // Técnico NÃO tem acesso a: usuarios.php, register.php, user_api.php
        ],
        'usuario' => [
            'index.php',
            'equipamentos.php',
            'manutencoes.php',
            'relatorios.php',
            'configuracoes.php',
            'profile.php'
        ]
    ];
    
    // Menu lateral por tipo de usuário
    private static $sidebarMenus = [
        'admin' => [
            [
                'title' => 'Dashboard',
                'icon' => 'bi-speedometer2',
                'url' => 'index.php',
                'active_pages' => ['index.php']
            ],
            [
                'title' => 'Equipamentos',
                'icon' => 'bi-hdd-stack',
                'url' => 'equipamentos.php',
                'active_pages' => ['equipamentos.php']
            ],
            [
                'title' => 'Manutenções',
                'icon' => 'bi-tools',
                'url' => 'manutencoes.php',
                'active_pages' => ['manutencoes.php']
            ],
            [
                'title' => 'Usuários',
                'icon' => 'bi-people',
                'url' => 'usuarios.php',
                'active_pages' => ['usuarios.php', 'register.php']
            ],
            [
                'title' => 'Relatórios',
                'icon' => 'bi-file-earmark-text',
                'url' => 'relatorios.php',
                'active_pages' => ['relatorios.php']
            ],
            [
                'title' => 'Configurações',
                'icon' => 'bi-gear',
                'url' => 'configuracoes.php',
                'active_pages' => ['configuracoes.php']
            ],
            [
                'title' => 'Logs do Sistema',
                'icon' => 'bi-journal-text',
                'url' => 'logs.php',
                'active_pages' => ['logs.php']
            ]
        ],
        'tecnico' => [
            [
                'title' => 'Dashboard',
                'icon' => 'bi-speedometer2',
                'url' => 'index.php',
                'active_pages' => ['index.php']
            ],
            [
                'title' => 'Equipamentos',
                'icon' => 'bi-hdd-stack',
                'url' => 'equipamentos.php',
                'active_pages' => ['equipamentos.php']
            ],
            [
                'title' => 'Manutenções',
                'icon' => 'bi-tools',
                'url' => 'manutencoes.php',
                'active_pages' => ['manutencoes.php']
            ],
            [
                'title' => 'Relatórios',
                'icon' => 'bi-file-earmark-text',
                'url' => 'relatorios.php',
                'active_pages' => ['relatorios.php']
            ],
            [
                'title' => 'Configurações',
                'icon' => 'bi-gear',
                'url' => 'configuracoes.php',
                'active_pages' => ['configuracoes.php']
            ],
            [
                'title' => 'Logs do Sistema',
                'icon' => 'bi-journal-text',
                'url' => 'logs.php',
                'active_pages' => ['logs.php']
            ]
        ],
        'usuario' => [
            [
                'title' => 'Dashboard',
                'icon' => 'bi-speedometer2',
                'url' => 'index.php',
                'active_pages' => ['index.php']
            ],
            [
                'title' => 'Equipamentos',
                'icon' => 'bi-eye',
                'url' => 'equipamentos.php',
                'active_pages' => ['equipamentos.php']
            ],
            [
                'title' => 'Manutenções',
                'icon' => 'bi-search',
                'url' => 'manutencoes.php',
                'active_pages' => ['manutencoes.php']
            ],
            [
                'title' => 'Configurações',
                'icon' => 'bi-gear',
                'url' => 'configuracoes.php',
                'active_pages' => ['configuracoes.php']
            ]
        ]
    ];
    
    /**
     * Verifica se um usuário tem uma permissão específica
     * Aceita tanto formato novo (módulo, ação) quanto antigo (permissão única)
     */
    public static function hasPermission($userType, $module, $action = null) {
        if (!isset(self::$permissions[$userType])) {
            return false;
        }
        
        // Se action não foi fornecida, trata module como permissão única
        if ($action === null) {
            return self::hasDirectPermission($userType, $module);
        }
        
        // Formato novo: módulo e ação separados
        if (!isset(self::$permissions[$userType][$module])) {
            return false;
        }
        
        return in_array($action, self::$permissions[$userType][$module]);
    }

    /**
     * Verifica permissões diretas (formato antigo)
     */
    private static function hasDirectPermission($userType, $permission) {
        // Mapeamento de permissões antigas para novas
        $permissionMap = [
            'visualizar_estatisticas_completas' => ['dashboard' => 'full_stats'],
            'visualizar_proprias_manutencoes' => ['manutencoes' => 'view'],
            'visualizar_todas_manutencoes' => ['manutencoes' => 'view'],
            'visualizar_equipamentos' => ['equipamentos' => 'view'],
            'visualizar_relatorios' => ['relatorios' => 'view'],
            'criar_manutencoes' => ['manutencoes' => 'create'],
            'gerenciar_usuarios' => ['usuarios' => 'manage'],
            'cadastro_usuarios' => ['usuarios' => 'create']
        ];
        
        // Verificar se existe mapeamento para esta permissão
        if (isset($permissionMap[$permission])) {
            foreach ($permissionMap[$permission] as $module => $action) {
                if (isset(self::$permissions[$userType][$module]) && 
                    in_array($action, self::$permissions[$userType][$module])) {
                    return true;
                }
            }
            return false;
        }
        
        // Se não há mapeamento, verificar diretamente nos módulos
        foreach (self::$permissions[$userType] as $module => $actions) {
            if (in_array($permission, $actions)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Verifica se um usuário pode acessar uma página específica
     */
    public static function canAccessPage($userType, $page) {
        if (!isset(self::$allowedPages[$userType])) {
            return false;
        }
        
        return in_array($page, self::$allowedPages[$userType]);
    }
    
    /**
     * Força o controle de acesso para uma página
     */
    public static function enforcePageAccess($userType, $currentPage) {
        if (!self::canAccessPage($userType, $currentPage)) {
            if (function_exists('logMessage')) {
                logMessage("Acesso negado para {$userType} à página {$currentPage}", 'WARNING');
            }
            
            // Redireciona para página apropriada
            $redirectPage = self::getDefaultPage($userType);
            header("Location: {$redirectPage}?error=access_denied");
            exit;
        }
    }
    
    /**
     * Retorna a página padrão para um tipo de usuário
     */
    public static function getDefaultPage($userType) {
        $defaultPages = [
            'admin' => 'index.php',
            'tecnico' => 'index.php',
            'usuario' => 'index.php'
        ];
        
        return $defaultPages[$userType] ?? 'login.php';
    }
    
    /**
     * Gera o menu lateral baseado no tipo de usuário
     */
    public static function generateSidebar($userType, $currentPage = '') {
        if (!isset(self::$sidebarMenus[$userType])) {
            return '';
        }
        
        $html = '';
        foreach (self::$sidebarMenus[$userType] as $item) {
            $isActive = in_array($currentPage, $item['active_pages']) ? 'active' : '';
            
            $html .= sprintf(
                '<li class="nav-item">
                    <a class="nav-link %s" href="%s">
                        <i class="%s"></i>
                        %s
                    </a>
                </li>',
                $isActive,
                htmlspecialchars($item['url']),
                htmlspecialchars($item['icon']),
                htmlspecialchars($item['title'])
            );
        }
        
        return $html;
    }
    
    /**
     * Filtra dados baseado nas permissões do usuário
     */
    public static function filterData($userType, $dataType, $data) {
        switch ($dataType) {
            case 'equipamentos':
                return self::filterEquipmentData($userType, $data);
            case 'usuarios':
                return self::filterUserData($userType, $data);
            case 'manutencoes':
                return self::filterMaintenanceData($userType, $data);
            default:
                return $data;
        }
    }
    
    /**
     * Filtra dados de equipamentos
     */
    private static function filterEquipmentData($userType, $data) {
        if ($userType === 'admin' || $userType === 'tecnico') {
            return $data; // Admin e Técnico veem tudo
        }

        if ($userType === 'usuario') {
            // Usuário comum vê apenas equipamentos ativos
            return array_filter($data, function($item) {
                return $item['status'] === 'ativo';
            });
        }

        return [];
    }
    
    /**
     * Filtra dados de usuários
     */
    private static function filterUserData($userType, $data) {
        if ($userType === 'admin') {
            return $data; // Admin vê todos os usuários
        }
        
        if ($userType === 'tecnico') {
            // Técnico vê apenas usuários que criou ou usuários comuns
            return array_filter($data, function($item) {
                return $item['created_by'] == $_SESSION['user_id'] || $item['tipo'] === 'usuario';
            });
        }
        
        // Usuários comuns não veem dados de outros usuários
        return [];
    }
    
    /**
     * Filtra dados de manutenções
     */
    private static function filterMaintenanceData($userType, $data) {
        if ($userType === 'admin' || $userType === 'tecnico') {
            return $data; // Admin e Técnico veem todas as manutenções
        }

        if ($userType === 'usuario') {
            // Usuário comum vê apenas manutenções concluídas de equipamentos públicos
            return array_filter($data, function($item) {
                return $item['status'] === 'concluida';
            });
        }

        return [];
    }
    
    /**
     * Obtém todas as permissões de um tipo de usuário
     */
    public static function getUserPermissions($userType) {
        return self::$permissions[$userType] ?? [];
    }
    
    /**
     * Verifica se é um tipo de usuário válido
     */
    public static function isValidUserType($userType) {
        return array_key_exists($userType, self::$permissions);
    }
    
    /**
     * Obtém tipos de usuário disponíveis
     */
    public static function getAvailableUserTypes() {
        return array_keys(self::$permissions);
    }
    
    /**
     * Gera badge de permissão para exibição
     */
    public static function getPermissionBadge($userType) {
        $badges = [
            'admin' => '<span class="badge bg-danger">Administrador</span>',
            'tecnico' => '<span class="badge bg-primary">Técnico</span>',
            'usuario' => '<span class="badge bg-secondary">Usuário</span>'
        ];
        
        return $badges[$userType] ?? '<span class="badge bg-dark">Desconhecido</span>';
    }
    
    /**
     * Obtém descrição do tipo de usuário
     */
    public static function getUserTypeDescription($userType) {
        $descriptions = [
            'admin' => 'Acesso completo ao sistema, pode gerenciar usuários, equipamentos, manutenções e configurações',
            'tecnico' => 'Acesso completo exceto gestão de usuários - pode criar, editar e excluir equipamentos, manutenções, acessar logs e fazer backups',
            'usuario' => 'Visualização de equipamentos ativos e manutenções concluídas, pode editar próprio perfil'
        ];

        return $descriptions[$userType] ?? 'Tipo de usuário não definido';
    }
    
    /**
     * Verifica se o usuário atual pode editar outro usuário
     */
    public static function canEditUser($currentUserType, $targetUserType, $targetUserId = null, $currentUserId = null) {
        // Admin pode editar qualquer usuário (exceto a si mesmo em certas operações)
        if ($currentUserType === 'admin') {
            return true;
        }
        
        // Técnico pode editar apenas usuários comuns
        if ($currentUserType === 'tecnico' && $targetUserType === 'usuario') {
            return true;
        }
        
        // Usuários podem editar apenas seus próprios dados
        if ($currentUserId && $targetUserId && $currentUserId == $targetUserId) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Gera mensagem de boas-vindas personalizada
     */
    public static function getWelcomeMessage($userType, $userName) {
        $messages = [
            'admin' => [
                'title' => "Bem-vindo, {$userName}!",
                'subtitle' => 'Painel de Administração - Controle total do sistema',
                'description' => 'Gerencie usuários, equipamentos, manutenções e configurações do sistema.',
                'welcome_message' => "Olá, {$userName}!",
                'badge_class' => 'badge bg-danger',
                'user_type_display' => 'Administrador'
            ],
            'tecnico' => [
                'title' => "Olá, {$userName}!",
                'subtitle' => 'Painel Técnico - Gestão Completa',
                'description' => 'Gerencie equipamentos, manutenções, relatórios, configurações e acesse logs do sistema.',
                'welcome_message' => "Olá, {$userName}!",
                'badge_class' => 'badge bg-primary',
                'user_type_display' => 'Técnico'
            ],
            'usuario' => [
                'title' => "Bem-vindo, {$userName}!",
                'subtitle' => 'Painel do Usuário - Consulta de informações',
                'description' => 'Visualize equipamentos ativos e acompanhe manutenções.',
                'welcome_message' => "Olá, {$userName}!",
                'badge_class' => 'badge bg-secondary',
                'user_type_display' => 'Usuário'
            ]
        ];
        
        return $messages[$userType] ?? $messages['usuario'];
    }

    /**
     * Verifica se pode gerenciar um usuário específico
     */
    public static function canManageUser($currentUserType, $targetUserType, $action = 'edit') {
        // Admin pode gerenciar qualquer um
        if ($currentUserType === 'admin') {
            return true;
        }
        
        // Técnico pode gerenciar apenas usuários comuns e apenas certas ações
        if ($currentUserType === 'tecnico' && $targetUserType === 'usuario') {
            $allowedActions = ['view', 'edit', 'create'];
            return in_array($action, $allowedActions);
        }
        
        // Usuário não pode gerenciar ninguém além de si mesmo (em contexto específico)
        return false;
    }

    /**
     * Retorna ações permitidas para um tipo de usuário específico
     */
    public static function getAllowedUserActions($currentUserType, $targetUserType) {
        $actions = [];
        
        if ($currentUserType === 'admin') {
            // Admin pode fazer tudo
            $actions = ['view', 'edit', 'delete', 'reset_password', 'change_type', 'activate', 'deactivate'];
        } elseif ($currentUserType === 'tecnico' && $targetUserType === 'usuario') {
            // Técnico pode gerenciar usuários comuns de forma limitada
            $actions = ['view', 'edit', 'create'];
        }
        
        return $actions;
    }
    
    /**
     * Log de ações baseado em permissões
     */
    public static function logAction($userType, $action, $details = '') {
        if (function_exists('logMessage')) {
            $message = "Ação realizada por {$userType}: {$action}";
            if ($details) {
                $message .= " - {$details}";
            }
            logMessage($message, 'INFO');
        }
    }
    
    /**
     * Middleware para verificação de permissões em AJAX
     */
    public static function checkAjaxPermission($userType, $module, $action) {
        if (!self::hasPermission($userType, $module, $action)) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'message' => 'Permissão negada',
                'error_code' => 'PERMISSION_DENIED'
            ]);
            exit;
        }
    }
    
    /**
     * Gera array de configurações JavaScript para o frontend
     */
    public static function getJSPermissions($userType) {
        return [
            'userType' => $userType,
            'permissions' => self::getUserPermissions($userType),
            'canEdit' => self::hasPermission($userType, 'equipamentos', 'edit'),
            'canDelete' => self::hasPermission($userType, 'equipamentos', 'delete'),
            'canCreate' => self::hasPermission($userType, 'equipamentos', 'create'),
            'canViewReports' => self::hasPermission($userType, 'relatorios', 'view'),
            'canManageUsers' => self::hasPermission($userType, 'usuarios', 'manage'),
            'canCreateUsers' => self::hasPermission($userType, 'usuarios', 'create'),
            'canResetPasswords' => self::hasPermission($userType, 'usuarios', 'reset_password'),
            'canChangeUserTypes' => self::hasPermission($userType, 'usuarios', 'change_type')
        ];
    }

    /**
     * Verifica se pode acessar configurações específicas
     */
    public static function canAccessConfiguration($userType, $configType) {
        $configPermissions = [
            'profile' => ['admin' => true, 'tecnico' => true, 'usuario' => true],
            'system' => ['admin' => true, 'tecnico' => false, 'usuario' => false],
            'security' => ['admin' => true, 'tecnico' => true, 'usuario' => true],
            'theme' => ['admin' => true, 'tecnico' => true, 'usuario' => true],
            'notifications' => ['admin' => true, 'tecnico' => true, 'usuario' => false]
        ];
        
        return $configPermissions[$configType][$userType] ?? false;
    }

    /**
     * Obtém configurações de dashboard baseadas no tipo de usuário
     */
    public static function getDashboardConfig($userType) {
        $configs = [
            'admin' => [
                'show_stats' => true,
                'show_all_users' => true,
                'show_system_info' => true,
                'show_recent_activities' => true,
                'show_charts' => true,
                'refresh_interval' => 30000, // 30 segundos
                'items_per_page' => 20
            ],
            'tecnico' => [
                'show_stats' => true,
                'show_all_users' => false,
                'show_system_info' => true,
                'show_recent_activities' => true,
                'show_charts' => true,
                'refresh_interval' => 30000, // 30 segundos (igual admin)
                'items_per_page' => 20 // Igual admin
            ],
            'usuario' => [
                'show_stats' => false,
                'show_all_users' => false,
                'show_system_info' => false,
                'show_recent_activities' => false,
                'show_charts' => false,
                'refresh_interval' => 120000, // 2 minutos
                'items_per_page' => 10
            ]
        ];
        
        return $configs[$userType] ?? $configs['usuario'];
    }

    /**
     * Verifica limites específicos por tipo de usuário
     */
    public static function getUserLimits($userType) {
        $limits = [
            'admin' => [
                'max_users_create' => -1, // Ilimitado
                'max_file_uploads' => -1,
                'max_concurrent_sessions' => 5,
                'can_delete_own_account' => false,
                'can_demote_self' => false
            ],
            'tecnico' => [
                'max_users_create' => 0, // Técnico não pode criar usuários
                'max_file_uploads' => -1, // Ilimitado (igual admin)
                'max_concurrent_sessions' => 5, // Igual admin
                'can_delete_own_account' => false,
                'can_demote_self' => false
            ],
            'usuario' => [
                'max_users_create' => 0,
                'max_file_uploads' => 5,
                'max_concurrent_sessions' => 2,
                'can_delete_own_account' => false,
                'can_demote_self' => false
            ]
        ];
        
        return $limits[$userType] ?? $limits['usuario'];
    }

    /**
     * Obtém configurações de menu contextual
     */
    public static function getContextMenuOptions($userType, $targetType = null, $targetId = null) {
        $options = [];
        
        if ($userType === 'admin') {
            $options = [
                ['label' => 'Ver Detalhes', 'icon' => 'bi-eye', 'action' => 'view'],
                ['label' => 'Editar', 'icon' => 'bi-pencil', 'action' => 'edit'],
                ['label' => 'Redefinir Senha', 'icon' => 'bi-key', 'action' => 'reset_password'],
                ['label' => 'Alterar Tipo', 'icon' => 'bi-arrow-left-right', 'action' => 'change_type'],
                ['label' => 'Ativar/Desativar', 'icon' => 'bi-toggle-on', 'action' => 'toggle_status'],
                ['separator' => true],
                ['label' => 'Excluir', 'icon' => 'bi-trash', 'action' => 'delete', 'class' => 'text-danger']
            ];
        } elseif ($userType === 'tecnico' && $targetType === 'usuario') {
            $options = [
                ['label' => 'Ver Detalhes', 'icon' => 'bi-eye', 'action' => 'view'],
                ['label' => 'Editar', 'icon' => 'bi-pencil', 'action' => 'edit']
            ];
        }
        
        return $options;
    }
}

/**
 * Função helper global para verificar permissões
 */
if (!function_exists('hasPermission')) {
    function hasPermission($module, $action) {
        $userType = $_SESSION['user_type'] ?? null;
        if (!$userType) {
            return false;
        }
        
        return UserPermissions::hasPermission($userType, $module, $action);
    }
}

/**
 * Função helper para verificar acesso a página
 */
if (!function_exists('canAccessPage')) {
    function canAccessPage($page) {
        $userType = $_SESSION['user_type'] ?? null;
        if (!$userType) {
            return false;
        }
        
        return UserPermissions::canAccessPage($userType, $page);
    }
}

/**
 * Função helper para obter badge de usuário
 */
if (!function_exists('getUserBadge')) {
    function getUserBadge($userType = null) {
        $userType = $userType ?? ($_SESSION['user_type'] ?? null);
        if (!$userType) {
            return '<span class="badge bg-dark">Não logado</span>';
        }
        
        return UserPermissions::getPermissionBadge($userType);
    }
}

/**
 * Função helper para log de ações
 */
if (!function_exists('logUserAction')) {
    function logUserAction($action, $details = '') {
        $userType = $_SESSION['user_type'] ?? 'unknown';
        UserPermissions::logAction($userType, $action, $details);
    }
}

/**
 * Função helper para verificar se pode gerenciar usuário
 */
if (!function_exists('canManageUser')) {
    function canManageUser($targetUserType, $action = 'edit') {
        $currentUserType = $_SESSION['user_type'] ?? null;
        if (!$currentUserType) {
            return false;
        }
        
        return UserPermissions::canManageUser($currentUserType, $targetUserType, $action);
    }
}

/**
 * Função helper para obter configurações de dashboard
 */
if (!function_exists('getDashboardConfig')) {
    function getDashboardConfig() {
        $userType = $_SESSION['user_type'] ?? 'usuario';
        return UserPermissions::getDashboardConfig($userType);
    }
}

/**
 * Função helper para obter limites do usuário
 */
if (!function_exists('getUserLimits')) {
    function getUserLimits() {
        $userType = $_SESSION['user_type'] ?? 'usuario';
        return UserPermissions::getUserLimits($userType);
    }
}

/**
 * Função helper para verificar se é admin
 */
if (!function_exists('isAdmin')) {
    function isAdmin() {
        return ($_SESSION['user_type'] ?? '') === 'admin';
    }
}

/**
 * Função helper para verificar se é técnico
 */
if (!function_exists('isTecnico')) {
    function isTecnico() {
        return ($_SESSION['user_type'] ?? '') === 'tecnico';
    }
}

/**
 * Função helper para verificar se é usuário comum
 */
if (!function_exists('isUsuario')) {
    function isUsuario() {
        return ($_SESSION['user_type'] ?? '') === 'usuario';
    }
}

/**
 * Função de verificação rápida de permissão
 */
if (!function_exists('requirePermission')) {
    function requirePermission($module, $action = 'view') {
        if (!isset($_SESSION['user_type'])) {
            header('Location: login.php');
            exit;
        }
        
        if (!UserPermissions::hasPermission($_SESSION['user_type'], $module, $action)) {
            logMessage("Acesso negado: {$_SESSION['user_name']} tentou acessar {$module}:{$action}", 'WARNING');
            header('Location: index.php?error=permission_denied');
            exit;
        }
    }
}
?>