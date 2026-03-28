# HidroApp - Guia Rápido de Implementação

## 🚀 Começando em 5 Minutos

---

## 1️⃣ Incluir Arquivos nas Páginas

### Em todas as páginas HTML/PHP, adicione no `<head>`:

```html
<!-- Bootstrap CSS (se ainda não tiver) -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

<!-- Bootstrap Icons -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">

<!-- CSS Responsivo do HidroApp -->
<link href="assets/css/responsive.css" rel="stylesheet">
```

### Antes do `</body>`, adicione:

```html
<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- Chart.js (apenas em páginas com gráficos) -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<!-- JavaScript Responsivo do HidroApp -->
<script src="assets/js/responsive.js"></script>
```

---

## 2️⃣ Estrutura HTML Básica

### Layout Principal:

```html
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HidroApp - Página</title>

    <!-- CSS aqui -->
    <link href="assets/css/responsive.css" rel="stylesheet">
</head>
<body>
    <!-- Sidebar -->
    <nav class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <h5>HidroApp</h5>
        </div>
        <div class="sidebar-nav">
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link" href="index.php">
                        <i class="bi bi-house"></i>Dashboard
                    </a>
                </li>
                <!-- Mais itens -->
            </ul>
        </div>
    </nav>

    <!-- Overlay (para mobile) -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Conteúdo Principal -->
    <main class="main-content">
        <!-- Header -->
        <header class="top-header">
            <div class="d-flex align-items-center">
                <button class="btn d-md-none" id="sidebarToggle">
                    <i class="bi bi-list fs-4"></i>
                </button>
                <h4 class="mb-0">Título da Página</h4>
            </div>
            <div><!-- Menu do usuário --></div>
        </header>

        <!-- Área de Conteúdo -->
        <div class="content-area">
            <!-- Seu conteúdo aqui -->
        </div>

        <!-- Footer -->
        <footer class="footer-area">
            <!-- Rodapé -->
        </footer>
    </main>

    <!-- JavaScript aqui -->
    <script src="assets/js/responsive.js"></script>
</body>
</html>
```

---

## 3️⃣ Componentes Prontos

### Cards Responsivos:

```html
<div class="card-responsive">
    <div class="card-header-responsive">
        <h5><i class="bi bi-star"></i>Título do Card</h5>
    </div>
    <div class="card-body-responsive">
        <p>Conteúdo do card...</p>
    </div>
    <div class="card-footer-responsive">
        <button class="btn btn-primary-custom">Ação</button>
    </div>
</div>
```

### Grid Responsivo:

```html
<!-- 4 colunas no desktop, 2 no tablet, 1 no mobile -->
<div class="grid-responsive grid-responsive-4">
    <div class="card-responsive">Item 1</div>
    <div class="card-responsive">Item 2</div>
    <div class="card-responsive">Item 3</div>
    <div class="card-responsive">Item 4</div>
</div>
```

### Formulários:

```html
<form>
    <div class="form-group-responsive">
        <label class="form-label-responsive">
            <i class="bi bi-person"></i>Nome
        </label>
        <input type="text" class="form-control-responsive" required>
    </div>

    <div class="form-group-responsive">
        <label class="form-label-responsive">
            <i class="bi bi-envelope"></i>Email
        </label>
        <input type="email" class="form-control-responsive" required>
    </div>

    <button type="submit" class="btn btn-primary-custom">
        <i class="bi bi-check"></i>Enviar
    </button>
</form>
```

### Tabelas (Auto-converte para cards no mobile):

```html
<div class="table-responsive-custom">
    <table class="table table-hover">
        <thead class="table-light">
            <tr>
                <th>Coluna 1</th>
                <th>Coluna 2</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Dado 1</td>
                <td>Dado 2</td>
                <td>
                    <button class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-eye"></i>
                    </button>
                </td>
            </tr>
        </tbody>
    </table>
</div>

<!-- Cards para mobile (gerados automaticamente pelo JS) -->
<div class="table-mobile-cards" id="tableCards"></div>
```

---

## 4️⃣ Usando o Sistema de Relatórios

### Acessar a Página:

```
http://localhost:8085/relatorios.php
```

### Gerar Relatório via JavaScript:

