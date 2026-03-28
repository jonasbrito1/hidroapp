/**
 * HidroApp - JavaScript Responsivo
 * Gerenciamento de comportamento mobile e responsividade
 * Última atualização: 2025-10-17
 */

(function() {
    'use strict';

    // ==========================================================================
    // 1. CONFIGURAÇÕES GLOBAIS
    // ==========================================================================
    const HidroAppResponsive = {
        // Breakpoints
        breakpoints: {
            mobile: 767,
            tablet: 991,
            desktop: 1199
        },

        // Estado atual
        state: {
            isMobile: false,
            isTablet: false,
            isDesktop: false,
            sidebarOpen: false,
            currentWidth: window.innerWidth
        },

        // Elementos DOM
        elements: {
            sidebar: null,
            sidebarToggle: null,
            sidebarOverlay: null,
            mainContent: null,
            tables: []
        },

        // ==========================================================================
        // 2. INICIALIZAÇÃO
        // ==========================================================================
        init: function() {
            console.log('🚀 Inicializando HidroApp Responsive...');

            // Detectar dispositivo
            this.detectDevice();

            // Buscar elementos DOM
            this.cacheElements();

            // Configurar event listeners
            this.setupEventListeners();

            // Inicializar componentes
            this.initSidebar();
            this.initTables();
            this.initForms();
            this.initDropdowns();
            this.initTooltips();

            // Configurar observadores
            this.setupObservers();

            console.log('✅ HidroApp Responsive iniciado com sucesso!');
        },

        // ==========================================================================
        // 3. DETECÇÃO DE DISPOSITIVO
        // ==========================================================================
        detectDevice: function() {
            const width = window.innerWidth;

            this.state.isMobile = width <= this.breakpoints.mobile;
            this.state.isTablet = width > this.breakpoints.mobile && width <= this.breakpoints.tablet;
            this.state.isDesktop = width > this.breakpoints.tablet;
            this.state.currentWidth = width;

            // Adicionar classes ao body
            document.body.classList.remove('is-mobile', 'is-tablet', 'is-desktop');
            if (this.state.isMobile) document.body.classList.add('is-mobile');
            if (this.state.isTablet) document.body.classList.add('is-tablet');
            if (this.state.isDesktop) document.body.classList.add('is-desktop');

            console.log('📱 Dispositivo detectado:', {
                mobile: this.state.isMobile,
                tablet: this.state.isTablet,
                desktop: this.state.isDesktop,
                width: width
            });
        },

        // ==========================================================================
        // 4. CACHE DE ELEMENTOS DOM
        // ==========================================================================
        cacheElements: function() {
            this.elements.sidebar = document.getElementById('sidebar') || document.querySelector('.sidebar');
            this.elements.sidebarToggle = document.getElementById('sidebarToggle') || document.querySelector('[data-toggle="sidebar"]');
            this.elements.mainContent = document.querySelector('.main-content');
            this.elements.tables = document.querySelectorAll('table');

            // Criar overlay se não existir
            if (this.state.isMobile && !document.querySelector('.sidebar-overlay')) {
                const overlay = document.createElement('div');
                overlay.className = 'sidebar-overlay';
                overlay.id = 'sidebarOverlay';
                document.body.appendChild(overlay);
            }

            this.elements.sidebarOverlay = document.getElementById('sidebarOverlay');
        },

        // ==========================================================================
        // 5. EVENT LISTENERS
        // ==========================================================================
        setupEventListeners: function() {
            const self = this;

            // Resize window
            let resizeTimeout;
            window.addEventListener('resize', function() {
                clearTimeout(resizeTimeout);
                resizeTimeout = setTimeout(function() {
                    self.handleResize();
                }, 250);
            });

            // Orientação do dispositivo
            window.addEventListener('orientationchange', function() {
                setTimeout(function() {
                    self.handleResize();
                }, 100);
            });

            // Sidebar toggle
            if (this.elements.sidebarToggle) {
                this.elements.sidebarToggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    self.toggleSidebar();
                });
            }

            // Sidebar overlay click
            if (this.elements.sidebarOverlay) {
                this.elements.sidebarOverlay.addEventListener('click', function() {
                    self.closeSidebar();
                });
            }

            // ESC key para fechar sidebar
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && self.state.sidebarOpen) {
                    self.closeSidebar();
                }
            });

            // Prevenir zoom em iOS no foco de inputs
            if (this.state.isMobile && /iPhone|iPad|iPod/.test(navigator.userAgent)) {
                const inputs = document.querySelectorAll('input, select, textarea');
                inputs.forEach(function(input) {
                    input.addEventListener('focus', function() {
                        if (input.style.fontSize && parseFloat(input.style.fontSize) < 16) {
                            input.style.fontSize = '16px';
                        }
                    });
                });
            }
        },

        // ==========================================================================
        // 6. GERENCIAMENTO DE SIDEBAR
        // ==========================================================================
        initSidebar: function() {
            if (!this.elements.sidebar) return;

            if (this.state.isMobile) {
                this.elements.sidebar.classList.remove('show');
                this.state.sidebarOpen = false;
            } else {
                this.elements.sidebar.classList.add('show');
                if (this.elements.sidebarOverlay) {
                    this.elements.sidebarOverlay.classList.remove('show');
                }
            }
        },

        toggleSidebar: function() {
            if (this.state.sidebarOpen) {
                this.closeSidebar();
            } else {
                this.openSidebar();
            }
        },

        openSidebar: function() {
            if (!this.elements.sidebar) return;

            this.elements.sidebar.classList.add('show', 'slide-in-left');
            if (this.elements.sidebarOverlay) {
                this.elements.sidebarOverlay.classList.add('show');
            }
            this.state.sidebarOpen = true;

            // Prevenir scroll do body
            if (this.state.isMobile) {
                document.body.style.overflow = 'hidden';
            }

            // Trigger event
            this.triggerEvent('sidebarOpened');
        },

        closeSidebar: function() {
            if (!this.elements.sidebar) return;

            this.elements.sidebar.classList.remove('show');
            if (this.elements.sidebarOverlay) {
                this.elements.sidebarOverlay.classList.remove('show');
            }
            this.state.sidebarOpen = false;

            // Restaurar scroll do body
            document.body.style.overflow = '';

            // Trigger event
            this.triggerEvent('sidebarClosed');
        },

        // ==========================================================================
        // 7. TABELAS RESPONSIVAS
        // ==========================================================================
        initTables: function() {
            const self = this;

            this.elements.tables.forEach(function(table) {
                // Adicionar wrapper se não existir
                if (!table.parentElement.classList.contains('table-responsive-custom')) {
                    const wrapper = document.createElement('div');
                    wrapper.className = 'table-responsive-custom';
                    table.parentNode.insertBefore(wrapper, table);
                    wrapper.appendChild(table);
                }

                // Converter para cards em mobile se necessário
                if (self.state.isMobile) {
                    self.convertTableToCards(table);
                }
            });
        },

        convertTableToCards: function(table) {
            // Verificar se já foi convertida
            const existingCards = table.parentElement.querySelector('.table-mobile-cards');
            if (existingCards) return;

            // Obter headers
            const headers = [];
            const headerCells = table.querySelectorAll('thead th');
            headerCells.forEach(function(th) {
                headers.push(th.textContent.trim());
            });

            // Obter dados
            const rows = table.querySelectorAll('tbody tr');
            if (rows.length === 0) return;

            // Criar container de cards
            const cardsContainer = document.createElement('div');
            cardsContainer.className = 'table-mobile-cards';

            // Criar cards
            rows.forEach(function(row) {
                const cells = row.querySelectorAll('td');
                const card = document.createElement('div');
                card.className = 'table-mobile-card';

                // Título do card (primeira célula)
                if (cells[0]) {
                    const cardHeader = document.createElement('div');
                    cardHeader.className = 'table-mobile-card-header';
                    cardHeader.textContent = cells[0].textContent.trim();
                    card.appendChild(cardHeader);
                }

                // Demais células
                cells.forEach(function(cell, index) {
                    if (index === 0) return; // Pular primeira célula

                    const cardRow = document.createElement('div');
                    cardRow.className = 'table-mobile-card-row';

                    const label = document.createElement('div');
                    label.className = 'table-mobile-card-label';
                    label.textContent = headers[index] || '';

                    const value = document.createElement('div');
                    value.className = 'table-mobile-card-value';
                    value.innerHTML = cell.innerHTML;

                    cardRow.appendChild(label);
                    cardRow.appendChild(value);
                    card.appendChild(cardRow);
                });

                cardsContainer.appendChild(card);
            });

            // Inserir cards após tabela
            table.parentElement.appendChild(cardsContainer);

            // Ocultar tabela em mobile
            table.style.display = 'none';
        },

        // ==========================================================================
        // 8. FORMULÁRIOS RESPONSIVOS
        // ==========================================================================
        initForms: function() {
            const forms = document.querySelectorAll('form');

            forms.forEach(function(form) {
                // Adicionar classes responsivas
                form.classList.add('form-responsive');

                // Ajustar inputs
                const inputs = form.querySelectorAll('input, select, textarea');
                inputs.forEach(function(input) {
                    if (!input.classList.contains('form-control-responsive')) {
                        input.classList.add('form-control-responsive');
                    }

                    // Prevenir zoom em iOS
                    if (/iPhone|iPad|iPod/.test(navigator.userAgent)) {
                        if (!input.style.fontSize || parseFloat(input.style.fontSize) < 16) {
                            input.style.fontSize = '16px';
                        }
                    }
                });

                // Agrupar botões em mobile
                const buttons = form.querySelectorAll('button[type="submit"], button[type="button"], .btn');
                if (buttons.length > 1 && this.state.isMobile) {
                    const btnGroup = document.createElement('div');
                    btnGroup.className = 'btn-group-mobile';
                    buttons.forEach(function(btn) {
                        if (!btn.classList.contains('btn-responsive')) {
                            btn.classList.add('btn-responsive');
                        }
                    });
                }
            }.bind(this));
        },

        // ==========================================================================
        // 9. DROPDOWNS RESPONSIVOS
        // ==========================================================================
        initDropdowns: function() {
            const dropdowns = document.querySelectorAll('.dropdown-toggle');

            dropdowns.forEach(function(dropdown) {
                dropdown.addEventListener('click', function(e) {
                    if (this.state.isMobile) {
                        const menu = dropdown.nextElementSibling;
                        if (menu && menu.classList.contains('dropdown-menu')) {
                            // Ajustar posição em mobile
                            menu.style.position = 'fixed';
                            menu.style.top = 'auto';
                            menu.style.bottom = '0';
                            menu.style.left = '0';
                            menu.style.right = '0';
                            menu.style.borderRadius = '12px 12px 0 0';
                        }
                    }
                }.bind(this));
            }.bind(this));
        },

        // ==========================================================================
        // 10. TOOLTIPS RESPONSIVOS
        // ==========================================================================
        initTooltips: function() {
            // Desabilitar tooltips em mobile
            if (this.state.isMobile) {
                const tooltips = document.querySelectorAll('[data-bs-toggle="tooltip"]');
                tooltips.forEach(function(element) {
                    element.removeAttribute('data-bs-toggle');
                    element.removeAttribute('title');
                });
            }
        },

        // ==========================================================================
        // 11. HANDLE RESIZE
        // ==========================================================================
        handleResize: function() {
            const oldState = {
                isMobile: this.state.isMobile,
                isTablet: this.state.isTablet,
                isDesktop: this.state.isDesktop
            };

            // Detectar novo dispositivo
            this.detectDevice();

            // Verificar se mudou de tipo
            const deviceChanged = (
                oldState.isMobile !== this.state.isMobile ||
                oldState.isTablet !== this.state.isTablet ||
                oldState.isDesktop !== this.state.isDesktop
            );

            if (deviceChanged) {
                console.log('📱 Mudança de dispositivo detectada');

                // Reinicializar componentes
                this.initSidebar();
                this.initTables();
                this.initForms();

                // Trigger event
                this.triggerEvent('deviceChanged', {
                    from: oldState,
                    to: this.state
                });
            }
        },

        // ==========================================================================
        // 12. OBSERVADORES
        // ==========================================================================
        setupObservers: function() {
            // Intersection Observer para animações
            const observerOptions = {
                threshold: 0.1,
                rootMargin: '0px 0px -50px 0px'
            };

            const observer = new IntersectionObserver(function(entries) {
                entries.forEach(function(entry) {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('fade-in');
                        entry.target.style.opacity = '1';
                        entry.target.style.transform = 'translateY(0)';
                    }
                });
            }, observerOptions);

            // Observar elementos com animação
            document.querySelectorAll('.animate-on-scroll').forEach(function(el) {
                el.style.opacity = '0';
                el.style.transform = 'translateY(20px)';
                el.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
                observer.observe(el);
            });
        },

        // ==========================================================================
        // 13. UTILITÁRIOS
        // ==========================================================================
        triggerEvent: function(eventName, data) {
            const event = new CustomEvent('hidroapp:' + eventName, {
                detail: data || {},
                bubbles: true,
                cancelable: true
            });
            document.dispatchEvent(event);
        },

        // Função pública para verificar estado
        isMobile: function() {
            return this.state.isMobile;
        },

        isTablet: function() {
            return this.state.isTablet;
        },

        isDesktop: function() {
            return this.state.isDesktop;
        },

        // Função pública para forçar refresh
        refresh: function() {
            this.handleResize();
        }
    };

    // ==========================================================================
    // 14. AUTO-INICIALIZAÇÃO
    // ==========================================================================
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            HidroAppResponsive.init();
        });
    } else {
        HidroAppResponsive.init();
    }

    // Expor globalmente
    window.HidroAppResponsive = HidroAppResponsive;

})();

