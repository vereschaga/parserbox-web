<?php

namespace AwardWallet\Common\Parsing\Web\HAR;

use JMS\Serializer\Annotation as Serializer;

class PostDataParam
{
    /**
     * @Serializer\Type("string")
     */
    public string $name;

    /**
     * @Serializer\Type("string")
     */
    public ?string $value = null;

    /**
     * @Serializer\Type("string")
     * @Serializer\SerializedName("fileName")
     */
    public ?string $fileName = null;

    /**
     * @Serializer\Type("string")
     * @Serializer\SerializedName("contentType")
     */
    public ?string $contentType = null;

    /**
     * @Serializer\Type("string")
     */
    public ?string $comment = null;
}
