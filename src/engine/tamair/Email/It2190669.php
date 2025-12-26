<?php

namespace AwardWallet\Engine\tamair\Email;

class It2190669 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*Von\s*:[^\n]*?[@.]tam[.]com[.]br#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "de";
    public $typesCount = "1";
    public $reFrom = "#[@.]tam[.]com[.]br#i";
    public $reProvider = "#[@.]tam[.]com[.]br#i";
    public $caseReference = "";
    public $isAggregator = "0";
    public $xPath = "";
    public $mailFiles = "tamair/it-2190669.eml";
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
                        return re_white('Buchungsnummer: (\w+)');
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $ppl = nodes("//span[contains(text(), 'e-Ticket')]/preceding::span[1]");

                        return nice($ppl);
                    },

                    "AccountNumbers" => function ($text = '', $node = null, $it = null) {
                        $accs = nodes("//*[contains(text(), 'Vielfliegerprogramm:')]/font[1]");

                        return array_unique(nice($accs));
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        $info = cell('Gesamt:', +1);
                        $x = re_white('[\d.,]+ .? $', $info);

                        return total($x);
                    },

                    "Tax" => function ($text = '', $node = null, $it = null) {
                        $x = re_white('Gesamtsteuern: .+? = ([\d., ]+ .?)');

                        return cost($x);
                    },

                    "SpentAwards" => function ($text = '', $node = null, $it = null) {
                        $info = cell('Gesamt:', +1);
                        $x = re_white('[\d.,]+ Pts', $info);

                        return nice($x);
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        if (re_white('Bestätigung Ihrer Buchung')) {
                            return 'confirmed';
                        }
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath('.');
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $fl = re_white('Flugnummer:? (\w+ \d+)');

                            return uberAir($fl);
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return re_white('Hinflug Von .+? \( (\w+) \)');
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $date = re_white('Hinflug Von .+? Nach .+? - \w+ (\d+ \w+ \d+)');
                            $date = en($date);

                            $time1 = re_white('Abflug:? .+? Zeit: (\d+:\d+)');
                            $time2 = re_white('Ankunftszeit:? .+? Zeit: (\d+:\d+)');

                            $dt1 = "$date, $time1";
                            $dt2 = "$date, $time2";
                            correctDates($dt1, $dt2);

                            return [
                                'DepDate' => $dt1,
                                'ArrDate' => $dt2,
                            ];
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return re_white('Hinflug Von .+? Nach .+? \( (\w+) \)');
                        },

                        "Aircraft" => function ($text = '', $node = null, $it = null) {
                            $x = re_white('
								Flugzeugtyp:?
								(.+?)
								Klasse:?
							');

                            return nice($x);
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            return between('Klasse', 'Freigepäck');
                        },

                        "Seats" => function ($text = '', $node = null, $it = null) {
                            $a = nodes("
								//*[normalize-space(text()) = 'Sitz']/ancestor::tr[1]
								/following-sibling::tr/td[3]
							");
                            $a = filter(nice($a));

                            return implode(',', $a);
                        },

                        "Duration" => function ($text = '', $node = null, $it = null) {
                            return re_white('Dauer:? (\d+:\d+)');
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
        return ["de"];
    }

    public function IsEmailAggregator()
    {
        return false;
    }
}
