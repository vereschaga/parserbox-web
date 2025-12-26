<?php

namespace AwardWallet\Common\Parsing\Web\HAR;

use JMS\Serializer\Annotation as Serializer;

class Response
{
    /**
     * @Serializer\Type("integer")
     */
    public int $status;

    /**
     * @Serializer\Type("string")
     * @Serializer\SerializedName("statusText")
     */
    public string $statusText;

    /**
     * @Serializer\Type("string")
     * @Serializer\SerializedName("httpVersion")
     */
    public string $httpVersion;

    /**
     * @var Cookie[]
     * @Serializer\Type("array<AwardWallet\Common\Parsing\Web\HAR\Cookie>")
     */
    public array $cookies;

    /**
     * @var Record[]
     * @Serializer\Type("array<AwardWallet\Common\Parsing\Web\HAR\Record>")
     */
    public array $headers;

    /**
     * @Serializer\Type("AwardWallet\Common\Parsing\Web\HAR\Content")
     */
    public Content $content;

    /**
     * @Serializer\Type("string")
     * @Serializer\SerializedName("redirectURL")
     */
    public string $redirectURL;

    /**
     * @Serializer\Type("integer")
     * @Serializer\SerializedName("headersSize")
     */
    public int $headersSize = 0;

    /**
     * @Serializer\Type("integer")
     * @Serializer\SerializedName("bodySize")
     */
    public int $bodySize = 0;

    /**
     * @Serializer\Type("string")
     * @Serializer\SerializedName("comment")
     */
    public ?string $comment = null;
}
