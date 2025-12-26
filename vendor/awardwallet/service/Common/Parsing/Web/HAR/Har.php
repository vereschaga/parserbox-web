<?php

namespace AwardWallet\Common\Parsing\Web\HAR;

use JMS\Serializer\Annotation as Serializer;

/**
 * Class Har
 * Represents the root of a HAR file.
 */
class Har
{
    /**
     * @var Log
     * @Serializer\Type("AwardWallet\Common\Parsing\Web\HAR\Log")
     * @Serializer\SerializedName("log")
     */
    public $log;

    /**
     * @var int
     * @Serializer\Type("integer")
     */
    public $total;
    /**
     * @var int
     * @Serializer\Type("integer")
     */
    public $skip;
    /**
     * @var int
     * @Serializer\Type("integer")
     * @Serializer\SerializedName("sum_out")
     */
    public $sumOut;
    /**
     * @var int
     * @Serializer\Type("integer")
     * @Serializer\SerializedName("sum_in")
     */
    public $sumIn;

}