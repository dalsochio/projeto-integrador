<?php

namespace App\Controllers;

use App\Records\RoleInfoRecord;
use App\Records\TableRecord;
use flight\Engine;
use Flight;
use Exception;

class RolesController
{
    private Engine $flight;

    public function __construct(Engine $flight)
    {
        $this->flight = $flight;
    }

    
    public function index(): void
    {
        $roleInfoRecord = new RoleInfoRecord();
        $roles = $roleInfoRecord
            ->orderBy('is_system DESC, display_name ASC')
            ->findAllToArray();

        $enforcer = Flight::casbin();
        foreach ($roles as &$role) {
            $permissions = $enforcer->getFilteredPolicy(0, $role['role_name']);
            $role['permissions_count'] = count($permissions);
            $role['is_locked'] = (bool)$role['is_locked'];
            $role['is_system'] = (bool)$role['is_system'];
        }

        Flight::render('page/panel/role/index.latte', [
            'title' => 'Gerenciar Roles',
            'roles' => $roles,
        ]);
    }

    
    public function create(): void
    {
        $tableRecord = new TableRecord();
        $tables = $tableRecord
            ->equal('is_active', 1)
            ->orderBy('display_name ASC')
            ->findAllToArray();

        Flight::render('page/panel/role/create.latte', [
            'title' => 'Nova Role',
            'tables' => $tables,
        ]);
    }

    
    public function store(): void
    {
        try {
            $data = Flight::request()->data->getData();

            if (empty($data['role_name']) || empty($data['display_name'])) {
                Flight::flash()->error('Nome da role e nome de exibição são obrigatórios.');
                Flight::redirect('/panel/role/create');
                return;
            }

            $roleInfoRecord = new RoleInfoRecord();
            $existing = $roleInfoRecord->equal('role_name', $data['role_name'])->find();

            if ($existing && $existing->id) {
                Flight::flash()->error('Já existe uma role com este nome.');
                Flight::redirect('/panel/role/create');
                return;
            }

            $roleInfo = new RoleInfoRecord();
            $roleInfo->role_name = $data['role_name'];
            $roleInfo->display_name = $data['display_name'];
            $roleInfo->description = $data['description'] ?? null;
            $roleInfo->is_system = 0;
            $roleInfo->is_locked = 0;
            $roleInfo->save();

            \App\Helpers\AuditLogger::logFromResourceRoute('panel_role_info', 'create', $roleInfo->id, $data);

            if (!empty($data['permissions']) && is_array($data['permissions'])) {
                $this->savePermissions($data['role_name'], $data['permissions']);
            }

            Flight::flash()->success('Role criada com sucesso!');
            Flight::redirect('/panel/role');

        } catch (Exception $e) {
            Flight::flash()->error('Erro ao criar role: ' . $e->getMessage());
            Flight::redirect('/panel/role/create');
        }
    }

    
    public function edit(string $id): void
    {
        $roleInfoRecord = new RoleInfoRecord();
        $role = $roleInfoRecord->equal('id', (int)$id)->find();

        if (empty($role->id)) {
            Flight::redirect('/panel/role');
            return;
        }

        $tableRecord = new TableRecord();
        $tables = $tableRecord
            ->equal('is_active', 1)
            ->orderBy('display_name ASC')
            ->findAllToArray();

        $permissions = $this->getRolePermissions($role->role_name);

        if (empty($permissions)) {
            $permissions = new \stdClass();
        }

        $roleData = $role->toArray();
        $roleData['is_locked'] = (bool)$roleData['is_locked'];
        $roleData['is_system'] = (bool)$roleData['is_system'];

        Flight::render('page/panel/role/edit.latte', [
            'title' => 'Editar Role: ' . $role->display_name,
            'role' => $roleData,
            'tables' => $tables,
            'permissions' => $permissions,
        ]);
    }

    
    public function update(string $id): void
    {
        try {
            $input = file_get_contents('php://input');
            $data = json_decode($input, true);

            $roleInfoRecord = new RoleInfoRecord();
            $role = $roleInfoRecord->equal('id', (int)$id)->find();

            if (empty($role->id)) {
                Flight::json([
                    'success' => false,
                    'message' => 'Role não encontrada.'
                ], 404);
                return;
            }

            if (empty($data['display_name'])) {
                Flight::json([
                    'success' => false,
                    'message' => 'Nome de exibição é obrigatório.'
                ], 400);
                return;
            }

            $role->display_name = $data['display_name'];
            $role->description = $data['description'] ?? null;
            $role->save();

            \App\Helpers\AuditLogger::logFromResourceRoute('panel_role_info', 'update', $role->id, $data);

            Flight::json([
                'success' => true,
                'message' => 'Role atualizada com sucesso!'
            ], 200);

        } catch (Exception $e) {
            Flight::json([
                'success' => false,
                'message' => 'Erro ao atualizar role: ' . $e->getMessage()
            ], 500);
        }
    }

    
    public function updatePermissions(string $id): void
    {
        try {
            $input = file_get_contents('php://input');
            $data = json_decode($input, true);

            $roleInfoRecord = new RoleInfoRecord();
            $role = $roleInfoRecord->equal('id', (int)$id)->find();

            if (empty($role->id)) {
                Flight::json([
                    'success' => false,
                    'message' => 'Role não encontrada.'
                ], 404);
                return;
            }

            if ($role->is_locked) {
                Flight::json([
                    'success' => false,
                    'message' => 'As permissões desta role não podem ser alteradas.'
                ], 403);
                return;
            }

            $permissions = $data['permissions'] ?? [];
            $this->savePermissions($role->role_name, $permissions);

            Flight::json([
                'success' => true,
                'message' => 'Permissões atualizadas com sucesso!'
            ], 200);

        } catch (Exception $e) {
            Flight::json([
                'success' => false,
                'message' => 'Erro ao atualizar permissões: ' . $e->getMessage()
            ], 500);
        }
    }

    
    public function destroy(string $id): void
    {
        try {
            $roleInfoRecord = new RoleInfoRecord();
            $role = $roleInfoRecord->equal('id', (int)$id)->find();

            if (empty($role->id)) {
                Flight::json([
                    'success' => false,
                    'message' => 'Role não encontrada.'
                ], 404);
                return;
            }

            if ($role->is_system) {
                Flight::json([
                    'success' => false,
                    'message' => 'Roles de sistema não podem ser deletadas.'
                ], 403);
                return;
            }

            $enforcer = Flight::casbin();

            $enforcer->removeFilteredPolicy(0, $role->role_name);

            $role->delete();

            \App\Helpers\AuditLogger::logFromResourceRoute('panel_role_info', 'delete', $id, null);

            Flight::json([
                'success' => true,
                'message' => 'Role deletada com sucesso!'
            ], 200);

        } catch (Exception $e) {
            Flight::json([
                'success' => false,
                'message' => 'Erro ao deletar role: ' . $e->getMessage()
            ], 500);
        }
    }

    
    private function savePermissions(string $roleName, array $permissions): void
    {
        $enforcer = Flight::casbin();

        $enforcer->removeFilteredPolicy(0, $roleName);

        $guestAllowedActions = ['list', 'read'];

        foreach ($permissions as $tableName => $actions) {
            foreach ($actions as $action => $enabled) {
                if ($enabled) {
                    if ($roleName === 'guest' && !in_array($action, $guestAllowedActions)) {
                        continue;
                    }

                    $enforcer->addPolicy($roleName, $tableName, '*', $action);
                }
            }
        }
    }

    
    private function getRolePermissions(string $roleName): array
    {
        $enforcer = Flight::casbin();
        $permissions = [];

        $policies = $enforcer->getFilteredPolicy(0, $roleName);

        foreach ($policies as $policy) {
            $tableName = $policy[1] ?? null;
            $action = $policy[3] ?? null;

            if ($tableName && $action) {
                if (!isset($permissions[$tableName])) {
                    $permissions[$tableName] = [];
                }
                $permissions[$tableName][$action] = true;
            }
        }

        return $permissions;
    }
}
