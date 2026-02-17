<?php
// sidebar.php - Include global para sidebar padrão
// Detecta a página atual para marcar como ativo na sidebar

// Obtem o arquivo atual
$arquivo_atual = basename($_SERVER['PHP_SELF']);

// Define mapa de páginas e suas classes "ativo"
$mapa_paginas = [
    'dashboard.php' => 'home',
    'dashboardadm.php' => 'home',
    'cadpraga.php' => 'cadpraga',
    'cadsurto.php' => 'cadsurto',
    'filtros_pragas.php' => 'filtros_pragas',
    'filtros_usuarios.php' => 'filtros_usuarios',
    'feedback.php' => 'feedback',
    'perfil.php' => 'perfil',
];

// Obtém a página ativa
$pagina_ativa = $mapa_paginas[$arquivo_atual] ?? null;
?>

<!-- Overlay para fechar menu no mobile -->
<div class="sidebar-overlay" id="sidebar-overlay" aria-hidden="true"></div>

<!-- Botão hamburger (visível apenas no mobile) -->
<button type="button" class="sidebar-toggle" id="btn-exp" aria-label="Abrir menu">
    <span class="hamburger-line"></span>
    <span class="hamburger-line"></span>
    <span class="hamburger-line"></span>
</button>

<!-- Sidebar (Menu Lateral) -->
<aside class="sidebar" id="sidebar">
    <div class="logo">
        <a href="<?= $isAdmin ? '/SMCPA/paginas/dashboard/dashboardadm.php' : '/SMCPA/paginas/dashboard/dashboard.php'; ?>">
            <img src="/SMCPA/imgs/logotrbf.png" alt="SMCPA Logo">
        </a>
    </div>

    <nav class="menu-lateral">
        <ul>
            <li class="item-menu <?= ($pagina_ativa === 'home') ? 'ativo' : ''; ?>">
                <a href="<?= $isAdmin ? '/SMCPA/paginas/dashboard/dashboardadm.php' : '/SMCPA/paginas/dashboard/dashboard.php'; ?>">
                    <span class="icon"><i class="fa-solid fa-home"></i></span>
                    <span class="txt-link">Home</span>
                </a>
            </li>
            <li class="item-menu <?= ($pagina_ativa === 'cadpraga') ? 'ativo' : ''; ?>">
                <a href="/SMCPA/paginas/cadastro/cadpraga.php">
                    <span class="icon"><i class="bi bi-columns-gap"></i></span>
                    <span class="txt-link">Cadastrar Pragas</span>
                </a>
            </li>
            <li class="item-menu <?= ($pagina_ativa === 'cadsurto') ? 'ativo' : ''; ?>">
                <a href="/SMCPA/paginas/cadastro/cadsurto.php">
                    <span class="icon"><i class="bi bi-exclamation-triangle"></i></span>
                    <span class="txt-link">Cadastrar Surtos</span>
                </a>
            </li>
            <li class="item-menu <?= ($pagina_ativa === 'filtros_pragas') ? 'ativo' : ''; ?>">
                <a href="/SMCPA/paginas/dashboard/filtros_pragas.php">
                    <span class="icon"><i class="bi bi-funnel"></i></span>
                    <span class="txt-link">Filtros de Pragas</span>
                </a>
            </li>
            <?php if ($isAdmin): ?>
            <li class="item-menu <?= ($pagina_ativa === 'filtros_usuarios') ? 'ativo' : ''; ?>">
                <a href="/SMCPA/paginas/dashboard/filtros_usuarios.php">
                    <span class="icon"><i class="bi bi-people"></i></span>
                    <span class="txt-link">Filtros de Usuários</span>
                </a>
            </li>
            <?php endif; ?>
            <li class="item-menu <?= ($pagina_ativa === 'feedback') ? 'ativo' : ''; ?>">
                <a href="/SMCPA/paginas/dashboard/feedback.php">
                    <span class="icon"><i class="bi bi-chat-dots"></i></span>
                    <span class="txt-link">Feedback</span>
                </a>
            </li>
            <li class="item-menu <?= ($pagina_ativa === 'perfil') ? 'ativo' : ''; ?>">
                <a href="/SMCPA/paginas/dashboard/perfil.php">
                    <span class="icon"><i class="bi bi-person-lines-fill"></i></span>
                    <span class="txt-link">Conta</span>
                </a>
            </li>
            <li class="item-menu">
                <a href="/SMCPA/paginas/login/logout.php">
                    <span class="icon"><i class="bi bi-box-arrow-right"></i></span>
                    <span class="txt-link">Sair</span>
                </a>
            </li>
        </ul>
    </nav>
</aside>
