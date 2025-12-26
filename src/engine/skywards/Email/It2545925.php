<?php

namespace AwardWallet\Engine\skywards\Email;

class It2545925 extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#\n[>\s*]*From\s*:[^\n]*?skywards#i', 'us', ''],
    ];
    public $reHtml = "";
    public $rePDF = [
        ['#Emirates(?:&\#160;|\s)+e\-ticket#i', 'blank', '2000'],
    ];
    public $reSubject = "";
    public $reFrom = [
        ['#[@.]skywards#i', 'us', ''],
    ];
    public $reProvider = [
        ['#[@.]skywards#i', 'us', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "15.03.2015, 14:38";
    public $crDate = "15.03.2015, 13:59";
    public $xPath = "";
    public $mailFiles = "skywards/it-2545925.eml";
    public $re_catcher = "#.*?#";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return [$text = $this->setDocument("#ticket#i", "simpletable")];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*([A-Z\d\-]{4,})\s+Reservation\s+number#");
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $names = explode("\n", text(xpath("//*[contains(text(), 'Passenger') and contains(text(), 'names:')]/following::tr[1]/td[string-length(normalize-space(.))>1][1]")));

                        foreach ($names as &$name) {
                            $name = nice(clear("#\d+\.\s*|\(.+#", $name));
                        }

                        return $names;
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return total(re("#\n\s*Total[:\s]+([^\n]+)#"));
                    },

                    "BaseFare" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#\n\s*Fare paid[:\s]+([^\n]+)#"));
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        return totime(re("#\n\s*Date:\s*([^\n]+)#"));
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return splitter("#\n\s*([A-Z\d]{2}\s*\d+\s+[A-Z]\s+\d{1,2}[A-Z]{3})#");
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $anchor = re("#\n\s*Date:\s*([^\n]+)#", $this->text());

                            return [
                                'AirlineName'  => re("#^([A-Z\d]{2})\s*(\d+)\s+([A-Z])\s+(\d{1,2}[A-Z]{3})\s+([A-Z]{3})\s*([A-Z]{3})\s+.*?\s+(\d{4})\s+(\d{4})\s+(\d{1,2}[A-Z]{3})\s*\(([^\)]+)#s"),
                                'FlightNumber' => re(2),
                                'BookingClass' => re(3),
                                'DepCode'      => re(5),
                                'ArrCode'      => re(6),
                                'DepDate'      => correctDate(re(4) . ', ' . re(7), $anchor),
                                'ArrDate'      => correctDate(re(9) . ', ' . re(8), $anchor),
                                'Cabin'        => re(10),
                            ];
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
