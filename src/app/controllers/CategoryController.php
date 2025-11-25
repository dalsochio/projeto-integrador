<?php

namespace App\Controllers;

use App\Records\CategoryRecord;
use App\Records\TableRecord;
use flight\Engine;
use Flight;
use Exception;
use Cocur\Slugify\Slugify;

class CategoryController
{
    private Engine $flight;
    private Slugify $slugify;

    public function __construct(Engine $flight)
    {
        $this->flight = $flight;
        $this->slugify = new Slugify(['separator' => '_', 'lowercase' => true]);
    }

    public function index(): void
    {
        $categoryRecord = new CategoryRecord();

        $categories = $categoryRecord
            ->select('panel_category.*, COUNT(panel_table.id) as module_count')
            ->join('panel_table', 'panel_table.category_id = panel_category.id', 'LEFT')
            ->groupBy('panel_category.id')
            ->orderBy('panel_category.menu_order ASC, panel_category.display_name ASC')
            ->findAllToArray();

        // Coletar IDs únicos de usuários para enriquecer
        $userIds = [];
        foreach ($categories as $category) {
            if (!empty($category['created_by'])) {
                $userIds[] = (int)$category['created_by'];
            }
            if (!empty($category['updated_by'])) {
                $userIds[] = (int)$category['updated_by'];
            }
        }
        $userIds = array_unique($userIds);

        // Buscar dados dos usuários
        $usersMap = [];
        if (!empty($userIds)) {
            $userRecord = new \App\Records\UserRecord();
            $users = $userRecord->select('id', 'username', 'email')->findAll();
            foreach ($users as $user) {
                if (in_array((int)$user->id, $userIds)) {
                    $usersMap[$user->id] = [
                        'username' => $user->username,
                        'email' => $user->email
                    ];
                }
            }
        }

        foreach ($categories as &$category) {
            $canDelete = true;
            $deleteReason = '';
            $canEdit = true;
            $editReason = '';

            if ($category['internal']) {
                $canDelete = false;
                $deleteReason = 'Categorias internas não podem ser deletadas';
                $canEdit = false;
                $editReason = 'Categorias internas não podem ser editadas';
            } elseif ($category['module_count'] > 0) {
                $canDelete = false;
                $deleteReason = "Esta categoria possui {$category['module_count']} módulo" . ($category['module_count'] > 1 ? 's' : '');
            }

            $category['can_delete'] = $canDelete;
            $category['delete_reason'] = $deleteReason;
            $category['can_edit'] = $canEdit;
            $category['edit_reason'] = $editReason;

            // Enriquecer com dados do usuário
            if (!empty($category['created_by']) && isset($usersMap[$category['created_by']])) {
                $category['created_by_name'] = $usersMap[$category['created_by']]['username'];
                $category['created_by_email'] = $usersMap[$category['created_by']]['email'];
            }
            if (!empty($category['updated_by']) && isset($usersMap[$category['updated_by']])) {
                $category['updated_by_name'] = $usersMap[$category['updated_by']]['username'];
                $category['updated_by_email'] = $usersMap[$category['updated_by']]['email'];
            }
        }

        Flight::render('page/panel/category/index.latte', [
            'title' => 'Categorias',
            'categories' => $categories,
        ]);
    }

    public function show(string $id): void
    {
    }

    public function create(): void
    {
        Flight::render('page/panel/category/create.latte', [
            'title' => 'Nova categoria'
        ]);
    }

    public function store(): void
    {
        try {
            $data = Flight::request()->data->getData();

            if (empty($data['display_name']) || empty($data['url_path'])) {
                Flight::flash()->error('Campos obrigatórios não preenchidos: nome de exibição e URL são necessários.');
                Flight::redirect('/panel/category/create');
                return;
            }

            $categoryRecord = new CategoryRecord();
            $categoryRecord->name = $this->slugify->slugify($data['display_name']);
            $categoryRecord->url_path = trim($data['url_path'], '/');
            $categoryRecord->display_name = $data['display_name'];
            $categoryRecord->description = $data['description'] ?? null;
            $categoryRecord->icon = $data['icon'] ?? null;
            $categoryRecord->icon_type = 'text';
            $categoryRecord->is_active = $data['is_active'] ?? 1;
            $categoryRecord->menu_order = $data['menu_order'] ?? 0;
            $categoryRecord->created_by = $_SESSION['user']['id'] ?? null;
            $categoryRecord->updated_by = $_SESSION['user']['id'] ?? null;
            $categoryRecord->save();

            \App\Helpers\AuditLogger::logFromResourceRoute('panel_category', 'create', $categoryRecord->id, $data);

            Flight::flash()->success('Categoria criada com sucesso!');
            Flight::redirect('/panel/category');

        } catch (Exception $e) {
            Flight::flash()->error('Erro ao criar categoria: ' . $e->getMessage());
            Flight::redirect('/panel/category/create');
        }
    }

