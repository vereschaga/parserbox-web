<?php

namespace AwardWallet\Engine\spg\Email;

class YourCashAndPointsAwardConfirmation extends \TAccountCheckerExtended
{
    public $rePlain = "#Congratulations,\s+you've\s+successfully\s+redeemed\s+Starpoints#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#GCCUSTSERVICE@CONFIRM\.STARWOODHOTELS\.COM#i";
    public $reProvider = "#CONFIRM\.STARWOODHOTELS\.COM#i";
    public $xPath = "";
    public $mailFiles = "spg/it-1810524.eml, spg/it-1884307.eml";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    if (!preg_match($this->rePlain, $text)) {
                        // Ignore emails of other formats
                        return null;
                    }

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
                        $res['HotelName'] = re('#Hotel:\s+(.*)#i');
                        $res['Address'] = $res['HotelName'];

                        return $res;
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        if (preg_match('#Stay\s+Dates:\s+(.*)\s+through\s+(.*)#i', $text, $m)) {
                            return [
                                'CheckInDate'  => strtotime($m[1]),
                                'CheckOutDate' => strtotime($m[2]),
                            ];
                        }
                    },

                    "SpentAwards" => function ($text = '', $node = null, $it = null) {
                        if (preg_match('#Number\s+of\s+(.*)\s+redeemed:\s+(.*)#i', $text, $m)) {
                            return $m[2] . ' ' . $m[1];
                        }
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
