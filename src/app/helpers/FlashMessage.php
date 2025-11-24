<?php

namespace App\Helpers;

/**
 * FlashMessage - Sistema de mensagens flash para feedback ao usuário
 * 
 * Permite armazenar mensagens em sessão que persistem entre redirects
 * e são automaticamente removidas após serem exibidas.
 * 
 * Uso:
 *   Flight::flash('Sucesso!', 'success');
 *   Flight::flash()->add('Atenção', 'warn');
 *   $messages = Flight::flash()->all();
 */
class FlashMessage
{
    private const SESSION_KEY = '_flash_messages';
    
    /**
     * Adiciona uma mensagem flash
     */
    public function add(string $message, string $type = 'success'): self
    {
        if (!isset($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = [];
        }
        
        $_SESSION[self::SESSION_KEY][] = [
            'message' => $message,
            'type' => $type,
            'timestamp' => time()
        ];
        
        return $this;
    }
    
    /**
     * Recupera todas as mensagens e limpa da sessão
     */
    public function all(): array
    {
        $messages = $_SESSION[self::SESSION_KEY] ?? [];
        unset($_SESSION[self::SESSION_KEY]);
        return $messages;
    }
    
    /**
     * Verifica se existem mensagens
     */
    public function has(): bool
    {
        return !empty($_SESSION[self::SESSION_KEY]);
    }
    
    /**
     * Limpa todas as mensagens sem retorná-las
     */
    public function clear(): self
    {
        unset($_SESSION[self::SESSION_KEY]);
        return $this;
    }
    
    /**
     * Recupera mensagens sem removê-las (útil para debug)
     */
    public function peek(): array
    {
        return $_SESSION[self::SESSION_KEY] ?? [];
    }
    
    /**
     * Adiciona mensagem de sucesso (atalho)
     */
    public function success(string $message): self
    {
        return $this->add($message, 'success');
    }
    
    /**
     * Adiciona mensagem de erro (atalho)
     */
    public function error(string $message): self
    {
        return $this->add($message, 'error');
    }
    
    /**
     * Adiciona mensagem de aviso (atalho)
     */
    public function warn(string $message): self
    {
        return $this->add($message, 'warn');
    }
    
    /**
     * Adiciona mensagem de info (atalho)
     */
    public function info(string $message): self
    {
        return $this->add($message, 'info');
    }
}
