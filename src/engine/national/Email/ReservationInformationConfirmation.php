<?php

namespace AwardWallet\Engine\national\Email;

class ReservationInformationConfirmation extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#\n[>\s*]*From\s*:[^\n]*?nationalcar\.#i', 'us', ''],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = [
        ['#[@.]nationalcar\.#i', 'us', ''],
    ];
    public $reProvider = [
        ['#[@.]nationalcar\.#i', 'us', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "24.06.2016, 10:41";
    public $crDate = "23.06.2016, 12:40";
    public $xPath = "";
    public $mailFiles = "national/it-3913033.eml";
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
                        return re('#Confirmation\#:\s+(\d+)#');
                    },

                    "PickupLocation" => function ($text = '', $node = null, $it = null) {
                        $s = implode("\n", nodes('//td[normalize-space(.) = "Pickup Date:"]/ancestor::tr[1]/following-sibling::tr[2]/td[2]//text()'));

                        if (preg_match('#((?s).*)\n(.*)#i', $s, $m)) {
                            return [
                                'PickupLocation' => nice($m[1], ','),
                                'PickupPhone'    => $m[2],
                            ];
                        }
                    },

                    "PickupDatetime" => function ($text = '', $node = null, $it = null) {
                        return strtotime(str_replace(' at ', ', ', cell('Pickup Date:', +1)));
                    },

                    "DropoffLocation" => function ($text = '', $node = null, $it = null) {
                        $s = implode("\n", nodes('//td[normalize-space(.) = "Return Date:"]/ancestor::tr[1]/following-sibling::tr[2]/td[2]//text()'));

                        if (preg_match('#((?s).*)\n(.*)#i', $s, $m)) {
                            return [
                                'DropoffLocation' => nice($m[1], ','),
                                'DropoffPhone'    => $m[2],
                            ];
                        }
                    },

                    "DropoffDatetime" => function ($text = '', $node = null, $it = null) {
                        return strtotime(str_replace(' at ', ', ', cell('Return Date:', +1)));
                    },

                    "PickupHours" => function ($text = '', $node = null, $it = null) {
                        return node('//td[normalize-space(.) = "Pickup Date:"]/ancestor::tr[1]/following-sibling::tr[1]/td[2]');
                    },

                    "DropoffHours" => function ($text = '', $node = null, $it = null) {
                        return node('//td[normalize-space(.) = "Return Date:"]/ancestor::tr[1]/following-sibling::tr[1]/td[2]');
                    },

                    "CarType" => function ($text = '', $node = null, $it = null) {
                        return cell('Type of Car:', +1);
                    },

                    "CarModel" => function ($text = '', $node = null, $it = null) {
                        return cell('Examples:', +1);
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return cell('Estimated Total Charges:', +1);
                    },

                    "Currency" => function ($text = '', $node = null, $it = null) {
                        return 'USD';
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
