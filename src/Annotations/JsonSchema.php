<?php

declare(strict_types=1);

namespace Benzine\Annotations;

use Doctrine\Common\Annotations\Annotation\Required;

/**
 * @Annotation
 *
 * @Target("METHOD")
 */
class JsonSchema
{
    /**
     * @Required
     */
    public string $schema;

}
