<?php

namespace Benzine\Middleware;

use Faker\Factory;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Route;

class AccessRequirements
{
    private $defaultRequirements = '';
    private $defaultVar = 'User';

    private $defaultRejectStatus = 401;
    private $defaultRejectBody = 'Unauthorized';

    private $rejectMap = [];

    private $hasSession = false;

    public function __construct()
    {
        if (class_exists(\⌬\Session\Session::class, false)) {
            $this->hasSession = true;
        }
    }

    public function __invoke(Request $request, Response $response, $next)
    {
        if ($this->hasSession) {
            /** @var \Slim\Route $route */
            $route = $request->getAttribute('route');
            if ($route) {
                $requirements = $this->getRequirementsFromRoute($route);
                $var = $this->getVarFromRoute($route);
                if (!empty($requirements)) {
                    if ($var) {
                        foreach ($requirements as $requirement) {
                            if (!$this->testRequirement($requirement, $var)) {
                                return $this->rejectRequest($request, $response, $requirement);
                            }
                        }
                    } else {
                        return $this->rejectRequest($request, $response, 'NO_VAR');
                    }
                }
            }
        }

        return $next($request, $response);
    }

    public static function Factory(): AccessRequirements
    {
        return new self();
    }

    public function setDefaultRequirements($requirements = []): AccessRequirements
    {
        if (is_array($requirements)) {
            $requirements = $this->cleanRequirementsArray($requirements);
            $requirements = implode(',', $requirements);
        }
        $this->defaultRequirements = $requirements;

        return $this;
    }

    public function setDefaultVar(string $var): AccessRequirements
    {
        $this->defaultVar = $var;

        return $this;
    }

    /**
     * @param array|string $requirements
     * @param int          $status
     * @param string       $body
     *
     * @return AccessRequirements
     */
    public function mapRejection($requirements, int $status, string $body): AccessRequirements
    {
        if (!is_array($requirements)) {
            $requirements = [$requirements];
        }

        foreach ($requirements as $requirement) {
            $this->rejectMap[strtoupper($requirement)] = [
                'status' => $status,
                'body' => $body,
            ];
        }

        return $this;
    }

    private function getRejection(string $requirement): array
    {
        return $this->rejectMap[strtoupper($requirement)] ?? [];
    }

    private function cleanRequirementsArray($requirements)
    {
        $requirements = array_map('trim', $requirements);
        $requirements = array_unique($requirements);

        return array_filter($requirements);
    }

    private function rejectRequest(Request $request, Response $response, string $requirement)
    {
        $rejection = $this->getRejection($requirement);
        $status = $rejection['status'] ?? $this->defaultRejectStatus;
        $body = $rejection['body'] ?? $this->defaultRejectBody;
        if ($status >= 300 && $status < 400) {
            return $response->withRedirect($body);
        }

        return $response->withStatus($status)->write($body);
    }

    /**
     * @param Route $route
     *
     * @return string[]
     */
    private function getRequirementsFromRoute(Route $route): array
    {
        $requirements = $route->getArgument('_accessRequirements') ?? $this->defaultRequirements;
        $requirements = explode(',', $requirements);

        $plus = array_search('+', $requirements, true);
        if (false !== $plus) {
            unset($requirements[$plus]);
            $requirements = array_merge($this->defaultRequirements, $requirements);
        }

        return $this->cleanRequirementsArray($requirements);
    }

    /**
     * @param Route $route
     *
     * @return mixed
     */
    private function getVarFromRoute(Route $route)
    {
        $varname = $route->getArgument('_accessVar') ?? $this->defaultVar;

        return \⌬\Session\Session::get($varname);
    }

    /**
     * @param string $requirement
     * @param $var
     *
     * @return bool
     */
    private function testRequirement(string $requirement, $var): bool
    {
        $_requirement = $requirement;
        $not = false;
        if (0 === strpos($_requirement, '!')) {
            $_requirement = ltrim($_requirement, '!');
            $not = true;
        }
        $methodName = 'is'.ucfirst($_requirement);
        $result = $var->{$methodName}();
        if ($not) {
            $result = !$result;
        }

        return $result;
    }
}
