<?php

namespace App\Controllers;

use App\Records\UserRecord;
use App\Records\RoleInfoRecord;
use flight\Engine;
use Flight;
use Exception;

class UserController
{
    private Engine $flight;

    public function __construct(Engine $flight)
    {
        $this->flight = $flight;
    }

    
    public function index(): void
    {
        $userRecord = new UserRecord();
        $users = $userRecord
            ->orderBy('is_active DESC, username ASC')
            ->findAllToArray();

        $enforcer = Flight::casbin();
        foreach ($users as &$user) {
            $roles = $enforcer->getRolesForUser("user:{$user['id']}");
            $cleanRoles = [];
            foreach ($roles as $role) {
                $cleanRoles[] = str_replace('role:', '', $role);
            }
            $user['roles'] = $cleanRoles;
            $user['roles_count'] = count($user['roles']);
        }

        Flight::render('page/user/index.latte', [
            'title' => 'Gerenciar Usuários',
            'users' => $users,
        ]);
    }

    
    public function create(): void
    {
        $roleInfoRecord = new RoleInfoRecord();
        $roles = $roleInfoRecord
            ->orderBy('is_system DESC, display_name ASC')
            ->findAllToArray();

        $filteredRoles = [];
        foreach ($roles as $role) {
            if ($role['role_name'] !== 'guest') {
                $filteredRoles[] = $role;
            }
        }
        $roles = $filteredRoles;

        Flight::render('page/user/create.latte', [
            'title' => 'Novo Usuário',
            'roles' => $roles,
        ]);
    }

    
    public function store(): void
    {
        try {
            $data = Flight::request()->data->getData();

            if (empty($data['username']) || empty($data['email']) || empty($data['password'])) {
                Flight::flash()->error('Campos obrigatórios não preenchidos: username, email e password são necessários.');
                Flight::redirect('/administration/user/create');
                return;
            }

            $checkUsername = new UserRecord();
            $existing = $checkUsername->equal('username', $data['username'])->find();

            if ($existing && $existing->id) {
                Flight::flash()->error('Já existe um usuário com este username.');
                Flight::redirect('/administration/user/create');
                return;
            }

            $checkEmail = new UserRecord();
            $existing = $checkEmail->equal('email', $data['email'])->find();

            if ($existing && $existing->id) {
                Flight::flash()->error('Já existe um usuário com este email.');
                Flight::redirect('/administration/user/create');
                return;
            }

            $user = new UserRecord();
            $user->username = $data['username'];
            $user->email = $data['email'];
            $user->password = password_hash($data['password'], PASSWORD_DEFAULT);
            $user->is_active = $data['is_active'] ?? 1;
            $user->save();

            \App\Helpers\AuditLogger::logFromResourceRoute('user', 'create', $user->id, $data);

            if (!empty($data['roles']) && is_array($data['roles'])) {
                $this->assignRolesToUser($user->id, $data['roles']);
            }

            Flight::flash()->success('Usuário criado com sucesso!');
            Flight::redirect('/administration/user');

        } catch (Exception $e) {
            Flight::flash()->error('Erro ao criar usuário: ' . $e->getMessage());
            Flight::redirect('/administration/user/create');
        }
    }

    
    public function edit(string $id): void
    {
        $userRecord = new UserRecord();
        $user = $userRecord->equal('id', (int)$id)->find();

        if (empty($user->id)) {
            Flight::redirect('/administration/user');
            return;
        }

        $roleInfoRecord = new RoleInfoRecord();
        $roles = $roleInfoRecord
            ->orderBy('is_system DESC, display_name ASC')
            ->findAllToArray();

        $filteredRoles = [];
        foreach ($roles as $role) {
            if ($role['role_name'] !== 'guest') {
                $filteredRoles[] = $role;
            }
        }
        $roles = $filteredRoles;

        $enforcer = Flight::casbin();
        $userRoles = $enforcer->getRolesForUser("user:{$user->id}");

        $userRoleNames = [];
        foreach ($userRoles as $role) {
            $userRoleNames[] = str_replace('role:', '', $role);
        }

        $userData = $user->toArray();
        $userData['is_active'] = (bool)$userData['is_active'];
        unset($userData['password']);

        Flight::render('page/user/edit.latte', [
            'title' => 'Editar Usuário: ' . $user->username,
            'user' => $userData,
            'roles' => $roles,
            'userRoles' => $userRoleNames,
        ]);
    }

    
    public function update(string $id): void
    {
        try {
            $data = Flight::request()->data->getData();

            $userRecord = new UserRecord();
            $user = $userRecord->equal('id', (int)$id)->find();

            if (empty($user->id)) {
                Flight::flash()->error('Usuário não encontrado.');
                Flight::redirect('/administration/user');
                return;
            }

            if (empty($data['username']) || empty($data['email'])) {
                Flight::flash()->error('Username e email são obrigatórios.');
                Flight::redirect("/administration/user/{$id}/edit");
                return;
            }

            $checkUsername = new UserRecord();
            $existing = $checkUsername->equal('username', $data['username'])->find();
            if ($existing && $existing->id && $existing->id != $user->id) {
                Flight::flash()->error('Já existe outro usuário com este username.');
                Flight::redirect("/administration/user/{$id}/edit");
                return;
            }

            $checkEmail = new UserRecord();
            $existing = $checkEmail->equal('email', $data['email'])->find();
            if ($existing && $existing->id && $existing->id != $user->id) {
                Flight::flash()->error('Já existe outro usuário com este email.');
                Flight::redirect("/administration/user/{$id}/edit");
                return;
            }

            $user->username = $data['username'];
            $user->email = $data['email'];

            if (!empty($data['password'])) {
                $user->password = password_hash($data['password'], PASSWORD_DEFAULT);
            }

            $user->is_active = $data['is_active'] ?? 1;
            $user->save();

            \App\Helpers\AuditLogger::logFromResourceRoute('user', 'update', $user->id, $data);

            if (isset($data['roles']) && is_array($data['roles'])) {
                $this->assignRolesToUser($user->id, $data['roles']);
            }

            Flight::flash()->success('Usuário atualizado com sucesso!');
            Flight::redirect('/administration/user');

        } catch (Exception $e) {
            Flight::flash()->error('Erro ao atualizar usuário: ' . $e->getMessage());
            Flight::redirect("/administration/user/{$id}/edit");
        }
    }

    
    public function toggleStatus(string $id): void
    {
        try {
            $userRecord = new UserRecord();
            $user = $userRecord->equal('id', (int)$id)->find();

            if (empty($user->id)) {
                Flight::json([
                    'success' => false,
                    'message' => 'Usuário não encontrado.'
                ], 404);
                return;
            }

            $user->is_active = $user->is_active ? 0 : 1;
            $user->save();

            \App\Helpers\AuditLogger::logFromResourceRoute('user', 'update', $user->id, ['is_active' => $user->is_active]);

            $status = $user->is_active ? 'ativado' : 'inativado';

            Flight::json([
                'success' => true,
                'message' => "Usuário {$status} com sucesso!",
                'data' => [
                    'is_active' => (bool)$user->is_active
                ]
            ], 200);

        } catch (Exception $e) {
            Flight::json([
                'success' => false,
                'message' => 'Erro ao alterar status do usuário: ' . $e->getMessage()
            ], 500);
        }
    }

    
    private function assignRolesToUser(int $userId, array $roleNames): void
    {
        $enforcer = Flight::casbin();

        $enforcer->deleteRolesForUser("user:{$userId}");

        foreach ($roleNames as $roleName) {
            if (!empty($roleName)) {
                $enforcer->addRoleForUser("user:{$userId}", $roleName);
            }
        }

        $enforcer->savePolicy();
    }
}
