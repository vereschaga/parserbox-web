<?php

namespace AwardWallet\Engine\tripbam\Email;

class Confirmation extends \TAccountCheckerExtended
{
    public $reFrom = "#notifications@tripbam\.com#i";
    public $reProvider = "#tripbam\.com#i";
    public $rePlain = "#tripBAM has confirmed a reservation#i";
    public $typesCount = "1";
    public $langSupported = "en";
    public $reSubject = "";
    public $reHtml = "";
    public $xPath = "";
    public $mailFiles = "tripbam/it-1650830.eml, tripbam/it-2367778.eml, tripbam/it-2369305.eml";
    public $pdfRequired = "0";

    private $date = 0;

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
                        return re('#Confirmation\s+Number:\s+([\w\-]+)#');
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        return re('#Hotel\s+Name:\s+(.*)#');
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $regex = '#Dates:\s+(\w+\s+\d+)\s+-\s+(\w+\s+\d+)#';

                        if (preg_match($regex, $text, $m)) {
                            $ci = strtotime($m[1], $this->date);
                            $co = strtotime($m[2], $this->date);

                            return ['CheckInDate' => $ci, 'CheckOutDate' => $co];
                        }
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        return re('#Hotel\s+Address:\s+(.*)#');
                    },

                    "Phone" => function ($text = '', $node = null, $it = null) {
                        return re('#Hotel\s+Phone:\s+(.*)#');
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        return [re('#Guest\s+Name:\s+(.*)#')];
                    },

                    "Rate" => function ($text = '', $node = null, $it = null) {
                        return re('#Nightly\s+Rate:\s+(.*)#');
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        return re('#Hotel\s+Cancellation\s+Policy:\s+(.*)#');
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        return re('#Room\s+Description:\s+(.*)#');
                    },
                ],
            ],
        ];
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getHeader('Date'));
        $result = parent::ParsePlanEmail($parser);

        return $result;
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
