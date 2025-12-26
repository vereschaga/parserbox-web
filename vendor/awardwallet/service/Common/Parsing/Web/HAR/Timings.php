<?php

namespace AwardWallet\Common\Parsing\Web\HAR;

use JMS\Serializer\Annotation as Serializer;

class Timings
{
    /**
     * @var int
     * @Serializer\Type("integer")
     */
    public $dns;
    /**
     * @var int
     * @Serializer\Type("integer")
     */
    public $connect;
    /**
     * @var int
     * @Serializer\Type("integer")
     */
    public $blocked;
    /**
     * @var int
     * @Serializer\Type("integer")
     */
    public $send;
    /**
     * @var int
     * @Serializer\Type("integer")
     */
    public $wait;
    /**
     * @var int
     * @Serializer\Type("integer")
     */
    public $receive;
    /**
     * @var int
     * @Serializer\Type("integer")
     */
    public $ssl;
    /**
     * @Serializer\Type("string")
     */
    public ?string $comment = null;
}
