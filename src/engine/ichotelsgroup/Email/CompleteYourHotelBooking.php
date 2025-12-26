<?php

namespace AwardWallet\Engine\ichotelsgroup\Email;

class CompleteYourHotelBooking extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?ichotelsgroup#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "#Complete\s+your.*hotel\s+booking\s+at\s+ihg\.com#i";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#IHGRewardsClub@sm\.ihg\.com#i";
    public $reProvider = "#ihg\.com#i";
    public $xPath = "";
    public $mailFiles = "ichotelsgroup/it-1911462.eml";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return [$text];
                },

                "#.*?#" => [
                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        return CONFNO_UNKNOWN;
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        return node('//tr[contains(., "Check-in") and not(.//tr)]/preceding-sibling::tr[last()]');
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        return strtotime(re('#Check-in\s+Date:\s+(.*)#i'));
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        return strtotime(re('#Check-out\s+Date:\s+(.*)#i'));
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        $x = '//tr[contains(., "Check-in") and not(.//tr)]/ancestor::td[1]/preceding-sibling::td[string-length(normalize-space(.)) > 0][last()]//text()';
                        $subj = implode("\n", array_filter(nodes($x)));

                        if (preg_match('#((?s).*)\n\s*([\d\-\+ ]+)#i', $subj, $m)) {
                            return [
                                'Address' => nice($m[1], ','),
                                'Phone'   => $m[2],
                            ];
                        }
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        return [re('#Dear\s+(.*),#i')];
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
}
