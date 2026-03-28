<?php
/**
 * Componentes Reutilizáveis de Interface
 * HidroApp - Sistema de Gestão de Manutenção
 * 
 * Este arquivo contém componentes de interface que se adaptam
 * automaticamente ao tipo de usuário logado
 */

class UIComponents {
    
    /**
     * Gera o sidebar completo baseado no tipo de usuário
     */
    public static function renderSidebar($current_page = '') {
        if (!isset($_SESSION['user_type'])) {
            return '';
        }
        
        $user_type = $_SESSION['user_type'];
        $config = UserPermissions::getInterfaceConfig($user_type);
        $theme = getUserTheme($user_type);
        
        ob_start();
        ?>
        <nav class="sidebar" id="sidebar" style="background: <?= $theme['sidebar_bg'] ?>;">
            <div class="sidebar-header">
                <div class="d-flex align-items-center">
                    <div class="me-3">
                        <i class="bi bi-droplet-fill fs-3"></i>
                    </div>
                    <div>
                        <h5 class="mb-0">HidroApp</h5>
                        <small class="opacity-75">v<?= APP_VERSION ?></small>
                    </div>
                </div>
                
                <!-- Badge do tipo de usuário -->
                <div class="mt-2">
                    <span class="badge bg-light text-dark px-2 py-1 rounded-pill">
                        <?php if ($user_type === 'admin'): ?>
                            🛡️ Administrador
                        <?php elseif ($user_type === 'tecnico'): ?>
                            🔧 Técnico
                        <?php else: ?>
                            👤 Usuário
                        <?php endif; ?>
                    </span>
                </div>
            </div>
            
            <div class="sidebar-nav">
                <ul class="nav flex-column">
                    <?= UserPermissions::generateSidebar($user_type, $current_page) ?>
                </ul>
                
                <!-- Informações da sessão -->
                <div class="px-3 mt-4">
                    <small class="text-white-50">
                        <i class="bi bi-clock me-1"></i>
                        Sessão: <?= gmdate("H:i", getSessionTimeRemaining()) ?>
                    </small>
                </div>
            </div>
        </nav>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Gera o cabeçalho superior da página
     */
    public static function renderTopHeader($page_title, $show_user_menu = true) {
        if (!isset($_SESSION['user_name'])) {
            return '';
        }
        
        $user = getCurrentUser();
        $theme = getUserTheme($_SESSION['user_type']);
        
        ob_start();
        ?>
        <header class="top-header">
            <div class="d-flex align-items-center">
                <button class="btn d-md-none me-3" id="sidebarToggle">
                    <i class="bi bi-list fs-4"></i>
                </button>
                <h4 class="mb-0"><?= htmlspecialchars($page_title) ?></h4>
                
                <!-- Indicador de tempo de sessão -->
                <div class="ms-3 d-none d-lg-block">
                    <small class="text-muted" id="sessionTimer">
                        <i class="bi bi-clock me-1"></i>
                        <span id="timeRemaining"><?= gmdate("H:i:s", getSessionTimeRemaining()) ?></span>
                    </small>
                </div>
            </div>
            
            <?php if ($show_user_menu): ?>
            <div class="dropdown">
                <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                    <i class="bi bi-person-circle me-2"></i><?= htmlspecialchars($user['name']) ?>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li>
                        <div class="dropdown-item-text">
                            <small class="text-muted">
                                <?= ucfirst($user['type']) ?> • 
                                Online há <?= self::getOnlineTime($user['login_time']) ?>
                            </small>
                        </div>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    
                    <?php if (canAccessPage('configuracoes.php')): ?>
                    <li><a class="dropdown-item" href="configuracoes.php">
                        <i class="bi bi-person me-2"></i>Perfil
                    </a></li>
                    <li><a class="dropdown-item" href="configuracoes.php">
                        <i class="bi bi-gear me-2"></i>Configurações
                    </a></li>
                    <?php endif; ?>
                    
                    <?php if (hasPermission('relatorios', 'view')): ?>
                    <li><a class="dropdown-item" href="relatorios.php">
                        <i class="bi bi-graph-up me-2"></i>Relatórios
                    </a></li>
                    <?php endif; ?>
                    
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="logout.php">
                        <i class="bi bi-box-arrow-right me-2"></i>Sair
                    </a></li>
                </ul>
            </div>
            <?php endif; ?>
        </header>
        
        <script>
        // Timer de sessão no cabeçalho
        function updateSessionTimer() {
            const timeElement = document.getElementById('timeRemaining');
            if (!timeElement) return;
            
            let remaining = <?= getSessionTimeRemaining() ?>;
            
            setInterval(() => {
                remaining--;
                
                if (remaining <= 0) {
                    window.location.href = 'logout.php';
                    return;
                }
                
                const hours = Math.floor(remaining / 3600);
                const minutes = Math.floor((remaining % 3600) / 60);
                const seconds = remaining % 60;
                
                timeElement.textContent = 
                    String(hours).padStart(2, '0') + ':' +
                    String(minutes).padStart(2, '0') + ':' +
                    String(seconds).padStart(2, '0');
                
                // Avisar quando restam 5 minutos
                if (remaining === 300) {
                    if (confirm('Sua sessão expira em 5 minutos. Deseja estender?')) {
                        location.reload();
                    }
                }
            }, 1000);
        }
        
        document.addEventListener('DOMContentLoaded', updateSessionTimer);
        </script>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Gera cards de estatísticas baseados no tipo de usuário
     */
    public static function renderStatsCards($stats_data) {
        if (!isset($_SESSION['user_type']) || empty($stats_data)) {
            return '';
        }
        
        $user_type = $_SESSION['user_type'];
        
        ob_start();
        ?>
        <div class="row mb-4">
            <?php foreach ($stats_data as $key => $stat): ?>
                <div class="col-lg-3 col-md-6 col-sm-6 mb-3">
                    <div class="stat-card hover-lift fade-in">
                        <div class="d-flex align-items-center">
                            <div class="stat-icon me-3" style="background: <?= $stat['gradient'] ?? 'linear-gradient(135deg, var(--primary-color), var(--secondary-color))' ?>;">
                                <i class="bi bi-<?= $stat['icon'] ?>"></i>
                            </div>
                            <div>
                                <h3 class="mb-1"><?= $stat['value'] ?></h3>
                                <p class="text-muted mb-0"><?= $stat['label'] ?></p>
                                
                                <?php if (isset($stat['trend'])): ?>
                                <small class="<?= $stat['trend'] > 0 ? 'text-success' : ($stat['trend'] < 0 ? 'text-danger' : 'text-muted') ?>">
                                    <i class="bi bi-<?= $stat['trend'] > 0 ? 'arrow-up' : ($stat['trend'] < 0 ? 'arrow-down' : 'dash') ?>"></i>
                                    <?= abs($stat['trend']) ?>%
                                </small>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Gera seção de boas-vindas personalizada
     */
    public static function renderWelcomeSection($additional_info = '') {
        if (!isset($_SESSION['user_type'])) {
            return '';
        }
        
        $user = getCurrentUser();
        $welcome_info = UserPermissions::getWelcomeMessage($_SESSION['user_type'], $user['name']);
        
        ob_start();
        ?>
        <div class="welcome-section fade-in mb-4">
            <div class="position-relative">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h2 class="mb-2">
                            <?php if ($_SESSION['user_type'] === 'admin'): ?>
                                <i class="bi bi-shield-check me-3"></i>
                            <?php elseif ($_SESSION['user_type'] === 'tecnico'): ?>
                                <i class="bi bi-tools me-3"></i>
                            <?php else: ?>
                                <i class="bi bi-person-check me-3"></i>
                            <?php endif; ?>
                            <?= htmlspecialchars($welcome_info['title']) ?>
                        </h2>
                        <p class="mb-1 opacity-90">
                            <i class="bi bi-info-circle me-2"></i><?= $welcome_info['subtitle'] ?>
                        </p>
                        <?php if ($additional_info): ?>
                        <p class="mb-0 opacity-75">
                            <i class="bi bi-lightbulb me-2"></i><?= $additional_info ?>
                        </p>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-4 text-end">
                        <div class="d-flex flex-column align-items-end">
                            <span class="<?= $welcome_info['badge_class'] ?> px-3 py-2 rounded mb-2">
                                <?= $welcome_info['user_type_display'] ?>
                            </span>
                            <small class="text-white-50">
                                <i class="bi bi-calendar me-1"></i>
                                <?= date('d/m/Y H:i') ?>
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Gera alertas de sistema baseados no tipo de usuário
     */
    public static function renderSystemAlerts() {
        if (!isset($_SESSION['user_type'])) {
            return '';
        }
        
        $alerts = [];
        $user_type = $_SESSION['user_type'];
        
        // Verificar alertas específicos por tipo de usuário
        if ($user_type === 'admin') {
            // Alertas para administradores
            if (MAINTENANCE_MODE) {
                $alerts[] = [
                    'type' => 'warning',
                    'icon' => 'exclamation-triangle',
                    'title' => 'Modo Manutenção Ativo',
                    'message' => 'O sistema está em modo manutenção. Apenas administradores têm acesso.'
                ];
            }
            
            if (DEBUG_MODE) {
                $alerts[] = [
                    'type' => 'info',
                    'icon' => 'bug',
                    'title' => 'Modo Debug Ativo',
                    'message' => 'O modo debug está ativo. Lembre-se de desativar em produção.'
                ];
            }
        }
        
        // Verificar sessão expirando
        $time_remaining = getSessionTimeRemaining();
        if ($time_remaining < 600) { // Menos de 10 minutos
            $alerts[] = [
                'type' => 'warning',
                'icon' => 'clock',
                'title' => 'Sessão Expirando',
                'message' => "Sua sessão expira em " . gmdate("i:s", $time_remaining) . " minutos."
            ];
        }
        
        if (empty($alerts)) {
            return '';
        }
        
        ob_start();
        ?>
        <div class="system-alerts">
            <?php foreach ($alerts as $alert): ?>
                <div class="alert alert-<?= $alert['type'] ?> alert-dismissible fade show" role="alert">
                    <i class="bi bi-<?= $alert['icon'] ?> me-2"></i>
                    <strong><?= $alert['title'] ?>:</strong> <?= $alert['message'] ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Gera filtros de busca adaptativos
     */
    public static function renderSearchFilters($filters_config, $current_values = []) {
        if (!isset($_SESSION['user_type']) || empty($filters_config)) {
            return '';
        }
        
        $user_type = $_SESSION['user_type'];
        
        ob_start();
        ?>
        <div class="search-filters fade-in">
            <form method="GET" class="row g-3" id="searchForm">
                <?php foreach ($filters_config as $filter): ?>
                    <?php if (!isset($filter['permission']) || hasPermission($filter['permission']['module'], $filter['permission']['action'])): ?>
                        <div class="col-lg-<?= $filter['col_size'] ?? 3 ?> col-md-6">
                            <label class="form-label"><?= $filter['label'] ?></label>
                            
                            <?php if ($filter['type'] === 'select'): ?>
                                <select class="form-select" name="<?= $filter['name'] ?>">
                                    <option value=""><?= $filter['placeholder'] ?? 'Todos' ?></option>
                                    <?php foreach ($filter['options'] as $value => $label): ?>
                                        <option value="<?= $value ?>" <?= ($current_values[$filter['name']] ?? '') === $value ? 'selected' : '' ?>>
                                            <?= $label ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                
                            <?php elseif ($filter['type'] === 'text'): ?>
                                <div class="input-group">
                                    <?php if (isset($filter['icon'])): ?>
                                        <span class="input-group-text"><i class="bi bi-<?= $filter['icon'] ?>"></i></span>
                                    <?php endif; ?>
                                    <input type="text" class="form-control" name="<?= $filter['name'] ?>" 
                                           placeholder="<?= $filter['placeholder'] ?? '' ?>" 
                                           value="<?= htmlspecialchars($current_values[$filter['name']] ?? '') ?>">
                                </div>
                                
                            <?php elseif ($filter['type'] === 'date'): ?>
                                <input type="date" class="form-control" name="<?= $filter['name'] ?>" 
                                       value="<?= htmlspecialchars($current_values[$filter['name']] ?? '') ?>">
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
                
                <div class="col-lg-2 col-md-6">
                    <label class="form-label">&nbsp;</label>
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary-custom">
                            <i class="bi bi-funnel me-1"></i>Filtrar
                        </button>
                    </div>
                </div>
                
                <div class="col-lg-2 col-md-6">
                    <label class="form-label">&nbsp;</label>
                    <div class="d-grid gap-2">
                        <a href="<?= $_SERVER['PHP_SELF'] ?>" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-clockwise me-1"></i>Limpar
                        </a>
                    </div>
                </div>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Gera footer padrão
     */
    public static function renderFooter() {
        ob_start();
        ?>
        <footer class="footer-area">
            <div class="container-fluid">
                <div class="text-center py-3">
                    <div class="row">
                        <div class="col-12 col-md-6">
                            <p class="mb-1 text-muted">
                                <small>
                                    Desenvolvido por 
                                    <a href="https://i9script.com" target="_blank" class="footer-link">
                                        <strong>i9Script Technology</strong>
                                    </a>
                                </small>
                            </p>
                        </div>
                        <div class="col-12 col-md-6">
                            <p class="mb-1 text-muted">
                                <small>© Hidro Evolution <?= date('Y') ?> - Todos os direitos reservados</small>
                            </p>
                        </div>
                    </div>
                    
                    <!-- Informações de debug para admins -->
                    <?php if (DEBUG_MODE && $_SESSION['user_type'] === 'admin'): ?>
                    <div class="row mt-2">
                        <div class="col-12">
                            <small class="text-muted">
                                Debug: PHP <?= PHP_VERSION ?> | Memória: <?= round(memory_get_usage()/1024/1024, 2) ?>MB |
                                Tempo: <?= round((microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']) * 1000, 2) ?>ms
                            </small>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </footer>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Gera CSS temático dinâmico
     */
    public static function renderThemeCSS() {
        if (!isset($_SESSION['user_type'])) {
            return '';
        }
        
        return generateUserThemeCSS($_SESSION['user_type']);
    }
    
    /**
     * Helper para calcular tempo online
     */
    private static function getOnlineTime($login_time) {
        if (!$login_time) return 'tempo desconhecido';
        
        $diff = time() - $login_time;
        
        if ($diff < 60) return 'menos de 1 min';
        if ($diff < 3600) return round($diff / 60) . ' min';
        if ($diff < 86400) return round($diff / 3600, 1) . 'h';
        
        return round($diff / 86400) . ' dias';
    }
}

// ============ FUNÇÕES HELPER PARA USO DIRETO ============

function renderSidebar($current_page = '') {
    return UIComponents::renderSidebar($current_page);
}

function renderTopHeader($page_title, $show_user_menu = true) {
    return UIComponents::renderTopHeader($page_title, $show_user_menu);
}

function renderWelcomeSection($additional_info = '') {
    return UIComponents::renderWelcomeSection($additional_info);
}

function renderStatsCards($stats_data) {
    return UIComponents::renderStatsCards($stats_data);
}

function renderSystemAlerts() {
    return UIComponents::renderSystemAlerts();
}

function renderSearchFilters($filters_config, $current_values = []) {
    return UIComponents::renderSearchFilters($filters_config, $current_values);
}

function renderFooter() {
    return UIComponents::renderFooter();
}

function renderThemeCSS() {
    return UIComponents::renderThemeCSS();
}
?>