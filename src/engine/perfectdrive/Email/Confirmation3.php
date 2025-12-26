<?php

namespace AwardWallet\Engine\perfectdrive\Email;

class Confirmation3 extends \TAccountCheckerExtended
{
    public $reFrom = "#customerservice@avisbudget\.com#i";
    public $reProvider = "#avisbudget\.com#i";
    public $rePlain = "#customerservice@avisbudget\.com#i";
    public $rePlainRange = "";
    public $typesCount = "1";
    public $langSupported = "en";
    public $reSubject = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $xPath = "";
    public $mailFiles = "perfectdrive/it-1732341.eml";
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
                    "Number" => function ($text = '', $node = null, $it = null) {
                        return re('#Reservation\s+number\s+([\w\-]+)#');
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "L";
                    },

                    "PickupDatetime" => function ($text = '', $node = null, $it = null) {
                        $res = null;

                        foreach (['Pickup' => 'Pick-Up', 'Dropoff' => 'Drop-Off'] as $key => $value) {
                            $regex = '#';
                            $regex .= $value . '\s+-.*\s+';
                            $regex .= '\w+,\s+(\w+\s+\d+,\s+\d+\s+\d+:\d+\s*(?:am|pm)?)\s+';
                            $regex .= '((?s).*?)';
                            $regex .= 'hours\s+(.*)\s+';
                            $regex .= 'phone\s+(.*)';
                            $regex .= '#i';

                            if (preg_match($regex, $text, $m)) {
                                $res[$key . 'Datetime'] = strtotime($m[1]);
                                $res[$key . 'Location'] = nice($m[2], ',');
                                $res[$key . 'Hours'] = nice($m[3]);
                                $res[$key . 'Phone'] = nice($m[4]);
                            }
                        }

                        return $res;
                    },

                    "CarType" => function ($text = '', $node = null, $it = null) {
                        return nice(re('#car\s+class\s+\w+#i'));
                    },

                    "CarModel" => function ($text = '', $node = null, $it = null) {
                        return nice(re('#.*\s+or\s+similar#'));
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        $subj = cell('Estimated Total', +1);

                        if ($subj) {
                            return [
                                'TotalCharge' => cost($subj),
                                'Currency'    => currency($subj),
                            ];
                        }
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
