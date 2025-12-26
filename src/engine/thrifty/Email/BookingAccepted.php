<?php

namespace AwardWallet\Engine\thrifty\Email;

class BookingAccepted extends \TAccountCheckerExtended
{
    public $reFrom = "#reservations@thrifty\.com\.au#i";
    public $reProvider = "#thrifty\.com\.au#i";
    public $rePlain = "#simply\s+ask\s+our\s+friendly\s+Thrifty\s+staff#i";
    public $rePlainRange = "/1";
    public $typesCount = "1";
    public $langSupported = "en";
    public $reSubject = "";
    public $reHtml = "";
    public $reHtmlRange = "1000";
    public $xPath = "";
    public $mailFiles = "thrifty/it-1425164.eml";
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
                        return re('#Reservation\s+number\s+([\w\-]+)#');
                    },

                    "PickupDatetime" => function ($text = '', $node = null, $it = null) {
                        $res = [];

                        foreach (['Pickup' => 'Pickup', 'Dropoff' => 'Return'] as $key => $value) {
                            $subj = implode("\n", nodes(".//text()[normalize-space(.) = '${value}']/ancestor::td[1]//text()"));
                            $regex = '#';
                            $regex .= "(?P<${key}Datetime>\d+/\d+/\d{4}\s+\d+:\d+\s*(?:am|pm)?)\s+";
                            $regex .= "(?P<${key}Location>(?s).*)\s+";
                            $regex .= "Phone:\s+(?P<${key}Phone>.*)\s+";
                            $regex .= "Fax:\s+(?P<${key}Fax>.*)";
                            $regex .= '#';

                            if (preg_match($regex, $subj, $m)) {
                                $m["${key}Datetime"] = strtotime(str_replace('/', '.', $m["${key}Datetime"]));
                                $keys = ["${key}Datetime", "${key}Location", "${key}Phone", "${key}Fax"];
                                copyArrayValues($res, $m, $keys);
                            }
                        }
                        $res = nice($res, ',');

                        return $res;
                    },

                    "CarType" => function ($text = '', $node = null, $it = null) {
                        $xpath = "//text()[normalize-space(.) = 'Return']/ancestor::tr[2]/following-sibling::tr[1]//tr[count(td) = 2]";
                        $subj = $this->http->XPath->query($xpath);

                        if ($subj->length > 0) {
                            $carInfoNode = $subj->item(0);
                            $res['CarImageUrl'] = node('./td[1]//img/@src', $carInfoNode);
                            $carInfo = implode("\n", nodes('./td[2]//text()', $carInfoNode));
                            $regex = '#';
                            $regex .= '\n\s*(?P<CarType>.*)';
                            $regex .= '\s*\n\s*(?P<CarModel>.*)\s+';
                            $regex .= '#';

                            if (preg_match($regex, $carInfo, $m)) {
                                copyArrayValues($res, $m, ['CarType', 'CarModel']);
                            }

                            return $res;
                        }
                    },

                    "RenterName" => function ($text = '', $node = null, $it = null) {
                        return cell('First Name', +1) . ' ' . cell('Last Name', +1);
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        $subj = cell('TOTAL:', +1);

                        return ['TotalCharge' => cost($subj), 'Currency' => currency($subj)];
                    },

                    "TotalTaxAmount" => function ($text = '', $node = null, $it = null) {
                        return cost(re('#Total\s+price\s+includes\s+GST\s+of\s+(.*)#'));
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
