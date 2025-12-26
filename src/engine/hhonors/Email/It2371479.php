<?php

namespace AwardWallet\Engine\hhonors\Email;

class It2371479 extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#Hilton\s+Worldwide#i', 'us', '-1000'],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = [
        ['#[@.]hilton[.]com#i', 'us', ''],
    ];
    public $reProvider = [
        ['#res[@.]hilton[.]com#i', 'us', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "27.01.2015, 08:14";
    public $crDate = "21.01.2015, 07:51";
    public $xPath = "";
    public $mailFiles = "hhonors/it-2371479.eml";
    public $re_catcher = "#.*?#";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    if (!rew('your reservation has been canceled')) {
                        return;
                    }

                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        return reni('(?:
								CANCELLATION |
								CONFIRMATION
							) :  (\w+)
						');
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $date = reni('YOUR STAY DATES: (.+? \d{4})');

                        return totime($date);
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        $date = reni('YOUR STAY DATES: .+? \d{4} (.+? \d{4})');

                        return totime($date);
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        return [reni('Welcome, (\w.+?) \n')];
                    },

                    "AccountNumbers" => function ($text = '', $node = null, $it = null) {
                        return reni('Account: (\d+)');
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return 'cancelled';
                    },

                    "Cancelled" => function ($text = '', $node = null, $it = null) {
                        return true;
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
