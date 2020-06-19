<?php

namespace Benzine\Middleware;

use Slim\Psr7\Request;
use Slim\Psr7\Response;

class ForceSSLMiddleware
{
    public function __invoke(Request $request, Response $response, $next)
    {
        // @var Response $response
        if ('80' == $request->getServerParam('SERVER_PORT')
            && 'https' != $request->getServerParam('HTTP_X_FORWARDED_PROTO')
            && 'yes' == strtolower($request->getServerParam('FORCE_HTTPS'))
        ) {
            return $response->withRedirect('https://'.$request->getServerParam('HTTP_HOST').'/'.ltrim($request->getServerParam('REQUEST_URI'), '/'));
        }

        return $next($request, $response);
    }
}
