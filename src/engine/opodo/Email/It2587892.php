<?php

namespace AwardWallet\Engine\opodo\Email;

class It2587892 extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#\n[>\s*]*Da\s*:[^\n]*?[@.]opodo#i', 'blank', ''],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = [
        ['#[@.]opodo#i', 'us', ''],
    ];
    public $reProvider = [
        ['#[@.]opodo#i', 'us', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "it";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "30.03.2015, 23:18";
    public $crDate = "30.03.2015, 23:00";
    public $xPath = "";
    public $mailFiles = "opodo/it-2587892.eml";
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
                        return re("#\n\s*Riferimento prenotazione\s*:\s*([A-Z\d-]+)#");
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return total(re("#\n\s*Totale[\s:]+([A-Z]{3}\s+[\d,.]+)#"));
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath("//*[contains(text(), 'Partenza:')]/ancestor-or-self::td[1][following::tr[1][contains(., 'Arrivo:')]]");
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return uberAir(re("#^.*?\(\s*([A-Z\d]{2}\s*\d+)\s*\)#", xpath("following::tr[3]")));
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return re("#\(([A-Z]{3})\)#");
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            return totime(uberDate(en(text(xpath("preceding::tr[1]")))) . ',' . uberTime(1));
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return re("#\(([A-Z]{3})\)#", xpath("following::tr[1]"));
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            return totime(uberDate(en(text(xpath("preceding::tr[1]")))) . ',' . uberTime(text(xpath("following::tr[1]"))));
                        },

                        "Aircraft" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*Tipo di aereo\s*:\s*([^\n]+)#", xpath("following::tr[3]"));
                        },

                        "FlightLocator" => function ($text = '', $node = null, $it = null) {
                            $airlineName = re("#^(.*?)\s*\(\s*[A-Z\d]{2}\s*\d+\s*\)#", xpath("following::tr[3]"));

                            return re("#\s+{$airlineName}\s*:\s*([A-Z\d-]+)#", $this->text());
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
        return ["it"];
    }

    public function IsEmailAggregator()
    {
        return false;
    }
}
