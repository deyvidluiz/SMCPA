-- ============================================================
-- POPULAÇÃO INICIAL DO SISTEMA SMCPA
-- Usuários: Taylor (admin), Isadora, Deyvid
-- Pragas / Surtos / Alertas
-- ============================================================

USE Sistema;

-- Se ainda não existir, descomente as linhas abaixo:
-- ALTER TABLE Usuarios ADD COLUMN is_admin TINYINT(1) DEFAULT 0;
-- ALTER TABLE Usuarios ADD COLUMN localizacao VARCHAR(100) DEFAULT NULL;

-------------------------------------------------------------
-- 1) CRIA O PRIMEIRO ADMIN (TAYLOR) NO SISTEMA
-------------------------------------------------------------
INSERT INTO Usuarios (usuario, senha, Email, is_admin, localizacao)
VALUES (
    'Taylor Swift',
    '$2y$12$gzosT6sMoL29t3b0jed7d.YEqT7Di9ygnf5XtuqrQLXbwtQQ/OVXq', -- senha: 123
    'taylor@gmail.com',
    1,
    NULL
); 

INSERT INTO Administrador (usuario, senha)
VALUES (
    'Taylor Swift',
    '$2y$12$gzosT6sMoL29t3b0jed7d.YEqT7Di9ygnf5XtuqrQLXbwtQQ/OVXq' -- senha: 123
);

-------------------------------------------------------------
-- 2) CRIA A USUÁRIA ISADORA
-------------------------------------------------------------
INSERT INTO Usuarios (usuario, senha, Email, is_admin, localizacao)
VALUES (
    'Isadora',
    '$2y$12$3K9W.4y99f2TKeM4.syQEe7R0L4ptP./FDlUVs0GZtUvIuHrPgnxC', -- senha: Isa
    'isadora.souza@gmail.com',
    0,                         -- não é admin
    'Curitiba – PR'
);

-- Guarda o ID da Isadora em uma variável
SET @id_isadora := (SELECT ID FROM Usuarios WHERE Email = 'isadora.souza@gmail.com');

-------------------------------------------------------------
-- 3) SURTOS DE PRAGAS NA REGIÃO DA ISADORA (Curitiba – PR)
-------------------------------------------------------------
INSERT INTO pragas_surtos
(Nome, Planta_Hospedeira, Descricao, Imagem_Not_Null, ID_Praga, ID_Usuario, Localidade, Data_Aparicao, Observacoes)
VALUES
-- SURTO 1
(
    'Pulgão-Verde em Hortaliças (Myzus persicae)',
    'Alface, couve e outras folhosas',
    'O pulgão-verde é um inseto sugador altamente destrutivo em cultivos folhosos. Ele se instala na face inferior
    das folhas, formando colônias densas que provocam amarelecimento, enrolamento e redução do crescimento.
    Além dos danos diretos, pode transmitir viroses importantes, comprometendo seriamente a produção.
    Em Curitiba, a combinação de clima ameno e umidade favorece sua rápida multiplicação.',
    'pulgao_verde_hortalicas.jpg',
    'IS001',
    @id_isadora,
    'Curitiba – PR',
    '2025-02-15',
    'Foram observadas colônias em folhas jovens e presença de fumagina. Recomendado iniciar monitoramento com maior frequência
    e avaliar medidas de controle biológico antes do uso de inseticidas.'
),

-- SURTO 2
(
    'Lesma-Cinza em Hortas Urbanas (Deroceras reticulatum)',
    'Hortaliças rasteiras e mudas jovens',
    'A lesma-cinza é uma praga comum em ambientes úmidos e sombreados, como hortas urbanas de Curitiba.
    Alimenta-se de brotos, raízes expostas e folhas tenras, deixando perfurações irregulares e trilhas de muco.
    Em surtos severos, pode destruir completamente canteiros recém-formados, exigindo ações imediatas para controle.',
    'lesma_cinza_horta.jpg',
    'IS002',
    @id_isadora,
    'Curitiba – PR',
    '2025-02-18',
    'Relatos de danos em mudas transplantadas recentemente. Recomendado uso de barreiras físicas, redução da umidade
    e remoção de abrigos naturais ao redor dos canteiros.'
);

