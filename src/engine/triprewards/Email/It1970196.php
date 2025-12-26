<?php

namespace AwardWallet\Engine\triprewards\Email;

class It1970196 extends \TAccountCheckerExtended
{
    public $mailFiles = "triprewards/it-1970196.eml, triprewards/it-1971170.eml, triprewards/it-1971768.eml, triprewards/it-1973230.eml, triprewards/it-2303755.eml, triprewards/it-2303827.eml, triprewards/it-2596690.eml, triprewards/it-2596692.eml, triprewards/it-3.eml, triprewards/it-3572863.eml, triprewards/it.eml";
    public $reBody = "The information in this electronic mail";
    public $reBody2 = "www.dreamhotels.com";
    public $reBody3 = "www.ramada.com";
    public $reBody4 = "www.travelodge.com";
    public $reBody5 = "www.daysinn.com";
    public $reBody6 = "wr.wyndhamrewards.com";

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
                        return orval(
                            re('#Web\s+ID\s*\#:\s+([\w\-]+)#i'),
                            re("#confirmation number is\s+([A-Z\d-]+)#"),
                            re("#Confirmation Number[:\s]+([A-Z\d-]+)#")
                        );
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        return orval(
                            node("(//img[contains(@src, 'branded/')]/ancestor::tr[1]/preceding::tr[1])[1]")/*
                            node("//img[contains(@src, 'branded/')]/ancestor::tr[1]/preceding-sibling::tr[1]"),
                            node("//img[contains(@src, 'branded/')]/ancestor::tr[2]/preceding-sibling::tr[1]")*/
                        );
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        return totime(uberDateTime(cell(["Check in", "Check In", "Check-In"], +1)));
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        return totime(uberDateTime(cell(["Check Out", "Check out", "Check-Out"], +1)));
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        $addr = text(xpath("(//img[contains(@src, 'branded/')]/ancestor::tr[1]/following::tr[1])[1]"));

                        return [
                            'Phone' => orval(
                                detach("#Phone:\s*([\d\-+\(\) ]+)#", $addr),
                                re("#\n\s*Phone:\s*([\d\-+\(\) ]+)#")
                            ),
                            'Fax' => orval(
                                detach("#Fax:\s*([\d\-+\(\) ]+)#", $addr),
                                re("#\n\s*Fax:\s*([\d\-+\(\) ]+)#")
                            ),
                            'Address' => nice($addr, ','),
                        ];
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        return [re("#\n\s*(?:Name|Occupant\s+Name):\s+([^\n]+)#")];
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        return orval(
                            re("#\((\d+)\s+people#"),
                            re("#(\d+)\s+Adult#i")
                        );
                    },

                    "Kids" => function ($text = '', $node = null, $it = null) {
                        return re("#(\d+)\s+Children#");
                    },

                    "Rooms" => function ($text = '', $node = null, $it = null) {
                        return re("#\s+(\d+)\s+Room\(s\)#");
                    },

                    "Rate" => function ($text = '', $node = null, $it = null) {
                        $res['Rate'] = null;
                        $res['Cost'] = null;
                        $res['Taxes'] = null;
                        $res['Total'] = null;
                        $res['Currency'] = null;
                        $rates = null;
                        $xpath = '//tr[contains(., "Room Rate") and not(.//tr)]/following-sibling::tr';
                        $rateInfoNodes = $this->http->XPath->query($xpath);

                        foreach ($rateInfoNodes as $n) {
                            $rates[node('./td[1]', $n)] = node('./td[2]', $n);
                            $res['Cost'] += cost(node('./td[2]', $n));
                            $res['Taxes'] += cost(node('./td[4]', $n));

                            if ($res['Currency'] === null) {
                                $res['Currency'] = currency(node('./td[2]', $n));
                            }
                        }

                        if (!$rates) {
                            $rates = [];
                            $res['Rate'] = [];
                        }
                        $rates = array_unique($rates);

                        if (count($rates) == 1) {
                            $res['Rate'] = reset($rates);
                        } else {
                            foreach ($rates as $key => $value) {
                                $res['Rate'][] = $key . ': ' . $value;
                            }
                            $res['Rate'] = implode('; ', $res['Rate']);
                        }
                        //$res['Rate'] = array_unique()
                        if ($res['Cost'] !== null and $res['Taxes'] !== null) {
                            $res['Total'] = $res['Cost'] + $res['Taxes'];
                        }

                        return $res;
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        return re('#Cancellation\s+Policy:\s+(.*)#i');
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        return orval(
                            re('#Room\s+Type:\s+(.*)#i'),
                            clear("#No\s+smoking#i", re("#[^;]+#", cell("Reservation:", +1, 0)))
                        );
                    },

                    "RoomTypeDescription" => function ($text = '', $node = null, $it = null) {
                        return clear("#^[^;]+;#", cell("Reservation:", +1, 0));
                    },

                    "Cost" => function ($text = '', $node = null, $it = null) {
                        return cost(node("//*[contains(normalize-space(text()), 'Total for Stay') or contains(normalize-space(text()), 'Total for stay')]/following::tr[1]/td[last()-2]", null, true, "#\d+\.\d+\s*[A-Z]{3}$#"));
                    },

                    "Taxes" => function ($text = '', $node = null, $it = null) {
                        return cost(node("//*[contains(normalize-space(text()), 'Total for Stay') or contains(normalize-space(text()), 'Total for stay')]/following::tr[1]/td[last() - 1]"));
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        return cost(node("//*[contains(normalize-space(text()), 'Total for Stay') or contains(normalize-space(text()), 'Total for stay')]/following::tr[1]/td[last()]"));
                    },

                    "Currency" => function ($text = '', $node = null, $it = null) {
                        return currency(node("//*[contains(normalize-space(text()), 'Total for Stay') or contains(normalize-space(text()), 'Total for stay')]/following::tr[1]/td[last()]", null, true, "#[A-Z]+$#"));
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re("# reservation has been (\w+)#");
                    },
                ],
            ],
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        return strpos($body, $this->reBody) !== false && (
            strpos($body, $this->reBody2) !== false
            || strpos($body, $this->reBody3) !== false
            || strpos($body, $this->reBody4) !== false
            || strpos($body, $this->reBody5) !== false
            || strpos($body, $this->reBody6) !== false
        );
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
