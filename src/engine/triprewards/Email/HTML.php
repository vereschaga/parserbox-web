<?php

namespace AwardWallet\Engine\triprewards\Email;

class HTML extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#Wyndham Hotel Resorts Confirmed Reservation Notification#i', 'blank', '1000'],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = [
        ['#Baymont Inns Confirmed Reservation Notification#i', 'blank', '1000'],
    ];
    public $reFrom = [
        ['#donotreply@wyn.com#i', 'us', ''],
    ];
    public $reProvider = [
        ['#wyn.com#i', 'us', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "";
    public $caseReference = "";
    public $upDate = "02.04.2015, 18:08";
    public $crDate = "";
    public $xPath = "";
    public $mailFiles = "triprewards/it-1413242.eml, triprewards/it-1969860.eml, triprewards/it-1970940.eml, triprewards/it-2460910.eml, triprewards/it-2847519.eml, triprewards/it-2847575.eml";
    public $re_catcher = "#.*?#";
    public $pdfRequired = "";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        return re("#Confirmation Number:\s+(\w+)#");
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        return orval(
                            node("//tr[td/div/img and following-sibling::tr[contains(., 'E-mail')]]/ancestor::table[2]/tbody/tr[1]"),
                            node("//*[contains(text(),'E-mail')]/ancestor::tr[//img[1]][2]/preceding-sibling::tr[1]"),
                            node("//*[contains(text(),'Fax:')]/ancestor::tr[//img[1]][2]/preceding-sibling::tr[1]"),
                            node("//*[contains(text(),'Phone:')]/ancestor::tr[2]/preceding-sibling::*[1]")
                        );
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        return totime(uberDateTime(re("#\n\s*Check[\s-]*In\s*:\s*([^\n]+)#i")));
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        return totime(uberDateTime(re("#\n\s*Check[\s-]*Out\s*:\s*([^\n]+)#i")));
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        $nodes = nodes("//tr[td/div/img and following-sibling::tr[contains(., 'E-mail') or contains(text(),'Fax:')]]/following-sibling::tr[1]//text()");

                        if (!count($nodes)) {
                            $nodes = nodes("//*[contains(text(),'E-mail') or contains(text(),'Fax:')]/ancestor::tr[//img[1]][2]//text()");
                        }

                        if (!count($nodes)) {
                            $nodes = nodes("//*[contains(text(),'Phone:')]/ancestor::tr[1]/preceding-sibling::tr[1]");
                        }

                        $subj = join(', ', array_filter($nodes));
                        $subj = trim(clear("#\s+Phone:\s*.+#ims", $subj), " \n,.");

                        $res['Address'] = $subj;
                        $regex = '#(?P<Addr>.*),\s+(?P<City>.*),\s+(?P<State>\w+)\s+(?P<PCode>\d+)\s+(?P<Country>.*)#';

                        if (preg_match($regex, $subj, $m)) {
                            $da['AddressLine'] = $m['Addr'];
                            $da['City'] = $m['City'];
                            $da['StateProv'] = $m['State'];
                            $da['PostalCode'] = $m['PCode'];
                            $da['Country'] = $m['Country'];
                            $res['DetailedAddress'] = $da;
                        }

                        return $res;
                    },

                    "Phone" => function ($text = '', $node = null, $it = null) {
                        $subj = node("//tr[td/div/img and following-sibling::tr[contains(., 'E-mail') or contains(text(),'Fax:')]]/following-sibling::tr[2]");

                        if (!$subj) {
                            $subj = node("//*[contains(text(),'E-mail') or contains(text(),'Fax:')]/ancestor::tr[//img[1]][2]");
                        }

                        if (!$subj) {
                            $subj = node("//*[contains(text(),'Phone:')]/ancestor::td[1]");
                        }

                        if (preg_match('#Phone:\s+([\d\-]+)\s+Fax:\s+([\d\-]+)#', $subj, $m)) {
                            return ['Phone' => $m[1], 'Fax' => $m[2]];
                        }
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Name\s*:\s*([^\n]+)#");
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        return re("#(\d+)\s+Adult#i");
                    },

                    "Kids" => function ($text = '', $node = null, $it = null) {
                        return re("#(\d+)\s+Child#i");
                    },

                    "Rooms" => function ($text = '', $node = null, $it = null) {
                        return re("#(\d+)\s+Rooms?#i");
                    },

                    "Rate" => function ($text = '', $node = null, $it = null) {
                        $xpath = "//*[contains(text(), 'Total for stay')]/ancestor::tr[1]/following-sibling::tr[1]/td[last() - 2]//td[2]/*[not(contains(@style, 'line-through'))]";

                        return node($xpath);
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        return re("#Cancellation Policy:\s+(.*)#");
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        $info = text(xpath("//text()[contains(., 'Room Information')]/ancestor::td[1]"));

                        if ($info) {
                            return [
                                "RoomType"            => re("#Room Information\s+([^\n]+)\s+(.+)$#s", $info),
                                "RoomTypeDescription" => re(2),
                            ];
                        }

                        $info = text(xpath("//text()[contains(., 'Reservation:')]/following::td[1]"));

                        return [
                            "RoomType"            => clear("#No\s*smoking#", re("#^([^\n]+)\s+(.+)$#is", $info)),
                            "RoomTypeDescription" => nice(trim(re(2), "->; ")),
                        ];
                    },

                    "Cost" => function ($text = '', $node = null, $it = null) {
                        return cost(node("//*[contains(text(), 'Total for stay') or contains(text(), 'Total for Stay')]/following::tr[1]/td[last()-2]", null, true, "#([\d.,]+\s*[A-Z]{3})#"));
                    },

                    "Taxes" => function ($text = '', $node = null, $it = null) {
                        return cost(node("//*[contains(text(), 'Total for stay') or contains(text(), 'Total for Stay')]/following::tr[1]/td[last()-1]"));
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        $subj = node("//*[contains(text(), 'Total for stay') or contains(text(), 'Total for Stay')]/following::tr[1]/td[last()]");

                        return ['Total' => cost($subj), 'Currency' => currency(clear("#[\d.,]#", $subj))];
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return orval(
                            re("#Your room reservation has been (\w+)\.#"),
                            re("#Cancellation Confirmed#i") ? 'Cancelled' : null
                        );
                    },

                    "Cancelled" => function ($text = '', $node = null, $it = null) {
                        return re("#Cancellation\s+Confirmed#i") ? true : false;
                    },
                ],
            ],
        ];
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $result = parent::ParsePlanEmail($parser);

        return $result;
    }

    public static function getEmailTypesCount()
    {
        return 1;
    }

    public static function getEmailLanguages()
    {
        return ["en"];
    }

    public function IsEmailAggregator()
    {
        return false;
    }
}
