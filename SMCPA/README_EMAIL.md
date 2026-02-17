# Envio de e-mail (Esqueci minha senha) – Gmail SMTP

O envio do link de redefinição de senha usa **Gmail SMTP** e funciona em **localhost** (incluindo `php -S localhost:8000`).

## 1. Instalar dependências (PHPMailer)

Na pasta **SMCPA** (onde está o `composer.json`):

```bash
composer install
```

Se não tiver o Composer instalado:

- Linux: `sudo apt install composer`
- Ou baixe em: https://getcomposer.org/

## 2. Configurar o Gmail

1. **Edite o arquivo `config_email.php`** (na pasta SMCPA) com seu e-mail e **Senha de App** do Gmail.

2. **Criar Senha de App no Google:**
   - Acesse: https://myaccount.google.com/
   - **Segurança** → ative **Verificação em duas etapas** (se ainda não estiver ativa)
   - **Segurança** → **Senhas de app** → **Gerar** senha para “Mail” ou “Outro (nome personalizado)”
   - Use a senha de **16 caracteres** (sem espaços) no campo `smtp_senha` do `config_email.php`

3. No `config_email.php` preencha:
   - `smtp_usuario` = seu e-mail Gmail (ex.: `seuemail@gmail.com`)
   - `smtp_senha` = a Senha de App de 16 caracteres
   - `remetente` = mesmo e-mail (geralmente igual a `smtp_usuario`)

## 3. Usar em localhost:8000

Se você sobe o servidor com:

```bash
cd SMCPA
php -S localhost:8000
```

defina no `config_email.php` a **URL base** para o link de redefinição ficar correto:

```php
'url_base' => 'http://localhost:8000',
```

Assim o link no e-mail será algo como:  
`http://localhost:8000/paginas/esqsenha/redefinir_senha.php?token=...`

Se usar Apache/XAMPP com a pasta do projeto em `/SMCPA`, pode deixar `url_base` vazio.

## 4. Testar

1. Acesse a tela de login e clique em **“Esqueceu a senha?”**.
2. Informe o **e-mail de um usuário** já cadastrado.
3. O sistema envia o e-mail via Gmail SMTP; verifique a caixa de entrada (e spam).
4. Clique no link recebido e defina a nova senha.

Se o Composer não tiver sido instalado (`vendor/` não existir), a página ainda mostrará o link na tela em localhost para você testar o fluxo sem e-mail.
