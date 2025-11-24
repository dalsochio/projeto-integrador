<?php

namespace App\Controllers;

use App\Helpers\FileUploadHelper;
use App\Helpers\CasbinHelper;
use Flight;
use flight\Engine;

class UploadController
{
    private Engine $flight;

    public function __construct(Engine $flight)
    {
        $this->flight = $flight;
    }

    
    public function serve(string $module, string $year, string $month, string $day, string $filename): void
    {
        $relativePath = "$module/$year/$month/$day/$filename";

        if (!FileUploadHelper::exists($relativePath)) {
            Flight::notFound();
            return;
        }

        if ($module !== 'general') {
            $userId = $_SESSION['user']['id'] ?? null;
            $hasPermission = CasbinHelper::userCan($userId, $module, '*', 'read');

            if (!$hasPermission) {
                Flight::halt(403, 'Acesso negado a este recurso');
                return;
            }
        }

        $absolutePath = FileUploadHelper::getAbsolutePath($relativePath);
        $mimeType = mime_content_type($absolutePath);
        $fileSize = filesize($absolutePath);

        header('Cache-Control: public, max-age=31536000');
        header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 31536000) . ' GMT');

        header('Content-Type: ' . $mimeType);
        header('Content-Length: ' . $fileSize);
        header('Content-Disposition: inline; filename="' . basename($filename) . '"');

        readfile($absolutePath);
        exit;
    }

    
    public function index(): void
    {
        $userId = $_SESSION['user']['id'] ?? null;
        $hasPermission = CasbinHelper::userCan($userId, 'uploads', '*', 'read');

        if (!$hasPermission) {
            Flight::halt(403, 'Você não tem permissão para acessar esta página');
            return;
        }

        $recentFiles = $this->getRecentFiles(30);

        $canCreate = CasbinHelper::userCan($userId, 'uploads', '*', 'create');
        $canDelete = CasbinHelper::userCan($userId, 'uploads', '*', 'delete');

        Flight::render('page/panel/uploads/index.latte', [
            'title' => 'Uploads Gerais',
            'recentFiles' => $recentFiles,
            'canCreate' => $canCreate,
            'canDelete' => $canDelete,
        ]);
    }

    
    public function create(): void
    {
        $userId = $_SESSION['user']['id'] ?? null;
        $hasPermission = CasbinHelper::userCan($userId, 'uploads', '*', 'create');

        if (!$hasPermission) {
            Flight::halt(403, 'Você não tem permissão para fazer uploads');
            return;
        }

        Flight::render('page/panel/uploads/create.latte', [
            'title' => 'Novo Upload',
        ]);
    }

    
    public function store(): void
    {
        $userId = $_SESSION['user']['id'] ?? null;
        $hasPermission = CasbinHelper::userCan($userId, 'uploads', '*', 'create');

        if (!$hasPermission) {
            Flight::json([
                'success' => false,
                'message' => 'Você não tem permissão para fazer uploads'
            ], 403);
            return;
        }

        $files = Flight::request()->files;
        if (empty($files['file'])) {
            Flight::json([
                'success' => false,
                'message' => 'Nenhum arquivo foi enviado'
            ], 400);
            return;
        }

        $relativePath = FileUploadHelper::upload($files['file'], 'general');

        if ($relativePath === false) {
            Flight::json([
                'success' => false,
                'message' => 'Erro ao fazer upload. Verifique o tipo e tamanho do arquivo.'
            ], 400);
            return;
        }

        $url = '/upload/' . $relativePath;

        Flight::json([
            'success' => true,
            'message' => 'Upload realizado com sucesso!',
            'data' => [
                'url' => $url,
                'relativePath' => $relativePath
            ]
        ], 201);
    }

    
    public function delete(string $relativePath): void
    {
        $userId = $_SESSION['user']['id'] ?? null;
        $hasPermission = CasbinHelper::userCan($userId, 'uploads', '*', 'delete');

        if (!$hasPermission) {
            Flight::json([
                'success' => false,
                'message' => 'Você não tem permissão para deletar arquivos'
            ], 403);
            return;
        }

        $relativePath = urldecode($relativePath);

        if (!str_starts_with($relativePath, 'general/')) {
            Flight::json([
                'success' => false,
                'message' => 'Apenas arquivos gerais podem ser deletados por aqui'
            ], 400);
            return;
        }

        if (!FileUploadHelper::exists($relativePath)) {
            Flight::json([
                'success' => false,
                'message' => 'Arquivo não encontrado'
            ], 404);
            return;
        }

        $deleted = FileUploadHelper::delete($relativePath);

        if ($deleted) {
            Flight::json([
                'success' => true,
                'message' => 'Arquivo deletado com sucesso'
            ], 200);
        } else {
            Flight::json([
                'success' => false,
                'message' => 'Erro ao deletar arquivo'
            ], 500);
        }
    }

    
    private function getRecentFiles(int $limit = 30): array
    {
        $generalPath = __DIR__ . '/../storage/upload/general';

        if (!is_dir($generalPath)) {
            return [];
        }

        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($generalPath, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && !str_contains($file->getFilename(), '_thumb')) {
                $relativePath = str_replace(__DIR__ . '/../storage/upload/', '', $file->getPathname());
                $relativePath = str_replace('\\', '/', $relativePath);

                $extension = pathinfo($file->getFilename(), PATHINFO_EXTENSION);
                $isImage = in_array(strtolower($extension), ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg']);

                $files[] = [
                    'filename' => $file->getFilename(),
                    'relativePath' => $relativePath,
                    'url' => '/upload/' . $relativePath,
                    'size' => $file->getSize(),
                    'sizeFormatted' => $this->formatBytes($file->getSize()),
                    'date' => $file->getMTime(),
                    'dateFormatted' => date('d/m/Y H:i', $file->getMTime()),
                    'extension' => $extension,
                    'isImage' => $isImage,
                ];
            }
        }

        for ($i = 0; $i < count($files) - 1; $i++) {
            for ($j = $i + 1; $j < count($files); $j++) {
                if ($files[$j]['date'] > $files[$i]['date']) {
                    $temp = $files[$i];
                    $files[$i] = $files[$j];
                    $files[$j] = $temp;
                }
            }
        }

        return array_slice($files, 0, $limit);
    }

    
    public function browse(): void
    {
        $userId = $_SESSION['user']['id'] ?? null;
        $hasPermission = CasbinHelper::userCan($userId, 'uploads', '*', 'read');

        if (!$hasPermission) {
            Flight::json([
                'success' => false,
                'message' => 'Você não tem permissão para acessar esta página'
            ], 403);
            return;
        }

        $structure = $this->getFolderStructure();

        Flight::json([
            'success' => true,
            'data' => $structure
        ], 200);
    }

    
    public function browseFolder(string $year, string $month, string $day): void
    {
        $userId = $_SESSION['user']['id'] ?? null;
        $hasPermission = CasbinHelper::userCan($userId, 'uploads', '*', 'read');

        if (!$hasPermission) {
            Flight::json([
                'success' => false,
                'message' => 'Você não tem permissão para acessar esta página'
            ], 403);
            return;
        }

        $folderPath = __DIR__ . "/../storage/upload/general/$year/$month/$day";

        if (!is_dir($folderPath)) {
            Flight::json([
                'success' => false,
                'message' => 'Pasta não encontrada'
            ], 404);
            return;
        }

        $files = [];
        $iterator = new \DirectoryIterator($folderPath);

        foreach ($iterator as $file) {
            if ($file->isFile() && !$file->isDot() && !str_contains($file->getFilename(), '_thumb')) {
                $relativePath = "general/$year/$month/$day/" . $file->getFilename();
                $extension = pathinfo($file->getFilename(), PATHINFO_EXTENSION);
                $isImage = in_array(strtolower($extension), ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg']);

                $files[] = [
                    'filename' => $file->getFilename(),
                    'relativePath' => $relativePath,
                    'url' => '/upload/' . $relativePath,
                    'size' => $file->getSize(),
                    'sizeFormatted' => $this->formatBytes($file->getSize()),
                    'date' => $file->getMTime(),
                    'dateFormatted' => date('d/m/Y H:i', $file->getMTime()),
                    'extension' => $extension,
                    'isImage' => $isImage,
                ];
            }
        }

        for ($i = 0; $i < count($files) - 1; $i++) {
            for ($j = $i + 1; $j < count($files); $j++) {
                if (strcmp($files[$i]['filename'], $files[$j]['filename']) > 0) {
                    $temp = $files[$i];
                    $files[$i] = $files[$j];
                    $files[$j] = $temp;
                }
            }
        }

        Flight::json([
            'success' => true,
            'data' => $files
        ], 200);
    }

    
    private function getFolderStructure(): array
    {
        $generalPath = __DIR__ . '/../storage/upload/general';

        if (!is_dir($generalPath)) {
            return [];
        }

        $structure = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($generalPath, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $file) {
            if ($file->isDir()) {
                $pathParts = explode('/', str_replace('\\', '/', $file->getPathname()));
                $depth = $iterator->getDepth();

                if ($depth === 0) {
                    $year = $file->getFilename();
                    if (!isset($structure[$year])) {
                        $structure[$year] = [
                            'year' => $year,
                            'months' => []
                        ];
                    }
                } elseif ($depth === 1) {
                    $year = basename(dirname($file->getPathname()));
                    $month = $file->getFilename();
                    if (!isset($structure[$year]['months'][$month])) {
                        $structure[$year]['months'][$month] = [
                            'month' => $month,
                            'monthName' => $this->getMonthName((int)$month),
                            'days' => []
                        ];
                    }
                } elseif ($depth === 2) {
                    $year = basename(dirname(dirname($file->getPathname())));
                    $month = basename(dirname($file->getPathname()));
                    $day = $file->getFilename();
                    
                    $fileCount = count(glob($file->getPathname() . '/*')) - count(glob($file->getPathname() . '/*_thumb.*'));
                    
                    $structure[$year]['months'][$month]['days'][] = [
                        'day' => $day,
                        'fileCount' => $fileCount,
                        'path' => "$year/$month/$day"
                    ];
                }
            }
        }

        krsort($structure);
        foreach ($structure as &$yearData) {
            krsort($yearData['months']);
            foreach ($yearData['months'] as &$monthData) {
                $days = $monthData['days'];
                for ($i = 0; $i < count($days) - 1; $i++) {
                    for ($j = $i + 1; $j < count($days); $j++) {
                        if ((int)$days[$j]['day'] > (int)$days[$i]['day']) {
                            $temp = $days[$i];
                            $days[$i] = $days[$j];
                            $days[$j] = $temp;
                        }
                    }
                }
                $monthData['days'] = $days;
            }
        }

        return array_values($structure);
    }

    
    private function getMonthName(int $month): string
    {
        $months = [
            1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril',
            5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto',
            9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro'
        ];
        return $months[$month] ?? $month;
    }

    
    private function formatBytes(int $bytes): string
    {
        if ($bytes === 0) return '0 Bytes';

        $k = 1024;
        $sizes = ['Bytes', 'KB', 'MB', 'GB'];
        $i = floor(log($bytes) / log($k));

        return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
    }
}
