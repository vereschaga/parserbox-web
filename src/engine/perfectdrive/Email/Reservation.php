<?php

namespace AwardWallet\Engine\perfectdrive\Email;

class Reservation extends \TAccountCheckerExtended
{
    public $rePlain = "#Budget\s+Rent\s+A\s+Car\s+System#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "#Budget\s+Reservation\s+Modification#i";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#BudgetConfirmations@budgetgroup\.com#i";
    public $reProvider = "#budgetgroup\.com#i";
    public $caseReference = "";
    public $xPath = "";
    public $mailFiles = "perfectdrive/it-1691354.eml, perfectdrive/it-1691358.eml, perfectdrive/it-1970199.eml, perfectdrive/it-1971045.eml, perfectdrive/it-1972562.eml, perfectdrive/it-1974267.eml";
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
                        return orval(
                            re('#Confirmation\s+number\s+([\w\-]+)#'),
                            re("#confirmation number is\s+([\w\-]+)#")
                        );
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "L";
                    },

                    "PickupDatetime" => function ($text = '', $node = null, $it = null) {
                        $res = null;

                        foreach (['Pickup' => 'pick-up', 'Dropoff' => 'return'] as $key => $value) {
                            $parent = $this->http->XPath->query("//text()[contains(., '${value}')]/ancestor::table[1]");

                            if ($parent and $parent->length > 0) {
                                $parent = $parent->item(0);

                                $subj = implode("\n", nodes('.//tr[2]//text()', $parent));
                                $regex = '#';
                                $regex .= '\w+,\s+(\w+\s+\d+,\s+\d+\s+\d+:\d+\s*(?:am|pm)?)';
                                $regex .= '(.*?)';
                                $regex .= '(?:hours|$)#si';

                                if (preg_match($regex, $subj, $m)) {
                                    $res["${key}Datetime"] = strtotime($m[1]);
                                    $res["${key}Location"] = nice($m[2], ',');
                                } else {
                                    if (re("#\w+,\s+(\d+\s+\w+,\s*\d+)\s*@\s*(\d+)(\d{2})\s+(.*?)(?:hours|phone|return|$)#si")) {
                                        $res["${key}Datetime"] = totime(clear("#,#", re(1)) . ',' . re(2) . ':' . re(3));
                                        $res["${key}Location"] = nice(re(4), ',');
                                    }

                                    if (re("#hours\s+([^\n]+)\s+phone\s+([^\n]+)\s+return\s+.+?\s+hours\s+([^\n]+)\s+phone\s+([^\n]+)#si")) {
                                        if ($key == 'Pickup') {
                                            $res["${key}Hours"] = re(1);
                                            $res["${key}Phone"] = re(2);
                                        } else {
                                            $res["${key}Hours"] = re(3);
                                            $res["${key}Phone"] = re(4);
                                        }
                                    }
                                }

                                if (!isset($res["${key}Hours"])) {
                                    $subj = node('.//tr[3]', $parent);

                                    if (preg_match('#hours\s+(.*)#', $subj, $m)) {
                                        $res["${key}Hours"] = $m[1];
                                    }
                                }

                                if (!isset($res["${key}Phone"])) {
                                    $subj = node('.//tr[4]', $parent);

                                    if (preg_match('#phone\s+(.*)#', $subj, $m)) {
                                        $res["${key}Phone"] = $m[1];
                                    } else {
                                        $res["${key}Phone"] = trim(re("#([\d\-+\(\) ]{5,})$#",
                                            node('.//tr[3]', $parent)));

                                        if (!$res["${key}Phone"]) {
                                            $res["${key}Phone"] = trim(re("#([\d\-+\(\) ]{5,})$#",
                                                node('.//tr[4]', $parent)));
                                        }
                                    }
                                }
                            }
                        }

                        return $res;
                    },

                    "RentalCompany" => function ($text = '', $node = null, $it = null) {
                        return re("#Budget\s+Rent\s+A\s+Car#i");
                    },

                    "CarType" => function ($text = '', $node = null, $it = null) {
                        $xpath = "//text()[contains(., 'car')]/ancestor::table[contains(., 'equipment & services') or contains(., 'or similar')][not(.//table)]";
                        $parent = $this->http->XPath->query($xpath);

                        if ($parent and $parent->length > 0) {
                            $parent = $parent->item(0);

                            $subj = implode("\n", nodes('.//td[1]//text()', $parent));
                            $res['CarType'] = nice(re('#^\s*car\s*(.*)#s', $subj), ',');

                            $subj = implode("\n", nodes('.//td[2]//text()', $parent));
                            $res['CarModel'] = nice($subj, ',');

                            $subj = node('.//td[2]//img/@src', $parent) . "\n";

                            if (stripos($subj, 'broken_image') === false) {
                                $res['CarImageUrl'] = nice($subj);
                            }

                            return $res;
                        }
                    },

                    "RenterName" => function ($text = '', $node = null, $it = null) {
                        return orval(
                            nice(re('#personal\s+information\s+(.*)#')),
                            re("#\n\s*([^\n,]+),\s+Your reservation#")
                        );
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        $xpath = "//text()[contains(., 'rental total')]/ancestor::tr[1]";
                        $subj = re('#rental\s+total\s+(.*)#', node($xpath));

                        if ($subj) {
                            return ['TotalCharge' => cost($subj), 'Currency' => currency($subj)];
                        }
                    },

                    "TotalTaxAmount" => function ($text = '', $node = null, $it = null) {
                        $xpath = "//text()[normalize-space(.) = 'taxes']/ancestor::td[1]/following-sibling::td[1]";
                        $subj = node($xpath);

                        if ($subj) {
                            return cost($subj);
                        }
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return orval(
                            re('#We\'ve\s+(changed)\s+your\s+reservation#'),
                            re("#reservation\s+was\s+(\w+)#"),
                            re("#You have successfully\s+(\w+)#")
                        );
                    },

                    "Cancelled" => function ($text = '', $node = null, $it = null) {
                        return re("#reservation\s+was\s+cancel+ed|successful+y\s+cancel+ed#") ? true : false;
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
