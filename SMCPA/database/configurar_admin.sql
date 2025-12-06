-- cria o primeiro adm do sistema automaticamente.

INSERT INTO Usuarios (usuario, senha, Email, is_admin, localizacao)
VALUES (
    'Taylor Swift',
    '$2y$12$gzosT6sMoL29t3b0jed7d.YEqT7Di9ygnf5XtuqrQLXbwtQQ/OVXq',-- senha 123
    'taylor@gmail.com',
    1,
    NULL
);

INSERT INTO Administrador (usuario, senha)
VALUES (
    'Taylor Swift',
    '$2y$12$gzosT6sMoL29t3b0jed7d.YEqT7Di9ygnf5XtuqrQLXbwtQQ/OVXq'-- senha 123
);

select * from usuarios;

