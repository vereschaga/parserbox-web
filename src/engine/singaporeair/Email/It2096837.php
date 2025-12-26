<?php

namespace AwardWallet\Engine\singaporeair\Email;

class It2096837 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?@singaporeair[.]com[.]sg#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#@singaporeair[.]com[.]sg#i";
    public $reProvider = "#[@.]singaporeair[.]com[.]sg#i";
    public $caseReference = "";
    public $isAggregator = "0";
    public $xPath = "";
    public $mailFiles = "singaporeair/it-2096837.eml, singaporeair/it-2186855.eml, singaporeair/it-2186858.eml, singaporeair/it-2186867.eml";
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
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return re_white('BOOKING REFERENCE:         (\w+)');
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $names = array_filter(explode("\n", re("#\n\s*Passenger/s:\s*([A-Z\s+,.]*?)\n\s*ITINERARY#s")));

                        foreach ($names as &$name) {
                            $name = niceName($name);
                        }

                        return orval(
                            $names,
                            [between('Passenger:', 'E-Ticket Number:')]
                        );
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        $x = re_white('Total:			- ([\w,.]+)');
                        $x = clear('/,/', $x);

                        return total($x);
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath("//*[normalize-space(text()) = 'Flight:']/ancestor::tr[1]");
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $fl = re_white('Flight:  (\w+)');

                            return uberAir($fl);
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            $info = node("preceding-sibling::tr[2]");

                            return orval(
                                re_white('\( (\w+) -', $info),
                                re("#\(([A-Z]{3})\)#", $info)
                            );
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $info = node('./preceding-sibling::tr[2]');
                            $dt = re_white('\bon \w+, (.+)', $info);
                            $dt = \DateTime::createFromFormat('d M Y, Hi \h\r\s', $dt);
                            $dt = $dt ? $dt->getTimestamp() : null;

                            return $dt;
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            $info = node('preceding-sibling::tr[1]');

                            return orval(
                                re_white('\( (\w+) -', $info),
                                re("#\(([A-Z]{3})\)#", $info)
                            );
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            $info = node('./preceding-sibling::tr[1]');
                            $dt = re_white('\bon \w+, (.+)', $info);
                            $dt = \DateTime::createFromFormat('d M Y, Hi \h\r\s', $dt);
                            $dt = $dt ? $dt->getTimestamp() : null;

                            return $dt;
                        },

                        "Aircraft" => function ($text = '', $node = null, $it = null) {
                            $x = re_white('\( (.+?) \)');

                            return nice($x);
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            return re_white('(\w+) Class $');
                        },
                    ],
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
