<?php

namespace AwardWallet\Engine\marriott\Email;

// parsers with similar formats: gcampaigns/HResConfirmation (object), marriott/ReservationConfirmation (object), mirage/It1591085, triprewards/It3520762, woodfield/It2220680, goldpassport/WelcomeTo

class It2506177 extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#Marriott\s+International#i', 'blank', '-1000'],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = [
        ['#[@.]marriott[.]#i', 'us', ''],
    ];
    public $reProvider = [
        ['#[@.]marriott[.]#i', 'us', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "26.02.2015, 08:37";
    public $crDate = "26.02.2015, 08:32";
    public $xPath = "";
    public $mailFiles = "marriott/it-2506177.eml, marriott/it-2506178.eml";
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
                        $number = nice(cell(['Hotel Confirmation:', 'Hotel Confirmation'], +1));

                        if (empty($number)) {
                            $number = nice(cell(['Online Confirmation:'], +1));
                        }

                        return $number;
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        $name = reni('at the (.+?) (?: and | $)', $this->parser->getHeader('subject'));

                        return [
                            'HotelName' => $name,
                            'Address'   => $name,
                        ];
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        return totime(cell('Arrival Date', +1));
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        return totime(cell('Departure Date', +1));
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        return [nice(cell('Reservation Name', +1))];
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        return nice(cell(['Cancellation Policy', 'Cancel Policy:'], +1));
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        return nice(cell('Room Type', +1));
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
