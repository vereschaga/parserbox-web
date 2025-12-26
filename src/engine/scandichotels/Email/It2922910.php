<?php

namespace AwardWallet\Engine\scandichotels\Email;

class It2922910 extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#Bekräftelse\s.+?\sBokningsnummer\s*:.+?\sHotell\s.+?\bscandichotels\.#si', 'blank', '15000'],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = [
        ['Bekräftelse', 'blank', ''],
    ];
    public $reFrom = [
        ['#[@.]scandichotels\.#i', 'blank', ''],
    ];
    public $reProvider = [
        ['#[@.]scandichotels\.#i', 'blank', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "se";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "03.08.2015, 14:33";
    public $crDate = "03.08.2015, 13:10";
    public $xPath = "";
    public $mailFiles = "scandichotels/it-2922910.eml";
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
                        return re("#Bokningsnummer\s*:\s*([\w-]+)#");
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Hotell\s+([^\n]+)#");
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        return totime(en(re("#\n\s*Ankomstdag\s+[^\s]+\s+(\d+\s+[^\s]+\s+\d+)#"), "sv") . " 00:00");
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        return strtotime(en(re("#\n\s*Avresedag\s+[^\s]+\s+(\d+\s+[^\s]+\s+\d+)#"), "sv") . " 00:00");
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        return re("#Adress\-?\s*och\s+kontaktinformation\s+([^\n]+)#");
                    },

                    "Phone" => function ($text = '', $node = null, $it = null) {
                        return re("#Om hotellet\s.+?Telefon\s*:\s*([+\d(][(\d) \-]+)#si");
                    },

                    "Fax" => function ($text = '', $node = null, $it = null) {
                        return re("#Om hotellet\s.+?Fax(?>\s*nummer)?\s*:\s*([+\d(][(\d) \-]+)#si");
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        return [re("#Kontaktperson\s+Namn\s+([^\n]+)#")];
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Gäster\s+[^\n]*?(\d+)\s*Vuxna#");
                    },

                    "Kids" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Gäster\s+[^\n]*?(\d+)\s*Barn#");
                    },

                    "Rooms" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Antal\s+rum\s+(\d+)#");
                    },

                    "Rate" => function ($text = '', $node = null, $it = null) {
                        return re("#Pris\s+per\s+natt\s+([^\n]+?)(?:\s*\n|\s\s)#");
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        return re("#Garanti\-?\s*och\s+avbokningspolicy\s+([^\n]+)#");
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        return re("#\sRumstyp\s*:\s*([^\n]+?)(?:\s+Pris\s+per\s+natt|\s*\n)#");
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        return total(re("#\n\s*Totalt\s+([^\n]+)#"), "Total");
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
        return ["sv"];
    }

    public function IsEmailAggregator()
    {
        return false;
    }
}
