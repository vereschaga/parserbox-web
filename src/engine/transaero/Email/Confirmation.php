<?php

namespace AwardWallet\Engine\transaero\Email;

class Confirmation extends \TAccountCheckerExtended
{
    public $reFrom = "#internet@transaero\.ru#i";
    public $reProvider = "#transaero\.ru#i";
    public $rePlain = "#\Confirmation Email.*transaero#is";
    public $typesCount = "1";
    public $langSupported = "ru";
    public $reSubject = "";
    public $reHtml = "";
    public $xPath = "";
    public $mailFiles = "transaero/it-1604225.eml, transaero/it-1604232.eml";
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
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return re("#Reservation\s+number:\s+([\w\-]+)#");
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $xpath = "//text()[contains(., 'Customer')]/ancestor::tr[contains(., 'Date')]/following-sibling::tr[position() > 1]/td[2]";

                        return nodes($xpath);
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        $xpath = "//text()[contains(., 'Total price for all')]/ancestor::td[1]/following-sibling::td[1]";
                        $subj = node($xpath);

                        return ['TotalCharge' => cost($subj), 'Currency' => currency($subj)];
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        $xpath = "//tr[contains(., 'Departure') and contains(., 'Arrival')]/following-sibling::tr";

                        return $this->http->XPath->query($xpath);
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return re('#\d+#', node('./td[2]'));
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "BookingClass" => function ($text = '', $node = null, $it = null) {
                            return node('./td[3]');
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            return node('./td[6]');
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            $subj = node('./td[1]');

                            if (preg_match("#(.*)\\\.*?\s-\s(.*)\\\#U", $subj, $m)) {
                                return ['DepName' => trim($m[1]), 'ArrName' => trim($m[2])];
                            }
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            return strtotime(node('./td[4]'));
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            return strtotime(node('./td[5]'));
                        },
                    ],
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
        return ["ru"];
    }
}