-------------------------------------------------------------
-- 4) ALERTAS PARA OS SURTOS DA ISADORA
-------------------------------------------------------------
INSERT INTO Alerta (ID_Surto, Data_Geracao, Mensagem, fk_Pragas_Surtos_ID)
VALUES
(
    (SELECT ID FROM pragas_surtos WHERE ID_Praga = 'IS001' AND ID_Usuario = @id_isadora),
    CURRENT_DATE,
    'Alerta: Surto de Pulgão-Verde em hortaliças na região de Curitiba – PR. Intensifique o monitoramento semanal e avalie ações de controle.',
    (SELECT ID FROM pragas_surtos WHERE ID_Praga = 'IS001' AND ID_Usuario = @id_isadora)
),
(
    (SELECT ID FROM pragas_surtos WHERE ID_Praga = 'IS002' AND ID_Usuario = @id_isadora),
    CURRENT_DATE,
    'Alerta: Lesma-Cinza ativa em hortas urbanas de Curitiba – PR. Atenção redobrada em mudas jovens e canteiros úmidos.',
    (SELECT ID FROM pragas_surtos WHERE ID_Praga = 'IS002' AND ID_Usuario = @id_isadora)
);

-------------------------------------------------------------
-- 5) CRIA O USUÁRIO DEYVID
-------------------------------------------------------------
INSERT INTO Usuarios (usuario, senha, Email)
VALUES (
    'Deyvid',
    '$2y$12$9.eQ7jaWJS1ZuuiNyW3kBOiecp/IXYpmMLtX4RiLwsteX4QJTUCpG', -- hash da senha
    'deyvidmartins692@gmail.com'
);

SET @usuario_id := LAST_INSERT_ID();

-------------------------------------------------------------
-- 6) 10 REGISTROS DE PRAGAS / SURTOS PARA DEYVID
-------------------------------------------------------------
INSERT INTO pragas_surtos
(Nome, Planta_Hospedeira, Descricao, Imagem_Not_Null, ID_Praga, ID_Usuario, Localidade, Data_Aparicao, Observacoes)
VALUES
-- 1
('Lagarta-do-Cartucho (Spodoptera frugiperda)',
 'Milho',
 'A lagarta-do-cartucho é uma das principais pragas do milho, atacando plantas desde os estágios iniciais até a fase de pendoamento. O inseto se alimenta intensamente do cartucho e das folhas jovens, causando destruição das estruturas internas. Em infestações severas, as plantas apresentam perfurações extensas, crescimento retardado e redução significativa do potencial produtivo. O ciclo rápido da praga favorece sua dispersão em grandes áreas agrícolas, principalmente em regiões com plantios contínuos.',
 'lagarta_cartucho.jpg',
 'LG001',
 @usuario_id,
 'Centro-Oeste do Brasil',
 '2025-01-10',
 'Foi observada presença elevada de ovos em áreas de cultivo contínuo. Recomenda-se monitoramento semanal e uso de controle biológico baseado em Bacillus thuringiensis como estratégia complementar.'),

-- 2
('Percevejo-Marrom (Euschistus heros)',
 'Soja',
 'O percevejo-marrom é um inseto sugador que ataca principalmente a fase reprodutiva da soja, comprometendo o enchimento dos grãos. Os danos incluem perda de peso, redução da qualidade fisiológica das sementes e aborto de vagens. A praga apresenta alta mobilidade e pode sobreviver em plantas voluntárias, o que facilita sua permanência entre safras. Infestações acima do nível de dano econômico podem gerar perdas superiores a 30% no rendimento.',
 'percevejo_marrom.jpg',
 'PM002',
 @usuario_id,
 'Paraná e Rio Grande do Sul',
 '2025-01-18',
 'Alta incidência devido ao clima quente no início do ciclo reprodutivo. Produtores relatam falhas na formação de grãos e odor característico ao esmagar os insetos.'),

-- 3
('Mosca-Branca (Bemisia tabaci)',
 'Tomate',
 'Inseto sugador altamente polífago que provoca murcha, amarelecimento e enrolamento das folhas. Além dos danos diretos, a mosca-branca é vetor de viroses importantes como o geminivírus. Sua capacidade de multiplicação é elevada, especialmente em ambientes de clima quente e seco. Estufas mal ventiladas favorecem a explosão populacional, tornando o manejo mais complexo.',
 'mosca_branca.jpg',
 'MB003',
 @usuario_id,
 'São Paulo e Minas Gerais',
 '2024-12-22',
 'Observada forte presença de fumagina devido ao excesso de honeydew. Recomendado manejo integrado com eliminação de plantas hospedeiras alternativas.'),

-- 4
('Ácaro-Rajado (Tetranychus urticae)',
 'Morango',
 'O ácaro-rajado causa dano raspador nas folhas, levando ao aspecto bronzeado e queda precoce. Sua reprodução é extremamente acelerada em ambientes secos, podendo dobrar a população em poucos dias. Em cultivos protegidos, sua infestação pode comprometer totalmente a produção, causando redução intensa da fotossíntese e murcha generalizada das plantas.',
 'acaro_rajado.jpg',
 'AR004',
 @usuario_id,
 'Serra Gaúcha',
 '2024-10-05',
 'Identificada alta densidade de ninfas na face inferior das folhas. Controle biológico com ácaros predadores recomendado.'),

