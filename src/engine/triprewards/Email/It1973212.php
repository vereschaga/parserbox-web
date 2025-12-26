<?php

namespace AwardWallet\Engine\triprewards\Email;

class It1973212 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?wyn.com#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#wyn.com#i";
    public $reProvider = "#wyn.com#i";
    public $caseReference = "";
    public $xPath = "";
    public $mailFiles = "triprewards/it-1973212.eml";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $this->date = strtotime($this->parser->getHeader("date"));

                    return [$text];
                },

                "#.*?#" => [
                    "ConfNo" => function ($text = '', $node = null, $it = null) {
                        return CONFNO_UNKNOWN;
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "E";
                    },

                    "Name" => function ($text = '', $node = null, $it = null) {
                        return re("#(?:^|\n)\s*Name\s*:\s*([^\n]+)#");
                    },

                    "StartDate" => function ($text = '', $node = null, $it = null) {
                        return strtotime(re("#\n\s*Date of reservation\s*:\s*([^\n]+)#") . ',' . re("#\n\s*Preferred reservation time:\s*([^\n]+)#"), $this->date);
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        return 'Dishoom ' . re("#\n\s*Which Dishoom do you want to visit\?\s*:\s*([^\n]+)#");
                    },

                    "Phone" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Phone number\s*:\s*([^\n]+)#");
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Number of guests\s*:\s*(\d+)#");
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
