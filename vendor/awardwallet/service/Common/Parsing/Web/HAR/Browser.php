<?php

namespace AwardWallet\Common\Parsing\Web\HAR;

use JMS\Serializer\Annotation as Serializer;

class Browser
{
    /**
     * @Serializer\Type("string")
     */
    public string $name;

    /**
     * @Serializer\Type("string")
     */
    public string $version;

    /**
     * @Serializer\Type("string")
     */
    public ?string $comment = null;
}