    public function edit(string $id): void
    {
        $categoryRecord = new CategoryRecord();
        $category = $categoryRecord->equal('id', (int)$id)->find();

        if (!$category->isHydrated()) {
            Flight::redirect('/panel/category');
            return;
        }

        if ($category->internal) {
            Flight::flash()->error('Categorias internas não podem ser editadas.');
            Flight::redirect('/');
            return;
        }

        $tableRecord = new TableRecord();
        $modules = $tableRecord
            ->equal('category_id', (int)$id)
            ->orderBy('menu_order ASC, display_name ASC')
            ->findAllToArray();

        Flight::render('page/panel/category/edit.latte', [
            'title' => 'Editar categoria: ' . $category->display_name,
            'category' => $category->toArray(),
            'modules' => $modules
        ]);
    }

    public function update(string $id): void
    {
        try {
            $categoryRecord = new CategoryRecord();
            $category = $categoryRecord->equal('id', (int)$id)->find();

            if (!$category->isHydrated()) {
                Flight::flash()->error('Categoria não encontrada.');
                Flight::redirect('/panel/category');
                return;
            }

            if ($category->internal) {
                Flight::flash()->error('Categorias internas não podem ser editadas.');
                Flight::redirect('/');
                return;
            }

            $data = Flight::request()->data->getData();

            if (empty($data['display_name']) || empty($data['url_path'])) {
                Flight::flash()->error('Campos obrigatórios não preenchidos: nome de exibição e URL são necessários.');
                Flight::redirect("/panel/category/{$id}/edit");
                return;
            }

            $category->name = $this->slugify->slugify($data['display_name']);
            $category->url_path = trim($data['url_path'], '/');
            $category->display_name = $data['display_name'];
            $category->description = $data['description'] ?? null;
            $category->icon = $data['icon'] ?? null;
            $category->is_active = $data['is_active'] ?? 1;
            $category->menu_order = $data['menu_order'] ?? 0;
            $category->updated_by = $_SESSION['user']['id'] ?? null;
            $category->save();

            \App\Helpers\AuditLogger::logFromResourceRoute('panel_category', 'update', $category->id, $data);

            Flight::flash()->success('Categoria atualizada com sucesso!');
            Flight::redirect('/panel/category');

        } catch (Exception $e) {
            Flight::flash()->error('Erro ao atualizar categoria: ' . $e->getMessage());
            Flight::redirect("/panel/category/{$id}/edit");
        }
    }

    public function updateModulesOrder(string $id): void
    {
        try {
            $data = Flight::request()->data->getData();
            
            if (empty($data['modules_order'])) {
                Flight::flash()->error('Ordem dos módulos não informada');
                Flight::redirect("/panel/category/{$id}/edit");
                return;
            }

            $modulesOrder = is_string($data['modules_order']) ? json_decode($data['modules_order'], true) : $data['modules_order'];
            
            if (!is_array($modulesOrder)) {
                Flight::flash()->error('Formato inválido');
                Flight::redirect("/panel/category/{$id}/edit");
                return;
            }

            $tableRecord = new TableRecord();

            foreach ($modulesOrder as $order => $moduleId) {
                $module = $tableRecord->equal('id', (int)$moduleId)->find();
                if ($module->isHydrated()) {
                    $module->menu_order = $order;
                    $module->updated_by = $_SESSION['user']['id'] ?? null;
                    $module->save();
                }
                $tableRecord = new TableRecord();
            }

            Flight::flash()->success('Ordem dos módulos atualizada com sucesso!');
            Flight::redirect("/panel/category/{$id}/edit");

        } catch (Exception $e) {
            Flight::flash()->error('Erro ao atualizar ordem: ' . $e->getMessage());
            Flight::redirect("/panel/category/{$id}/edit");
        }
    }

    public function destroy(string $id): void
    {
        $categoryRecord = new CategoryRecord();
        $category = $categoryRecord->equal('id', $id)->find();

        if (!$category->isHydrated()) {
            Flight::flash()->error('Categoria não encontrada');
            Flight::redirect('/panel/category');
            return;
        }

        if ($category->internal) {
            Flight::flash()->error('Categorias internas não podem ser deletadas.');
            Flight::redirect('/panel/category');
            return;
        }

        try {
            $tableRecord = new TableRecord();
            $modules = $tableRecord->equal('category_id', $id)->findAll();
            $moduleCount = count($modules);

            if ($moduleCount > 0) {
                Flight::flash()->warn("Não é possível deletar esta categoria pois ela possui {$moduleCount} módulo(s) associado(s). Mova ou delete os módulos primeiro.");
                Flight::redirect('/panel/category');
                return;
            }

            $category->delete();

            \App\Helpers\AuditLogger::logFromResourceRoute('panel_category', 'delete', $id, null);

            Flight::flash()->success('Categoria deletada com sucesso!');
            Flight::redirect('/panel/category');

        } catch (Exception $e) {
            Flight::flash()->error('Erro ao deletar categoria: ' . $e->getMessage());
            Flight::redirect('/panel/category');
        }
    }
}
