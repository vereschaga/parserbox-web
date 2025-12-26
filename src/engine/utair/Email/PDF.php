<?php

namespace AwardWallet\Engine\utair\Email;

class PDF extends \TAccountCheckerExtended
{
    public $reFrom = "#utair-booking@crpu\.ru#i";
    public $reProvider = "#utair#i";
    public $rePlain = "#В системе интернет\-бронирования авиаперевозок#i";
    public $rePlainRange = "1000";
    public $typesCount = "1";
    public $langSupported = "ru";
    public $reSubject = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $xPath = "";
    public $mailFiles = "utair/it-2.eml";
    public $rePDF = "";
    public $rePDFRange = "";
    public $pdfRequired = "1";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $regex = '#В\s+системе\s+интернет-бронирования\s+авиаперевозок\s+"Сирена-Трэвел"\s+оплачен\s+заказ:\s+([\w\-]+)#';
                    $this->recordLocator = re($regex);
                    $text = $this->setDocument('#receipt.pdf#', 'text');

                    return [$text];
                },

                "#.*?#" => [
                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return $this->recordLocator;
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $regex = '#ФАМИЛИЯ/NAME:\s+(.*)#u';

                        if (preg_match_all($regex, $text, $m)) {
                            array_walk($m[1], function (&$value, $key) { $value = preg_replace('#\d{2}\w{3}\d{2,4}#', '', $value); });

                            return $m[1];
                        }
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return [$text];
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $res = [];
                            $months = ['ЯНВ' => 'January',
                                'ФЕВ'        => 'February',
                                'МАР'        => 'March',
                                'АПР'        => 'April',
                                'МАЙ'        => 'May',
                                'ИЮН'        => 'June',
                                'ИЮЛ'        => 'July',
                                'АВГ'        => 'August',
                                'СЕН'        => 'September',
                                'ОКТ'        => 'October',
                                'НОЯ'        => 'November',
                                'ДЕК'        => 'December', ];
                            $regex = '#';
                            $regex .= '-----+\n';
                            $regex .= '(?P<DepName>.*)\n';
                            $regex .= '(?P<AirlineName>\w+)\s+(?P<FlightNumber>\d+)\n';
                            $regex .= '(?P<BookingClass>\w)\s+';
                            $regex .= '(?P<DepDay>\d{2})(?P<DepMonth>\w+)\s+';
                            $regex .= '(?P<DepHour>\d{2})(?P<DepMin>\d{2})\n';
                            $regex .= '.*\n';
                            $regex .= '.*\n';
                            $regex .= '(?P<DepAirport>.*)\n';
                            $regex .= 'ВРЕМЯ ПРИБЫТИЯ/ARRIVAL\s+TIME:\s*(?P<ArrHour>\d{2})(?P<ArrMin>\d{2})\n';
                            $regex .= '(?P<ArrName>.*)\n';
                            $regex .= '\s+(?P<ArrAirport>.*)\n';
                            $regex .= '#u';

                            if (preg_match($regex, $text, $m)) {
                                foreach (['Day', 'Month'] as $suf) {
                                    if (!isset($m['Arr' . $suf])) {
                                        $m['Arr' . $suf] = $m['Dep' . $suf];
                                    }
                                }

                                foreach (['Dep', 'Arr'] as $pref) {
                                    if (isset($m[$pref . 'Airport'])) {
                                        $m[$pref . 'Name'] .= ' ' . $m[$pref . 'Airport'];
                                    }

                                    $str = $m[$pref . 'Day'] . ' ' . $months[$m[$pref . 'Month']] . ' ' . $this->getEmailYear() . ', ' . $m[$pref . 'Hour'] . ':' . $m[$pref . 'Min'];
                                    $res[$pref . 'Date'] = strtotime($str);
                                }

                                $keys = ['FlightNumber', 'AirlineName', 'DepName', 'ArrName', 'BookingClass'];
                                $res = array_merge($res, array_intersect_key($m, array_flip($keys)));
                            }

                            return $res;
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
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