```javascript
// Configurar dados
const formData = new FormData();
formData.append('reportType', 'manutencoes'); // ou equipamentos, tecnicos, custos
formData.append('dataInicio', '2025-01-01');
formData.append('dataFim', '2025-10-17');

// Fazer requisição
fetch('relatorios_api.php?action=generate', {
    method: 'POST',
    body: formData
})
.then(response => response.json())
.then(data => {
    if (data.success) {
        console.log('Relatório:', data.data);
        // Processar dados
    }
});
```

### Exportar Relatório:

```javascript
// PDF
window.open('relatorios_api.php?action=export_pdf&reportType=manutencoes&dataInicio=2025-01-01&dataFim=2025-10-17', '_blank');

// CSV
window.location.href = 'relatorios_api.php?action=export_csv&reportType=equipamentos&dataInicio=2025-01-01&dataFim=2025-10-17';
```

---

## 5️⃣ Funções JavaScript Úteis

### Verificar Dispositivo:

```javascript
if (isMobileDevice()) {
    console.log('Está no mobile');
}

if (isTabletDevice()) {
    console.log('Está no tablet');
}

if (isDesktopDevice()) {
    console.log('Está no desktop');
}
```

### Mostrar Notificação:

```javascript
// Sucesso
showNotification('Operação realizada com sucesso!', 'success');

// Erro
showNotification('Ocorreu um erro!', 'danger');

// Aviso
showNotification('Atenção: verifique os dados', 'warning');

// Informação
showNotification('Processando...', 'info');
```

### Gerenciar Sidebar:

```javascript
// Abrir
HidroAppResponsive.openSidebar();

// Fechar
HidroAppResponsive.closeSidebar();

// Alternar
HidroAppResponsive.toggleSidebar();
```

### Escutar Eventos:

```javascript
// Mudança de dispositivo
document.addEventListener('hidroapp:deviceChanged', function(e) {
    console.log('Mudou de:', e.detail.from);
    console.log('Para:', e.detail.to);
});

// Sidebar aberta
document.addEventListener('hidroapp:sidebarOpened', function() {
    console.log('Sidebar foi aberta');
});

// Sidebar fechada
document.addEventListener('hidroapp:sidebarClosed', function() {
    console.log('Sidebar foi fechada');
});
```

---

## 6️⃣ Classes CSS Úteis

### Visibilidade Responsiva:

```html
<!-- Ocultar no mobile -->
<div class="hide-mobile">Visível apenas em tablet/desktop</div>

<!-- Mostrar apenas no mobile -->
<div class="show-mobile">Visível apenas no mobile</div>

<!-- Ocultar no tablet -->
<div class="hide-tablet">Oculto no tablet</div>
```

### Espaçamentos:

```html
<div class="mt-responsive">Margem top responsiva</div>
<div class="mb-responsive">Margem bottom responsiva</div>
<div class="px-responsive">Padding horizontal responsivo</div>
<div class="py-responsive">Padding vertical responsivo</div>
```

### Texto:

```html
<div class="text-center-mobile">Centralizado no mobile</div>
<div class="text-truncate-mobile">Texto truncado no mobile</div>
```

### Layout:

```html
<div class="w-100-mobile">Largura 100% no mobile</div>
<div class="d-flex-mobile">Display flex no mobile</div>
<div class="flex-column-mobile">Flex em coluna no mobile</div>
```

---

## 7️⃣ Criar Gráficos

### Gráfico de Barras:

```javascript
const ctx = document.getElementById('meuGrafico').getContext('2d');
const chart = new Chart(ctx, {
    type: 'bar',
    data: {
        labels: ['Jan', 'Fev', 'Mar', 'Abr', 'Mai'],
        datasets: [{
            label: 'Manutenções',
            data: [12, 19, 15, 25, 22],
            backgroundColor: 'rgba(0, 102, 204, 0.8)',
            borderColor: 'rgba(0, 102, 204, 1)',
            borderWidth: 2
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: { beginAtZero: true }
        }
    }
});
```

### Gráfico de Rosca:

```javascript
const ctx = document.getElementById('meuGrafico').getContext('2d');
const chart = new Chart(ctx, {
    type: 'doughnut',
    data: {
        labels: ['Concluídas', 'Pendentes', 'Canceladas'],
        datasets: [{
            data: [45, 30, 10],
            backgroundColor: [
                'rgba(82, 196, 26, 0.8)',
                'rgba(250, 173, 20, 0.8)',
                'rgba(255, 77, 79, 0.8)'
            ]
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false
    }
});
```

