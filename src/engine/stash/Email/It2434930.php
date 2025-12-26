<?php

namespace AwardWallet\Engine\stash\Email;

class It2434930 extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#\n[>\s*]*From\s*:[^\n]*?stash#i', 'us', ''],
    ];
    public $reHtml = [
        ['#Stash\s+Hotel\s+Rewards#i', 'blank', '-5000'],
    ];
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = [
        ['#stash#i', 'us', ''],
    ];
    public $reProvider = [
        ['#stash#i', 'us', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "03.02.2015, 15:49";
    public $crDate = "03.02.2015, 15:36";
    public $xPath = "";
    public $mailFiles = "stash/it-2434930.eml";
    public $re_catcher = "#.*?#";
    public $pdfRequired = "0";
    public $subj;

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $this->subj = $this->parser->getSubject();

                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Confirmation Number\s+([A-Z\d\-]+)#ix");
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        if (isset($it['ConfirmationNumber']) && !empty($it['ConfirmationNumber']) && preg_match("#Your reservation confirmation at the (.+?) {$it['ConfirmationNumber']}#", $this->subj, $m)) {
                            return $m[1];
                        }

                        return re("#\n\s*(.*?) is dedicated to making your stay#ix");
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        return totime(re("#\n\s*Arrival Date\s+([^\n]+)#ix") . ',' . uberTime(re("#\n\s*Check\-in Time\s+([^\n]+)#ix")));
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        return totime(re("#\n\s*Departure Date\s+([^\n]+)#ix") . ',' . uberTime(re("#\n\s*Check\-out Time\s+([^\n]+)#ix")));
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        $addr = text(xpath("//text()[contains(., 'Phone:')]/ancestor::p[1]"));
                        detach("#\|\s*Reservations:\s*([^\n]+)#", $addr);

                        if (empty($addr) && isset($it['HotelName'])) {
                            return $it['HotelName'];
                        }

                        return [
                            'Phone'   => detach("#Phone:\s*([\d.\(\)+\-]+)#", $addr),
                            'Fax'     => detach("#\n\s*Fax:\s*([\d.\(\)+\-]+).+#s", $addr),
                            'Address' => nice(clear("#\s*\|\s*#", $addr, ", ")),
                        ];
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Guest Name\s+([^\n]+)#ix");
                    },

                    "Rate" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Nightly Rate\s+([^\d]*[\d,.]+)#") . '/night';
                    },

                    "RateType" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Rate Description\s+([^\n]+)#");
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Number of Adults\s+(\d+)#");
                    },

                    "Kids" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Number of Children\s+(\d+)#");
                    },
                    "Total" => function ($text = '', $node = null, $it = null) {
                        return total(re("#\n\s*Total Cost\s+([^\n]+)#"), 'Total');
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Cancellation\s+(?:Policy:\s*)?([^\n]+)#");
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Room Type (?:Requested\s*)?([^\n]+)#ix");
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
