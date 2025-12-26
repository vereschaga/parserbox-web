<?php

namespace AwardWallet\Common\Parsing\Web\HAR;

use JMS\Serializer\Annotation as Serializer;

class Request
{
    /**
     * @Serializer\Type("string")
     */
    public string $method;

    /**
     * @Serializer\Type("string")
     */
    public string $url;

    /**
     * @Serializer\Type("string")
     */
    public string $httpVersion;

    /**
     * @Serializer\Type("array<AwardWallet\Common\Parsing\Web\HAR\Cookie>")
     */
    public array $cookies;

    /**
     * @Serializer\Type("array<AwardWallet\Common\Parsing\Web\HAR\Record>")
     */
    public array $headers;

    /**
     * @Serializer\Type("array<AwardWallet\Common\Parsing\Web\HAR\Record>")
     */
    public array $queryString;

    /**
     * @Serializer\Type("AwardWallet\Common\Parsing\Web\HAR\PostData")
     */
    public ?PostData $postData = null;

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
     */
    public ?string $comment = null;
}
