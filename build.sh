#!/bin/bash

set -e

PROJECT_NAME="methone-panel"
BUILD_DIR="./dist"
OUTPUT_DIR="./build"
SRC_DIR="./src"

echo "=== Build de Produção - $PROJECT_NAME ==="

# limpa build anterior e cria diretórios
rm -rf "$BUILD_DIR"
mkdir -p "$BUILD_DIR"
mkdir -p "$OUTPUT_DIR"

echo ">> Instalando dependências Node e buildando assets..."
cd "$SRC_DIR"

# instala e builda
if command -v pnpm &> /dev/null; then
    pnpm install
    pnpm build
elif command -v npm &> /dev/null; then
    npm install
    npm run build
else
    echo "Erro: pnpm ou npm não encontrado"
    exit 1
fi

cd ..

echo ">> Copiando arquivos PHP..."
rsync -av --exclude='node_modules' \
          --exclude='vite.config.js' \
          --exclude='package.json' \
          --exclude='pnpm-lock.yaml' \
          --exclude='package-lock.json' \
          --exclude='.env.example' \
          --exclude='app/views/assets/js' \
          --exclude='app/views/assets/css' \
          "$SRC_DIR/" "$BUILD_DIR/"

echo ">> Copiando .env para o build..."
if [ -f "$SRC_DIR/.env" ]; then
    cp "$SRC_DIR/.env" "$BUILD_DIR/.env"
    echo "   .env copiado com sucesso"
else
    echo "   AVISO: .env não encontrado em $SRC_DIR/"
fi

echo ">> Instalando dependências Composer (produção)..."
cd "$BUILD_DIR"
composer install --no-dev --optimize-autoloader --no-interaction
cd ..

echo ">> Criando arquivo zip..."
ZIP_NAME="${PROJECT_NAME}-$(date +%Y%m%d-%H%M%S).zip"
cd "$BUILD_DIR"
zip -rq "../${OUTPUT_DIR}/${ZIP_NAME}" . -x "*.git*"
cd ..

echo ">> Limpando diretório temporário..."
rm -rf "$BUILD_DIR"

echo ""
echo "=== Build concluído! ==="
echo "Arquivo: ${OUTPUT_DIR}/${ZIP_NAME}"
echo ""
echo "Para deploy em host compartilhado:"
echo "1. Faça upload do zip para o servidor"
echo "2. Extraia no diretório public_html (ou equivalente)"
echo "3. Configure o .env com as credenciais do banco"
echo "4. Aponte o DocumentRoot para a pasta /public"
