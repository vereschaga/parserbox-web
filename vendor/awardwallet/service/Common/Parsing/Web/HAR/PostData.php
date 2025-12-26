<?php

namespace AwardWallet\Common\Parsing\Web\HAR;

use JMS\Serializer\Annotation as Serializer;

class PostData
{
    /**
     * @Serializer\Type("string")
     * @Serializer\SerializedName("mimeType")
     */
    public string $mimeType;

    /**
     * @Serializer\Type("string")
     */
    public ?string $text = null;

    /**
     * @Serializer\Type("array<AwardWallet\Common\Parsing\Web\HAR\PostDataParam>")
     */
    public array $params = [];

    /**
     * @Serializer\Type("string")
     */
    public ?string $comment = null;
}
