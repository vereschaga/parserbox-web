<?php

namespace AwardWallet\Engine\opentable\Email;

class It1641556 extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#member_services@opentable\.co\.uk#i', 'blank', ''],
        ['#Booking\s+made\s+by\s*:.+?Restaurant\s*:.+?\bopentable\b#si', 'blank', '15000'],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = [
        ['Your upcoming reservation at', 'blank', ''],
    ];
    public $reFrom = [
        ['#(?>member_services)?[@]opentable\.co\.uk#i', 'blank', ''],
    ];
    public $reProvider = [
        ['#\bopentable\.#i', 'blank', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "2";
    public $isAggregator = "";
    public $caseReference = "";
    public $upDate = "29.09.2015, 15:03";
    public $crDate = "";
    public $xPath = "";
    public $mailFiles = "opentable/it-1641556.eml, opentable/it-2944719.eml, opentable/it-2964614.eml, opentable/it-3075832.eml";
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
                        return "E";
                    },

                    "ConfNo" => function ($text = '', $node = null, $it = null) {
                        return orval(
                            node('//td[contains(text(), "Diner\'s name:")]/following-sibling::td[1]/strong/text()[4]'),
                            re("#Confirmation\s+number\s*:\s*([\w-]+)#i")
                        );
                    },

                    "Name" => function ($text = '', $node = null, $it = null) {
                        return orval(
                            node('//td[contains(text()[2], "Restaurant:")]/following-sibling::td[1]/strong/text()[1]'),
                            re("#\n\s*(Table\s+for[:\s]+\d+)\s+on\s+\w+#i")
                        );
                    },

                    "StartDate" => function ($text = '', $node = null, $it = null) {
                        return orval(
                            totime(str_replace(' at', '', node('//td[contains(text(), "Diner\'s name:")]/following-sibling::td[1]/strong/text()[2]'))),
                            totime(re("#Table\s+for\s+\d+\s+on\s+\w+,\s+(\w+.+?\d+)\s+at\s+(\d+:\d+\s*\w*)#i") . " " . re(2)),
                            totime(re("#Table\s+for[:\s]+\d+\s[^\n]+?(\d+:\d+\s*\w*)\s+Date\s*:\s*(\S+\s+\d+,\s*\d+)#i", $text, 2) . ", " . re(1))
                        );
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        return orval(
                            node('//td[contains(text()[2], "Restaurant:")]/following-sibling::td[1]/strong/text()[2]'),
                            nice(re("#\n\s*Restaurant\s*:\s*[^\n]+\s+(.+?)\s*\n\s*(?>[\d+\(][(\d) \-]+|See\s+menus,)#si"), ", ")
                        );
                    },

                    "Phone" => function ($text = '', $node = null, $it = null) {
                        return orval(
                            node('//td[contains(text()[2], "Restaurant:")]/following-sibling::td[1]/strong/text()[3]'),
                            re("#\n\s*Restaurant\s*:\s*(?>[^\n]+\s+){1,5}?([\d\+\(][(\d) \-]+?)\s*\n#si")
                        );
                    },

                    "DinerName" => function ($text = '', $node = null, $it = null) {
                        return orval(
                            node('//td[contains(text(), "Diner\'s name:")]/following-sibling::td[1]/strong/text()[1]'),
                            trim(re("#Booking\s+made\s+by\s*:\s*([^\n]+)#i"))
                        );
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        return orval(
                            node('//td[contains(text(), "Diner\'s name:")]/following-sibling::td[1]/strong/text()[3]'),
                            re("#\n\s*Table\s+for[:\s]+(\d+)#i")
                        );
                    },
                ],
            ],
        ];
    }

    public static function getEmailTypesCount()
    {
        return 2;
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
