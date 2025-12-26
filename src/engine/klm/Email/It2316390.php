<?php

namespace AwardWallet\Engine\klm\Email;

class It2316390 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?[@.]klm[.]com#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en, nl";
    public $typesCount = "1";
    public $reFrom = "#[@.]klm[.]com#i";
    public $reProvider = "#[@.]klm[.]com#i";
    public $caseReference = "";
    public $isAggregator = "0";
    public $upDate = "13.01.2015, 11:29";
    public $crDate = "13.01.2015, 09:46";
    public $xPath = "";
    public $mailFiles = "klm/it-2316363.eml, klm/it-2316390.eml";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $date = uberDate(1);
                    $this->anchor = totime($date);

                    $text = $this->setDocument('application/pdf', 'text');

                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return reni('(?:
								Confirmation number |
								Bevestigingsnummer
							): (\w+)
						');
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $info = rew('(?:
								Number Loyalty Program |
								Nummer loyaliteits programma
							)  (.+?)  (?:
								Itinerary Information |
								Reisinformatie
							)
						');
                        $q = white('^ ([A-Z]\w+ [A-Z]\w+ .+?) $');

                        if (preg_match_all("/$q/imu", $info, $m)) {
                            return nice($m[1]);
                        }
                    },

                    "AccountNumbers" => function ($text = '', $node = null, $it = null) {
                        $info = rew('
							Number Loyalty Program
							(.+?)
							Itinerary Information
						');
                        $q = white('^ ([A-Z]{2,3} \d+) $');

                        if (preg_match_all("/$q/imu", $info, $m)) {
                            return nice($m[1]);
                        }
                    },

                    "BaseFare" => function ($text = '', $node = null, $it = null) {
                        $x = rew('(?:
								Fare amount |
								Tarief ticket
							):  (\w+ \d+[.]\d+)
						');

                        return [
                            'BaseFare' => cost($x),
                            'Currency' => currency($x),
                        ];
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        $date = reni('(?:
								issue date |
								e-ticket
							) : (\d+\w+)
						');
                        $date = date_carry($date, $this->anchor);

                        return $date;
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        $info = rew('(?:
								Itinerary Information |
								Reisinformatie
							)  (.+?)  (?:
								Receipt |
								Betalingsbewijs
							)
						');
                        $q = white('
							\d+[A-Z]{3} .+? OK
						');

                        return splitter("/($q)/isu", $info);
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $fl = reni('\s+ ([A-Z]{2} \d+)');

                            return uberAir($fl);
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return reni('\b ([A-Z]{3}) \b');
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $date = reni('(\d+ \w+)');
                            $date = date_carry($date, $this->anchor);

                            $time1 = reni('(\d+[.]\d+)');
                            $time2 = reni('(?:\d+[.]\d+) .*? (\d+[.]\d+)');

                            $dt1 = strtotime($time1, $date);
                            $dt2 = date_carry($time2, $dt1);

                            return [
                                'DepDate' => $dt1,
                                'ArrDate' => orval($dt2, MISSING_DATE),
                            ];
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return reni('
								\b (?:[A-Z]{3}) \b
								.*?
								\b ([A-Z]{3}) \b
							');
                        },

                        "BookingClass" => function ($text = '', $node = null, $it = null) {
                            return reni('\b (\w) \b');
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
        return ["en", "nl"];
    }

    public function IsEmailAggregator()
    {
        return false;
    }
}
