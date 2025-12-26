<?php

namespace AwardWallet\Engine\mirage\Email;

class ReservationConfirmationPDF extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#\n[>\s*]*From\s*:[^\n]*?(?:noreply@mgmresorts|montecarlo)\.com#i', 'blank', ''],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = [
        ['#noreply@mgmresorts\.com#i', 'blank', ''],
    ];
    public $reProvider = [
        ['#[@.]mgmresorts\.com#i', 'blank', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "06.05.2015, 07:58";
    public $crDate = "06.05.2015, 07:51";
    public $xPath = "";
    public $mailFiles = "";
    public $re_catcher = "#.*?#";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $text = $this->setDocument('application/pdf', 'text');

                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        return re('#Confirmation\s+(?:No\.|Number)\s*:\s+([\w\-]+)#');
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        $hn = re('#Thank\s+you\s+for\s+choosing\s+(.*?)\s+as\s+your\s+resort\s+destination#');

                        return [
                            'HotelName' => $hn,
                            'Address'   => $hn,
                        ];
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        return strtotime(re('#Arrival:\s+(\d+\/\d+\/\d+)#'));
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        return strtotime(re('#Departure:\s+(\d+\/\d+\/\d+)#'));
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        return re('#\n\s*Name:\s+(.*)#');
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        return re('#(?:No\.\s+of\s+Guests|Number\s+of\s+Guests):\s+(\d+)#');
                    },

                    "Rate" => function ($text = '', $node = null, $it = null) {
                        return re('#Rate:\s+(.*)#');
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        return re('#Room\s+Type:\s+(.*)#');
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
