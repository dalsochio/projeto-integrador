<?php

use App\Controllers\AccountController;
use App\Controllers\AuditController;
use App\Controllers\AuthenticationController;
use App\Controllers\ConfigController;
use App\Controllers\WelcomeController;
use App\Controllers\DeveloperController;
use App\Controllers\ModulesController;
use App\Controllers\ResourceController;
use App\Controllers\RolesController;
use App\Controllers\UserController;
use App\Helpers\AuditLogger;
use App\Helpers\MenuHelper;
use App\Helpers\FlightResourceWrapper;
use App\Middlewares\ResourceMiddleware;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7Server\ServerRequestCreator;
function authenticationMiddleware(): callable
{
    return function () {
        if (!isset($_SESSION['user'])) {
            Flight::redirect('/login');
            return false;
        }

        $allowUserPanelAccess = \App\Helpers\ConfigHelper::get('allow_user_role_panel_access', '1');
        $userRedirectUrl = \App\Helpers\ConfigHelper::get('user_role_redirect_url', '');
        
        if ($allowUserPanelAccess === '0' && !empty($userRedirectUrl)) {
            $enforcer = Flight::casbin();
            $userRoles = $enforcer->getRolesForUser("user:{$_SESSION['user']['id']}");
            
            $hasOnlyUserRole = count($userRoles) === 1 && in_array('user', $userRoles);
            
            if ($hasOnlyUserRole) {
                Flight::redirect($userRedirectUrl);
                return false;
            }
        }

        return true;
    };
}
function authorizationMiddleware(string $table, string $column = '*', string $action = '*'): callable
{
    return function () use ($table, $column, $action) {
        $user = $_SESSION['user'] ?? null;

        if (!$user) {
            $isApiRequest = str_starts_with(Flight::request()->url, '/api/');

            if ($isApiRequest) {
                Flight::json([
                    'code' => 1011,
                    'message' => 'Authentication required'
                ], 401);
            } else {
                Flight::redirect('/login');
            }
            return false;
        }

        $enforcer = Flight::casbin();

        $userId = "user:{$user['id']}";

        if ($action === '*') {
            $possibleActions = ['list', 'read', 'create', 'update', 'delete'];
            $allowed = false;

            foreach ($possibleActions as $testAction) {
                if ($enforcer->enforce($userId, $table, $column, $testAction)) {
                    $allowed = true;
                    break;
                }
            }
        } else {
            $allowed = $enforcer->enforce($userId, $table, $column, $action);
        }

        if (!$allowed) {
            $isApiRequest = str_starts_with(Flight::request()->url, '/api/');

            if ($isApiRequest) {
                Flight::json([
                    'code' => 1003,
                    'message' => 'Forbidden',
                    'error' => "Usuário '{$user['username']}' não tem permissão para executar '{$action}' em '{$table}.{$column}'",
                    'details' => [
                        'user_id' => $user['id'],
                        'username' => $user['username'],
                        'roles' => $user['roles'] ?? null,
                        'table' => $table,
                        'column' => $column,
                        'action' => $action
                    ]
                ], 403);
            } else {
                Flight::response()->status(403);
                Flight::render('error/403.latte', [
                    'title' => 'Acesso Negado',
                    'message' => 'Você não tem permissão para acessar este recurso.'
                ]);
            }
            return false;
        }

        return true;
    };
}
function handleApiRequest(bool $enhanceMetadata = false): void
{
    try {
        $api = Flight::api();

        $psr17Factory = new Psr17Factory();
        $creator = new ServerRequestCreator(
            $psr17Factory,
            $psr17Factory,
            $psr17Factory,
            $psr17Factory
        );

        $psrRequest = $creator->fromGlobals();
        $psrResponse = $api->handle($psrRequest);

        if (!$psrResponse || !method_exists($psrResponse, 'getStatusCode')) {
            throw new \Exception('Invalid PSR-7 response from API');
        }

        $body = (string)$psrResponse->getBody();
        if ($enhanceMetadata) {
            $data = json_decode($body, true);
            if ($data && !isset($data['code'])) {
                $data = \App\Helpers\ColumnMetadataCustomizer::enhance($data);
                $body = json_encode($data);
            }
        }

        foreach ($psrResponse->getHeaders() as $name => $values) {
            foreach ($values as $value) {
                Flight::response()->header($name, $value);
            }
        }

        Flight::response()->status($psrResponse->getStatusCode());

        if ($enhanceMetadata) {
            Flight::response()->write($body);
        } else {
            Flight::response()->write((string)$psrResponse->getBody());
        }

        if ($psrResponse->getStatusCode() < 300) {
            $method = $psrRequest->getMethod();
            $modifyingMethods = ['POST', 'PUT', 'DELETE'];

            if (in_array(strtoupper($method), $modifyingMethods)) {
                AuditLogger::logFromApiRequest($psrRequest, $psrResponse, $method);
            }
        }
    } catch (\Exception $e) {
        Flight::json([
            'code' => 9999,
            'message' => $e->getMessage(),
            'trace' => $_ENV['APP_ENV'] === 'development' ? $e->getTraceAsString() : null
        ], 500);
    }
}
$router = Flight::router();
Flight::group('/api', function () {
    
    Flight::route('GET|POST|PUT|DELETE|PATCH /*', function () {
        handleApiRequest(false);
    });
});
Flight::group('', function () {
    Flight::route('GET /', [WelcomeController::class, 'getDashboard']);
}, [authenticationMiddleware()]);

