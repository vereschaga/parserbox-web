<?php

namespace AwardWallet\Common\Parsing\Web\HAR;

use JMS\Serializer\Annotation as Serializer;

class Cache
{
    /**
     * @var CacheEntry
     * @Serializer\Type("AwardWallet\Common\Parsing\Web\HAR\CacheEntry")
     * @Serializer\SerializedName("beforeRequest")
     */
    public $beforeRequest;
    /**
     * @var CacheEntry
     * @Serializer\Type("AwardWallet\Common\Parsing\Web\HAR\CacheEntry")
     * @Serializer\SerializedName("afterRequest")
     */
    public $afterRequest;
    /**
     * @var string
     * @Serializer\Type("string")
     */
    public $comment;

}
