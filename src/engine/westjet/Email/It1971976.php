<?php

namespace AwardWallet\Engine\westjet\Email;

class It1971976 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?westjet#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#westjet#i";
    public $reProvider = "#westjet#i";
    public $caseReference = "7071";
    public $xPath = "";
    public $mailFiles = "westjet/it-1971976.eml, westjet/it-1971986.eml, westjet/it-2.eml";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return [$text];
                },

                "#.*?#" => [
                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Your reservation code is\s*:\s*([A-Z\d\-]+)#");
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return nodes("//*[contains(text(), 'Guest')]/ancestor-or-self::h2[1]/following-sibling::table[1]//tr/td[1][string-length(normalize-space(.))>1]");
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return cost(node("//*[contains(text(), 'Total')]/ancestor-or-self::h2[1]/following-sibling::table[1]//tr[1]/td[last()]"));
                    },

                    "BaseFare" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#\n\s*Total airfare\s*:\s*([^\n]+)#"));
                    },

                    "Currency" => function ($text = '', $node = null, $it = null) {
                        return currency(node("//*[contains(text(), 'Total')]/ancestor-or-self::h2[1]/following-sibling::table[1]//tr[1]/td[last()]"));
                    },

                    "Tax" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#\n\s*Tax Details.*?\n\s*Total airfare\s*:\s*([^\n]+)#ims"));
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath("//*[contains(text(), 'Air Itinerary Details')]/ancestor-or-self::h2[1]/following-sibling::table[contains(.,'Fare type:')]/tr");
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return uberAir(node('td[1]'));
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            return [
                                'DepName' => re("#^\s*([^\n]+)\s+(.+)#ims", implode("\n", nodes('td[2]//text()'))),
                                'DepDate' => totime(re(2)),
                                'DepCode' => re("#(?:\n|,|\-)\s*" . clear("#,\s*[A-Z]+$#", re(1)) . "\s*\(([A-Z]{3})\)#ims", $this->text()),
                            ];
                        },

                        "ArrName" => function ($text = '', $node = null, $it = null) {
                            return [
                                'ArrName' => re("#^\s*([^\n]+)\s+(.+)#ims", implode("\n", nodes('td[3]//text()'))),
                                'ArrDate' => totime(re(2)),
                                'ArrCode' => re("#(?:\n|,|\-)\s*" . clear("#,\s*[A-Z]+$#", re(1)) . "\s*\(([A-Z]{3})\)#ims", $this->text()),
                            ];
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*Fare type\s*:\s*([^\n]+)#");
                        },

                        "Stops" => function ($text = '', $node = null, $it = null) {
                            return re("#Non\-stop#") ? 0 : null;
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
}
