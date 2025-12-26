<?php

namespace AwardWallet\Engine\lufthansa\Email;

class MaMReservation extends \TAccountCheckerExtended
{
    public $reFrom = "#noreply@milesandmore\.com#i";
    public $reProvider = "#milesandmore\.com#i";
    public $rePlain = "#Thank\s+you\s+for\s+choosing\s+Miles\s+&\s+More\s+Hotel\s+&\s+Car\s+Awards#i";
    public $rePlainRange = "1000";
    public $typesCount = "1";
    public $langSupported = "en";
    public $reSubject = "#Miles\s+and\s+More\s+RESERVATION#i";
    public $reHtml = "";
    public $reHtmlRange = "1000";
    public $xPath = "";
    public $mailFiles = "lufthansa/it-1665224.eml, lufthansa/it-1665225.eml";
    public $rePDF = "";
    public $rePDFRange = "";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        return re('#Booking\s+number:\s+([\w\-]+)#');
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        $root = $this->http->XPath->query("//img[contains(@src, 'symbol_star')]/ancestor::tr[1]")->item(0);
                        $res['HotelName'] = node('./preceding-sibling::tr[1]', $root);
                        $hotelInfo = implode("\n", array_filter(nodes('./following-sibling::tr[1]//text()', $root)));

                        if (preg_match('#(.*)\s*\n([\d\s+]+)#s', $hotelInfo, $m)) {
                            $res['Address'] = nice($m[1], ',');
                            $res['Phone'] = $m[2];
                        }

                        return $res;
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $res = [];

                        foreach (['CheckIn' => 'Check-in', 'CheckOut' => 'Check-out'] as $key => $value) {
                            $regex = '#';
                            $regex .= $value . ':\s+';
                            $regex .= '\w+\s+(?P<Day>\d+)/(?P<Month>\d+)/(?P<Year>\d+)\s+';
                            $regex .= '(?P<Time>\d+:\d+(?:am|pm)?)';
                            $regex .= '#i';

                            if (preg_match($regex, $text, $m)) {
                                $subj = $m['Day'] . '.' . $m['Month'] . '.20' . $m['Year'] . ' ' . $m['Time'];
                                $res[$key . 'Date'] = strtotime($subj);
                            }
                        }

                        return $res;
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        return [re('#Lead\s+Traveler\s+(.*)#')];
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        $subj = re('#Occupants:\s+(\d+)#');

                        if ($subj) {
                            return (int) $subj;
                        }
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        $subj = cell('Package Price', +1);

                        if (stripos($subj, 'miles') !== false) {
                            return ['SpentAwards' => cost(str_replace(',', '', $subj))];
                        }
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
