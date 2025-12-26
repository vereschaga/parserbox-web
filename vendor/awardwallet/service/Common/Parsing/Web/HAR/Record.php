<?php

namespace AwardWallet\Common\Parsing\Web\HAR;

use JMS\Serializer\Annotation as Serializer;

class Record
{
    /**
     * @Serializer\Type("string")
     */
    public string $name;

    /**
     * @var string|string[]
     * @Serializer\Type("ArrayOrString")
     */
    public $value;

    /**
     * @Serializer\Type("string")
     */
    public ?string $comment = null;
}
