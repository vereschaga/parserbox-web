<?php

namespace AwardWallet\Engine\worldhotels\Email;

class It2087046 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?worldhotels#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#worldhotels#i";
    public $reProvider = "#worldhotels#i";
    public $caseReference = "";
    public $isAggregator = "0";
    public $xPath = "";
    public $mailFiles = "worldhotels/it-2087046.eml";
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
                        $node = node("(//*[contains(text(), 'Arrival Date')]/ancestor-or-self::table[1]//tr[1])[1]");
                        $number = re("#[A-Z\d-]+#", $node);

                        return $number;
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        $node = node("(//*[contains(text(), 'Arrival Date')]/ancestor-or-self::td[3]/following-sibling::td[2]//table[1]//tr[1]//font[1])[1]");

                        return $node;
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $node = cell("Arrival Date:", +1);
                        $node = uberDate($node) . " 00:00";

                        return totime($node);
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        $node = cell("Departure Date:", +1);
                        $node = uberDate($node) . " 00:00";

                        return totime($node);
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        $node = node("(//*[contains(text(), 'Arrival Date')]/ancestor-or-self::td[3]/following-sibling::td[2]//table[1]//tr[1]//font[1])[2]");

                        return $node;
                    },

                    "Phone" => function ($text = '', $node = null, $it = null) {
                        $node = node("(//*[contains(text(), 'Arrival Date')]/ancestor-or-self::td[3]/following-sibling::td[2]//table[1]//tr[1]//font[2])[1]");

                        $node = re("#[0-9\s*+]+#", $node);

                        return trim($node);
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        $node = node("(//*[contains(text(), 'Arrival Date')]/ancestor-or-self::table[1]//tr[1])[1]");
                        $node = re("#[A-Z\d-]+,\s*([^\n]+)#", $node);

                        return $node;
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        $node = cell("adults / children", +1);
                        $ad = re("#[\d]#", $node);
                        $ch = re("#/\s*([\d])#", $node);
                        $guests = $ad + $ch;

                        return [
                            "Guests" => $guests,
                            "Kids"   => $ch,
                        ];
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
