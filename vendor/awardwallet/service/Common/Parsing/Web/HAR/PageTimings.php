<?php

namespace AwardWallet\Common\Parsing\Web\HAR;

use JMS\Serializer\Annotation as Serializer;

class PageTimings
{
    /**
     * @Serializer\Type("float")
     */
    public ?float $onContentLoad = null;

    /**
     * @Serializer\Type("float")
     */
    public ?float $onLoad = null;

    /**
     * @Serializer\Type("string")
     */
    public ?string $comment = null;
}
