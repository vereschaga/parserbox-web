<?php

namespace AwardWallet\Engine\perfectdrive\Email;

class It2074702 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?@avisbudget[.]com#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "#Budget\s+Reservation#";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#@avisbudget[.]com#i";
    public $reProvider = "#[@.]avisbudget[.]com#i";
    public $caseReference = "";
    public $isAggregator = "0";
    public $xPath = "";
    public $mailFiles = "perfectdrive/it-2074702.eml";
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
                        return re_white('Reservation number (\w+)');
                    },

                    "PickupDatetime" => function ($text = '', $node = null, $it = null) {
                        // f slashes.
                        $q = white('
							Drop-Off - \w+
							(?P<PickupDatetime>.+? (?:PM|AM)) -
							(?P<DropoffDatetime>.+? (?:PM|AM))
							(?P<PickupLocation>.+?)
							hours
						');

                        if (!preg_match("/$q/isu", $text, $ms)) {
                            return;
                        }
                        $res = $ms;

                        $res['DropoffLocation'] = $res['PickupLocation'];

                        // remove numbers
                        $n = sizeof($ms);

                        for ($i = 0; $i < $n; $i++) {
                            unset($res[$i]);
                        }

                        $res = nice($res);
                        $res['PickupDatetime'] = strtotime($res['PickupDatetime']);
                        $res['DropoffDatetime'] = strtotime($res['DropoffDatetime']);

                        return $res;
                    },

                    "PickupPhone" => function ($text = '', $node = null, $it = null) {
                        return re_white('phone ([\d-]+)');
                    },

                    "PickupHours" => function ($text = '', $node = null, $it = null) {
                        return between('hours', 'phone');
                    },

                    "CarType" => function ($text = '', $node = null, $it = null) {
                        $x = node("//*[contains(@class, 'car-class-info')]");
                        $x = clear('/car\s*/i', $x);

                        return nice($x);
                    },

                    "CarModel" => function ($text = '', $node = null, $it = null) {
                        $x = node("//*[contains(@class, 'car-make-info')]");

                        return nice($x);
                    },

                    "RenterName" => function ($text = '', $node = null, $it = null) {
                        return between('Personal Information', 'Email:');
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        $x = re_white('Estimated Total	(\d+[.]\d+ \w+)');

                        return total($x);
                    },

                    "Discount" => function ($text = '', $node = null, $it = null) {
                        $x = re_white('Coupon Disct  -(\d+[.]\d+ \w+)');

                        return cost($x);
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
