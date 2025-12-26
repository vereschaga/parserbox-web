<?php

namespace AwardWallet\Engine\pegasus\Email;

class It1784984 extends \TAccountCheckerExtended
{
    public $reFrom = "#@pegasus#i";
    public $reProvider = "#@pegasus#i";
    public $rePlain = "#PEGASUS\s*BİLET\s*HATTI#i";
    public $rePlainRange = "2000";
    public $typesCount = "1";
    public $langSupported = "en";
    public $reSubject = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $xPath = "";
    public $mailFiles = "pegasus/it-1784984.eml";
    public $rePDF = "";
    public $rePDFRange = "";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return xpath("(//*[contains(normalize-space(text()), 'PASSENGER ITINERARY RECEIPT')]/ancestor::table[3])[1]");
                },

                "#.*?#" => [
                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return cell('BOOKING REF', +2);
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $names = nodes('//*[contains(text(), "PASSENGER NAME")]/following-sibling::td[2]');

                        return array_values(array_unique($names));
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        //$x = cell('ÖDEME/PAYMENT', +2);
                        //return total(re('#[:](.+?)[(]#', $x));
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        $d = cell('ISSUED BY', +2);
                        $d = re('#(\d+/\d+/\d+)#', $d);

                        if ($d) {
                            $d = \DateTime::createFromFormat('d/m/Y H:i', "$d 00:00");

                            return $d ? $d->getTimestamp() : null;
                        }
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        $table = ".//*[contains(normalize-space(text()), 'COUPON NO')]/ancestor::table[1]";

                        return xpath("$table//tr[position() >= 3 and (position() mod 2) = 1]");
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return node('td[4]');
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            $dep = node('td[2]');

                            return re('#[(](\w+)[)]#', $dep);
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $date = node('td[6]');
                            $time1 = node('td[7]');
                            $time2 = node('td[8]');

                            $fmt = 'd/m/Y, H:i';
                            $dt1 = \DateTime::createFromFormat($fmt, "$date, $time1");
                            $dt2 = \DateTime::createFromFormat($fmt, "$date, $time2");

                            if ($dt2 && $dt2 < $dt1) {
                                $dt2->modify('+1 day');
                            }

                            return [
                                'DepDate' => $dt1 ? $dt1->getTimeStamp() : null,
                                'ArrDate' => $dt2 ? $dt2->getTimeStamp() : null,
                            ];
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            $arr = node('following-sibling::tr[1]/td[2]');

                            return re('#[(](\w+)[)]#', $arr);
                        },

                        "AirlineName" => function ($text = '', $node = null, $it = null) {
                            return node('td[3]');
                        },

                        "BookingClass" => function ($text = '', $node = null, $it = null) {
                            return node('td[5]');
                        },
                    ],
                ],

                "PostProcessing" => function ($text = '', $node = null, $it = null) {
                    return $it;
                },
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
