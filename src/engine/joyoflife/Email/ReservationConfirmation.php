<?php

namespace AwardWallet\Engine\joyoflife\Email;

class ReservationConfirmation extends \TAccountCheckerExtended
{
    public $reFrom = "#citizenres@jdvhotels\.com|info@communehotels\.com#i";
    public $reProvider = "#jdvhotels\.com|communehotels\.com#i";
    public $rePlain = "#Thank\s+you\s+for\s+choosing\s+(The\s+Citizen\s+Hotel|Hotel\s+Vitale)#i";
    public $rePlainRange = "";
    public $typesCount = "2";
    public $langSupported = "en";
    public $reSubject = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $xPath = "";
    public $mailFiles = "";
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
                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        $xpath = "//text()[contains(., 'Confirmation Number')]/ancestor::td[1]/following-sibling::td[1]";

                        return node($xpath);
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        $res['HotelName'] = re("#Thank you for choosing\s+([^,]+),#");

                        if ($address = re("#((?:The\s+Citizen|Hotel\s+Vitale)\s+is\s+[^\.]+)\.#")) {
                            $res['Address'] = $address;
                        }

                        if ($hotelInfoHref = node('//a[contains(., "Hotel Vitale")]/@href')) {
                            $httpNew = new \HttpBrowser("none", new \CurlDriver());

                            if ($httpNew->GetURL($hotelInfoHref)) {
                                $subj = $httpNew->XPath->query('//address');

                                if ($subj->length == 1) {
                                    $subj = $subj->item(0)->nodeValue;

                                    if (preg_match('#\s*(.*)\s+map\s+(.*)#i', $subj, $m)) {
                                        $res['Address'] = $m[1];
                                        $res['Phone'] = $m[2];
                                    }
                                }
                            }
                        }

                        return $res;
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $res = null;
                        $arr = [
                            'CheckIn'  => ['Date' => 'Arrival', 'Time' => 'Check-In'],
                            'CheckOut' => ['Date' => 'Departure', 'Time' => 'Check-Out'],
                        ];

                        foreach ($arr as $key => $value) {
                            $dateStr = cell($value['Date'] . ' Date', +1);
                            $timeStr = re('#\d+:\d+\s*(?:am|pm)#', cell($value['Time'] . ' Time', +1));
                            $res[$key . 'Date'] = strtotime($dateStr . ', ' . $timeStr);
                        }

                        return $res;
                    },

                    "Phone" => function ($text = '', $node = null, $it = null) {
                        return str_replace('.', ' ', re("#For\s+reservations,\s+call\s+([\d\.]+)\.#"));
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        $xpath = "//text()[contains(., 'Guest Name')]/ancestor::td[1]/following-sibling::td[1]";

                        return [node($xpath)];
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        $xpath = "//text()[contains(., 'Number of Adults')]/ancestor::td[1]/following-sibling::td[1]";

                        return (int) node($xpath);
                    },

                    "Kids" => function ($text = '', $node = null, $it = null) {
                        $xpath = "//text()[contains(., 'Number of Children')]/ancestor::td[1]/following-sibling::td[1]";

                        return (int) node($xpath);
                    },

                    "Rooms" => function ($text = '', $node = null, $it = null) {
                        $xpath = "//text()[contains(., 'Number of Rooms')]/ancestor::td[1]/following-sibling::td[1]";

                        return (int) node($xpath);
                    },

                    "Rate" => function ($text = '', $node = null, $it = null) {
                        $xpath = "//text()[contains(., 'Nightly Rate')]/ancestor::td[1]/following-sibling::td[1]";

                        return node($xpath);
                    },

                    "RateType" => function ($text = '', $node = null, $it = null) {
                        $xpath = "//text()[contains(., 'Rate Plan')]/ancestor::td[1]/following-sibling::td[1]";

                        return node($xpath);
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        $xpath = "//text()[contains(., 'Cancellation Policy')]/ancestor::td[1]/following-sibling::td[1]";

                        return node($xpath);
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        $xpath = "//text()[contains(., 'Room Type')]/ancestor::td[1]/following-sibling::td[1]";

                        return node($xpath);
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        $xpath = "//text()[contains(., 'Total Charges with Tax')]/ancestor::td[1]/following-sibling::td[1]";
                        $subj = node($xpath);

                        return ['Total' => cost($subj), 'Currency' => currency($subj)];
                    },
                ],
            ],
        ];
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $result = parent::ParsePlanEmail($parser);

        return $result;
    }

    public static function getEmailTypesCount()
    {
        return 2;
    }

    public static function getEmailLanguages()
    {
        return ["en"];
    }
}
