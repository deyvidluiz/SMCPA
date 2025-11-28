<?php
// Verifica se o usuário está logado (opcional, tutorial pode ser público)
session_start();
$isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1;
$usuarioID = $_SESSION['usuario_id'] ?? $_SESSION['id'] ?? null;
$logado = isset($usuarioID);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>Tutorial - SMCPA</title>
    <link rel="shortcut icon" href="/SMCPA/imgs/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="tutorial.css">
</head>
<body>
    <?php if ($logado): ?>
        <a href="<?= $isAdmin ? '../dashboard/dashboardadm.php' : '../dashboard/dashboard.php'; ?>" class="back-button">
            <i class="bi bi-arrow-left"></i> Voltar
        </a>
    <?php else: ?>
        <a href="../inicial/inicial.html" class="back-button">
            <i class="bi bi-arrow-left"></i> Voltar
        </a>
    <?php endif; ?>

    <div class="tutorial-container">
        <div class="tutorial-header">
            <h1><i class="bi bi-book"></i> Tutorial do SMCPA</h1>
            <p>Sistema de Monitoramento e Controle de Pragas Agrícolas</p>
        </div>

        <div class="tutorial-nav">
            <div class="nav-buttons">
                <a href="#sobre" class="nav-btn">Sobre o Projeto</a>
                <a href="#cadastro" class="nav-btn">Cadastro</a>
                <a href="#dashboard" class="nav-btn">Dashboard</a>
                <a href="#pragas" class="nav-btn">Pragas</a>
                <a href="#relatorios" class="nav-btn">Relatórios</a>
                <a href="#admin" class="nav-btn">Funções Admin</a>
            </div>
        </div>

        <div class="tutorial-content">
            <!-- Seção Sobre o Projeto -->
            <section id="sobre" class="section">
                <h2><i class="bi bi-info-circle"></i> Sobre o SMCPA</h2>
                <p>
                    O <strong>SMCPA (Sistema de Monitoramento e Controle de Pragas Agrícolas)</strong> é uma plataforma 
                    web desenvolvida para auxiliar agricultores e profissionais do agronegócio no monitoramento, 
                    controle e análise de pragas agrícolas em suas lavouras.
                </p>
                <p>
                    O sistema permite o cadastro de pragas, registro de surtos, geração de relatórios detalhados, 
                    visualização de estatísticas e recomendações personalizadas para cada tipo de praga identificada.
                </p>
                
                <h3>Objetivos do Sistema</h3>
                <div class="feature-card">
                    <ul>
                        <li><strong>Monitoramento:</strong> Acompanhar a ocorrência de pragas nas propriedades rurais</li>
                        <li><strong>Controle:</strong> Auxiliar na tomada de decisões para controle efetivo de pragas</li>
                        <li><strong>Análise:</strong> Gerar relatórios e estatísticas sobre surtos de pragas</li>
                        <li><strong>Recomendações:</strong> Fornecer orientações específicas para cada tipo de praga</li>
                    </ul>
                </div>
            </section>

            <!-- Seção Cadastro -->
            <section id="cadastro" class="section">
                <h2><i class="bi bi-person-plus"></i> Como se Cadastrar</h2>
                <ol class="step-list">
                    <li>Acesse a página inicial do SMCPA</li>
                    <li>Clique no botão <strong>"Cadastro"</strong> no canto superior direito</li>
                    <li>Preencha todos os campos obrigatórios:
                        <ul style="margin-top: 10px; margin-left: 20px;">
                            <li>Nome completo</li>
                            <li>Email (será usado para login)</li>
                            <li>Senha (mínimo de caracteres conforme política do sistema)</li>
                            <li>Localização/Região</li>
                        </ul>
                    </li>
                    <li>Faça upload de uma foto de perfil (opcional)</li>
                    <li>Clique em <strong>"Cadastrar"</strong></li>
                    <li>Após o cadastro, você será redirecionado para a página de login</li>
                </ol>
                <div class="highlight-box">
                    <strong>Dica:</strong> O primeiro usuário cadastrado no sistema é automaticamente definido como 
                    administrador, tendo acesso a funções exclusivas de gerenciamento.
                </div>
            </section>

            <!-- Seção Dashboard -->
            <section id="dashboard" class="section">
                <h2><i class="bi bi-columns-gap"></i> Dashboard do Usuário</h2>
                <p>
                    O dashboard é a tela principal após o login, onde você pode visualizar e gerenciar todas as 
                    informações relacionadas às pragas da sua propriedade.
                </p>

                <h3>Seções do Dashboard <span class="badge-user">Usuário</span></h3>
                
                <div class="feature-card">
                    <h4><i class="bi bi-bug"></i> Pragas</h4>
                    <p>Visualize todas as pragas que você cadastrou. Cada praga possui:</p>
                    <ul>
                        <li>Nome da praga</li>
                        <li>Data do registro</li>
                        <li>Localização do surto</li>
                        <li>Gravidade</li>
                    </ul>
                    <p style="margin-top: 10px;">
                        <strong>Seletor de Pragas:</strong> Use o dropdown para filtrar e visualizar uma praga específica.
                    </p>
                </div>

                <div class="feature-card">
                    <h4><i class="bi bi-exclamation-triangle"></i> Surtos</h4>
                    <p>
                        Esta seção exibe os surtos da praga selecionada registrados nos últimos 30 dias. 
                        Os dados podem ser da sua região ou de outras propriedades próximas, permitindo um 
                        monitoramento regional amplo.
                    </p>
                    <p><strong>Funcionalidade:</strong> Selecione uma praga no dropdown acima da seção para visualizar 
                    os surtos relacionados a ela.</p>
                </div>

                <div class="feature-card">
                    <h4><i class="bi bi-lightbulb"></i> Recomendações</h4>
                    <p>
                        Receba recomendações personalizadas baseadas na praga selecionada. As recomendações incluem:
                    </p>
                    <ul>
                        <li>Métodos de controle adequados</li>
                        <li>Produtos recomendados</li>
                        <li>Melhores práticas de prevenção</li>
                        <li>Período ideal para tratamento</li>
                    </ul>
                    <p style="margin-top: 10px;">
                        <strong>Nota:</strong> As recomendações são atualizadas automaticamente conforme você seleciona 
                        diferentes pragas.
                    </p>
                </div>

                <div class="feature-card">
                    <h4><i class="bi bi-file-text"></i> Relatórios</h4>
                    <p>
                        Acesse e gere relatórios detalhados sobre as pragas cadastradas. Os relatórios incluem:
                    </p>
                    <ul>
                        <li>Informações completas da praga</li>
                        <li>Histórico de ocorrências</li>
                        <li>Dados estatísticos</li>
                        <li>Gravidade e impactos</li>
                    </ul>
                    <p style="margin-top: 10px;">
                        <strong>Acesso:</strong> Use o botão "Ver Relatório" na seção para gerar um relatório completo 
                        da praga selecionada.
                    </p>
                </div>

                <div class="feature-card">
                    <h4><i class="bi bi-graph-up"></i> Estatísticas (Gráfico)</h4>
                    <p>
                        Visualize a evolução dos surtos através de um gráfico de linha interativo. O gráfico mostra:
                    </p>
                    <ul>
                        <li>Quantidade de surtos ao longo do tempo</li>
                        <li>Tendências de aumento ou diminuição</li>
                        <li>Períodos de maior ocorrência</li>
                    </ul>
                    <p style="margin-top: 10px;">
                        <strong>Como usar:</strong> Selecione uma praga no dropdown da seção para atualizar o gráfico 
                        automaticamente.
                    </p>
                </div>
            </section>

            <!-- Seção Cadastro de Pragas -->
            <section id="pragas" class="section">
                <h2><i class="bi bi-calendar-plus"></i> Cadastrar Pragas</h2>
                <p>
                    O cadastro de pragas é uma funcionalidade essencial do sistema. Permite registrar novas ocorrências 
                    de pragas em sua propriedade.
                </p>

                <h3>Como Cadastrar uma Praga</h3>
                <ol class="step-list">
                    <li>Acesse o menu lateral e clique em <strong>"Cadastrar Pragas"</strong></li>
                    <li>Preencha o formulário com as seguintes informações:
                        <ul style="margin-top: 10px; margin-left: 20px;">
                            <li><strong>Nome da Praga:</strong> Nome científico ou comum da praga</li>
                            <li><strong>Data do Surtos:</strong> Data em que o surto foi identificado</li>
                            <li><strong>Localização:</strong> Região ou área da propriedade afetada</li>
                            <li><strong>Gravidade:</strong> Nível de severidade do surto (baixa, média, alta)</li>
                            <li><strong>Descrição:</strong> Detalhes adicionais sobre o surto</li>
                            <li><strong>Imagem:</strong> Foto da praga ou área afetada (opcional mas recomendado)</li>
                        </ul>
                    </li>
                    <li>Revise todas as informações</li>
                    <li>Clique em <strong>"Cadastrar Praga"</strong></li>
                    <li>O sistema irá registrar a praga e você será redirecionado para o dashboard</li>
                </ol>

                <div class="feature-card">
                    <h4><i class="bi bi-eye"></i> Filtros de Pragas</h4>
                    <p>
                        A página de <strong>"Filtros de Pragas"</strong> permite visualizar todas as pragas cadastradas 
                        no sistema (tanto suas quanto de outros usuários). Você pode:
                    </p>
                    <ul>
                        <li>Pesquisar pragas por nome</li>
                        <li>Visualizar detalhes de cada praga</li>
                        <li>Gerar relatórios completos de qualquer praga</li>
                        <li>Ver estatísticas gerais</li>
                    </ul>
                </div>
            </section>

            <!-- Seção Relatórios -->
            <section id="relatorios" class="section">
                <h2><i class="bi bi-file-earmark-pdf"></i> Relatórios</h2>
                <p>
                    O sistema permite gerar relatórios detalhados sobre qualquer praga cadastrada. Os relatórios são 
                    formatados para impressão e contêm informações completas.
                </p>

                <h3>Como Gerar um Relatório</h3>
                <ol class="step-list">
                    <li>Acesse a seção <strong>"Relatórios"</strong> no dashboard ou vá para <strong>"Filtros de Pragas"</strong></li>
                    <li>Selecione ou pesquise pela praga desejada</li>
                    <li>Clique no botão <strong>"Ver Relatório"</strong></li>
                    <li>O relatório será exibido em uma nova página formatada para impressão</li>
                    <li>Use a função de impressão do navegador para salvar como PDF ou imprimir</li>
                </ol>

                <div class="feature-card">
                    <h4><i class="bi bi-list-check"></i> Informações Incluídas no Relatório</h4>
                    <ul>
                        <li>Dados completos da praga</li>
                        <li>Histórico de ocorrências</li>
                        <li>Localizações afetadas</li>
                        <li>Níveis de gravidade registrados</li>
                        <li>Imagens associadas (se disponíveis)</li>
                        <li>Estatísticas de surtos</li>
                    </ul>
                </div>
            </section>

            <!-- Seção Funções Admin -->
            <section id="admin" class="section">
                <h2><i class="bi bi-shield-lock"></i> Funções do Administrador <span class="badge-admin">Apenas Admin</span></h2>
                <p>
                    Os administradores possuem acesso a funcionalidades adicionais para gerenciar o sistema e os usuários.
                </p>

                <div class="highlight-box">
                    <strong>Atenção:</strong> Apenas usuários com permissões de administrador podem acessar essas funcionalidades.
                </div>

                <div class="feature-card">
                    <h4><i class="bi bi-people"></i> Dashboard Administrativo</h4>
                    <p>
                        O dashboard administrativo permite gerenciar usuários e visualizar todas as pragas cadastradas 
                        no sistema.
                    </p>
                    <ul>
                        <li><strong>Lista de Usuários:</strong> Visualize todos os usuários cadastrados</li>
                        <li><strong>Gerenciar Usuários:</strong> Edite, exclua ou visualize perfis de usuários</li>
                        <li><strong>Pesquisa de Usuários:</strong> Busque usuários por nome ou email</li>
                        <li><strong>Pesquisa de Pragas:</strong> Filtre pragas cadastradas por nome</li>
                    </ul>
                </div>

                <div class="feature-card">
                    <h4><i class="bi bi-shield-plus"></i> Cadastrar Administradores</h4>
                    <p>
                        Os administradores podem criar novos usuários com permissões administrativas através do botão 
                        <strong>"Cadastrar Admin"</strong> localizado na barra superior do dashboard administrativo.
                    </p>
                    <p><strong>Processo:</strong></p>
                    <ul>
                        <li>Clique no botão "Cadastrar Admin"</li>
                        <li>Preencha o formulário com os dados do novo administrador</li>
                        <li>O novo admin terá acesso completo às funções administrativas</li>
                    </ul>
                </div>

                <div class="feature-card">
                    <h4><i class="bi bi-funnel"></i> Filtros de Usuários</h4>
                    <p>
                        A página <strong>"Filtros de Usuários"</strong> permite aos administradores:
                    </p>
                    <ul>
                        <li>Visualizar todos os usuários do sistema</li>
                        <li>Pesquisar usuários por nome ou email</li>
                        <li>Acessar perfis completos de qualquer usuário</li>
                        <li>Gerenciar contas de usuários</li>
                    </ul>
                </div>

                <div class="feature-card">
                    <h4><i class="bi bi-person-gear"></i> Gerenciamento de Usuários</h4>
                    <p>
                        No dashboard administrativo, você pode realizar as seguintes ações:
                    </p>
                    <ul>
                        <li><strong>Visualizar Perfil:</strong> Clique no nome do usuário para ver detalhes completos</li>
                        <li><strong>Redefinir Senha:</strong> Permite redefinir a senha de qualquer usuário</li>
                        <li><strong>Excluir Usuário:</strong> Remove usuário e todos os seus registros de pragas (cuidado!)</li>
                    </ul>
                </div>
            </section>

            <!-- Seção Navegação e Menu -->
            <section id="navegacao" class="section">
                <h2><i class="bi bi-menu-button-wide"></i> Navegação e Menu</h2>
                <p>
                    O menu lateral está presente em todas as páginas do sistema e permite acesso rápido às principais 
                    funcionalidades.
                </p>

                <div class="feature-card">
                    <h4><i class="bi bi-house"></i> Home</h4>
                    <p>Retorna ao dashboard principal (usuário ou admin, dependendo do seu tipo de conta).</p>
                </div>

                <div class="feature-card">
                    <h4><i class="bi bi-calendar-range"></i> Cadastrar Pragas</h4>
                    <p>Acesse o formulário para cadastrar novas ocorrências de pragas.</p>
                </div>

                <div class="feature-card">
                    <h4><i class="bi bi-funnel"></i> Filtros de Pragas</h4>
                    <p>Visualize e pesquise todas as pragas cadastradas no sistema.</p>
                </div>

                <div class="feature-card">
                    <h4><i class="bi bi-people"></i> Filtros de Usuários <span class="badge-admin">Admin</span></h4>
                    <p>Apenas para administradores. Permite gerenciar usuários do sistema.</p>
                </div>

                <div class="feature-card">
                    <h4><i class="bi bi-gear"></i> Configurações</h4>
                    <p>Acesse as configurações gerais do sistema.</p>
                </div>

                <div class="feature-card">
                    <h4><i class="bi bi-person-lines-fill"></i> Conta</h4>
                    <p>Visualize e edite seu perfil, incluindo foto, nome, email e senha.</p>
                </div>

                <div class="feature-card">
                    <h4><i class="bi bi-box-arrow-right"></i> Sair</h4>
                    <p>Faça logout do sistema de forma segura, encerrando sua sessão.</p>
                </div>
            </section>

            <!-- Seção Dicas e Boas Práticas -->
            <section id="dicas" class="section">
                <h2><i class="bi bi-lightbulb-fill"></i> Dicas e Boas Práticas</h2>
                
                <div class="feature-card">
                    <h4><i class="bi bi-check-circle"></i> Cadastro de Pragas</h4>
                    <ul>
                        <li>Sempre forneça informações precisas sobre a localização do surto</li>
                        <li>Adicione fotos quando possível para facilitar identificação</li>
                        <li>Registre surtos imediatamente após identificação</li>
                        <li>Use nomes científicos quando souber para maior precisão</li>
                    </ul>
                </div>

                <div class="feature-card">
                    <h4><i class="bi bi-graph-up-arrow"></i> Monitoramento</h4>
                    <ul>
                        <li>Consulte o gráfico de estatísticas regularmente para identificar tendências</li>
                        <li>Compare surtos da sua região com outras áreas</li>
                        <li>Use as recomendações do sistema como guia, mas sempre consulte profissionais</li>
                        <li>Mantenha um histórico completo registrando todos os surtos</li>
                    </ul>
                </div>

                <div class="feature-card">
                    <h4><i class="bi bi-shield-check"></i> Segurança</h4>
                    <ul>
                        <li>Mantenha sua senha segura e não a compartilhe</li>
                        <li>Faça logout sempre que terminar de usar o sistema</li>
                        <li>Atualize regularmente suas informações de perfil</li>
                        <li>Administradores devem revisar regularmente os usuários do sistema</li>
                    </ul>
                </div>
            </section>

            <!-- Seção Suporte -->
            <section id="suporte" class="section">
                <h2><i class="bi bi-question-circle"></i> Suporte e Contato</h2>
                <p>
                    Se você tiver dúvidas, problemas ou sugestões sobre o sistema SMCPA, entre em contato através 
                    dos canais de suporte disponíveis.
                </p>
                <div class="highlight-box">
                    <strong>Importante:</strong> Este é um sistema de monitoramento agrícola. Para questões técnicas 
                    relacionadas ao controle de pragas, sempre consulte um agrônomo ou profissional especializado.
                </div>
            </section>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Smooth scroll para links de navegação
        document.querySelectorAll('.nav-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const targetId = this.getAttribute('href');
                const targetSection = document.querySelector(targetId);
                if (targetSection) {
                    targetSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            });
        });
    </script>
</body>
</html>

