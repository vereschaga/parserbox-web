<?php

namespace AwardWallet\Common\Parsing\Web\HAR;

use JMS\Serializer\Annotation as Serializer;

class Content
{
    /**
     * @Serializer\Type("integer")
     */
    public int $size;

    /**
     * @Serializer\Type("integer")
     */
    public ?int $compression = null;

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
     * @Serializer\Type("string")
     */
    public ?string $encoding = null;

    /**
     * @Serializer\Type("string")
     */
    public ?string $comment = null;
}
