<?php

namespace AwardWallet\Engine\perfectdrive\Email;

class It2891216 extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#Thank\s+you\s+for\s+booking\s+Budget\.#i', 'blank', ''],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = [
        ['Your Budget booking confirmation', 'blank', ''],
    ];
    public $reFrom = [
        ['#[@.]budget(?:group)?\.com#i', 'blank', ''],
    ];
    public $reProvider = [
        ['#\bbudget(?:group)?\.com#i', 'blank', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "13.07.2015, 17:24";
    public $crDate = "13.07.2015, 16:49";
    public $xPath = "";
    public $mailFiles = "perfectdrive/it-2891216.eml";
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
                        return "L";
                    },

                    "Number" => function ($text = '', $node = null, $it = null) {
                        return re("#\sreservation\s+number\s+is\s+([\w-]+)#");
                    },

                    "PickupLocation" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Pickup\s+location:\s*([^\n]+)#");
                    },

                    "PickupDatetime" => function ($text = '', $node = null, $it = null) {
                        $dates = [];
                        $dates['PickupDatetime'] = re("#\n\s*Rental\s+from\s+\w+,\s+(\d+\s+\w+\s+\d{4}\s+\d+:\d+|\d+\.\d+.\d{4}\s+\d+:\d+).+?\s\w+,\s+(\d+\s+\w+\s+\d{4}\s+\d+:\d+|\d+\.\d+.\d{4}\s+\d+:\d+)#");
                        $dates['PickupDatetime'] = strtotime($dates['PickupDatetime']);
                        $dates['DropoffDatetime'] = strtotime(re(2));

                        return $dates;
                    },

                    "DropoffLocation" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Return\s+location:\s*([^\n]+)#");
                    },

                    "PickupPhone" => function ($text = '', $node = null, $it = null) {
                        return orval(
                            re("#\n\s*Pickup\s+location:\s*(?>[^\n]+\s+){1,3}?([\d-()]+)#"),
                            re("#\n\s*Telephone\s+\(\s*(.*?)\s*\)#")
                        );
                    },

                    "PickupHours" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Opening hours on day of pick-up:\s*([^\n]+)#");
                    },

                    "DropoffPhone" => function ($text = '', $node = null, $it = null) {
                        return orval(
                            re("#\n\s*Return\s+location:\s*(?>[^\n]+\s+){1,3}?([\d-()]+)#"),
                            ure("#\n\s*Telephone\s+\(\s*(.*?)\s*\)#", 2)
                        );
                    },

                    "DropoffHours" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Opening hours on day of return:\s*([^\n]+)#");
                    },

                    "CarType" => function ($text = '', $node = null, $it = null) {
                        $data = [];
                        $data['CarType'] = orval(
                            re("#Car group:\s*(.+?)\s*\((.+?)\)\s*\n#"),
                            re("#Car group:\s+(.*?)\s+-\s+e\.g\.\s+(.+)\s*\n#")
                        );
                        $data['CarModel'] = re(2);

                        return $data;
                    },

                    "RenterName" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*YOUR RENTAL DETAILS:[\s*]*([^\n]+)#i");
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return total(re("#\n\s*Price:\s*(.+)#"), 'TotalCharge');
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        return strtotime(re("#\n\s*Date of Issue:\s*(\d+\s+\w+\s+\d{4}\s\d+:\d+)#"));
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
