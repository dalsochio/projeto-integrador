<?php

namespace App\Helpers;

use Tqdev\PhpCrudApi\OpenApi\OpenApiDefinition;
use Tqdev\PhpCrudApi\Column\ReflectionService;

class AuthOpenApiBuilder
{
    private $openapi;
    
    public function __construct(OpenApiDefinition $openapi, ReflectionService $reflection)
    {
        $this->openapi = $openapi;
    }
    
    public function build()
    {
        // Adicionar tag primeiro para aparecer no topo
        $this->addAuthTag();
        $this->addLoginRoutes();
        $this->addRegisterRoutes();
        $this->addLogoutRoute();
        $this->addSchemas();
    }
    
    private function addLoginRoutes()
    {
        // POST /login
        $this->openapi->set("paths|/login|post|tags|", "authentication");
        $this->openapi->set("paths|/login|post|summary", "User login");
        $this->openapi->set("paths|/login|post|description", "Authenticate user with username/email and password");
        $this->openapi->set("paths|/login|post|operationId", "login");
        $this->openapi->set("paths|/login|post|requestBody|required", true);
        $this->openapi->set("paths|/login|post|requestBody|content|application/json|schema|\$ref", "#/components/schemas/LoginRequest");
        $this->openapi->set("paths|/login|post|responses|200|description", "Login successful");
        $this->openapi->set("paths|/login|post|responses|200|content|application/json|schema|\$ref", "#/components/schemas/LoginResponse");
        $this->openapi->set("paths|/login|post|responses|401|description", "Invalid credentials");
        $this->openapi->set("paths|/login|post|responses|401|content|application/json|schema|\$ref", "#/components/schemas/ErrorResponse");
        
        // GET /login (página HTML)
        $this->openapi->set("paths|/login|get|tags|", "authentication");
        $this->openapi->set("paths|/login|get|summary", "Login page");
        $this->openapi->set("paths|/login|get|description", "Display login form (HTML)");
        $this->openapi->set("paths|/login|get|operationId", "getLoginPage");
        $this->openapi->set("paths|/login|get|responses|200|description", "Login form HTML page");
        $this->openapi->set("paths|/login|get|responses|200|content|text/html|schema|type", "string");
    }
    
    private function addRegisterRoutes()
    {
        // POST /register
        $this->openapi->set("paths|/register|post|tags|", "authentication");
        $this->openapi->set("paths|/register|post|summary", "User registration");
        $this->openapi->set("paths|/register|post|description", "Register a new user account");
        $this->openapi->set("paths|/register|post|operationId", "register");
        $this->openapi->set("paths|/register|post|requestBody|required", true);
        $this->openapi->set("paths|/register|post|requestBody|content|application/json|schema|\$ref", "#/components/schemas/RegisterRequest");
        $this->openapi->set("paths|/register|post|responses|200|description", "Registration successful");
        $this->openapi->set("paths|/register|post|responses|200|content|application/json|schema|\$ref", "#/components/schemas/RegisterResponse");
        $this->openapi->set("paths|/register|post|responses|422|description", "Validation error");
        $this->openapi->set("paths|/register|post|responses|422|content|application/json|schema|\$ref", "#/components/schemas/ValidationErrorResponse");
        
        // GET /register (página HTML)
        $this->openapi->set("paths|/register|get|tags|", "authentication");
        $this->openapi->set("paths|/register|get|summary", "Registration page");
        $this->openapi->set("paths|/register|get|description", "Display registration form (HTML)");
        $this->openapi->set("paths|/register|get|operationId", "getRegisterPage");
        $this->openapi->set("paths|/register|get|responses|200|description", "Registration form HTML page");
        $this->openapi->set("paths|/register|get|responses|200|content|text/html|schema|type", "string");
    }
    
    private function addLogoutRoute()
    {
        $this->openapi->set("paths|/logout|get|tags|", "authentication");
        $this->openapi->set("paths|/logout|get|summary", "User logout");
        $this->openapi->set("paths|/logout|get|description", "Logout current user and destroy session");
        $this->openapi->set("paths|/logout|get|operationId", "logout");
        $this->openapi->set("paths|/logout|get|responses|302|description", "Redirect to login page");
        $this->openapi->set("paths|/logout|get|responses|302|headers|Location|schema|type", "string");
    }
    
    private function addAuthTag()
    {
        $this->openapi->set("tags|", [
            'name' => 'authentication',
            'description' => 'Authentication operations (login, register, logout)',
            'x-displayOrder' => -1  // Propriedade customizada para ordenar no topo
        ]);
    }
    
