<?php

namespace App\Controllers;

use App\Helpers\CasbinHelper;
use App\Records\LogRecord;
use App\Records\TableRecord;
use App\Records\UserRecord;
use Flight;

class WelcomeController
{
    public function getDashboard(): void
    {
        $userId = $_SESSION['user']['id'] ?? null;
        
        $stats = [];
        $recentActivities = [];
        
        // Verificar permissões e buscar estatísticas
        if (CasbinHelper::userCan($userId, 'user', '*', 'read')) {
            $userRecord = new UserRecord();
            $result = $userRecord->select('COUNT(*) as total')->find();
            $stats['users'] = $result->total ?? 0;
        }
        
        if (CasbinHelper::userCan($userId, 'table', '*', 'read')) {
            $tableRecord = new TableRecord();
            $result = $tableRecord->equal('is_active', 1)->select('COUNT(*) as total')->find();
            $stats['modules'] = $result->total ?? 0;
        }
        
        if (CasbinHelper::userCan($userId, 'uploads', '*', 'read')) {
            $uploadStats = $this->getUploadStats();
            $stats['uploads'] = $uploadStats['count'];
        }
        
        if (CasbinHelper::userCan($userId, 'log', '*', 'read')) {
            $logRecord = new LogRecord();
            $today = date('Y-m-d');
            $result = $logRecord->startWrap()
                ->greaterThanOrEqual('created_at', $today . ' 00:00:00')
                ->lessThan('created_at', date('Y-m-d', strtotime($today . ' +1 day')) . ' 00:00:00')
                ->endWrap('AND')
                ->select('COUNT(*) as total')
                ->find();
            $stats['todayActions'] = $result->total ?? 0;
            
            // Buscar últimas 5 atividades
            $logRecordQuery = new LogRecord();
            $logs = $logRecordQuery
                ->select('action', 'table_name', 'record_id', 'created_at', 'user_id')
                ->orderBy('created_at DESC')
                ->limit(5)
                ->findAll();
            
            // Buscar todos os user_ids únicos
            $userIds = [];
            foreach ($logs as $log) {
                if ($log->user_id) {
                    $userIds[$log->user_id] = true;
                }
            }
            
            // Buscar todos os usuários de uma vez
            $users = [];
            if (!empty($userIds)) {
                $userRecord = new UserRecord();
                $usersList = $userRecord->in('id', array_keys($userIds))->findAll();
                foreach ($usersList as $user) {
                    $users[$user->id] = $user->name;
                }
            }
            
            // Montar array de atividades
            foreach ($logs as $log) {
                $recentActivities[] = [
                    'action' => $log->action,
                    'table_name' => $log->table_name,
                    'record_id' => $log->record_id,
                    'created_at' => $log->created_at,
                    'user_name' => $users[$log->user_id] ?? 'Sistema'
                ];
            }
        }
        
        Flight::render('page/welcome/index.latte', [
            'title' => 'Bem vindo',
            'stats' => $stats,
            'recentActivities' => $recentActivities
        ]);
    }
    
    private function getUploadStats(): array
    {
        $uploadPath = __DIR__ . '/../storage/upload';
        
        if (!is_dir($uploadPath)) {
            return ['count' => 0];
        }
        
        $totalFiles = 0;
        
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($uploadPath, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile() && !str_contains($file->getFilename(), '_thumb')) {
                $totalFiles++;
            }
        }
        
        return ['count' => $totalFiles];
    }
}