Flight::route('GET /login', [AuthenticationController::class, 'getLogin']);
Flight::route('POST /login', [AuthenticationController::class, 'postLogin']);

Flight::route('GET /register', [AuthenticationController::class, 'getRegister']);
Flight::route('POST /register', [AuthenticationController::class, 'postRegister']);

Flight::route('GET /logout', [AuthenticationController::class, 'getLogout']);

Flight::group('/account', function () {
    Flight::route('GET /', [AccountController::class, 'index']);
    Flight::route('PUT /', [AccountController::class, 'update']);
    Flight::route('PUT /theme', [AccountController::class, 'updateTheme']);
}, [authenticationMiddleware()]);

Flight::route('GET /developer/swagger', [DeveloperController::class, 'swagger']);

Flight::route('GET /upload/@module/@year:[0-9]{4}/@month:[0-9]{2}/@day:[0-9]{2}/@filename', function($module, $year, $month, $day, $filename) {
    $controller = new \App\Controllers\UploadController(Flight::app());
    $controller->serve($module, $year, $month, $day, $filename);
});

Flight::group('/panel/uploads', function () {
    Flight::route('GET /', function() {
        $controller = new \App\Controllers\UploadController(Flight::app());
        $controller->index();
    });

    Flight::route('GET /create', function() {
        $controller = new \App\Controllers\UploadController(Flight::app());
        $controller->create();
    });

    Flight::route('POST /', function() {
        $controller = new \App\Controllers\UploadController(Flight::app());
        $controller->store();
    });

    Flight::route('GET /browse', function() {
        $controller = new \App\Controllers\UploadController(Flight::app());
        $controller->browse();
    });

    Flight::route('GET /browse/@year:[0-9]{4}/@month:[0-9]{2}/@day:[0-9]{2}', function($year, $month, $day) {
        $controller = new \App\Controllers\UploadController(Flight::app());
        $controller->browseFolder($year, $month, $day);
    });

    Flight::route('DELETE /*', function($relativePath) {
        $controller = new \App\Controllers\UploadController(Flight::app());
        $controller->delete($relativePath);
    });
}, [authenticationMiddleware(), authorizationMiddleware('uploads', '*', '*')]);

Flight::group('/panel/module', function () {
    FlightResourceWrapper::register('', ModulesController::class);
    Flight::route('POST /@id:[0-9]+/fields', [ModulesController::class, 'updateFields']);
    Flight::route('DELETE /@id:[0-9]+', [ModulesController::class, 'destroy']);
    Flight::route('GET /columns/@moduleName', [ModulesController::class, 'getModuleColumns']);
}, [authenticationMiddleware(), authorizationMiddleware('panel_table', '*', '*')]);

Flight::group('/panel/category', function () {
    Flight::route('POST /@id:[0-9]+/modules-order', [\App\Controllers\CategoryController::class, 'updateModulesOrder']);
    Flight::route('DELETE /@id:[0-9]+', [\App\Controllers\CategoryController::class, 'destroy']);
    FlightResourceWrapper::register('', \App\Controllers\CategoryController::class);
}, [authenticationMiddleware(), authorizationMiddleware('panel_category', '*', '*')]);

Flight::group('/panel/role', function () {
    FlightResourceWrapper::register('', RolesController::class);
    Flight::route('POST /@id:[0-9]+/permissions', [RolesController::class, 'updatePermissions']);
    Flight::route('DELETE /@id:[0-9]+', [RolesController::class, 'destroy']);
}, [authenticationMiddleware(), authorizationMiddleware('panel_role_info', '*', '*')]);

Flight::group('/administration/user', function () {
    FlightResourceWrapper::register('', UserController::class);
    Flight::route('POST /@id:[0-9]+/toggle-status', [UserController::class, 'toggleStatus']);
}, [authenticationMiddleware(), authorizationMiddleware('user', '*', '*')]);

Flight::group('/panel/audit', function () {
    Flight::route('GET /', [\App\Controllers\AuditController::class, 'index']);
    Flight::route('GET /@id:[0-9]+', [\App\Controllers\AuditController::class, 'show']);
}, [authenticationMiddleware(), authorizationMiddleware('panel_log', '*', 'read')]);

Flight::group('/panel/config', function () {
    Flight::route('GET /', [ConfigController::class, 'index']);
    Flight::route('POST /', [ConfigController::class, 'update']);
}, [authenticationMiddleware()]);

FlightResourceWrapper::register('/@category/@resource', ResourceController::class, [ResourceMiddleware::class]);
Flight::map('notFound', function () {
    $path = Flight::request()->url;
    Flight::json([
        'code' => 404,
        'message' => 'Not Found',
        'error' => "Rota não encontrada: {$path}",
        'suggestion' => 'Consulte os endpoints disponíveis em GET /'
    ], 404);
});
