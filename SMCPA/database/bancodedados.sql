-- Trabalho Interdisciplinar – Banco de Dados
-- Projeto: SMCPA – Sistema de Monitoramento de Controles e Pragas Agrícolas

CREATE DATABASE IF NOT EXISTS Sistema;
USE Sistema;

-- =========================
-- TABELA: Usuarios
-- =========================
CREATE TABLE Usuarios (
	ID INT NOT NULL AUTO_INCREMENT,
	usuario VARCHAR(100) NOT NULL,
	senha VARCHAR(255),
	Email VARCHAR(100) UNIQUE NOT NULL,
	Data_Cadastro DATE DEFAULT (CURRENT_DATE),
	Imagem VARCHAR(255) DEFAULT NULL,
	is_admin TINYINT(1) DEFAULT 0,
	localizacao VARCHAR(255),
	PRIMARY KEY (ID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- =========================
-- TABELA: Administrador
-- =========================
CREATE TABLE Administrador (
	ID INT NOT NULL AUTO_INCREMENT,
	usuario VARCHAR(100) NOT NULL,
	senha VARCHAR(255),
	Data_Cadastro DATE DEFAULT (CURRENT_DATE),
	PRIMARY KEY (ID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- =========================
-- TABELA: Feedback
-- Inclui todas as alterações: avaliação por estrelas (DECIMAL 1-5), questionário de usabilidade,
-- dados do autor quando excluído (Autor_Nome, Autor_Email, etc.), Usuario NULL, FK ON DELETE SET NULL
-- Para migração de bancos antigos, use: migrate_feedback_legado.sql
-- =========================
CREATE TABLE Feedback (
	ID INT NOT NULL AUTO_INCREMENT,
	Mensagem TEXT NOT NULL,
	Usuario INT NULL COMMENT 'ID do usuário; NULL se autor foi excluído (dados em Autor_*)',
	Data_Envio DATE DEFAULT (CURRENT_DATE),
	Avaliacao_Estrelas DECIMAL(3,2) NULL COMMENT 'Nota 1 a 5 (pode ser decimal)',
	Usabilidade_Facilidade TINYINT NULL COMMENT 'O sistema é fácil de usar? 1-5',
	Usabilidade_Organizacao TINYINT NULL COMMENT 'As informações estão organizadas? 1-5',
	Usabilidade_Registro TINYINT NULL COMMENT 'O processo de registro é simples? 1-5',
	Usabilidade_Relatorio TINYINT NULL COMMENT 'O relatório facilita a análise? 1-5',
	Usabilidade_Decisao TINYINT NULL COMMENT 'O sistema auxilia na decisão? 1-5',
	Usabilidade_Usaria TINYINT NULL COMMENT 'Utilizaria em situação real? 1-5',
	Autor_Nome VARCHAR(255) NULL COMMENT 'Nome do autor (preenchido quando usuário é excluído)',
	Autor_Email VARCHAR(255) NULL COMMENT 'Email do autor (preenchido quando usuário é excluído)',
	Autor_Localizacao VARCHAR(255) NULL COMMENT 'Localização do autor (preenchido quando usuário é excluído)',
	Autor_Data_Cadastro DATE NULL COMMENT 'Data de cadastro do autor (preenchido quando usuário é excluído)',
	PRIMARY KEY (ID),
	CONSTRAINT Feedback_ibfk_1 FOREIGN KEY (Usuario) REFERENCES Usuarios(ID) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- =========================
-- TABELA: Tutorial
-- =========================
CREATE TABLE Tutorial (
	ID INT NOT NULL AUTO_INCREMENT,
	Titulo VARCHAR(100) NOT NULL,
	Conteudo TEXT NOT NULL,
	Data_Criacao DATE DEFAULT (CURRENT_DATE),
	PRIMARY KEY (ID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- =========================
-- TABELA: Pragas_Surtos
-- =========================
CREATE TABLE Pragas_Surtos (
	ID INT NOT NULL AUTO_INCREMENT,
	Nome VARCHAR(100) NOT NULL,
	Planta_Hospedeira VARCHAR(100) NOT NULL,
	Descricao TEXT,
	Imagem_Not_Null VARCHAR(255) DEFAULT NULL,
	ID_Praga VARCHAR(50) DEFAULT NULL,
	ID_Usuario INT NOT NULL,
	Localidade VARCHAR(100) DEFAULT NULL,
	Data_Aparicao DATE DEFAULT NULL,
	Observacoes TEXT,
	PRIMARY KEY (ID),
	CONSTRAINT fk_ID_Usuario
		FOREIGN KEY (ID_Usuario) REFERENCES Usuarios(ID)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- =========================
-- TABELA: Imagem
-- =========================
CREATE TABLE Imagem (
	ID INT NOT NULL AUTO_INCREMENT,
	ID_Surto INT NOT NULL,
	URL_Imagem VARCHAR(255) NOT NULL,
	Descricao TEXT,
	fk_Pragas_Surtos_ID INT,
	PRIMARY KEY (ID),
	FOREIGN KEY (fk_Pragas_Surtos_ID) REFERENCES Pragas_Surtos(ID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- =========================
-- TABELA: Recomendacao
-- =========================
CREATE TABLE Recomendacao (
	ID INT NOT NULL AUTO_INCREMENT,
	fk_Praga INT NOT NULL,
	Tipo VARCHAR(100),
	Descricao TEXT,
	Arquivo_Anexo VARCHAR(255),
	PRIMARY KEY (ID),
	FOREIGN KEY (fk_Praga) REFERENCES Pragas_Surtos(ID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- =========================
-- TABELA: Conselho_Manejo
-- =========================
CREATE TABLE Conselho_Manejo (
	fk_Recomendacao_ID INT NOT NULL,
	fk_Pragas_Surtos_ID INT NOT NULL,
	PRIMARY KEY (fk_Recomendacao_ID, fk_Pragas_Surtos_ID),
	FOREIGN KEY (fk_Recomendacao_ID) REFERENCES Recomendacao(ID),
	FOREIGN KEY (fk_Pragas_Surtos_ID) REFERENCES Pragas_Surtos(ID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- =========================
-- TABELA: Historico
-- =========================
CREATE TABLE Historico (
	ID INT NOT NULL AUTO_INCREMENT,
	ID_Usuario INT NOT NULL,
	Data_Modificacao DATE DEFAULT (CURRENT_DATE),
	Dados_Antigos TEXT,
	Dados_Novos TEXT,
	PRIMARY KEY (ID),
	FOREIGN KEY (ID_Usuario) REFERENCES Usuarios(ID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- =========================
-- TABELA: Registra_Historico_Pragas
-- =========================
CREATE TABLE Registra_Historico_Pragas (
	fk_Historico_ID INT NOT NULL,
	fk_Pragas_Surtos_ID INT NOT NULL,
	PRIMARY KEY (fk_Historico_ID, fk_Pragas_Surtos_ID),
	FOREIGN KEY (fk_Historico_ID) REFERENCES Historico(ID),
	FOREIGN KEY (fk_Pragas_Surtos_ID) REFERENCES Pragas_Surtos(ID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- =========================
-- TABELA: Alerta
-- =========================
CREATE TABLE Alerta (
	ID INT NOT NULL AUTO_INCREMENT,
	ID_Surto INT NOT NULL,
	Data_Geracao DATE DEFAULT (CURRENT_DATE),
	Mensagem TEXT,
	fk_Pragas_Surtos_ID INT,
	PRIMARY KEY (ID),
	FOREIGN KEY (fk_Pragas_Surtos_ID) REFERENCES Pragas_Surtos(ID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- =========================
-- TABELA: Cadastra_Surtos_Admin
-- =========================
CREATE TABLE Cadastra_Surtos_Admin (
	fk_Surto_ID INT NOT NULL,
	fk_Administrador_ID INT NOT NULL,
	fk_Usuarios_ID INT NOT NULL,
	PRIMARY KEY (fk_Surto_ID, fk_Administrador_ID, fk_Usuarios_ID),
	FOREIGN KEY (fk_Surto_ID) REFERENCES Pragas_Surtos(ID),
	FOREIGN KEY (fk_Administrador_ID) REFERENCES Administrador(ID),
	FOREIGN KEY (fk_Usuarios_ID) REFERENCES Usuarios(ID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- =========================
-- TABELA: Avalia
-- =========================
CREATE TABLE Avalia (
	fk_Feedback_ID INT NOT NULL,
	fk_Usuarios_ID INT NOT NULL,
	PRIMARY KEY (fk_Feedback_ID, fk_Usuarios_ID),
	FOREIGN KEY (fk_Feedback_ID) REFERENCES Feedback(ID),
	FOREIGN KEY (fk_Usuarios_ID) REFERENCES Usuarios(ID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- =========================
-- TABELA: Comenta
-- =========================
CREATE TABLE Comenta (
	fk_Usuarios_ID INT NOT NULL,
	fk_Feedback_ID INT NOT NULL,
	PRIMARY KEY (fk_Usuarios_ID, fk_Feedback_ID),
	FOREIGN KEY (fk_Usuarios_ID) REFERENCES Usuarios(ID),
	FOREIGN KEY (fk_Feedback_ID) REFERENCES Feedback(ID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- =========================
-- TABELA: Visualiza_Tutorial
-- =========================
CREATE TABLE Visualiza_Tutorial (
	fk_Usuarios_ID INT NOT NULL,
	fk_Tutorial_ID INT NOT NULL,
	PRIMARY KEY (fk_Usuarios_ID, fk_Tutorial_ID),
	FOREIGN KEY (fk_Usuarios_ID) REFERENCES Usuarios(ID),
	FOREIGN KEY (fk_Tutorial_ID) REFERENCES Tutorial(ID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- =========================
-- TABELA: Visualiza_Historico
-- =========================
CREATE TABLE Visualiza_Historico (
	fk_Historico_ID INT NOT NULL,
	fk_Usuarios_ID INT NOT NULL,
	PRIMARY KEY (fk_Historico_ID, fk_Usuarios_ID),
	FOREIGN KEY (fk_Historico_ID) REFERENCES Historico(ID),
	FOREIGN KEY (fk_Usuarios_ID) REFERENCES Usuarios(ID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- =========================
-- TABELA: Avisa
-- =========================
CREATE TABLE Avisa (
	fk_Usuarios_ID INT NOT NULL,
	fk_Alerta_ID INT NOT NULL,
	PRIMARY KEY (fk_Usuarios_ID, fk_Alerta_ID),
	FOREIGN KEY (fk_Usuarios_ID) REFERENCES Usuarios(ID),
	FOREIGN KEY (fk_Alerta_ID) REFERENCES Alerta(ID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- =========================
-- TABELA: recuperacao_senha (tokens para "Esqueci minha senha")
-- =========================
CREATE TABLE recuperacao_senha (
	ID INT NOT NULL AUTO_INCREMENT,
	ID_Usuario INT NOT NULL,
	token VARCHAR(64) NOT NULL,
	expira_em DATETIME NOT NULL,
	usado TINYINT NOT NULL DEFAULT 0,
	criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (ID),
	UNIQUE KEY uk_token (token),
	KEY idx_expira (expira_em),
	KEY idx_usado (usado),
	FOREIGN KEY (ID_Usuario) REFERENCES Usuarios(ID) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


