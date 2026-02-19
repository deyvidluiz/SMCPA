-- Migração: Avaliação por estrelas e questionário de usabilidade (Feedback)
-- Execute este script no banco Sistema para adicionar as novas colunas.

USE Sistema;

-- Execute cada ADD COLUMN separadamente. Se a coluna já existir, ignore o erro.

ALTER TABLE Feedback ADD COLUMN Avaliacao_Estrelas TINYINT NULL COMMENT 'Avaliação geral 1-5 estrelas';
ALTER TABLE Feedback ADD COLUMN Usabilidade_Facilidade TINYINT NULL COMMENT 'O sistema é fácil de usar? 1-5';
ALTER TABLE Feedback ADD COLUMN Usabilidade_Organizacao TINYINT NULL COMMENT 'As informações estão organizadas? 1-5';
ALTER TABLE Feedback ADD COLUMN Usabilidade_Registro TINYINT NULL COMMENT 'O processo de registro é simples? 1-5';
ALTER TABLE Feedback ADD COLUMN Usabilidade_Relatorio TINYINT NULL COMMENT 'O relatório facilita a análise? 1-5';
ALTER TABLE Feedback ADD COLUMN Usabilidade_Decisao TINYINT NULL COMMENT 'O sistema auxilia na decisão? 1-5';
ALTER TABLE Feedback ADD COLUMN Usabilidade_Usaria TINYINT NULL COMMENT 'Utilizaria em situação real? 1-5';
