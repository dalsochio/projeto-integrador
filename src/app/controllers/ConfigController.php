<?php

namespace App\Controllers;

use App\Helpers\FileUploadHelper;
use App\Records\ConfigRecord;
use flight\Engine;
use Flight;

class ConfigController
{
    protected Engine $app;

    public function __construct(Engine $app)
    {
        $this->app = $app;
    }

    public function index(): void
    {
        $configs = ConfigRecord::all();

        $this->app->render('page/panel/config/index.latte', [
            'title' => 'Configurações do Sistema',
            'breadcrumbs' => [
                ['label' => 'Painel', 'url' => '/'],
                ['label' => 'Configurações', 'url' => null]
            ],
            'configs' => $configs
        ]);
    }

    public function update(): void
    {
        try {
            $data = Flight::request()->data->getData();
            $files = Flight::request()->files;

            if (isset($files['logo']) && $files['logo']['error'] === UPLOAD_ERR_OK) {
                $relativePath = FileUploadHelper::upload($files['logo'], 'config');
                if ($relativePath) {
                    $logoUrl = '/upload/' . $relativePath;
                    ConfigRecord::set('logo_url', $logoUrl);
                }
            }

            if (isset($files['favicon']) && $files['favicon']['error'] === UPLOAD_ERR_OK) {
                $relativePath = FileUploadHelper::upload($files['favicon'], 'config');
                if ($relativePath) {
                    $faviconUrl = '/upload/' . $relativePath;
                    ConfigRecord::set('favicon_url', $faviconUrl);
                }
            }

            $textConfigs = [
                'system_name', 'system_short_name', 'theme_light', 'theme_dark',
                'login_column', 'items_per_page', 'timezone', 'date_format', 'user_role_redirect_url'
            ];

            foreach ($textConfigs as $key) {
                if (isset($data[$key]) && $data[$key] !== '') {
                    ConfigRecord::set($key, $data[$key]);
                }
            }

            if (isset($data['allow_registration'])) {
                ConfigRecord::set('allow_registration', '1');
            } else {
                ConfigRecord::set('allow_registration', '0');
            }

            if (isset($data['allow_user_role_panel_access'])) {
                ConfigRecord::set('allow_user_role_panel_access', '1');
            } else {
                ConfigRecord::set('allow_user_role_panel_access', '0');
            }

            \App\Helpers\ConfigHelper::refresh();

            Flight::json([
                'success' => true,
                'message' => 'Configurações atualizadas com sucesso'
            ]);
        } catch (\Exception $e) {
            Flight::json([
                'success' => false,
                'message' => 'Erro ao salvar: ' . $e->getMessage(),
                'trace' => $_ENV['APP_ENV'] === 'development' ? $e->getTraceAsString() : null
            ], 500);
        }
    }
}
