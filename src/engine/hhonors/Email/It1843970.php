<?php

namespace AwardWallet\Engine\hhonors\Email;

use PlancakeEmailParser;

class It1843970 extends \TAccountCheckerExtended
{
    public $mailFiles = "hhonors/it-1843970.eml";

    private $from = "/[@.](?:hhonors|conradhotels)[.](?:com)?/i";

    private $detects = [
        'Thank you for your reservation. We look forward to welcoming',
        'Thanks you for choosing to spend a holiday at',
    ];

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return [$text];
                },

                "#.*?#" => [
                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*(?:Reservation No\.|Confirmation Number)\s+([A-Z\d\-]+)#");
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $date = totime(cell("Arrival Date", +1, 0));

                        if ($time = cell('Check-in Time', +1, 0)) {
                            $date = strtotime($time, $date);
                        }

                        return $date;
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        $date = totime(cell("Departure Date", +1, 0));

                        if ($time = cell('Check-out Time', +1, 0)) {
                            $date = strtotime($time, $date);
                        }

                        return $date;
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        $re = '/Reservation Confirmation\s+.+\s+(.+)\s+([\s\S]+)\s+Tel\s*:\s*(.+)\s+.*\s*Fax\s*:\s*([\(\)\s\-\+\d]+)/i';
                        $nodes = $this->http->FindNodes("(//td[contains(., 'Reservation Confirmation') and contains(., 'Tel')])[last()]/descendant::text()[normalize-space(.)]");
                        $node = implode("\n", $nodes);

                        if (preg_match($re, $node, $m)) {
                            return [
                                'HotelName' => $m[1],
                                'Address'   => preg_replace('/\s+/', ' ', $m[2]),
                                'Phone'     => preg_replace('/\s+/', ' ', $m[3]),
                                'Fax'       => trim($m[4]),
                            ];
                        }
                        $nodes = $this->http->FindNodes("(//text()[normalize-space()='Reservation Agent']/ancestor::*[1]/following::div[1])[1][contains(.,'Tel')]//text()[normalize-space(.)!='']");
                        $node = implode("\n", $nodes);

                        if (preg_match("#([^\n]+)\n(.+?)\n *Tel.*?([\+\-\d\(\) ]+)$#s", $node, $m)) {
                            return [
                                'HotelName' => $m[1],
                                'Address'   => preg_replace('/\s+/', ' ', $m[2]),
                                'Phone'     => trim($m[3]),
                            ];
                        }
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        return cell("Guest Name", +1, 0, '/descendant::text()[1]');
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        return re("#(\d+)\s+Adult#");
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        return cell("Cancellation Policy", +1, 0);
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        $subj = re("#\s+Room Type\s*:\s*([^\n]*?)\s+Arrival Date#");

                        if (empty($subj)) {
                            $subj = cell("Accommodation", +1, 0);
                        }

                        return $subj;
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        return cost(cell("Total per stay", +1, 0));
                    },

                    "Currency" => function ($text = '', $node = null, $it = null) {
                        return currency(cell("Total per stay", +1, 0));
                    },
                ],
            ],
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && preg_match($this->from, $headers['from']);
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match($this->from, $from);
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (stripos($body, 'hilton') !== false || stripos($body, 'Conrad') !== false) {
            foreach ($this->detects as $detect) {
                if (false !== stripos($body, $detect)) {
                    return true;
                }
            }
        }

        return false;
    }
}
