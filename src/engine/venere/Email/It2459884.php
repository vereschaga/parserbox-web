<?php

namespace AwardWallet\Engine\venere\Email;

class It2459884 extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#\n[>\s*]*From\s*:[^\n]*?venere#i', 'us', ''],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = [
        ['#venere#i', 'us', ''],
    ];
    public $reProvider = [
        ['#venere#i', 'us', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "sv";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "15.02.2015, 18:11";
    public $crDate = "15.02.2015, 17:58";
    public $xPath = "";
    public $mailFiles = "venere/it-2459884.eml";
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
                        return re("#\n\s*Här är din bokningskod\s*:\s*([A-Z\d\-]+)#");
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*HOTELL\s*:\s*([^\n]+)#");
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        return totime(en(re("#\n\s*INCHECKNINGSDATUM\s*:\s*([^\n]+)#")));
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        return totime(en(re("#\n\s*UTCHECKNINGSDATUM\s*:\s*([^\n]+)#")));
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        return nice(re("#\n\s*ADRESS\s*:\s*(.*?)\n\s*(HEMSIDA:|TELEFON)#s"));
                    },

                    "Phone" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*TELEFON\s*:\s*([^\n]+)#");
                    },

                    "Fax" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*FAX\s*:\s*([^\n]+)#");
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*NAMN\s*:\s*([^\n]+)#");
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*VUXNA\s*:\s*(\d+)#");
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        return node("//*[contains(text(), 'Avbokningsregler')]/following::p[1]");
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        return clear("#\s+Boka\s+\d+.+#", re("#\n\s*([^\n]+)\s+INCHECKNINGSDATUM#"));
                    },

                    "Taxes" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#Moms (.*?) totalt#ix"));
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        return total(re("#\n\s*Totalt pris\s+([^\n]+)#ix"), 'Total');
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
