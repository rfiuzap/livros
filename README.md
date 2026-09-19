# Livro — Gestão de Leitura de Livros

Gestão completa da sua leitura de livros: cadastre o que já leu, está lendo ou quer ler, acompanhe páginas, gênero, resumo, personagens e sua nota — tudo numa estante pessoal conectada à comunidade.

Aplicação web em PHP 8.x + SQLite, pronta para hospedagem compartilhada (Apache/cPanel), com interface inspirada em shadcn/ui (light mode), Tailwind CDN e Phosphor Icons.

## Funcionalidades

- **Busca automática de livros** via Google Books e Open Library, com preenchimento de ano, páginas, gênero, sinopse, capa e personagens principais.
- **Complemento por IA** (Gemini ou OpenAI, opcional): quando as APIs públicas não trazem todos os dados, a IA completa a ficha em português a partir de título e autor.
- **Catálogo compartilhado**: livros cadastrados por qualquer usuário ficam disponíveis para todos em "Livros Registrados" — basta editar para adicionar à própria estante.
- **Estante pessoal** com status (Já li, Lendo, Quero ler, Não li), nota, data de leitura e resenha.
- **Feed da comunidade**, dividido em "Por livro" e "Por pessoa" (total de livros lidos e leitura atual de cada usuário).
- **Privacidade configurável**: cada usuário decide se aparece no feed geral.
- **Cadastro sem depender de email**: em vez de confirmação por email, o usuário define uma pergunta de segurança (de uma lista fixa) usada para recuperar a senha.
- **Segurança**: senhas com `password_hash`, tokens hash-only, CSRF em formulários que alteram dados, rate limit por IP em login e recuperação de senha.

## Arquivos principais

- `index.php`: entrada pública, com os livros mais lidos pela comunidade.
- `login.php` e `register.php`: autenticação com sessões PHP e `password_hash`.
- `dashboard.php`: estante pessoal, catálogo compartilhado, feed geral e formulário de leitura.
- `api.php`: endpoints AJAX para busca de livros, personagens, salvar leitura, catálogo e feed.
- `config.php`: caminhos, conexão SQLite, chaves de IA e funções compartilhadas.
- `profile.php`: dados pessoais, troca de senha e pergunta de segurança.
- `reset_with_question.php`: recuperação de senha por pergunta de segurança (sem email).
- `init_db.php`: inicialização/verificação do schema (requer login).
- `data/livro.sqlite`: banco criado automaticamente quando a aplicação roda.

## Configuração de chaves de IA (opcional)

O preenchimento de livros usa Google Books sem chave obrigatória e Open Library como fallback para título, autor e capa. Para completar a ficha com IA (ano, páginas, gênero, sinopse e personagens), configure uma chave:

- **Local (XAMPP/dev):** copie `.env.example` para `.env` e preencha. O `.env` nunca é commitado (está no `.gitignore`) e é lido automaticamente por `config.php`.
- **Produção (cPanel/HostGator):** configure como variável de ambiente real, por exemplo via `SetEnv` no `.htaccess` do servidor (não no repositório).

```bash
GEMINI_API_KEY=sua_chave_google_gemini
OPENAI_API_KEY=sua_chave_openai
```

**Nunca** cole uma chave real diretamente em `config.php` ou no `.htaccess` versionado — isso a publicaria no GitHub no primeiro commit.

Se nenhuma chave existir, o sistema tenta fallback via Wikidata (`P674`) para personagens. Quando não houver retorno, o usuário ainda pode adicionar tags manualmente.

## Busca de livros em ambiente local

Se o PHP local estiver sem `curl` e sem `openssl`, chamadas HTTPS do backend não funcionam. A tela tenta automaticamente uma busca direta pelo navegador no Google Books, mas em produção o recomendado é habilitar ao menos uma dessas extensões no PHP.

## Cadastro e recuperação de senha (sem email)

O app não depende de envio de email em nenhum ponto. No cadastro, além de nome/email/senha, o usuário escolhe uma **pergunta de segurança** (de uma lista fixa de 5, ver `security_questions()` em `config.php`) e define uma resposta — guardada só como hash, igual senha.

Para recuperar a senha, `reset_with_question.php` pede o email, mostra a pergunta cadastrada e, se a resposta bater, deixa definir uma nova senha na hora. A resposta pode ser trocada a qualquer momento em **Meu perfil**, mediante confirmação da senha atual.

Proteções: rate limit por IP (login, início da recuperação e tentativas de resposta) e CSRF em todos os formulários que alteram dados.

## Deploy na HostGator

1. Envie todos os arquivos para `public_html` ou uma subpasta do site.
2. Garanta PHP 8.x com extensões `pdo_sqlite` e `openssl` ou `curl` habilitadas.
3. A pasta `data/` precisa permitir escrita pelo PHP:

```bash
chmod 755 data
chmod 644 data/.htaccess
```

Se o servidor não conseguir criar/escrever o SQLite, use temporariamente `chmod 775 data`. Evite `777` em produção. O `data/.htaccess` bloqueia download direto do SQLite, e o `.htaccess` da raiz também bloqueia extensões `.sqlite`, `.sqlite3` e `.db`.

Acesse `init_db.php` uma vez após o upload (logado) para verificar/criar as tabelas — a aplicação também inicializa o schema automaticamente via `config.php`.

## Tecnologias

PHP 8.x · SQLite (PDO) · Tailwind CSS (CDN) · Phosphor Icons · Google Books API · Open Library API · Gemini / OpenAI (opcional)

## Licença

Distribuído sob a licença MIT — veja [LICENSE](LICENSE).
