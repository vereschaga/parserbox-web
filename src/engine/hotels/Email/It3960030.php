<?php

namespace AwardWallet\Engine\hotels\Email;

class It3960030 extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#\n[>\s*]*From\s*:[^\n]*?hotels#i', 'us', ''],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = [
        ['#[@.]hotels\.com#i', 'us', ''],
    ];
    public $reProvider = [
        ['#[@.]hotels\.com#i', 'us', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "da";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "23.06.2016, 09:19";
    public $crDate = "23.06.2016, 07:44";
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
                        return "R";
                    },

                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        return re('#Bekræftelsesnummer:\s+(\d+)#');
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        return nice(re('#(.*)\s+.*\s+Gæster:#'));
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $d = str_replace('/', '.', re('#Indtjekning\s+(.*)#'));
                        $t = re('#Indtjekning\s+.*\s+kl\.\s+(.*)#');

                        if ($d and $t) {
                            return strtotime($d . ', ' . $t . ':00');
                        }
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        $d = str_replace('/', '.', re('#Udtjekning\s+(.*)#'));
                        $t = re('#Udtjekning\s+.*\s+kl\.\s+(.*)#');

                        if ($d and $t) {
                            return strtotime($d . ', ' . $t . ':00');
                        }
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        return nice(re('#(.*)\s+Gæster:#'));
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        return nice(re('#Gæster:\s+voksne\s+-\s+(\d)#'));
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        return total(re('#Samlet pris\s+(.*)#'), 'Total');
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
        return ["da"];
    }

    public function IsEmailAggregator()
    {
        return false;
    }
}
