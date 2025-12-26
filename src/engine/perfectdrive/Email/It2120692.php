<?php

namespace AwardWallet\Engine\perfectdrive\Email;

class It2120692 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?@Budget[.]com[.]au#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#Budget[.]com[.]au#i";
    public $reProvider = "#[@.]Budget[.]com[.]au#i";
    public $caseReference = "";
    public $isAggregator = "0";
    public $xPath = "";
    public $mailFiles = "perfectdrive/it-2120692.eml, perfectdrive/it-2120878.eml";
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
                        return "L";
                    },

                    "Number" => function ($text = '', $node = null, $it = null) {
                        return re_white('RESERVATION NUMBER (\w+)');
                    },

                    "PickupLocation" => function ($text = '', $node = null, $it = null) {
                        $loc = re_white('
							PICKUP
							.+? (?:PM|AM)
							(.+?)
							Ph:
						');

                        return nice($loc);
                    },

                    "PickupDatetime" => function ($text = '', $node = null, $it = null) {
                        $info = re_white('
							PICKUP
							(?: [-]+)?
							(.+? (?:PM|AM))
						');

                        $date = re_white('(?: [a-z]+ ,)? (.+?) \s+ (\d+:\d+) .+? (PM|AM)', $info);
                        $date = nice($date);
                        $time = re(2);
                        $a = re(3);

                        $dt = "$date $time $a";
                        $dt = timestamp_from_format($dt, 'd/m/Y h:i a');

                        return $dt;
                    },

                    "DropoffLocation" => function ($text = '', $node = null, $it = null) {
                        $loc = re_white('
							RETURN
							.+? (?:PM|AM)
							(.+?)
							Ph:
						');

                        return nice($loc);
                    },

                    "DropoffDatetime" => function ($text = '', $node = null, $it = null) {
                        $info = re_white('
							RETURN
							(?: [-]+)?
							(.+? (?:PM|AM))
						');

                        $date = re_white('(?: [a-z]+ ,)? (.+?) \s+ (\d+:\d+) .+? (PM|AM)', $info);
                        $date = nice($date);
                        $time = re(2);
                        $a = re(3);

                        $dt = "$date $time $a";
                        $dt = timestamp_from_format($dt, 'd/m/Y h:i a');

                        return $dt;
                    },

                    "PickupPhone" => function ($text = '', $node = null, $it = null) {
                        $x = re_white('Ph: ([\d ]+) RETURN');

                        return nice($x);
                    },

                    "DropoffPhone" => function ($text = '', $node = null, $it = null) {
                        $x = re_white('Ph: ([\d ]+) VEHICLE & OPTIONS');

                        return nice($x);
                    },

                    "CarType" => function ($text = '', $node = null, $it = null) {
                        return re_white('\( (.+?) \) Excess Reduction');
                    },

                    "CarModel" => function ($text = '', $node = null, $it = null) {
                        $x = re_white('
							Vehicle: (.+? similar)
							(?: \( | Excess Reduction)
						');

                        return nice($x);
                    },

                    "RenterName" => function ($text = '', $node = null, $it = null) {
                        $name = re_white('Dear (.+?),');

                        return nice($name);
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        $info = between('Estimated Total:', 'TERMS & CONDITIONS');
                        $q = white('\( .+? \)');
                        $info = clear("/$q/", $info);

                        $x = re_white('(.\d+[.]\d+)', $info);

                        return total($x);
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        if (re_white('HAS BEEN CANCELLED')) {
                            return 'cancelled';
                        }

                        if (re_white('Your booking is confirmed')) {
                            return 'confirmed';
                        }
                    },

                    "Cancelled" => function ($text = '', $node = null, $it = null) {
                        if (re_white('HAS BEEN CANCELLED')) {
                            return true;
                        } else {
                            return false;
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

    public function IsEmailAggregator()
    {
        return false;
    }
}
