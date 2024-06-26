<?php

declare(strict_types=1);

namespace Benzine\Controllers;

use Benzine\ORM\Abstracts\AbstractService;
use Benzine\ORM\Interfaces\ModelInterface;
use Laminas\Db\Adapter\Exception\InvalidQueryException;
use Slim\Psr7\Request;
use Slim\Psr7\Response;

abstract class AbstractCrudController extends AbstractController
{
    abstract protected function getService(): AbstractService;

    public function listRequest(Request $request, Response $response): Response
    {
        $objects = [];
        $service = $this->getService();
        if ($this->requestHasFilters($request, $response)) {
            $filterBehaviours = $this->parseFilters($request, $response);
            $foundObjects     = $service->getAll(
                $filterBehaviours->getLimit(),
                $filterBehaviours->getOffset(),
                $filterBehaviours->getWheres(),
                $filterBehaviours->getOrder(),
                $filterBehaviours->getOrderDirection()
            );
        } else {
            $foundObjects = $service->getAll();
        }

        foreach ($foundObjects as $object) {
            $objects[] = $object->__toPublicArray();
        }

        return $this->jsonResponse(
            [
                'Status'                             => 'Okay',
                'Action'                             => 'LIST',
                $this->getService()->getTermPlural() => $objects,
            ],
            $request,
            $response
        );
    }

    public function getRequest(Request $request, Response $response, $args): Response
    {
        $object = $this->getService()->getById($args['id']);
        if ($object) {
            return $this->jsonResponse(
                [
                    'Status'                               => 'Okay',
                    'Action'                               => 'GET',
                    $this->getService()->getTermSingular() => $object->__toArray(),
                ],
                $request,
                $response
            );
        }

        return $this->jsonResponse(
            [
                'Status' => 'Fail',
                'Reason' => sprintf(
                    'No such %s found with id %s',
                    strtolower($this->getService()->getTermSingular()),
                    $args['id']
                ),
            ],
            $request,
            $response
        );
    }

    public function createRequest(Request $request, Response $response, $args): Response
    {
        $newObjectArray = $request->getParsedBody();

        try {
            $object = $this->getService()->createFromArray($newObjectArray);

            return $this->jsonResponse(
                [
                    'Status'                               => 'Okay',
                    'Action'                               => 'CREATE',
                    $this->getService()->getTermSingular() => $object->__toArray(),
                ],
                $request,
                $response
            );
        } catch (InvalidQueryException $iqe) {
            return $this->jsonResponseException($iqe, $request, $response);
        }
    }

    public function deleteRequest(Request $request, Response $response, $args): Response
    {
        /** @var ModelInterface $object */
        $object = $this->getService()->getById($args['id']);
        if ($object) {
            $array = $object->__toArray();
            $object->destroy();

            return $this->jsonResponse(
                [
                    'Status'                               => 'Okay',
                    'Action'                               => 'DELETE',
                    $this->getService()->getTermSingular() => $array,
                ],
                $request,
                $response
            );
        }

        return $this->jsonResponse(
            [
                'Status' => 'Fail',
                'Reason' => sprintf(
                    'No such %s found with id %s',
                    strtolower($this->service->getTermSingular()),
                    $args['id']
                ),
            ],
            $request,
            $response
        );
    }
}
