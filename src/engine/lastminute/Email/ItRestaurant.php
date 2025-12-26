<?php

namespace AwardWallet\Engine\lastminute\Email;

class ItRestaurant extends \TAccountCheckerExtended
{
    public $reFrom = "#reservations@restaurants.lastminute.com#i";
    public $reProvider = "#restaurants.lastminute.com#i";
    public $rePlain = "#Your lastminute.com booking is confirmed#i";
    public $typesCount = "1";
    public $langSupported = "en";
    public $reSubject = "";
    public $reHtml = "";
    public $xPath = "";
    public $mailFiles = "lastminute/it-1550788.eml, lastminute/it-1565306.eml";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "E";
                    },

                    "ConfNo" => function ($text = '', $node = null, $it = null) {
                        $infoNodes = nodes('//text()[contains(., "Diner\'s name:")]/ancestor::td[1]/following-sibling::td[1]//text()');
                        [$dinerName, $dateStr, $guests, $confNo] = array_values(array_filter($infoNodes));

                        $res['DinerName'] = $dinerName;
                        $res['ConfNo'] = $confNo;
                        $res['Guests'] = (int) $guests;

                        $regexp = '#\w+,\s+(?P<DayAndMonth>\w+\s+\w+),?\s+(?P<Year>\d+)\s+at\s+(?P<Time>\d{1,2}:\d{2})\s*(?P<AMPM>AM|PM)?#';

                        if (preg_match($regexp, $dateStr, $m)) {
                            $s = $m['DayAndMonth'] . ' ' . $m['Year'] . ', ' . $m['Time'];

                            if (isset($m['AMPM'])) {
                                $s .= $m['AMPM'];
                            }
                            $res['StartDate'] = strtotime($s);
                        }

                        return $res;
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        $infoNodes = nodes('//text()[contains(., "Restaurant:")]/ancestor::td[1]/following-sibling::td[1]//text()');
                        $infoNodes = array_values(array_filter($infoNodes));
                        [$address, $phone] = array_slice($infoNodes, 1);

                        return ['Address' => $address, 'Phone' => $phone];
                    },

                    "Name" => function ($text = '', $node = null, $it = null) {
                        return re("#You have booked the following offer:\s+(.*)\s+The offer#");
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re("#Your lastminute.com booking is (\w+):#");
                    },
                ],
            ],
        ];
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
