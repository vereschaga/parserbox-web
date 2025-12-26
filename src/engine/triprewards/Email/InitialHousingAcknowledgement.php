<?php

namespace AwardWallet\Engine\triprewards\Email;

class InitialHousingAcknowledgement extends \TAccountCheckerExtended
{
    public $reFrom = "#asis@wyndhamjade\.com#i";
    public $reProvider = "#wyndhamjade\.com#i";
    public $rePlain = "#wyndhamjade\.com#i";
    public $rePlainRange = "/1";
    public $typesCount = "1";
    public $langSupported = "en";
    public $reSubject = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $xPath = "";
    public $mailFiles = "triprewards/it-1789658.eml";
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
                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        return re('#Web\s+ID\s*\#:\s+([\w\-]+)#i');
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        $xpath = '//td[contains(., "Phone:") and not(.//td) and not(contains(., "Hotel Reservation Modifications"))]//text()';
                        $subj = implode("\n", nodes($xpath));
                        $regex = '#\s*\n\s*(.*)\s+((?s).*)\s+Phone:\s+(.*)\s+Fax:\s+(.*)#i';

                        if (preg_match($regex, $subj, $m)) {
                            return [
                                'HotelName' => $m[1],
                                'Address'   => nice($m[2], ','),
                                'Phone'     => $m[3],
                                'Fax'       => $m[4],
                            ];
                        }
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $res = null;

                        foreach (['CheckIn' => 'Check-In', 'CheckOut' => 'Check-Out'] as $key => $value) {
                            if (preg_match('#' . $value . ':\s*(.*)\s+at\s+(.*)#i', $text, $m)) {
                                $res[$key . 'Date'] = strtotime(str_replace('-', ' ', $m[1]) . ', ' . $m[2]);
                            }
                        }

                        return $res;
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        return [re('#Occupant\s+Name:\s+(.*)#')];
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        return re('#\((\d+)\s+people#');
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
                        return re('#Room\s+Type:\s+(.*)#i');
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