    private function addSchemas()
    {
        // LoginRequest schema
        $this->openapi->set("components|schemas|LoginRequest|type", "object");
        $this->openapi->set("components|schemas|LoginRequest|required", ['login', 'password']);
        $this->openapi->set("components|schemas|LoginRequest|properties|login|type", "string");
        $this->openapi->set("components|schemas|LoginRequest|properties|login|description", "Username or email");
        $this->openapi->set("components|schemas|LoginRequest|properties|login|example", "admin");
        $this->openapi->set("components|schemas|LoginRequest|properties|password|type", "string");
        $this->openapi->set("components|schemas|LoginRequest|properties|password|format", "password");
        $this->openapi->set("components|schemas|LoginRequest|properties|password|description", "User password");
        $this->openapi->set("components|schemas|LoginRequest|properties|password|example", "password123");
        
        // LoginResponse schema
        $this->openapi->set("components|schemas|LoginResponse|type", "object");
        $this->openapi->set("components|schemas|LoginResponse|properties|success|type", "boolean");
        $this->openapi->set("components|schemas|LoginResponse|properties|success|example", true);
        $this->openapi->set("components|schemas|LoginResponse|properties|message|type", "string");
        $this->openapi->set("components|schemas|LoginResponse|properties|message|example", "Login successful");
        $this->openapi->set("components|schemas|LoginResponse|properties|user|type", "object");
        $this->openapi->set("components|schemas|LoginResponse|properties|user|properties|id|type", "integer");
        $this->openapi->set("components|schemas|LoginResponse|properties|user|properties|username|type", "string");
        $this->openapi->set("components|schemas|LoginResponse|properties|user|properties|email|type", "string");
        
        // RegisterRequest schema
        $this->openapi->set("components|schemas|RegisterRequest|type", "object");
        $this->openapi->set("components|schemas|RegisterRequest|required", ['username', 'email', 'password']);
        $this->openapi->set("components|schemas|RegisterRequest|properties|username|type", "string");
        $this->openapi->set("components|schemas|RegisterRequest|properties|username|description", "Unique username");
        $this->openapi->set("components|schemas|RegisterRequest|properties|username|example", "johndoe");
        $this->openapi->set("components|schemas|RegisterRequest|properties|email|type", "string");
        $this->openapi->set("components|schemas|RegisterRequest|properties|email|format", "email");
        $this->openapi->set("components|schemas|RegisterRequest|properties|email|description", "Valid email address");
        $this->openapi->set("components|schemas|RegisterRequest|properties|email|example", "john@example.com");
        $this->openapi->set("components|schemas|RegisterRequest|properties|password|type", "string");
        $this->openapi->set("components|schemas|RegisterRequest|properties|password|format", "password");
        $this->openapi->set("components|schemas|RegisterRequest|properties|password|minLength", 12);
        $this->openapi->set("components|schemas|RegisterRequest|properties|password|description", "Password (min 12 characters)");
        $this->openapi->set("components|schemas|RegisterRequest|properties|password|example", "securePassword123");
        
        // RegisterResponse schema
        $this->openapi->set("components|schemas|RegisterResponse|type", "object");
        $this->openapi->set("components|schemas|RegisterResponse|properties|success|type", "boolean");
        $this->openapi->set("components|schemas|RegisterResponse|properties|success|example", true);
        $this->openapi->set("components|schemas|RegisterResponse|properties|message|type", "string");
        $this->openapi->set("components|schemas|RegisterResponse|properties|message|example", "Registration successful");
        $this->openapi->set("components|schemas|RegisterResponse|properties|user|type", "object");
        $this->openapi->set("components|schemas|RegisterResponse|properties|user|properties|id|type", "integer");
        $this->openapi->set("components|schemas|RegisterResponse|properties|user|properties|username|type", "string");
        $this->openapi->set("components|schemas|RegisterResponse|properties|user|properties|email|type", "string");
        
        // ErrorResponse schema
        $this->openapi->set("components|schemas|ErrorResponse|type", "object");
        $this->openapi->set("components|schemas|ErrorResponse|properties|success|type", "boolean");
        $this->openapi->set("components|schemas|ErrorResponse|properties|success|example", false);
        $this->openapi->set("components|schemas|ErrorResponse|properties|message|type", "string");
        $this->openapi->set("components|schemas|ErrorResponse|properties|message|example", "Invalid credentials");
        
        // ValidationErrorResponse schema
        $this->openapi->set("components|schemas|ValidationErrorResponse|type", "object");
        $this->openapi->set("components|schemas|ValidationErrorResponse|properties|success|type", "boolean");
        $this->openapi->set("components|schemas|ValidationErrorResponse|properties|success|example", false);
        $this->openapi->set("components|schemas|ValidationErrorResponse|properties|message|type", "string");
        $this->openapi->set("components|schemas|ValidationErrorResponse|properties|message|example", "Validation failed");
        $this->openapi->set("components|schemas|ValidationErrorResponse|properties|errors|type", "object");
        $this->openapi->set("components|schemas|ValidationErrorResponse|properties|errors|additionalProperties|type", "array");
        $this->openapi->set("components|schemas|ValidationErrorResponse|properties|errors|additionalProperties|items|type", "string");
    }
}
