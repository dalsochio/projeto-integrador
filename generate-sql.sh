#!/bin/bash

set -e

OUTPUT_DIR="./build"
SQL_FILE="database-complete.sql"
MIGRATIONS_DIR="./src/app/databases/migrations/up"

echo "=== Gerando SQL Unificado ==="

# Cria diretório se não existe
mkdir -p "$OUTPUT_DIR"

# Remove arquivo anterior se existir
rm -f "${OUTPUT_DIR}/${SQL_FILE}"

echo "-- Methone Panel - Database Complete Migration" > "${OUTPUT_DIR}/${SQL_FILE}"
echo "-- Generated on: $(date)" >> "${OUTPUT_DIR}/${SQL_FILE}"
echo "-- Execute este arquivo no phpMyAdmin ou cliente MySQL" >> "${OUTPUT_DIR}/${SQL_FILE}"
echo "" >> "${OUTPUT_DIR}/${SQL_FILE}"

# Concatena todas as migrações na ordem
for migration in $(ls "$MIGRATIONS_DIR"/*.sql | sort); do
    echo "-- =================================================" >> "${OUTPUT_DIR}/${SQL_FILE}"
    echo "-- Migration: $(basename "$migration")" >> "${OUTPUT_DIR}/${SQL_FILE}"
    echo "-- =================================================" >> "${OUTPUT_DIR}/${SQL_FILE}"
    echo "" >> "${OUTPUT_DIR}/${SQL_FILE}"
    
    # Remove a linha @description e adiciona o conteúdo
    tail -n +2 "$migration" >> "${OUTPUT_DIR}/${SQL_FILE}"
    echo "" >> "${OUTPUT_DIR}/${SQL_FILE}"
    echo "" >> "${OUTPUT_DIR}/${SQL_FILE}"
done

echo ""
echo "=== SQL Unificado criado! ==="
echo "Arquivo: ${OUTPUT_DIR}/${SQL_FILE}"
echo ""
echo "Use este arquivo para criar o banco em hosts compartilhados:"
echo "1. Acesse o phpMyAdmin do seu host"
echo "2. Crie um banco de dados"
echo "3. Importe o arquivo ${SQL_FILE}"