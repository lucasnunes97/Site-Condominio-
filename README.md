# Site Nuvipama — Condomínio

Site em PHP para consulta de documentos de condomínio, formulário de seguros e ligação à imobiliária.

## Requisitos

- PHP 8+ com extensão FTP (para documentos remotos), `mail()` ou SMTP conforme alojamento.

## Configuração local

1. Copie `config.example.php` para `config.local.php`.
2. Preencha credenciais e opções (FTP, e-mail) em `config.local.php`.
3. **Não commite** `config.local.php` — já está no `.gitignore`.

## Estrutura principal

- `index.php` — páginas (Início, área do condomínio, seguros)
- `documento.php` — consulta de PDF/HTML por NIF e senha (ficheiro: ANO-NIF+SENHA.htm)
- `contato.php` — envio do formulário de seguros (JSON)
- `lib/` — resolução de documentos (local/FTP), erros, config

## Licença

Uso interno / cliente — ajustar conforme necessário.
