<?php

namespace AwardWallet\Common\Parsing\Web\HAR;

use JMS\Serializer\Annotation as Serializer;

class Log
{
    /**
     * @Serializer\Type("string")
     */
    public string $version;

    /**
     * @Serializer\Type("AwardWallet\Common\Parsing\Web\HAR\Creator")
     */
    public Creator $creator;

    /**
     * @Serializer\Type("AwardWallet\Common\Parsing\Web\HAR\Browser")
     */
    public ?Browser $browser = null;

    /**
     * @Serializer\Type("array<AwardWallet\Common\Parsing\Web\HAR\Page>")
     */
    public array $pages = [];

    /**
     * @var Entry[]
     * @Serializer\Type("array<AwardWallet\Common\Parsing\Web\HAR\Entry>")
     */
    public array $entries;

    /**
     * @Serializer\Type("string")
     */
    public ?string $comment = null;
}
