# Methone Painel

Sistema de painel administrativo construído com PHP (FlightPHP), MySQL e frontend moderno (Alpine.js + Tailwind CSS).

## Pré-requisitos

- Docker e Docker Compose
- Node.js 18+ (para desenvolvimento)
- PHP 8.4+ e Composer (para desenvolvimento sem Docker)

## Configuração Inicial

1. **Clone o repositório:**
```bash
git clone <url-do-repo>
cd projeto-integrador
```

2. **Configure as variáveis de ambiente:**
```bash
cp .env.example .env
```

Edite o `.env` com suas configurações:
```env
MARIADB_ROOT_PASSWORD=sua_senha_forte
```

---

## 🛠️ Desenvolvimento

### Subindo o ambiente completo

```bash
# Inicia todos os serviços (app, vite, banco, phpmyadmin)
docker-compose up -d

# Ou para ver os logs
docker-compose up
```

**Serviços disponíveis:**
- **App:** http://localhost:8080
- **Vite (HMR):** http://localhost:5173  
- **phpMyAdmin:** http://localhost:8081

### Rodando migrações

```bash
# Entre no container
docker exec -it methone-panel bash

# Execute as migrações
php runway migrate:up
```

### Desenvolvimento sem Docker

1. **Instale dependências PHP:**
```bash
cd src/
composer install
```

2. **Instale dependências Node:**
```bash
cd src/
pnpm install
# ou: npm install
```

3. **Configure banco MySQL local e execute migrações**

4. **Rode o Vite para assets:**
```bash
pnpm dev
```

5. **Inicie o servidor web:**

   **Opção A: Servidor PHP embutido (mais simples)**
   ```bash
   cd src/public
   php -S localhost:8080
   ```
   Acesse: http://localhost:8080

   **Opção B: Apache/Nginx**
   Configure o servidor apontando DocumentRoot para `/src/public`

---

## 🚀 Produção (Docker)

### Build e execução

```bash
# Build da imagem de produção
docker-compose -f docker-compose.prod.yml build

# Subir em produção
docker-compose -f docker-compose.prod.yml up -d
```

### Configurando banco em produção

```bash
# Entre no container
docker exec -it methone-panel-prod bash

# Execute migrações
php runway migrate:up
```

**Acesso:** http://localhost:8080

---

## 📦 Deploy em Host Compartilhado

Para hosts que só aceitam upload de arquivos (sem SSH/CLI).

### 1. Gerar o build

```bash
# Executa build completo e gera ZIP
./build.sh
```

O arquivo será criado em `/build/methone-panel-YYYYMMDD-HHMMSS.zip`

### 2. Preparar banco de dados

Como muitos hosts não têm SSH, você precisa executar o SQL completo via phpMyAdmin ou painel do host.

#### Opção A: SQL Unificado (Recomendado)
Execute os arquivos na ordem dentro do diretório `src/app/databases/migrations/up/`:

1. `00001.sql` - Database creation and initial settings
2. `00002.sql` - Create panel_category table and insert default data  
3. `00003.sql` - Create panel_table table and insert default data
4. `00004.sql` - Create panel_column table and insert default data
5. `00005.sql` - Create panel_config table and insert default data
6. `00006.sql` - Create panel_log table
7. `00007.sql` - Create panel_role table and insert default data
8. `00008.sql` - Create panel_role_info table and insert default data
9. `00009.sql` - Create user table and insert default admin user
10. `00010.sql` - Add foreign key constraint to panel_column

#### Opção B: SQL Único (Gerado automaticamente)
```bash
# Gera arquivo SQL único com todas as migrações
cat src/app/databases/migrations/up/*.sql > database-complete.sql
```

Execute o arquivo `database-complete.sql` no phpMyAdmin do seu host.

### 3. Upload e configuração

1. **Faça upload do ZIP** para seu host compartilhado
2. **Extraia no diretório raiz** (public_html, www, htdocs, etc.)
3. **Configure o .env:**
```env
# Dados do seu provedor de hospedagem
DB_HOST=localhost
DB_NAME=seu_banco
DB_USER=seu_usuario  
DB_PASS=sua_senha

# Configurações de produção
APP_ENV=production
APP_DEBUG=false
```

4. **Configure DocumentRoot** (se possível) para apontar para `/public`
   - Se não conseguir alterar DocumentRoot, mova o conteúdo de `/public` para a raiz

### 4. Usuário padrão

**Login:** admin  
**Senha:** admin  
**Email:** contato@example.com

⚠️ **Importante:** Altere a senha padrão imediatamente após o primeiro login!

---

## 🗄️ Estrutura do Banco

O sistema usa as seguintes tabelas principais:

- `user` - Usuários do sistema
- `panel_*` - Configurações do painel (categorias, tabelas, colunas, etc.)
- `panel_role` / `panel_role_info` - Sistema de permissões (Casbin)
- `panel_log` - Auditoria de ações
- `panel_config` - Configurações gerais

---

## 📁 Estrutura do Projeto

```
src/
├── app/
│   ├── controllers/     # Controllers FlightPHP
│   ├── helpers/         # Classes auxiliares
│   ├── middlewares/     # Middlewares
│   ├── records/         # ActiveRecord models
│   ├── services/        # Lógica de negócio
│   ├── views/           # Templates Latte
│   │   └── assets/      # CSS/JS fonte
│   ├── databases/
│   │   └── migrations/  # Migrações SQL
│   ├── bootstrap.php    # Inicialização
│   └── routes.php       # Rotas
├── public/              # DocumentRoot
│   ├── assets/          # Assets buildados
│   └── index.php        # Entry point
├── package.json         # Deps Node.js
├── vite.config.js       # Config Vite
└── composer.json        # Deps PHP
```

---

## 🔧 Comandos Úteis

```bash
# Ver logs do container
docker logs methone-panel -f

# Entrar no container
docker exec -it methone-panel bash

# Rebuild assets (dev)
docker exec -it methone-panel-vite pnpm build

# Limpar volumes Docker
docker-compose down -v
```

---

## 🚨 Solução de Problemas

### Erro de permissões
```bash
# Ajustar permissões do storage
sudo chown -R www-data:www-data src/app/storage/
sudo chmod -R 755 src/app/storage/
```

### Problemas com Vite/HMR
- Verifique se a porta 5173 não está em uso
- Reinicie o container: `docker-compose restart methone-panel-vite`

### Banco não conecta
- Verifique as credenciais no `.env`
- Aguarde o banco inicializar completamente (pode demorar 1-2 min)

---

## 📄 Licença

GNU AFFERO GENERAL PUBLIC LICENSE - veja LICENSE para detalhes.