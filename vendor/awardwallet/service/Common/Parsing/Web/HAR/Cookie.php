<?php

namespace AwardWallet\Common\Parsing\Web\HAR;

use JMS\Serializer\Annotation as Serializer;

class Cookie
{
    /**
     * @Serializer\Type("string")
     */
    public string $name;

    /**
     * @Serializer\Type("string")
     */
    public string $value;

    /**
     * @Serializer\Type("string")
     */
    public ?string $path = null;

    /**
     * @Serializer\Type("string")
     */
    public ?string $domain = null;

    /**
     * @Serializer\Type("string")
     */
    public ?string $expires = null;

    /**
     * @Serializer\Type("boolean")
     * @Serializer\SerializedName("httpOnly")
     */
    public ?bool $httpOnly = null;

    /**
     * @Serializer\Type("boolean")
     */
    public ?bool $secure = null;

    /**
     * @Serializer\Type("string")
     */
    public ?string $comment = null;
}
