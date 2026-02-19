-- Permite nota quebrada (ex.: 3,4). Execute depois de alter_feedback_usabilidade.sql.

USE Sistema;

ALTER TABLE Feedback MODIFY COLUMN Avaliacao_Estrelas DECIMAL(3,2) NULL COMMENT 'Nota 1 a 5 (pode ser decimal)';
