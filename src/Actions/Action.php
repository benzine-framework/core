<?php

declare(strict_types=1);

namespace Benzine\Actions;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Slim\Exception\HttpBadRequestException;
use Slim\Views\Twig;

abstract class Action
{
    protected Request $request;

    protected Response $response;

    protected array $args;

    public function __construct(
        protected LoggerInterface $logger,
        protected Twig $twig,
    ) {
        $this->response = new \Slim\Psr7\Response();
    }

    /**
     * @return array|object
     */
    protected function getFormData()
    {
        return $this->request->getParsedBody();
    }

    /**
     * @throws HttpBadRequestException
     */
    protected function resolveArg(string $name)
    {
        if (!isset($this->args[$name])) {
            throw new HttpBadRequestException($this->request, "Could not resolve argument `{$name}`.");
        }

        return $this->args[$name];
    }

    /**
     * @param null|array|object $data
     */
    protected function respondWithData($data = null, int $statusCode = 200): Response
    {
        $payload = new ActionPayload($statusCode, $data);

        return $this->respond($payload);
    }

    protected function respond(ActionPayload $payload): Response
    {
        $json = json_encode($payload, JSON_PRETTY_PRINT);
        $this->response->getBody()->write($json);

        return $this->response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($payload->getStatusCode())
        ;
    }

    protected function redirect(string $redirectUrl): Response
    {
        return $this->response
            ->withHeader('Location', $redirectUrl)
            ->withStatus(302)
        ;
    }

    protected function render($response, string $template, array $data = []): Response
    {
        return $this->twig->render(
            $response,
            $template,
            $data
        )->withHeader('Content-Type', 'text/html');
    }
}
