<?php

namespace AwardWallet\Engine\indigo\Email;

class It1966209 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?\bgoindigo\b#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#\bgoindigo\b#i";
    public $reProvider = "#\bgoindigo\b#i";
    public $caseReference = "";
    public $isAggregator = "";
    public $xPath = "";
    public $mailFiles = "indigo/it-1966209.eml, indigo/it-2074695.eml, indigo/it-3.eml";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    // set another document if xpath doesn't work
                    if (!nodes("//*[contains(text(), 'Booking Refence') or contains(text(), 'Booking Reference')]/ancestor-or-self::td[1]")) {
                        $this->setDocument("text/html", "html");
                    }

                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return cell(["Booking Reference:", "Booking Refence"], 0, +1);
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $info = between('IndiGo Passenger(s)', 'IndiGo Flight(s)');
                        $q = white('\d+ [.] (.+?) (?=\d+|$)');

                        if (preg_match_all("/$q/isu", $info, $ms)) {
                            return $ms[1];
                        }
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return cost(cell(["Total Payment", "Total Fare"], +2, 0));
                    },

                    "BaseFare" => function ($text = '', $node = null, $it = null) {
                        return cost(cell(["Base Fare", "Airfare Charges"], +2, 0));
                    },

                    "Currency" => function ($text = '', $node = null, $it = null) {
                        return currency(cell(["Base Fare", "Total Fare"], +1, 0));
                    },

                    "Tax" => function ($text = '', $node = null, $it = null) {
                        return cost(cell("Service Tax", +2, 0));
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return cell("Status:", 0, +1);
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        $date = node("//*[contains(text(), 'IndiGo Passenger(s)')]/preceding::font[1]");
                        $date = \DateTime::createFromFormat('dMy', $date);
                        $date = $date ? $date->setTime(0, 0)->getTimestamp() : null;

                        return $date;
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath("//*[contains(text(), 'Arrives') or contains(text(), 'Arr Time')]/ancestor::tr[1]/following-sibling::tr//tr[contains(., ':')]");
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return uberAir(node("td[1]"));
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return re("#\(([A-Z]{3})\)#");
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            return node("td[3]");
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $date = node('td[2]');
                            $time1 = node('td[5]');
                            $time2 = node('td[6]');

                            $dt1 = "$date, $time1";
                            $dt2 = "$date, $time2";

                            $fmt = 'dMy, H : i a';
                            $dt1 = \DateTime::createFromFormat($fmt, $dt1);
                            $dt1 = $dt1 ? $dt1->getTimestamp() : null;
                            $dt2 = \DateTime::createFromFormat($fmt, $dt2);
                            $dt2 = $dt2 ? $dt2->getTimestamp() : null;

                            return [
                                'DepDate' => $dt1,
                                'ArrDate' => $dt2,
                            ];
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return ure("#\(([A-Z]{3})\)#", 2);
                        },

                        "ArrName" => function ($text = '', $node = null, $it = null) {
                            return node("td[4]");
                        },

                        "Seats" => function ($text = '', $node = null, $it = null) {
                            $codes = $it['DepCode'] . ' - ' . $it['ArrCode'];
                            $pos = count(nodes("//*[contains(text(), '$codes')]/ancestor-or-self::td[1]/preceding-sibling::td")) + 1;

                            return implode(',', nodes("//*[contains(text(), '$codes')]/ancestor::tr[1]/following-sibling::tr/td[$pos]"));
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
