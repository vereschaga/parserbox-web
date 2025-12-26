<?php

namespace AwardWallet\Common\Parsing\Web\HAR;

use JMS\Serializer\Annotation as Serializer;

class Page
{
    /**
     * @Serializer\Type("string")
     */
    public string $startedDateTime;

    /**
     * @Serializer\Type("string")
     */
    public string $id;

    /**
     * @Serializer\Type("string")
     */
    public string $title;

    /**
     * @Serializer\Type("AwardWallet\Common\Parsing\Web\HAR\PageTimings")
     */
    public PageTimings $pageTimings;

    /**
     * @Serializer\Type("string")
     */
    public ?string $comment = null;
}