---

## 8️⃣ Permissões nos Relatórios

### Verificar no PHP:

```php
// Verificar se pode visualizar relatórios
if (UserPermissions::hasPermission($user_type, 'relatorios', 'view')) {
    // Mostrar página de relatórios
}

// Verificar se pode exportar
if (UserPermissions::hasPermission($user_type, 'relatorios', 'export')) {
    // Mostrar botões de exportação
}

// Verificar se pode ver relatório de técnicos
if ($user_type === 'admin') {
    // Mostrar relatório completo de técnicos
} else {
    // Mostrar apenas dados do próprio técnico
}
```

---

## 9️⃣ Debugging

### Console do Navegador:

```javascript
// Verificar se o sistema responsivo está carregado
console.log('HidroAppResponsive:', typeof HidroAppResponsive !== 'undefined');

// Ver estado atual
console.log('Dispositivo:', {
    mobile: HidroAppResponsive.state.isMobile,
    tablet: HidroAppResponsive.state.isTablet,
    desktop: HidroAppResponsive.state.isDesktop,
    width: HidroAppResponsive.state.currentWidth
});

// Forçar refresh
HidroAppResponsive.refresh();
```

### PHP (Backend):

```php
// Habilitar logs
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Log customizado
logMessage('Mensagem de debug', 'DEBUG', $user_type);

// Ver query SQL
$query = "SELECT * FROM manutencoes WHERE ...";
error_log("Query: $query");
error_log("Params: " . print_r($params, true));
```

---

## 🔟 Troubleshooting Comum

### Sidebar não abre no mobile:

```javascript
// Verificar se elementos existem
console.log('Sidebar:', document.getElementById('sidebar'));
console.log('Toggle:', document.getElementById('sidebarToggle'));

// Forçar abertura
document.getElementById('sidebar').classList.add('show');
```

### Tabela não converte para cards:

```javascript
// Verificar se está em mobile
console.log('É mobile?', HidroAppResponsive.state.isMobile);

// Forçar conversão
const table = document.querySelector('table');
HidroAppResponsive.convertTableToCards(table);
```

### Gráfico não aparece:

```javascript
// Verificar se Chart.js está carregado
console.log('Chart.js:', typeof Chart !== 'undefined');

// Verificar elemento canvas
const canvas = document.getElementById('meuGrafico');
console.log('Canvas:', canvas);
console.log('Context:', canvas ? canvas.getContext('2d') : null);
```

### Relatório não gera:

```javascript
// Ver resposta completa da API
fetch('relatorios_api.php?action=generate', {
    method: 'POST',
    body: formData
})
.then(response => {
    console.log('Status:', response.status);
    return response.text();
})
.then(text => {
    console.log('Resposta bruta:', text);
    try {
        const data = JSON.parse(text);
        console.log('Dados:', data);
    } catch (e) {
        console.error('Erro ao parsear JSON:', e);
    }
});
```

---

## ✅ Checklist de Implementação

Antes de usar em produção, verifique:

- [ ] Arquivos CSS e JS incluídos em todas as páginas
- [ ] Estrutura HTML correta (sidebar, main-content)
- [ ] Botão de toggle da sidebar presente
- [ ] Overlay da sidebar criado
- [ ] Tabelas com classe `.table-responsive-custom`
- [ ] Formulários com classes responsivas
- [ ] Permissões de relatórios configuradas
- [ ] mPDF instalado (composer require mpdf/mpdf)
- [ ] Chart.js incluído nas páginas necessárias
- [ ] Testes em diferentes dispositivos
- [ ] Logs habilitados para debug

---

## 📚 Documentação Completa

Para mais detalhes, consulte:

- [RESPONSIVIDADE_E_RELATORIOS.md](RESPONSIVIDADE_E_RELATORIOS.md) - Documentação técnica completa
- [RESUMO_MELHORIAS_RESPONSIVIDADE.md](RESUMO_MELHORIAS_RESPONSIVIDADE.md) - Resumo executivo

---

## 🎉 Pronto!

Seu sistema HidroApp está agora 100% responsivo e com sistema de relatórios completo!

**Dúvidas?** Consulte a documentação ou entre em contato.

---

© Hidro Evolution 2025 - Desenvolvido por [i9Script Technology](https://i9script.com)
