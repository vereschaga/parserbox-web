<?php

namespace AwardWallet\Engine\virginamerica\Email;

class It7 extends \TAccountCheckerExtended
{
    public $reProvider = "#[@.]virginamerica.#i";
    public $mailFiles = "virginamerica/it-1.eml, virginamerica/it-2.eml, virginamerica/it-3.eml, "
            . "virginamerica/it-7.eml, virginamerica/it-8.eml, virginamerica/it-9.eml, virginamerica/it-4137696.eml";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return [$text];
                },

                "#.*?#" => [
                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return orval(
                            re("#Confirmation Code:\s*([\w\d]+)#"),
                            re("#\s+([A-Z\d\-]{5,6})\s+Who's Flying#")
                        );
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $i = 1;
                        $names = [];

                        while ($name = cell("Traveler(s)", 0, $i)) {
                            $names[] = $name;
                            $i++;
                        }

                        return $names;
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#Grand Total:\s*([^\n]+)#"));
                    },

                    "BaseFare" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#\n\s*Base Fare(?:\s*\(x\d+\))*:\s*([^\n]+)#"));
                    },

                    "Currency" => function ($text = '', $node = null, $it = null) {
                        return currency(re("#Grand Total:\s*([^\n]+)#"));
                    },

                    "Tax" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#\n\s*Federal Tax:\s*([^\n]+)#"));
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xPath("//*[contains(text(), 'Stops')]/ancestor-or-self::tr[1]/following-sibling::tr");
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            if (preg_match("#^\s*\d+\s*$#is", node("td[2]"))) {
                                return node("td[2]");
                            } else {
                                return uberflight(node("td[2]"));
                            }
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return re("#\(([A-Z]{3})\)#", node("td[3]"));
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $dep = strtotime(node("td[1]") . ', ' . uberTime(node("td[3]")));
                            $arr = strtotime(node("td[1]") . ', ' . uberTime(node("td[4]")));

                            if ($arr < $dep) {
                                $arr += 3600 * 24;
                            }

                            return [
                                'DepDate' => $dep,
                                'ArrDate' => $arr,
                            ];
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return re("#\(([A-Z]{3})\)#", node("td[4]"));
                        },

                        "AirlineName" => function ($text = '', $node = null, $it = null) {
                            if (preg_match("#^\s*\d+\s*$#is", node("td[2]"))) {
                                return "Virgin America";
                            } else {
                                return uberAirline(node('td[2]'));
                            }
                        },

                        "Stops" => function ($text = '', $node = null, $it = null) {
                            return node("td[5]");
                        },
                    ],
                ],
            ],
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], '@elevate.virginamerica.com') !== false
                && isset($headers['subject']) && (
                    stripos($headers['subject'], 'Virgin America Reservation') !== false
                    || stripos($headers['subject'], 'It\'s Time to Check In') !== false
                );
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query('//img[contains(@alt, "Let\'s Get Going. 24-Hour Flight Reminder")]')->length > 0
            || $this->http->XPath->query('//img[contains(@alt, "Booking Confirmation. Ready. Set. Fly.")]')->length > 0
            || $this->http->XPath->query('//text()[contains(., "Guests may send comments or concerns to Virgin America")]')->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'virginamerica.com') !== false;
    }

    public static function getEmailTypesCount()
    {
        return 1;
    }

    public static function getEmailLanguages()
    {
        return ["en"];
    }
}
