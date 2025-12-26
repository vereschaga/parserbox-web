<?php

namespace AwardWallet\Engine\utair\Email;

class HTML extends \TAccountCheckerExtended
{
    public $reFrom = "#utair-booking@crpu\.ru#i";
    public $reProvider = "#utair#i";
    public $rePlain = "#Вами создан заказ [\w\-]+ в системе бронирования \"Сирена-Трэвел\"#i";
    public $typesCount = "1";
    public $langSupported = "ru";
    public $reSubject = "";
    public $reHtml = "";
    public $xPath = "";
    public $mailFiles = "utair/it-1604263.eml";
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
                        $regex = '#В системе бронирования авиаперевозок "Сирена-Трэвел" Вами создан заказ\s+([\w\-]+)#u';

                        return re($regex);
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $xpath = "//text()[contains(., 'Пассажиры')]/ancestor::tr[1]/following-sibling::tr/td[1]";
                        $passengers = nodes($xpath);
                        array_walk($passengers, function (&$value, $key) { $value = trim(re('#(.*)\s*\d{2}\s+#u', $value)); });

                        return $passengers;
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        $subj = re('#Итого к оплате:\s+(\d+\s+\w+)#u');

                        return ['TotalCharge' => cost($subj), 'Currency' => currency($subj)];
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        $xpath = "//tr[contains(., 'Рейс') and contains(., 'Вылет')]/following-sibling::tr";

                        return $this->http->XPath->query($xpath);
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $airline = re("#^\s*(\w{2})\s*(\d+)#u", node('./td[1]'));
                            $flight = re(2);

                            if ($airline === 'ЮТ') {
                                $airline = 'UT';
                            }

                            return ["FlightNumber" => $flight, "AirlineName" => $airline];
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            return node('./td[2]/*[1]');
                        },

                        "ArrName" => function ($text = '', $node = null, $it = null) {
                            return node('./td[3]/*[1]');
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $subj = str_replace('*', '', node('./td[2]/*[2]'));

                            return strtotime($subj);
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            $subj = str_replace('*', '', node('./td[3]/*[2]'));

                            return strtotime($subj);
                        },

                        "Aircraft" => function ($text = '', $node = null, $it = null) {
                            return node('./td[5]');
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            return node('./td[4]');
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
        return ["ru"];
    }
}
