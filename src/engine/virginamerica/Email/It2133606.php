<?php

namespace AwardWallet\Engine\virginamerica\Email;

class It2133606 extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#\n[>\s*]*From\s*:[^\n]*?virginamerica#i', 'us', ''],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = [
        ['#virginamerica#i', 'us', ''],
    ];
    public $reProvider = [
        ['#virginamerica#i', 'us', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "6972";
    public $upDate = "16.06.2016, 08:16";
    public $crDate = "";
    public $xPath = "";
    public $mailFiles = "virginamerica/it-2133606.eml, virginamerica/it-3905198.eml";
    public $re_catcher = "#.*?#";
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
                        $locator = re("#\n\s*Confirmation Code\s*:\s*([A-Z\d-]+)#");

                        return empty($locator) ? CONFNO_UNKNOWN : $locator;
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Your\s+Flight\s+Has\s+(\w+)#i");
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath("//img[contains(@alt, 'New Flight Information')]/ancestor::tr[1]/following-sibling::tr[1]//tr[contains(., '/')]");
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return uberAir(node("td[2]"));
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return node('td[3]', $node, true, "#\(([A-Z]{3})\)#");
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            //$date = clear("#/#", node('td[1]'), '.');
                            $date = node('td[1]');

                            $dep = $date . ',' . node('following-sibling::tr[1]/td[3]');
                            $arr = $date . ',' . node('following-sibling::tr[1]/td[4]');

                            correctDates($dep, $arr);

                            return [
                                'DepDate' => $dep,
                                'ArrDate' => $arr,
                            ];
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return node('td[4]', $node, true, "#\(([A-Z]{3})\)#");
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
        return ["en"];
    }

    public function IsEmailAggregator()
    {
        return false;
    }
}