-- 5
('Cigarrinha-do-Milho (Dalbulus maidis)',
 'Milho',
 'Inseto transmissor dos enfezamentos vermelho e pálido, doenças capazes de reduzir drasticamente a produtividade. A cigarrinha se alimenta do floema e injeta toxinas, diminuindo o vigor das plantas. Sua disseminação rápida ocorre devido ao hábito migratório e à presença de milho tiguera. Em anos de clima quente, a praga alcança níveis críticos, exigindo manejo conjunto e monitoramento constante.',
 'cigarrinha_milho.jpg',
 'CM005',
 @usuario_id,
 'Goiás e Mato Grosso',
 '2024-12-12',
 'Sintomas de enfezamento observados com maior intensidade em plantas de até 45 dias. Produtores relatam redução significativa na altura das plantas.'),

-- 6
('Broca-do-Café (Hypothenemus hampei)',
 'Café',
 'Inseto perfurador que se alimenta diretamente das sementes de café, reduzindo qualidade industrial e valor comercial. Cada fêmea pode infestar múltiplos frutos, abrindo galerias que facilitam entrada de fungos. A praga é considerada uma das mais difíceis de manejar, exigindo colheita criteriosa e monitoramento intensivo.',
 'broca_cafe.jpg',
 'BC006',
 @usuario_id,
 'Sul de Minas e Espírito Santo',
 '2024-09-28',
 'Níveis elevados próximos ao período pré-colheita. Armadilhas com atrativos alcoólicos mostraram boa eficiência no monitoramento.'),

-- 7
('Ferrugem-Asiática da Soja (Phakopsora pachyrhizi)',
 'Soja',
 'Doença fúngica agressiva que causa desfolha precoce e perdas produtivas severas. As lesões apresentam coloração castanho-escura com pústulas abundantes na face inferior das folhas. Sua disseminação ocorre pelo vento e pode atingir longas distâncias. O manejo inadequado pode resultar em perdas acima de 70%.',
 'ferrugem_asiatica.jpg',
 'FA007',
 @usuario_id,
 'Mato Grosso do Sul e Paraná',
 '2024-11-10',
 'Primeiros focos detectados após fechamento das entrelinhas. Recomendado uso preventivo de fungicidas multissítio.'),

-- 8
('Cochonilha-da-Videira (Planococcus ficus)',
 'Uva',
 'Inseto sugador que se fixa em ramos, folhas e cachos, excretando substâncias açucaradas que favorecem o surgimento de fumagina. A praga compromete a qualidade dos frutos e dificulta a fotossíntese. Em vinhedos com condução densa, o inseto encontra condições ideais para multiplicação.',
 'cochonilha_videira.jpg',
 'CV008',
 @usuario_id,
 'Vale dos Vinhedos – RS',
 '2024-08-14',
 'Alta infestação encontrada durante poda verde. Remoção de ramos comprometidos recomendada.'),

-- 9
('Trips-do-Algodoeiro (Frankliniella schultzei)',
 'Algodão',
 'Inseto minúsculo que raspa a epiderme das folhas jovens, causando prateamento, deformações e atraso no desenvolvimento inicial. A ocorrência é mais severa em períodos de estiagem. Plantas atacadas na fase inicial tendem a apresentar menor capacidade de ramificação e redução na produtividade final.',
 'trips_algodao.jpg',
 'TA009',
 @usuario_id,
 'Bahia e Tocantins',
 '2024-12-03',
 'Ataques intensos nas primeiras semanas após emergência. Tratamento de sementes mostrou eficácia parcial.'),

-- 10
('Pulgão-da-Soja (Aphis glycines)',
 'Soja',
 'Inseto sugador que se concentra no terço superior das plantas, provocando enrolamento das folhas, redução do vigor e transmissão de vírus. Sua reprodução é acelerada em clima ameno e seco, podendo gerar explosões populacionais em poucos dias. Plantas muito infestadas apresentam queda no potencial produtivo e atraso no ciclo.',
 'pulgão_soja.jpg',
 'PS010',
 @usuario_id,
 'Rio Grande do Sul',
 '2025-01-05',
 'Populações elevadas detectadas em áreas sem controle biológico. Recomenda-se preservação de inimigos naturais como joaninhas e crisopídeos.');
