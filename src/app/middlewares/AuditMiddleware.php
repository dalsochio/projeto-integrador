<?php

namespace App\Middlewares;

use App\Helpers\AuditLogger;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Tqdev\PhpCrudApi\Middleware\Base\Middleware;
class AuditMiddleware extends Middleware
{
    
    public function process(ServerRequestInterface $request, RequestHandlerInterface $next): ResponseInterface
    {
        $method = $request->getMethod();

        $response = $next->handle($request);

        if (
            in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'])
            && $response->getStatusCode() < 300
            && $this->isRecordsRoute($request)
        ) {
            AuditLogger::logFromApiRequest($request, $response, $method);
        }

        return $response;
    }

    
    private function isRecordsRoute(ServerRequestInterface $request): bool
    {
        $path = $request->getUri()->getPath();
        return strpos($path, '/records/') !== false;
    }
}
