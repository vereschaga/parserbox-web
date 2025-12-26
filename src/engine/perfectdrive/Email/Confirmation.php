<?php

namespace AwardWallet\Engine\perfectdrive\Email;

class Confirmation extends \TAccountCheckerExtended
{
    public $reFrom = "#no-reply@budget\.com#i";
    public $reProvider = "#budget\.com#i";
    public $rePlain = "#thank\s+you\s+for\s+choosing\s+Budget#i";
    public $rePlainRange = "";
    public $typesCount = "1";
    public $langSupported = "en";
    public $reSubject = "#Budget\s+(?:Rental|Reservation)\s+Confirmation#i";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $xPath = "";
    public $mailFiles = "perfectdrive/it-1692695.eml, perfectdrive/it-1692696.eml";
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
                        return re('#Reservation\s+Confirmation\s+Number:\s+([\w\-]+)#');
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "L";
                    },

                    "PickupDatetime" => function ($text = '', $node = null, $it = null) {
                        $res = [];

                        foreach (['Pickup' => 'Pick-up', 'Dropoff' => 'Return'] as $key => $value) {
                            $xpath = "//text()[contains(., '${value} Date Time')]//ancestor::td[1]//text()";
                            $subj = implode("\n", nodes($xpath));
                            $regex = '#';
                            $regex .= '\s+.*\s+';
                            $regex .= '\w+,?\s+(\w+\s+\d+,\s+\d+)\s+at\s+(\d+:\d+\s+(?:am|pm))(?:\s*at)?\s+';
                            $regex .= '((?s).*)\s*';
                            $regex .= '\n\s*';
                            $regex .= '([\d\-\s]+)';
                            $regex .= '#i';

                            if (preg_match($regex, $subj, $m)) {
                                $res["${key}Datetime"] = strtotime($m[1] . ', ' . $m[2]);
                                $res["${key}Location"] = nice($m[3], ',');
                                $res["${key}Phone"] = $m[4];
                            }
                        }

                        return $res;
                    },

                    "CarType" => function ($text = '', $node = null, $it = null) {
                        if (preg_match('#Your\s+Vehicle\s+(.*?)\s+-\s+(.*)#', $text, $m)) {
                            return ['CarType' => $m[1], 'CarModel' => $m[2]];
                        }
                    },

                    "CarImageUrl" => function ($text = '', $node = null, $it = null) {
                        $subj = node("//text()[contains(., 'Your Vehicle')]/ancestor::td[1]//img/@src");

                        if (stripos($subj, 'unavailable') === false) {
                            return $subj;
                        }
                    },

                    "RenterName" => function ($text = '', $node = null, $it = null) {
                        return nice(re('#(.*),\s+thank\s+you\s+for\s+choosing#'));
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        $subj = cell('Estimated Total:', +1);

                        if ($subj) {
                            return ['TotalCharge' => cost($subj), 'Currency' => currency($subj)];
                        }
                    },

                    "TotalTaxAmount" => function ($text = '', $node = null, $it = null) {
                        $subj = cell('Taxes and Surcharges', +1);

                        if ($subj) {
                            return cost($subj);
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
