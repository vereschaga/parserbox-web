<?php

namespace AwardWallet\Engine\goldcrown\Email;

class It2313132 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?[@.]bestwestern[.]com#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#[@.]bestwestern[.]com#i";
    public $reProvider = "#[@.]bestwestern[.]com#i";
    public $caseReference = "";
    public $isAggregator = "0";
    public $upDate = "29.12.2014, 07:28";
    public $crDate = "29.12.2014, 07:05";
    public $xPath = "";
    public $mailFiles = "goldcrown/it-2313132.eml";
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
                        return reni('Your confirmation number is: (\w+)');
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        $name = node("//*[contains(text(), 'confirmation number')]
							/following::font[1]");
                        $addr = reni("
							$name
							(.+?)
							Phone:
						");

                        return [
                            'HotelName' => $name,
                            'Address'   => $addr,
                        ];
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $date = re('#Your stay\s+(.+\d{4})\s*-\s*.+#');

                        return totime($date);
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        $date = re('#Your stay\s+.+\d{4}\s*-(\s*.+)#');

                        return totime($date);
                    },

                    "Phone" => function ($text = '', $node = null, $it = null) {
                        return reni('Phone: ([\d-]+)');
                    },

                    "Fax" => function ($text = '', $node = null, $it = null) {
                        return reni('Fax: ([\d-]+)');
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        return [reni('confirmation for: (.+?) \n')];
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        return reni('Guests: (\d+)');
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        return reni('
							Cancellation Policy
							(.+?)
							Special Requests:
						');
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        $info = node("//*[contains(text(), 'Guests:')]/following::font[1]");
                        $q = white('
							(?P<RoomType> .+?) ,
							(?P<RoomTypeDescription> .+)
						');
                        $res = re2dict($q, $info);

                        return $res;
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        $x = reni('Total Reservation ([\d.,]+ \w+)');

                        return total($x, 'Total');
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
