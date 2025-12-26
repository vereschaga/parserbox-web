<?php

namespace AwardWallet\Common\Parsing\Web\HAR;

use JMS\Serializer\Annotation as Serializer;

class CacheEntry
{
    /**
     * @var string
     * @Serializer\Type("string")
     */
    public $expires;
    /**
     * @var string
     * @Serializer\Type("string")
     * @Serializer\SerializedName("lastAccess")
     */
    public $lastAccess;
    /**
     * @var string
     * @Serializer\Type("string")
     */
    public $etag;
    /**
     * @var int
     * @Serializer\Type("integer")
     * @Serializer\SerializedName("hitCount")
     */
    public $hitCount;
    /**
     * @var string
     * @Serializer\Type("string")
     */
    public $comment;

}
