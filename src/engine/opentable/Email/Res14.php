<?php

namespace AwardWallet\Engine\opentable\Email;

class Res14 extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#Thanks for using Rezbook to make your reservation at#i', 'us', ''],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = [
        ['#rez-noreply@opentable\.com#i', 'us', ''],
    ];
    public $reProvider = [
        ['#opentable\.com#i', 'us', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "";
    public $caseReference = "";
    public $upDate = "15.01.2015, 10:46";
    public $crDate = "";
    public $xPath = "";
    public $mailFiles = "opentable/it-4.eml";
    public $re_catcher = "#.*?#";
    public $pdfRequired = "";

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
                        return CONFNO_UNKNOWN;
                    },

                    "Name" => function ($text = '', $node = null, $it = null) {
                        return nice(re('#Thanks for using .* to make your reservation at ([^!]+)!#U'));
                    },

                    "StartDate" => function ($text = '', $node = null, $it = null) {
                        $year = re('#\d{4}#i', $this->parser->getHeader('date'));

                        if (!$year) {
                            return null;
                        }
                        $whenStr = re("#When: (.*)#");

                        if (preg_match('#([0-9]+):([0-9]+)([a-z]+) ([a-z]+) ([a-z]+) ([0-9]+)#i', $text, $m)) {
                            [$hour, $min, $ampm, $weekDay, $month, $day] = array_slice($m, 1);

                            return strtotime("$hour:$min $ampm $month $day $year");
                        } else {
                            return null;
                        }
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        return trim(str_replace("\n", ' ', re("#Here's the address:(.*)http://#msU")));
                    },

                    "Phone" => function ($text = '', $node = null, $it = null) {
                        return re("#If\s+you\sneed\sto\smake\schanges\sto\sor\shave\squestions\sabout\syour\sreservation,\splease\s+contact\sHillstone\s\(Park Avenue\)\sdirectly\sat\s([\(\) 0-9\-]+)#");
                    },

                    "DinerName" => function ($text = '', $node = null, $it = null) {
                        return re("#Name: (.*)#");
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        return (int) re("#Party: ([0-9]+) people#");
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re("#Your reservation has been ([a-z]+), and no further action is needed#");
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
