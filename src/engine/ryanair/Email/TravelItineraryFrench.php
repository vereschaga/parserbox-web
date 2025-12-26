<?php

namespace AwardWallet\Engine\ryanair\Email;

class TravelItineraryFrench extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#Merci d’avoir réservé avec Ryanair#i', 'blank', '/1'],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = [
        ['#[@.]ryanair#i', 'us', ''],
    ];
    public $reProvider = [
        ['#[@.]ryanair#i', 'us', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "fr";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "20.06.2016, 07:14";
    public $crDate = "16.06.2016, 12:53";
    public $xPath = "";
    public $mailFiles = "";
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
                        return re('#Réservation\s*:\s+(\w{6})#');
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return re('#Passager\(s\)\s*:\s*(.*)#');
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return total(cell('Total réglé', +1));
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re('#Statut\s*:\s+(.*)#');
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath('//img[contains(@src, "plane/blue/in.png") or contains(@src, "plane/blue/out.png")]/ancestor::table[1]/following-sibling::table[1]');
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $s = node('./preceding-sibling::table[1]//img[contains(@src, "plane/blue/in.png") or contains(@src, "plane/blue/out.png")]/ancestor::tr[1]');

                            if (preg_match('#:\s+(\w{2})\s*(\d+)#i', $s, $m)) {
                                return [
                                    'FlightNumber' => $m[2],
                                    'AirlineName'  => $m[1],
                                ];
                            }
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return re('#\((\w{3})\)#i', node('.//td[contains(., "Départ") and not(.//td)]/following-sibling::td[1]'));
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $d = re('#\d+\s+\w+\s+\d+#i', node('.//td[contains(., "Départ") and not(.//td)]/ancestor::tr[1]/following-sibling::tr[1]/td[2]'));
                            $t = node('.//td[contains(., "Départ") and not(.//td)]/ancestor::tr[1]/following-sibling::tr[2]/td[2]');

                            if ($d and $t) {
                                return strtotime($d . ', ' . $t);
                            }
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return re('#\((\w{3})\)#i', node('.//td[contains(., "Arrivée") and not(.//td)]/following-sibling::td[1]'));
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            $d = re('#\d+\s+\w+\s+\d+#i', node('.//td[contains(., "Arrivée") and not(.//td)]/ancestor::tr[1]/following-sibling::tr[1]/td[2]'));
                            $t = node('.//td[contains(., "Arrivée") and not(.//td)]/ancestor::tr[1]/following-sibling::tr[2]/td[2]');

                            if ($d and $t) {
                                return strtotime($d . ', ' . $t);
                            }
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
        return ["fr"];
    }

    public function IsEmailAggregator()
    {
        return false;
    }
}
