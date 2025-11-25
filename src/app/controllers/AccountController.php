<?php

namespace App\Controllers;

use App\Records\UserRecord;
use Flight;

class AccountController
{
    public function index(): void
    {
        $user = $_SESSION['user'] ?? null;
        
        if (!$user) {
            Flight::redirect('/login');
            return;
        }
        
        $userRecord = new UserRecord();
        $userRecord->find($user['id']);
        
        if ($userRecord->isHydrated()) {
            $userData = $userRecord->toArray();
            unset($userData['password']);
            $_SESSION['user'] = $userData;
            $user = $userData;
        }
        
        Flight::render('page/account/index.latte', [
            'title' => 'Minha Conta',
            'user' => $user,
            'htmx' => ['boost' => true]
        ]);
    }
    
    public function update(): void
    {
        $user = $_SESSION['user'] ?? null;
        
        if (!$user) {
            Flight::json(['success' => false, 'message' => 'Usuário não autenticado'], 401);
            return;
        }
        
        $data = Flight::request()->data->getData();
        
        $userRecord = new UserRecord();
        $userRecord->find($user['id']);
        
        if (!$userRecord->isHydrated()) {
            Flight::json(['success' => false, 'message' => 'Usuário não encontrado'], 404);
            return;
        }
        
        if (!empty($data['username'])) {
            $userRecord->username = $data['username'];
        }
        
        if (!empty($data['email'])) {
            $userRecord->email = $data['email'];
        }
        
        if (!empty($data['theme'])) {
            if (in_array($data['theme'], ['light', 'dark', 'system'])) {
                $userRecord->theme = $data['theme'];
            }
        }
        
        if (!empty($data['current_password']) && !empty($data['new_password'])) {
            if (!password_verify($data['current_password'], $userRecord->password)) {
                Flight::json(['success' => false, 'message' => 'Senha atual incorreta'], 400);
                return;
            }
            
            if (strlen($data['new_password']) < 6) {
                Flight::json(['success' => false, 'message' => 'Nova senha deve ter no mínimo 6 caracteres'], 400);
                return;
            }
            
            $userRecord->password = password_hash($data['new_password'], PASSWORD_BCRYPT);
        }
        
        $userRecord->updated_by = $_SESSION['user']['id'] ?? null;
        $userRecord->save();
        
        $_SESSION['user']['username'] = $userRecord->username;
        $_SESSION['user']['email'] = $userRecord->email;
        $_SESSION['user']['theme'] = $userRecord->theme;
        
        Flight::json(['success' => true, 'message' => 'Conta atualizada com sucesso']);
    }
    
    public function updateTheme(): void
    {
        $user = $_SESSION['user'] ?? null;
        
        if (!$user) {
            Flight::json(['success' => false, 'message' => 'Usuário não autenticado'], 401);
            return;
        }
        
        $data = Flight::request()->data->getData();
        
        if (empty($data['theme']) || !in_array($data['theme'], ['light', 'dark', 'system'])) {
            Flight::json(['success' => false, 'message' => 'Tema inválido'], 400);
            return;
        }
        
        $userRecord = new UserRecord();
        $userRecord->find($user['id']);
        
        if (!$userRecord->isHydrated()) {
            Flight::json(['success' => false, 'message' => 'Usuário não encontrado'], 404);
            return;
        }
        
        $userRecord->theme = $data['theme'];
        $userRecord->updated_by = $_SESSION['user']['id'] ?? null;
        $userRecord->save();
        
        $_SESSION['user']['theme'] = $userRecord->theme;
        
        Flight::json(['success' => true, 'message' => 'Tema atualizado com sucesso', 'theme' => $userRecord->theme]);
    }
}
