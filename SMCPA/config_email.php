<?php

/**
 * Configuração de email (Gmail SMTP).
 * Preencha com seu email e Senha de App do Gmail.
 *
 * Como obter Senha de App: Google Conta → Segurança → Verificação em 2 etapas
 * → Senhas de app → Gerar senha. Use essa senha em smtp_senha.
 */
return [
    'smtp_ativo'     => true,
    'smtp_host'      => 'smtp.gmail.com',
    'smtp_porta'     => 587,
    'smtp_seguro'    => 'tls',
    'smtp_usuario'   => 'SMCPA2025.ofc@gmail.com',
    'smtp_senha'     => 'rudrthnbfwhmogoq',
    'remetente'      => 'SMCPA2025.ofc@gmail.com',
    'nome_remetente' => 'SMCPA',
];
