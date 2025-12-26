<?php

namespace AwardWallet\Engine\orbitz\Email;

class It2122509 extends \TAccountCheckerExtended
{
    public $mailFiles = "orbitz/it-2122509.eml, orbitz/it-2224120.eml";

    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?@orbitz[.]com#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#@orbitz[.]com#i";
    public $reProvider = "#[@.]orbitz[.]com#i";
    public $caseReference = "";
    public $isAggregator = "0";
    public $xPath = "";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    if (nodes('//text()[contains(., "Depart:")]/ancestor::table[2][contains(., "E-mail Itinerary") or contains(., "has been sent from Orbitz")]')) {
                        // Skip similar but other format emails
                        return null;
                    }
                    $ppl = nodes("//*[contains(text(), 'Traveler(s)')]/ancestor::tr[1]/following-sibling::tr/td[1]");
                    $this->ppl = nice($ppl);

                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return re_white('Orbitz record locator:  (\w+)');
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return $this->ppl;
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath("//*[contains(text(), 'Depart:')]/ancestor::table[1]");
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            if ($fn = re('#\#(\d+)#i', node('./preceding-sibling::*[1]'))) {
                                return $fn;
                            } else {
                                $info = node("./ancestor::*[1]");

                                return re_white('(\d+)', $info);
                            }
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            $info = node(".//*[contains(text(), 'Depart:')]/ancestor::tr[1]");

                            return re_white('\( (\w+) \)', $info);
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $date = orval(
                                re_white('\w+, (\w+ \d+, \d{4})'),
                                re_white('\w+, (\w+ \d+, \d{4})', node('./ancestor::table[1]'))
                            );

                            if (!$date) {
                                return null;
                            }
                            $time1 = uberTime(1);
                            $time2 = uberTime(2);

                            $date = strtotime($date);
                            $dt1 = strtotime($time1, $date);
                            $dt2 = strtotime($time2, $date);

                            return [
                                'DepDate' => $dt1,
                                'ArrDate' => $dt2,
                            ];
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            $info = node(".//*[contains(text(), 'Arrive:')]/ancestor::tr[1]");

                            return re_white('\( (\w+) \)', $info);
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            $q = white('
								(?:Seats: .+?)?
								(?P<Cabin> \w+) \| (?P<Aircraft> .+?) \| (?P<Duration> .+?) \| (?P<TraveledMiles> .+? miles)
							');

                            $res = re2dict($q, node('.'));

                            return $res;
                        },

                        "Seats" => function ($text = '', $node = null, $it = null) {
                            $info = between('Seats:', '|');
                            $q = white('\b(\d+[A-Z]+)\b');

                            if (preg_match_all("/$q/isu", $info, $ms)) {
                                return implode(',', $ms[1]);
                            }
                        },

                        "FlightLocator" => function ($text = '', $node = null, $it = null) {
                            $info = node(".//*[contains(text(), 'Depart:')]/preceding::strong[1]");
                            $air = re_white('(.+?) \#', $info);
                            $air = nice($air);

                            $conf = re_white("$air - (\w+)", $this->text());

                            return $conf;
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
