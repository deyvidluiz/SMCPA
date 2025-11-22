<?php
// Inicia a sessão para manter o login
session_start();

// Verifica se o usuário está logado
if (!isset($_SESSION['usuario_id']) && !isset($_SESSION['id']) && !isset($_SESSION['logado'])) {
    // Se não estiver logado, redireciona para login
    header("Location: /SMCPA/login/login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="shortcut icon" href="/SMCPA/imgs/favicon.ico" type="image/x-icon">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <link rel="stylesheet" href="/SMCPA/paginas/dashboard/dashboard.css">
  <title>Dashboard - SMCPA</title>
</head>
<body>
  <div class="dashboard-container">
    <!-- Sidebar -->
    <aside class="sidebar">
      <div class="logo">
        <a href="#">
          <img src="/SMCPA/imgs/logotrbf.png" alt="Logo">
        </a>
      </div>

      <nav class="menu-lateral">
        <ul>
          <li class="item-menu ativo">
            <a href="#">
              <span class="icon"><i class="fa-solid fa-home"></i></span>
              <span class="txt-link">Home</span>
            </a>
          </li>
          <li class="item-menu">
            <a href="/SMCPA/paginas/dashboard/dashboardadm.php">
              <span class="icon"><i class="bi bi-columns-gap"></i></span>
              <span class="txt-link">Dashboard</span>
            </a>
          </li>
          <li class="item-menu">
            <a href="/SMCPA/paginas/cadastro/cadpraga.php">
              <span class="icon"><i class="bi bi-calendar-range"></i></span>
              <span class="txt-link">Agenda</span>
            </a>
          </li>
          <li class="item-menu">
            <a href="/SMCPA/paginas/inicial/inicial.html">
              <span class="icon"><i class="bi bi-gear"></i></span>
              <span class="txt-link">Configurações</span>
            </a>
          </li>
          <li class="item-menu">
            <a href="/SMCPA/paginas/login/perfil.php">
              <span class="icon"><i class="bi bi-person-lines-fill"></i></span>
              <span class="txt-link">Conta</span>
            </a>
          </li>
        </ul>
      </nav>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
      <header class="topbar">
        <div class="left">
          <button class="btn btn-outline-light">
            <i class="fa-solid fa-bars"></i>
          </button>
        </div>
        <div class="right">
          <button class="btn btn-outline-light position-relative">
            <i class="fa-solid fa-bell"></i>
            <span class="badge bg-danger position-absolute top-0 start-100 translate-middle">1</span>
          </button>
          <a href="./perfil.php" class="btn btn-outline-light" style="text-decoration: none; color: inherit;">
            <i class="fa-solid fa-user"></i> Perfil
          </a>
          <button class="btn btn-outline-light">
            <i class="fa-solid fa-book"></i> Tutoriais
          </button>
        </div>
      </header>

      <section class="content">
        <div class="dashboard-grid">
          <!-- Bloco Vendas Hoje -->
          <div class="dashboard-item blue-item" id="vendas-hoje">
            <h1>Pragas</h1>  
          </div>

          <!-- Bloco Vendas Periódicas -->
          <div class="dashboard-item orange-item" id="vendas-periodicas">
            <h1>Surtos</h1> 
          </div>

          <!-- Bloco Receber Hoje -->
          <div class="dashboard-item green-item" id="receber-hoje">
            <h1>recomendações</h1>
          </div>

          <!-- Bloco Tabela de Vendas -->
          <div class="dashboard-item small-item" id="tabela-vendas">
            <h5>Relatórios</h5>
            <table class="table">
              <p>Lorem ipsum dolor sit amet consectetur adipisicing elit. Rerum nulla accusamus assumenda velit excepturi veniam debitis ipsum quod iure ab, tempore esse voluptatem eius fuga veritatis itaque perferendis beatae. Perspiciatis.</p>
            </table>
          </div>

          <!-- Bloco Gráfico de Vendas -->
          <div class="dashboard-item large-item" id="grafico-vendas">
            <h5>Estatistica</h5>
            <canvas id="vendas-diarias"></canvas>
          </div>
        </div>
      </section>
    </main>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script src="/SMCPA/js/menu.js"></script>
  <script>
    // Dados do gráfico de linha
    const data = {
      labels: ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul'],
      datasets: [{
        label: 'Estatisticas Mensais',
        data: [120, 150, 180, 170, 160, 190, 220],
        borderColor: '#007bff',
        backgroundColor: 'rgba(0, 123, 255, 0.2)',
        fill: true,
        tension: 0.1
      }]
    };

    const options = {
      responsive: true,
      plugins: {
        legend: { position: 'top' },
        tooltip: { enabled: true }
      },
      scales: {
        y: { beginAtZero: true }
      }
    };

    const ctx = document.getElementById('vendas-diarias').getContext('2d');
    const vendasDiariasChart = new Chart(ctx, {
      type: 'line',
      data: data,
      options: options
    });
  </script>
</body>
</html>
