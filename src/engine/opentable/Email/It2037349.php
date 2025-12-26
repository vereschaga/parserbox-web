<?php

namespace AwardWallet\Engine\opentable\Email;

class It2037349 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?@opentable[.]com#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#@opentable[.]com#i";
    public $reProvider = "#[@.]opentable[.]com#i";
    public $caseReference = "";
    public $xPath = "";
    public $mailFiles = "";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    // Parser toggled of as it is duplicate of emailYourReservationConfirmation2Checker.php
                    return null;

                    return [$text];
                },

                "#.*?#" => [
                    "ConfNo" => function ($text = '', $node = null, $it = null) {
                        return re_white('You can reference reservation number (\w+)');
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "E";
                    },

                    "Name" => function ($text = '', $node = null, $it = null) {
                        $name = node("//*[contains(normalize-space(text()), 'Your reservation is confirmed for')]/following::td[1]");

                        return nice($name);
                    },

                    "StartDate" => function ($text = '', $node = null, $it = null) {
                        $date = re_white('Party of \d+  (.+?)  Share with friends');
                        $date = uberDateTime(nice($date));

                        return strtotime($date);
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        $info = node("//*[contains(normalize-space(text()), 'Transportation & details')]/preceding::td[1]");
                        $q = white('
							(.+?)
							Cross Street: .+?
							(\( \d+ \) .+)
						');

                        if (preg_match("/$q/isu", $info, $ms)) {
                            return [
                                'Address' => nice($ms[1]),
                                'Phone'   => nice($ms[2]),
                            ];
                        }

                        return nice($info);
                    },

                    "DinerName" => function ($text = '', $node = null, $it = null) {
                        $name = node("//*[contains(normalize-space(text()), 'Party of')]/preceding::td[1]");

                        return nice($name);
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        return re_white('Party of (\d+)');
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        if (re_white('Your reservation is confirmed for')) {
                            return 'confirmed';
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
