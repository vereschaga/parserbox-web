<?php

namespace AwardWallet\Engine\sncf\Email;

class It4 extends \TAccountCheckerExtended
{
    public $mailFiles = "sncf/it-4.eml";
    public $reFrom = "";
    public $reSubject = "";
    public $reProvider = "";
    public $rePlain = "#From:.*noreply@uk\.voyages-sncf\.com.*\n.*\nSubject: Voyages-sncf.com: Your Ticket on Departure Booking Confirmation#is";
    public $reHtml = "";
    public $xPath = "";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function (&$text = '', &$node = null) {
                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function (&$text = '', &$node = null) {
                        return "T";
                    },

                    "TripCategory" => function (&$text = '', &$node = null) {
                        return TRIP_CATEGORY_TRAIN;
                    },

                    "RecordLocator" => function (&$text = '', &$node = null) {
                        return re("#Booking reference number:\s*([0-9]+)#");
                    },

                    "Passengers" => function (&$text = '', &$node = null) {
                        return ['Passengers' => nodes("//td[contains(text(), 'Passenger details')]//ancestor::tr[2]/following-sibling::tr[1]/descendant::tr[2]/following-sibling::tr")];
                    },

                    "SegmentsSplitter" => function (&$text = '', &$node = null) {
                        return $this->http->XPath->query("//td[contains(text(), 'journey details')]/ancestor::tr[1]/following-sibling::tr[1]");
                    },

                    "TripSegments" => [
                        "FlightNumber" => function (&$text = '', &$node = null) {
                            return re("#Train\sNo:\s([0-9]+)#", $text->nodeValue);
                        },

                        "DepCode" => function (&$text = '', &$node = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "ArrCode" => function (&$text = '', &$node = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function (&$text = '', &$node = null) {
                            return re("#Dep:\s(\S+)#", $text->nodeValue);
                        },

                        "ArrName" => function (&$text = '', &$node = null) {
                            return re("#Arr:\s(\S+)#", $text->nodeValue);
                        },

                        "DepDate" => function (&$text = '', &$node = null) {
                            $s = node(".//td/span[contains(text(), 'Dep:')]/ancestor::tr[1]/following-sibling::tr[1]");
                            $dt = \DateTime::createFromFormat('G:i d/m/y', $s);

                            if ($dt) {
                                return $dt->getTimestamp();
                            }
                        },

                        "ArrDate" => function (&$text = '', &$node = null) {
                            $s = node(".//td/span[contains(text(), 'Arr:')]/ancestor::tr[1]/following-sibling::tr[1]");
                            $dt = \DateTime::createFromFormat('G:i d/m/y', $s);

                            if ($dt) {
                                return $dt->getTimestamp();
                            }
                        },

                        "Seats" => function (&$text = '', &$node = null) {
                            return join(', ', nodes(".//td[span[contains(text(), 'Coach/Seat:')]]/following-sibling::td[1]/span/node()[position() mod 2 = 1]"));
                        },

                        "Duration" => function (&$text = '', &$node = null) {
                            return trim(re('#\|(.*)#', node("./preceding-sibling::tr[1]")));
                        },
                    ],
                ],
            ],
        ];
    }
}
