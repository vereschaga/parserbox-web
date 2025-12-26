<?php

namespace AwardWallet\Schema\Itineraries;

use JMS\Serializer\Annotation as Serializer;
use JMS\Serializer\Annotation\Type;
use JMS\Serializer\Annotation\SkipWhenEmpty;

class Person {

    const TYPE_INFANT = 'infant';

    /**
     * @var string
     * @Type("string")
     */
	public $name;

	/**
	 * @var boolean
	 * @Type("boolean")
	 */
	public $full;
    /**
     * @var string
     * @Type("string")
     */
	public $type;

    /**
     * @var ParsedNumber[]
     * @Type("array<AwardWallet\Schema\Itineraries\ParsedNumber>")
     */
    public $accountNumbers;
    /**
     * @var ParsedNumber[]
     * @Type("array<AwardWallet\Schema\Itineraries\ParsedNumber>")
     */
    public $ticketNumbers;
    /**
     * @var NumberedSeat[]
     * @Type("array<AwardWallet\Schema\Itineraries\NumberedSeat>")
     */
    public $seats;

}