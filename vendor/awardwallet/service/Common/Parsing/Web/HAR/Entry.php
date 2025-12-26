<?php

namespace AwardWallet\Common\Parsing\Web\HAR;

use JMS\Serializer\Annotation as Serializer;

class Entry
{
    /**
     * @Serializer\Type("string")
     */
    public ?string $pageref = null;

    /**
     * @Serializer\Type("string")
     * @Serializer\SerializedName("startedDateTime")
     */
    public ?string $startedDateTime;

    /**
     * @Serializer\Type("integer")
     */
    public ?int $time;

    /**
     * @Serializer\Type("AwardWallet\Common\Parsing\Web\HAR\Request")
     */
    public Request $request;

    /**
     * @Serializer\Type("AwardWallet\Common\Parsing\Web\HAR\Response")
     */
    public Response $response;

    /**
     * @Serializer\Type("AwardWallet\Common\Parsing\Web\HAR\Cache")
     */
    public Cache $cache;

    /**
     * @Serializer\Type("AwardWallet\Common\Parsing\Web\HAR\Timings")
     */
    public Timings $timings;

    /**
     * @Serializer\Type("string")
     * @Serializer\SerializedName("serverIPAddress")
     */
    public ?string $serverIPAddress = null;

    /**
     * @Serializer\Type("string")
     */
    public ?string $connection = null;

    /**
     * @Serializer\Type("string")
     * @Serializer\SerializedName("comment")
     */
    public ?string $comment = null;
}
