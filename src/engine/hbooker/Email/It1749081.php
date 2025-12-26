<?php

namespace AwardWallet\Engine\hbooker\Email;

class It1749081 extends \TAccountCheckerExtended
{
    public $reFrom = "#hbooker#i";
    public $reProvider = "#hbooker#i";
    public $rePlain = "#\n[>\s*]*Von\s*:[^\n]*?HostelBookers#i";
    public $rePlainRange = "";
    public $typesCount = "1";
    public $langSupported = "de";
    public $reSubject = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $xPath = "";
    public $mailFiles = "hbooker/it-1749081.eml";
    public $rePDF = "";
    public $rePDFRange = "";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return [$text];
                },

                "#.*?#" => [
                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Bestätigungsnummer\s*([\d\-A-Z]+)#");
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        $info = text(xpath("//*[contains(normalize-space(text()), 'Anreise / Abreise:')]/ancestor::table[1]"));

                        return [
                            'HotelName' => re("#^\s*([^\n]+)\s+(.*?)\s+Telefon:\s*([\(\)+\d\- ]+)#ims", $info),
                            'Address'   => nice(glue(re(2))),
                            'Phone'     => re(3),
                        ];
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        return totime(clear("#/#", re("#\n\s*Anreise\s*:\s*([^\n]+)#"), '-'));
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        return totime(clear("#/#", re("#\n\s*Abreise\s*:\s*([^\n]+)#"), '-'));
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Name\s*:\s*([^\n]+)#");
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Gäste\s*:\s*([^\n]+)#");
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        return node("//*[contains(normalize-space(text()), 'Zimmertyp')]/ancestor::tr[1]/following-sibling::tr[1]/td[1]//strong");
                    },

                    "Cost" => function ($text = '', $node = null, $it = null) {
                        return cost(node("//*[contains(normalize-space(text()), 'Zimmertyp')]/ancestor::tr[1]/following-sibling::tr[1]/td[2]"));
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#\n\s*Gesamt\s+([\d.,]+\s*[A-Z]{3})#"));
                    },

                    "Currency" => function ($text = '', $node = null, $it = null) {
                        return currency(re("#\n\s*Gesamt\s+([\d.,]+\s*[A-Z]{3})#"));
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
        return ["de"];
    }
}
