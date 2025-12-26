<?php

namespace AwardWallet\Engine\perfectdrive\Email;

class PlainReservation extends \TAccountCheckerExtended
{
    public $reFrom = "#BudgetConfirmations@budgetgroup\.com#i";
    public $reProvider = "#perfectdrive#i";
    public $rePlain = "#Budget Reservation Confirmation#i";
    public $rePlainRange = "1000";
    public $typesCount = "1";
    public $langSupported = "en";
    public $reSubject = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $xPath = "";
    public $mailFiles = "perfectdrive/it-1595735.eml";
    public $rePDF = "";
    public $rePDFRange = "";
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
                        return re("#Your\s+confirmation\s+number\s+is\s+([\w\-]+)#");
                    },

                    "PickupDatetime" => function ($text = '', $node = null, $it = null) {
                        $res = [];

                        foreach (['Pickup' => 'pick-up', 'Dropoff' => 'drop-off'] as $key => $prefix) {
                            $regex = '#';
                            $regex .= $prefix . '\s+-+\s+';
                            $regex .= '\w+,\s+(?P<Date>\w+\s+\d+,\s+\d+)\s+(?P<Time>\d+:\d+\s+\w+)\s+';
                            $regex .= '(?P<' . $key . 'Location>.*)\s+';
                            $regex .= 'location\s+hours\s+(?P<' . $key . 'Hours>.*)\s+';
                            $regex .= 'phone.*(?P<' . $key . 'Phone>[\d\-]+)\s+';
                            $regex .= '#smU';

                            if (preg_match($regex, $text, $m)) {
                                $res[$key . 'Datetime'] = strtotime(str_replace(',', '', $m['Date']) . ', ' . $m['Time']);
                                $keys = [$key . 'Location', $key . 'Hours', $key . 'Phone'];
                                $res = array_merge($res, array_intersect_key($m, array_flip($keys)));
                            }
                        }
                        $res = array_map('trim', $res);
                        array_walk($res, function (&$value, $key) { $value = preg_replace('#\s+#', ' ', $value); });

                        return $res;
                    },

                    "CarType" => function ($text = '', $node = null, $it = null) {
                        $res = [];
                        $regex = '#';
                        $regex .= 'car\s+-+\n';
                        $regex .= '(?P<CarType>.*)\n';
                        $regex .= '.*';
                        $regex .= 'car class\s+\w\s+';
                        $regex .= '(?P<CarModel>.*)\n';
                        $regex .= '#msU';

                        if (preg_match($regex, $text, $m)) {
                            $keys = ['CarType', 'CarModel'];
                            $res = array_merge($res, array_intersect_key($m, array_flip($keys)));
                        }
                        $res = array_map('trim', $res);
                        array_walk($res, function (&$value, $key) { $value = preg_replace('#\s+#', ' ', $value); });

                        return $res;
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        $subj = re('#-+\n\s*total\s+(\w+\s+:\s+[\d\.,]+)\s*\n#') . "\n";

                        return ['TotalCharge' => cost($subj), 'Currency' => currency($subj)];
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
}
