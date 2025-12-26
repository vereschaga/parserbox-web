<?php

namespace AwardWallet\Engine\orbitz\Email;

class It1553434 extends \TAccountCheckerExtended
{
    public $mailFiles = "orbitz/it-1553434.eml, orbitz/it-1677774.eml";

    public $rePlain = [
        ['#\n[>\s*]*From\s*:[^\n]*?orbitz#i', 'blank', '1000'],
        ['Orbitz booking number#i', 'blank', '/1'],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = [
        ['#orbitz#i', 'us', ''],
    ];
    public $reProvider = [
        ['#orbitz#i', 'us', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "";
    public $caseReference = "";
    public $upDate = "29.04.2015, 14:26";
    public $crDate = "";
    public $xPath = "";
    public $re_catcher = "#.*?#";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    // this parser shoud parse only rentals (not hotels or flights)
                    if (re("#Hotel|Flight#")) {
                        return null;
                    }

                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "L";
                    },

                    "Number" => function ($text = '', $node = null, $it = null) {
                        return re("#\s+Booking Reference\s*:\s*([A-Z\d\-]+)#");
                    },

                    "PickupDatetime" => function ($text = '', $node = null, $it = null) {
                        $info = re("#\n\s*Pick\-up:\s*([^\n]+)#");

                        $date = uberDate($info, 1);
                        $time = uberTime($info, 1);
                        $dt = strtotime($time, strtotime($date));

                        $loc = reni("$time \|? (.+)", $info);

                        return [
                            'PickupDatetime' => $dt,
                            'PickupLocation' => $loc,
                        ];
                    },

                    "DropoffDatetime" => function ($text = '', $node = null, $it = null) {
                        $info = re("#\n\s*Drop\-off:\s*([^\n]+)#");

                        $date = uberDate($info, 1);
                        $time = uberTime($info, 1);
                        $dt = strtotime($time, strtotime($date));

                        $loc = reni("$time \|? (.+)", $info);

                        return [
                            'DropoffDatetime' => $dt,
                            'DropoffLocation' => $loc,
                        ];
                    },

                    "PickupPhone" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Pick\-up.*?Phone\s*:\s*([\d \-\(\)]+)#ims");
                    },

                    "DropoffPhone" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Drop\-off.*?Phone\s*:\s*([\d \-\(\)]+)#ims");
                    },

                    "RentalCompany" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*([^\n]*)\s+Booking Reference#");
                    },

                    "CarType" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Rental:\s+([^\n]+)#");
                    },

                    "RenterName" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Car reservation under\s*:\s*([^\n]+)#");
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return COST(re("#\n\s*Total car rental estimate\s*:\s*([^\n]+)#"));
                    },

                    "Currency" => function ($text = '', $node = null, $it = null) {
                        return currency(re(1));
                    },

                    "TotalTaxAmount" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#\n\s*Taxes and fees\s*([^\n]+)#"));
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Your booking is\s+([^\n]+)#");
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        return totime(re("#\n\s*This reservation was made on\s*([^\n]+)#"));
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

    public function IsEmailAggregator()
    {
        return false;
    }
}
