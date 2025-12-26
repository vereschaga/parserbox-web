<?php

namespace AwardWallet\Schema\Itineraries;

use JMS\Serializer\Annotation\Type;

class NumberedSeat
{

    /**
     * @var string
     * @Type("string")
     */
    public $seatNumber;

    /**
     * @var integer
     * @Type("integer")
     */
    public $segmentNumber;

}