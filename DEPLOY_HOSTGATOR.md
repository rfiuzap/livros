# Publicar o Livro na HostGator

Este guia publica o projeto disponível em:

`https://github.com/rfiuzap/livros`

## 1. Preparar o domínio

1. Entre no cPanel da HostGator.
2. Confirme em **Domínios** qual é a pasta raiz do domínio.
3. Para o domínio principal, normalmente a pasta é `public_html`.
4. Para testar sem substituir outro site, crie um subdomínio ou use uma pasta como `public_html/livros`.
5. Ative o certificado SSL em **SSL/TLS Status** antes de divulgar o site.

Se já existir outro site em `public_html`, faça um backup antes de substituir qualquer arquivo.

## 2. Configurar o PHP

1. Abra **MultiPHP Manager** ou **Selecionar versão do PHP**.
2. Escolha PHP 8.1, 8.2 ou uma versão 8.x mais recente disponível.
3. Habilite estas extensões:

- `pdo_sqlite`
- `sqlite3`
- `curl`
- `openssl`
- `mbstring`

Se alguma extensão não aparecer no painel, solicite sua ativação ao suporte da HostGator.

## 3. Baixar o projeto pelo Git

### Opção recomendada: Terminal do cPanel

Abra **Terminal** no cPanel e escolha a pasta de instalação.

Para instalar em `seudominio.com/livros`:

```bash
cd ~/public_html
git clone https://github.com/rfiuzap/livros.git livros
cd livros
```

Para instalar diretamente no domínio principal, use somente uma pasta `public_html` vazia:

```bash
cd ~/public_html
git clone https://github.com/rfiuzap/livros.git .
```

O ponto final no último comando é obrigatório. Ele coloca os arquivos diretamente em `public_html`.

### Alternativa: Git Version Control do cPanel

1. Abra **Git Version Control**.
2. Clique em **Create**.
3. Ative **Clone a Repository**.
4. Em **Clone URL**, informe:

```text
https://github.com/rfiuzap/livros.git
```

1. Em **Repository Path**, escolha `public_html/livros` ou a pasta raiz vazia do domínio.
2. Conclua em **Create**.

### Alternativa sem Git

1. Abra o repositório no GitHub.
2. Clique em **Code > Download ZIP**.
3. Envie o ZIP pelo **Gerenciador de Arquivos** do cPanel.
4. Extraia o conteúdo na pasta do domínio.
5. Mova os arquivos de dentro da pasta extraída para a pasta final do site.
6. Ative **Show Hidden Files** no Gerenciador de Arquivos e confirme que `.htaccess` também foi enviado.

## 4. Configurar permissões do banco

O SQLite será criado automaticamente em `data/livro.sqlite`. No Terminal, execute dentro da pasta do projeto:

```bash
chmod 755 data
chmod 644 data/.htaccess
```

Se o site informar que o banco é somente leitura ou não pode ser criado, tente:

```bash
chmod 775 data
```

Não use permissão `777`.

## 5. Configurar as chaves de IA

As chaves são opcionais. Sem elas, Google Books, Open Library e os fallbacks públicos continuam disponíveis.

No Gerenciador de Arquivos:

1. Copie `.env.example` para um novo arquivo chamado `.env`.
2. Edite `.env` e informe somente as chaves que utilizar:

```text
GEMINI_API_KEY=sua_chave_google_gemini
OPENAI_API_KEY=sua_chave_openai
```

1. Defina permissão `600` para o arquivo, quando disponível:

```bash
chmod 600 .env
```

Nunca envie `.env` ao GitHub. O projeto já ignora esse arquivo e o `.htaccess` bloqueia seu acesso pelo navegador.

## 6. Abrir e inicializar o site

1. Abra o endereço da instalação:

```text
https://seudominio.com/
```

ou, se estiver em uma subpasta:

```text
https://seudominio.com/livros/
```

1. Clique em **Criar conta** e faça o primeiro cadastro.
2. O banco e as tabelas são criados automaticamente no primeiro acesso.
3. Depois de entrar, abra `init_db.php` para confirmar o banco:

```text
https://seudominio.com/init_db.php
```

Em uma instalação em subpasta, use `https://seudominio.com/livros/init_db.php`.

## 7. Testar antes de divulgar

Confirme estes fluxos:

1. Criar uma conta e entrar.
2. Buscar um livro pelo título.
3. Salvar uma leitura e atualizar a página.
4. Conferir o livro na estante e no Feed Geral.
5. Alterar o perfil para privado e confirmar que ele deixa de aparecer no feed.
6. Sair e recuperar a senha pela pergunta de segurança.
7. Abrir uma capa de livro e confirmar que não há bloqueio de conteúdo HTTP em uma página HTTPS.

## 8. Atualizar o site pelo GitHub

Antes de atualizar, faça backup de `data/livro.sqlite` pelo Gerenciador de Arquivos.

No Terminal, entre na pasta do projeto e execute:

```bash
cd ~/public_html/livros
git pull origin main
```

Se o projeto estiver diretamente em `public_html`, use:

```bash
cd ~/public_html
git pull origin main
```

O banco e o `.env` não são substituídos pelo `git pull`, pois não fazem parte do repositório.

## Solução de problemas

### Erro 500

Abra **Errors** no cPanel e consulte as linhas mais recentes. Confirme também a versão do PHP e se o arquivo `.htaccess` foi enviado.

### `could not find driver`

Ative `pdo_sqlite` e `sqlite3` na configuração do PHP.

### `attempt to write a readonly database`

Confirme que a pasta `data` pertence ao usuário da hospedagem e use permissão `755` ou, se necessário, `775`.

### Busca de livros não funciona

Ative `curl` e `openssl`. Depois teste novamente e consulte **Errors** no cPanel.

### A página abre sem estilos ou ícones

Confirme que o servidor consegue acessar `cdn.tailwindcss.com`, `fonts.googleapis.com` e `unpkg.com` por HTTPS.

### O Git pede usuário e senha

O repositório é público e deve clonar sem autenticação. Para enviar alterações da HostGator ao GitHub, use um Personal Access Token; a senha comum do GitHub não é aceita para Git via HTTPS.
