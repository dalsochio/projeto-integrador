<?php

namespace App\Helpers;

use Flight;
class AuditLogger
{
    
    public static function log(
        ?int   $userId,
        string $action,
        string $tableName,
               $recordId = null,
        ?array $data = null,
        ?array $changes = null,
        string $routeType = 'panel'
    ): bool
    {
        try {
            if (!in_array(strtoupper($action), ['POST', 'PUT', 'PATCH', 'DELETE'])) {
                return false;
            }

            $db = Flight::db();

            $table = $_ENV['DB_TABLE_PREFIX'] . 'log';

            $sql = "INSERT INTO `{$table}` (
                user_id, action, table_name, record_id, data, changes,
                ip_address, user_agent, route_type, created_at
            ) VALUES (
                :user_id, :action, :table_name, :record_id, :data, :changes,
                :ip_address, :user_agent, :route_type, :created_at
            )";

            $stmt = $db->prepare($sql);

            return $stmt->execute([
                ':user_id' => $userId,
                ':action' => strtoupper($action),
                ':table_name' => $tableName,
                ':record_id' => $recordId ? (string)$recordId : null,
                ':data' => $data ? json_encode($data, JSON_UNESCAPED_UNICODE) : null,
                ':changes' => $changes ? json_encode($changes, JSON_UNESCAPED_UNICODE) : null,
                ':ip_address' => self::getClientIp(),
                ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                ':route_type' => $routeType,
                ':created_at' => date('Y-m-d H:i:s')
            ]);

        } catch (\Exception $e) {
            return false;
        }
    }

    
    private static function getClientIp(): ?string
    {
        $ipHeaders = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'REMOTE_ADDR'
        ];

        foreach ($ipHeaders as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];

                if (strpos($ip, ',') !== false) {
                    $ips = explode(',', $ip);
                    $ip = trim($ips[0]);
                }

                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return null;
    }

    
    public static function logFromApiRequest($request, $response, string $method): bool
    {
        try {
            $userId = $_SESSION['user']['id'] ?? null;

            $pathSegments = explode('/', trim($request->getUri()->getPath(), '/'));

            $tableName = $pathSegments[2] ?? null;
            $recordId = $pathSegments[3] ?? null;

            if (!$tableName) {
                return false;
            }

            if ($method === 'POST' && !$recordId) {
                $response->getBody()->rewind();
                $responseBody = json_decode($response->getBody()->getContents(), true);
                $recordId = $responseBody['id'] ?? $responseBody['lastInsertId'] ?? null;
            }

            $data = $request->getParsedBody();

            if ($data === null) {
                $body = (string) $request->getBody();
                if (!empty($body)) {
                    $data = json_decode($body, true);
                }
            }

            return self::log(
                $userId,
                $method,
                $tableName,
                $recordId,
                is_array($data) ? $data : null,
                null,
                'api'
            );

        } catch (\Exception $e) {
            return false;
        }
    }

    
    public static function logFromResourceRoute(
        string $table,
        string $action,
               $recordId = null,
        ?array $data = null
    ): bool
    {
        $user = $_SESSION['user'] ?? null;
        $userId = $user['id'] ?? null;

        $methodMap = [
            'create' => 'POST',
            'update' => 'PUT',
            'delete' => 'DELETE',
            'edit' => 'PATCH'
        ];

        $method = $methodMap[$action] ?? strtoupper($action);

        return self::log(
            $userId,
            $method,
            $table,
            $recordId,
            $data,
            null,
            'panel'
        );
    }
}
