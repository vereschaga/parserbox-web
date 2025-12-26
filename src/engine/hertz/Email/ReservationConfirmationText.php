<?php

namespace AwardWallet\Engine\hertz\Email;

class ReservationConfirmationText extends \TAccountCheckerExtended
{
    public $rePlain = "#Thanks\s+for\s+reserving\s+your\s+ride\s+with\s+Hertz\s+24/7™.\s+Your\s+reservation\s+has\s+been\s+confirmed.#i";
    public $rePlainRange = "1000";
    public $reHtml = "";
    public $reHtmlRange = "1000";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "#Hertz\s+24/7\s+–\s+Reservation\s+Confirmation#i";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#hertz#i";
    public $reProvider = "#hertz#i";
    public $xPath = "";
    public $mailFiles = "";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return [$text];
                },

                "#.*?#" => [
                    "Number" => function ($text = '', $node = null, $it = null) {
                        return CONFNO_UNKNOWN;
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "L";
                    },

                    "PickupDatetime" => function ($text = '', $node = null, $it = null) {
                        $res = [];
                        $regex = '#';
                        $regex .= 'You\s+booked\s+the\s+(?P<CarModel>.*)\s+';
                        $regex .= 'at\s+(?P<PickupLocation>.*)\s+';
                        $regex .= 'from\s+(?P<PickupDatetime>.*)\s+';
                        $regex .= 'to\s+(?P<DropoffDatetime>.*)';
                        $regex .= '\.#';

                        if (preg_match($regex, $text, $matches)) {
                            foreach (['CarModel', 'PickupLocation'] as $key) {
                                $res[$key] = $matches[$key];
                            }
                            $res['DropoffLocation'] = $res['PickupLocation'];

                            $regex = '#(\w+\s+\d+,\s+\d+)\s+at\s+(\d+:\d+\s+(?:am|pm))#i';

                            foreach (['Pickup', 'Dropoff'] as $key) {
                                if (preg_match($regex, $matches["${key}Datetime"], $m)) {
                                    $res["${key}Datetime"] = strtotime($m[1] . ' ' . $m[2]);
                                }
                            }
                        }

                        return $res;
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re('#Your\s+reservation\s+has\s+been\s+(\w+)#');
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
