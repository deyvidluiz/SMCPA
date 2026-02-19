-- Permite excluir usuário que tenha feedback: guarda nome/email/localização/data do autor no Feedback.
-- Assim, ao excluir o usuário, o feedback continua visível para o admin com os dados do autor.
-- Execute uma vez no banco.

-- 1) Colunas para guardar dados do autor quando o usuário for excluído
ALTER TABLE Feedback
  ADD COLUMN Autor_Nome VARCHAR(255) NULL COMMENT 'Nome do autor (preenchido quando usuário é excluído)',
  ADD COLUMN Autor_Email VARCHAR(255) NULL COMMENT 'Email do autor (preenchido quando usuário é excluído)',
  ADD COLUMN Autor_Localizacao VARCHAR(255) NULL COMMENT 'Localização do autor (preenchido quando usuário é excluído)',
  ADD COLUMN Autor_Data_Cadastro DATE NULL COMMENT 'Data de cadastro do autor (preenchido quando usuário é excluído)';

-- 2) Remover a FK que impede excluir usuário
ALTER TABLE Feedback DROP FOREIGN KEY Feedback_ibfk_1;

-- 3) Permitir Usuario nulo (quando autor foi excluído, usamos Autor_*)
ALTER TABLE Feedback MODIFY COLUMN Usuario INT NULL COMMENT 'ID do usuário; NULL se autor foi excluído (dados em Autor_*)';

-- 4) Recriar a FK com ON DELETE SET NULL (segurança extra; o app já grava snapshot antes de excluir)
ALTER TABLE Feedback
  ADD CONSTRAINT Feedback_ibfk_1
  FOREIGN KEY (Usuario) REFERENCES Usuarios(ID) ON DELETE SET NULL ON UPDATE CASCADE;
