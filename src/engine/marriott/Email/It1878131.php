<?php

namespace AwardWallet\Engine\marriott\Email;

class It1878131 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?Arola\s*\-#i";
    public $rePlainRange = "2000";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "#Arola\s*\-#";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#[@]marriott#i";
    public $reProvider = "#[@]marriott#i";
    public $xPath = "";
    public $mailFiles = "marriott/it-1878131.eml, marriott/it-1881410.eml";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
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
                        return [
                            'Name'    => nice(glue(re("#\n\s*Please find below the restaurant address:\s*([^\n]+)\s+(.*?)\s{3,}#ims"))),
                            'Address' => re(2),
                        ];
                    },

                    "StartDate" => function ($text = '', $node = null, $it = null) {
                        return totime(clear("#/#", re("#\n\s*\-\s*Date\s*:\s*\w+\s+([^\n]+)#"), '-') . ',' . re("#\n\s*\-\s*Time:\s*(\d+:\d+\s*[apmAPM]{2})#"));
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        return nice(glue(re("#\n\s*Please find below the restaurant address:\s*[^\n]+\s+(.*?)\s{3,}#ims")));
                    },

                    "Phone" => function ($text = '', $node = null, $it = null) {
                        return re("#don't hesitate to call\s*:\s*([\d\-\(\)+ ]+)#");
                    },

                    "DinerName" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*([^\n,]+),\s+Your booking has been#i");
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*-\s*Number of guests\s*:\s*(\d+)#");
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Your booking has been\s+(\w+)#");
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
