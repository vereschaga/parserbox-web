<?php

namespace AwardWallet\Engine\hertz\Email;

class ReservationHTML extends \TAccountCheckerExtended
{
    public $reFrom = "#hertz#i";
    public $reProvider = "#hertz#i";
    public $rePlain = "#Your\s+Reservation\s+has\s+been\s+made.*Hertz#is";
    public $rePlainRange = "";
    public $typesCount = "1";
    public $langSupported = "en";
    public $reSubject = "#My\s+Hertz\s+Reservation#i";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $xPath = "";
    public $mailFiles = "";
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
                        return re("#confirmation number is:\s*([\d\w\-]+)#i");
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "L";
                    },

                    "PickupLocation" => function ($text = '', $node = null, $it = null) {
                        $res = [];
                        $i = 1;

                        foreach (['Pickup' => ['Pickup', 'Pick Up'], 'Dropoff' => 'Return'] as $key => $value) {
                            if (!is_array($value)) {
                                $value = [$value];
                            }

                            $condition = [];

                            foreach ($value as $v) {
                                $condition[] = "contains(., '${v} Location')";
                            }
                            $condition = implode(' or ', $condition);
                            $xpath = "//text()[${condition}]/ancestor::tr[1]/following-sibling::tr[1]/td[$i]//text()";
                            $subj = implode("\n", nodes($xpath));
                            $regex = '#';
                            $regex .= "(?P<${key}Location>(?s).*?)\s+";
                            $regex .= 'Location\s+Type:\s+.*\s+';
                            $regex .= "Hours\s+of\s+Operation:\s+(?P<${key}Hours>.*)\s+";
                            $regex .= "Phone\s+Number:\s+(?P<${key}Phone>.*)\s+";
                            $regex .= "Fax\s+Number:\s+(?P<${key}Fax>.*)";
                            $regex .= '#i';

                            if (preg_match($regex, $subj, $m)) {
                                $keys = ["${key}Location", "${key}Phone", "${key}Fax", "${key}Hours"];
                                copyArrayValues($res, $m, $keys);
                                $res["${key}Location"] = nice(glue($res["${key}Location"]));
                            }

                            $condition = [];

                            foreach ($value as $v) {
                                $condition[] = "contains(., '${v} Time')";
                            }
                            $condition = implode(' or ', $condition);
                            $xpath = "//text()[${condition}]/ancestor::tr[1]/following-sibling::tr[1]/td[$i]";
                            $subj = node($xpath);

                            if (preg_match('#(\w+\s+\d+,\s+\d+)\s+at\s+(\d+:\d+\s*(?:am|pm))#i', $subj, $m)) {
                                $res["${key}Datetime"] = strtotime($m[1] . ' ' . $m[2]);
                            }

                            $i++;
                        }

                        return $res;
                    },

                    "RentalCompany" => function ($text = '', $node = null, $it = null) {
                        return nodes("//img[contains(@src, 'hertz.')]/@src") ? 'Hertz' : null;
                    },

                    "CarType" => function ($text = '', $node = null, $it = null) {
                        $xpath = "//text()[contains(., 'Your Vehicle')]/ancestor::tr[1]/following-sibling::tr[1]//text()";
                        $subj = array_values(array_filter(nodes($xpath)));

                        if ($subj) {
                            return [
                                'CarType'  => $subj[1],
                                'CarModel' => $subj[0],
                            ];
                        }
                    },

                    "CarImageUrl" => function ($text = '', $node = null, $it = null) {
                        return node("//img[contains(@src, 'vehicles/')]/@src");
                    },

                    "RenterName" => function ($text = '', $node = null, $it = null) {
                        return re("#Thanks,\s*([^\.]+).#");
                    },

                    "PromoCode" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Rate Code[:\s]*([\d\w\-]+)#");
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        $subj = cell(['Total Estimated Charge', 'Total Approximate Charge'], +1);

                        if ($subj) {
                            return [
                                'TotalCharge' => cost($subj),
                                'Currency'    => currency($subj),
                            ];
                        }
                    },

                    "ServiceLevel" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Service\s+Type:\s*([^\n]+)#");
                    },

                    "Discounts" => function ($text = '', $node = null, $it = null) {
                        $r = filter(preg_split("#\s*\n\s*#", re("#Your Vehicle.*?\n\s*Discounts:\s*(.*?)\s+(?:Total Estimate|Included in the rates)#ms")));
                        $array = [];

                        foreach ($r as $d) {
                            $items = preg_split('#\s*:\s*:\s*#', $d);

                            if (count($items) == 2) {
                                [$name, $value] = $items;
                                $array[] = ['Code' => $name, 'Name' => $value];
                            } else {
                                break;
                            }
                        }

                        return $array;
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
