-- Script SQL para configurar o usuário Deyvid (ID 7) como administrador
-- Execute este script no phpMyAdmin ou MySQL

-- Adicionar coluna is_admin se não existir
ALTER TABLE usuarios ADD COLUMN is_admin TINYINT(1) DEFAULT 0;

-- Tornar o usuário ID 7 (Deyvid) como administrador
UPDATE usuarios SET is_admin = 1 WHERE id = 7;

