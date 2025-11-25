<?php

namespace App\Controllers;

use App\Records\UserRecord;
use Flight;
use Formr\Formr;

class AuthenticationController
{

    public function getLogin()
    {

        Flight::render('page/login.latte', [
            'title' => 'Login',
            'configs' => [
                'allow_registration' => \App\Helpers\ConfigHelper::get('allow_registration', '1')
            ]
        ]);

    }

    public function postLogin(): void
    {
        $login = Flight::request()->data->login;
        $password = Flight::request()->data->password;
        $loginColumn = $_ENV['USER_LOGIN_COLUMN'];

        $userRecord = new UserRecord();
        $userExists = $userRecord
            ->select('id', 'username', 'email', 'password', 'theme', 'created_at')
            ->equal($loginColumn, $login)
            ->find();

        if (!empty($userExists->id)) {
            $databasePassword = $userExists->password;
            if (password_verify($password, $databasePassword)) {
                $userData = $userExists->toArray();
                unset($userData['password']);
                $_SESSION['user'] = $userData;

                $enforcer = Flight::casbin();
                $userRoles = $enforcer->getRolesForUser("user:{$userData['id']}");
                
                $allowUserPanelAccess = \App\Helpers\ConfigHelper::get('allow_user_role_panel_access', '1');
                $userRedirectUrl = \App\Helpers\ConfigHelper::get('user_role_redirect_url', '');
                
                $hasOnlyUserRole = count($userRoles) === 1 && in_array('user', $userRoles);
                
                if ($hasOnlyUserRole && $allowUserPanelAccess === '0' && !empty($userRedirectUrl)) {
                    Flight::response()
                        ->header('HX-Redirect', $userRedirectUrl)
                        ->send();
                    return;
                }

                Flight::response()
                    ->header('HX-Redirect', '/')
                    ->send();
                return;
            } else {
                Flight::response()
                    ->status(401)
                    ->header('HX-Trigger', json_encode([
                        'showToast' => [
                            'message' => 'Credenciais inválidas! Verifique seu usuário e senha.',
                            'type' => 'error'
                        ]
                    ]));

                Flight::render('component/login-form.latte', [
                    'error' => 'Credenciais inválidas! Verifique seu usuário e senha.',
                    'login' => $login,
                    'configs' => [
                        'allow_registration' => \App\Helpers\ConfigHelper::get('allow_registration', '1')
                    ]
                ]);
                return;
            }
        } else {
            Flight::response()
                ->status(401)
                ->header('HX-Trigger', json_encode([
                    'showToast' => [
                        'message' => 'Credenciais inválidas! Verifique seu usuário e senha.',
                        'type' => 'error'
                    ]
                ]));

            Flight::render('component/login-form.latte', [
                'error' => 'Credenciais inválidas! Verifique seu usuário e senha.',
                'login' => $login,
                'configs' => [
                    'allow_registration' => \App\Helpers\ConfigHelper::get('allow_registration', '1')
                ]
            ]);
            return;
        }
    }

    public function getRegister(): void
    {
        $allowRegistration = \App\Helpers\ConfigHelper::get('allow_registration', '1');

        if ($allowRegistration !== '1') {
            Flight::redirect('/login');
            return;
        }

        Flight::render('page/register.latte', [
            'title' => 'Registro'
        ]);
    }

    public function postRegister(): void
    {
        $allowRegistration = \App\Helpers\ConfigHelper::get('allow_registration', '1');

        if ($allowRegistration !== '1') {
            Flight::json([
                'success' => false,
                'message' => 'Registro de novos usuários está desabilitado'
            ], 403);
            return;
        }

        $username = Flight::request()->data->username;
        $email = Flight::request()->data->email;
        $password = Flight::request()->data->password;
        $passwordConfirm = Flight::request()->data->password_confirm;

        if (empty($username) || empty($email) || empty($password)) {
            Flight::render('', [
                'htmx' => [
                    'oob' => true,
                    'triggers' => [
                        'toast' => [
                            'text' => 'Preencha todos os campos obrigatórios!',
                            'type' => 'error'
                        ]
                    ]
                ]
            ]);
            return;
        }

        if ($password !== $passwordConfirm) {
            Flight::render('', [
                'htmx' => [
                    'oob' => true,
                    'triggers' => [
                        'toast' => [
                            'text' => 'As senhas não coincidem!',
                            'type' => 'error'
                        ]
                    ]
                ]
            ]);
            return;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Flight::render('', [
                'htmx' => [
                    'oob' => true,
                    'triggers' => [
                        'toast' => [
                            'text' => 'Email inválido!',
                            'type' => 'error'
                        ]
                    ]
                ]
            ]);
            return;
        }

        $userRecord = new UserRecord();

        $usernameExists = $userRecord
            ->select('id')
            ->equal('username', $username)
            ->find();

        if (!empty($usernameExists->id)) {
            Flight::render('', [
                'htmx' => [
                    'oob' => true,
                    'triggers' => [
                        'toast' => [
                            'text' => 'Nome de usuário já está em uso!',
                            'type' => 'error'
                        ]
                    ]
                ]
            ]);
            return;
        }

        $emailExists = $userRecord
            ->select('id')
            ->equal('email', $email)
            ->find();

        if (!empty($emailExists->id)) {
            Flight::render('', [
                'htmx' => [
                    'oob' => true,
                    'triggers' => [
                        'toast' => [
                            'text' => 'Email já está cadastrado!',
                            'type' => 'error'
                        ]
                    ]
                ]
            ]);
            return;
        }

        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        $newUser = new UserRecord();
        $newUser->username = $username;
        $newUser->email = $email;
        $newUser->password = $hashedPassword;

        try {
            $newUser->save();

            $enforcer = Flight::casbin();
            $enforcer->addRoleForUser("user:{$newUser->id}", "user");

            $userData = $newUser->toArray();
            unset($userData['password']);
            $_SESSION['user'] = $userData;

            Flight::redirect('/');
        } catch (\Exception $e) {
            Flight::render('', [
                'htmx' => [
                    'oob' => true,
                    'triggers' => [
                        'toast' => [
                            'text' => 'Erro ao criar usuário. Tente novamente.',
                            'type' => 'error'
                        ]
                    ]
                ]
            ]);
        }
    }

    public function getLogout(): void
    {
        $_SESSION = [];

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }

        Flight::redirect('/login');
    }
}
