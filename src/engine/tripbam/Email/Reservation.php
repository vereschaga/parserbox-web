<?php

namespace AwardWallet\Engine\tripbam\Email;

class Reservation extends \TAccountCheckerExtended
{
    public $reFrom = "#notifications@tripbam\.com#i";
    public $reProvider = "#tripbam\.com#i";
    public $rePlain = "#tripBAM reserved a hotel for you#i";
    public $typesCount = "1";
    public $langSupported = "en";
    public $reSubject = "";
    public $reHtml = "";
    public $xPath = "";
    public $mailFiles = "tripbam/it-1630462.eml, tripbam/it-1631143.eml, tripbam/it-1639913.eml";
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
                        return re('#Your\s+new\s+confirmation\s+number\s+is\s+-\s+([\w\-]+)\s*\.#');
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        $res = [];
                        $regex = '#';
                        $regex .= 'you\s+must\s+accept\s+or\s+decline\s+this\s+rate\s+';
                        $regex .= 'by\s+clicking\s+here\s+or\s+the\s+link\s+below\.\s+';
                        $regex .= '(?P<HotelName>.*?)\n';
                        $regex .= '(?P<Address>.*?)\n';
                        $regex .= '(?P<Phone>[\d\-]+)\n';
                        $regex .= '\n\n';
                        $regex .= '#is';

                        if (preg_match($regex, $text, $m)) {
                            $m['Address'] = nice($m['Address'], ',');
                            copyArrayValues($res, $m, ['HotelName', 'Address', 'Phone']);
                        }

                        return $res;
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $regex = '#for\s+your\s+hotel\s+stay\s+in\s+.*?\s+from\s+(\w+\s+\d+)\s+through\s+(\w+\s+\d+)#';

                        if (preg_match($regex, $text, $m)) {
                            $ci = strtotime($m[1], $this->date);
                            $co = strtotime($m[2], $this->date);

                            return ['CheckInDate' => $ci, 'CheckOutDate' => $co];
                        }
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        return [re('#Name:\s+(.*)\s+Search#')];
                    },

                    "Rate" => function ($text = '', $node = null, $it = null) {
                        return re('#.*\s+average\s+nightly\s+rate#');
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
