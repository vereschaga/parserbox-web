<?php

namespace AwardWallet\Engine\sncf\Email;

class YourBooking extends \TAccountCheckerExtended
{
    public $rePlain = "#From:.*no-reply@pasngr.idtgv.com.*Subject:.*Your iDTGV booking for your journey#ims";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "#Your iDTGV booking|Votre réservation iDTGV#";
    public $langSupported = "en, fr";
    public $typesCount = "";
    public $reFrom = "";
    public $reProvider = "";
    public $caseReference = "";
    public $xPath = "";
    public $mailFiles = "sncf/it-2.eml, sncf/it-2031329.eml";
    public $pdfRequired = "";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return [$text];
                },

                "#.*?#" => [
                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return re('#(?:Reference\s+number|Numéro\s+de\s+dossier)\s*([\w\-]+)#i');
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "TripCategory" => function (&$text = '', &$node = null) {
                        return TRIP_CATEGORY_TRAIN;
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        $s = node("//td/span/b[contains(., 'AMOUNT PAID') or contains(., 'TOTAL PAYE')]/ancestor::tr[1]/td[2]");

                        return ['TotalCharge' => cost($s), 'Currency' => currency($s)];
                    },

                    "Tax" => function ($text = '', $node = null, $it = null) {
                        return cost(node("//td/span[contains(., 'Total VAT')]/ancestor::tr[1]/td[2]"));
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return $this->http->XPath->query("//tr//table[contains(., 'passenger(s)') or contains(., 'voyageur(s)')]");
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return re('#iDTGV n°([0-9]+)#', node(".//tr[4]/td[2]/span[3]"));
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            $x = node(".//tr[4]/td[2]/span[1]/node()[2]");

                            return $x;
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            if (preg_match('#(\d+)\s+(\w+)\s+(\d+)#i', node(".//tr[2]/td[3]"), $m)) {
                                $depDateStr = $m[1] . ' ' . en($m[2]) . ' ' . $m[3];
                                $depTimeStr = node(".//tr[4]/td[2]/span[1]/b");
                                $arrTimeStr = node(".//tr[4]/td[2]/span[2]/b");
                                $depDatetimeStr = "$depDateStr $depTimeStr";
                                $arrDatetimeStr = "$depDateStr $arrTimeStr";

                                return [
                                    'DepDate' => strtotime($depDatetimeStr),
                                    'ArrDate' => strtotime($arrDatetimeStr),
                                ];
                            }
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "ArrName" => function ($text = '', $node = null, $it = null) {
                            $x = node(".//tr[4]/td[2]/span[2]/node()[2]");

                            return $x;
                        },

                        "Seats" => function ($text = '', $node = null, $it = null) {
                            $places = [];
                            $regex = '#(?:Coach|Voiture)\s+(\d+).*(?:Seat|Place)\s+(\d+)#iu';

                            if (preg_match_all($regex, $node->nodeValue, $matches, PREG_SET_ORDER)) {
                                foreach ($matches as $m) {
                                    $places[] = "$m[1]-$m[2]";
                                }
                            }

                            return join(', ', $places);
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
        return ["en", "fr"];
    }
}