// ==========================================================================
// 15. UTILITÁRIOS GLOBAIS
// ==========================================================================

/**
 * Verificar se está em mobile
 */
function isMobileDevice() {
    return window.HidroAppResponsive && window.HidroAppResponsive.isMobile();
}

/**
 * Verificar se está em tablet
 */
function isTabletDevice() {
    return window.HidroAppResponsive && window.HidroAppResponsive.isTablet();
}

/**
 * Verificar se está em desktop
 */
function isDesktopDevice() {
    return window.HidroAppResponsive && window.HidroAppResponsive.isDesktop();
}

/**
 * Mostrar notificação responsiva
 */
function showNotification(message, type = 'info', duration = 5000) {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show alert-responsive`;

    if (isMobileDevice()) {
        alertDiv.style.cssText = 'position: fixed; bottom: 20px; left: 10px; right: 10px; z-index: 9999;';
    } else {
        alertDiv.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 9999; min-width: 300px; max-width: 500px;';
    }

    alertDiv.innerHTML = `
        <div class="d-flex align-items-center">
            <i class="bi bi-${type === 'success' ? 'check-circle' : type === 'danger' ? 'x-circle' : type === 'warning' ? 'exclamation-triangle' : 'info-circle'} me-2"></i>
            <div>${message}</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;

    document.body.appendChild(alertDiv);

    setTimeout(function() {
        alertDiv.classList.remove('show');
        setTimeout(function() {
            alertDiv.remove();
        }, 150);
    }, duration);
}

/**
 * Confirmar ação (modal responsivo)
 */
function confirmAction(message, callback) {
    if (isMobileDevice()) {
        // Em mobile, usar confirm nativo estilizado
        if (confirm(message)) {
            callback();
        }
    } else {
        // Em desktop, pode usar modal do Bootstrap
        if (confirm(message)) {
            callback();
        }
    }
}

console.log('✅ HidroApp Responsive JavaScript carregado!');
