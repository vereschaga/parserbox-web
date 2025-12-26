<?php

namespace AwardWallet\Engine\tport\Email;

class It2209585 extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#itinerary\s+has\s+been\s+cancelled\s+as\s+requested.*Travelport#is', 'us', '1000'],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = [
        ['#OTR@travelport\.com#i', 'us', ''],
    ];
    public $reProvider = [
        ['#OTR@travelport\.com#i', 'us', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "14.04.2015, 20:19";
    public $crDate = "";
    public $xPath = "";
    public $mailFiles = "tport/it-2209585.eml";
    public $re_catcher = "#.*?#";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $userEmail = strtolower(re("#\n\s*To\s*:[^\n]*?([^\s@\n]+@[\w\-.]+)#"));

                    if (!$userEmail) {
                        $userEmail = niceName(re("#\n\s*To\s*:\s*([^\n]+)#"));
                    }

                    if (!$userEmail) {
                        $userEmail = strtolower(re("#([a-zA-Z0-9_.+-]+@[a-zA-Z0-9-.]+)#", $this->parser->getHeader("To")));
                    }

                    if (!$userEmail) {
                        $userEmail = strtolower($this->parser->getHeader("To"));
                    }

                    if ($userEmail) {
                        $userEmail = explode(',', $userEmail);

                        if (count($userEmail) > 0) {
                            $this->parsedValue('userEmail', array_shift($userEmail));
                        }
                    }

                    if (!re_white('Hotel Cancellation Number:')) {
                        return null;
                    }

                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        return re_white('Agency Confirmation Number: (\w+)');
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $date = nice(re_white('Trip Dates: (.+?) -'));

                        return totime($date);
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        $date = nice(re_white('Trip Dates: (?:.+?) - (.+?) Hotel'));

                        return totime($date);
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        return [between('Traveler Name:', 'Trip Name:')];
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
