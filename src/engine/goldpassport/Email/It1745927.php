<?php

namespace AwardWallet\Engine\goldpassport\Email;

use PlancakeEmailParser;

class It1745927 extends \TAccountCheckerExtended
{
    public $mailFiles = "goldpassport/it-1745927.eml";

    private $subjects = [
        'Hyatt Web Check-In Invitation',
    ];

    private $detects = [
        'We would like to remind you that you can use Hyatt Web Check-In for today\'s reservation',
    ];

    private $from = '/[@\.]hyatt\.com/i';

    private $prov = 'hyatt';

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return [$text];
                },

                "#.*?#" => [
                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Confirmation Number\s*:\s*([A-Z\d\-]+)#");
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        return [
                            'HotelName' => re("#Guest Name:[^\n]+\s+([^\n]+)\s+(.*?)\s+Tel:\s*([+\-\d\(\) ]+)\s+Fax:\s*([+\-\d\(\) ]+)#ims"),
                            'Address'   => nice(glue(re(2))),
                            'Phone'     => re(3),
                            'Fax'       => re(4),
                        ];
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        return totime(re("#\n\s*Check In Date\s*:\s*([^\n]+)#"));
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        return totime(re("#\n\s*Check Out Date\s*:\s*([^\n]+)#"));
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Guest Name\s*:\s*([^\n]+)#");
                    },
                ],
            ],
        ];
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = !empty($parser->getHTMLBody()) ? $parser->getHTMLBody() : $parser->getPlainBody();

        if (false === stripos($body, $this->prov)) {
            return false;
        }

        foreach ($this->detects as $detect) {
            if (false !== stripos($body, $detect)) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match($this->from, $from);
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['subject'], $headers['from'])) {
            if (!preg_match($this->from, $headers['from'])) {
                return false;
            }

            foreach ($this->subjects as $subject) {
                if (false !== stripos($headers['subject'], $subject)) {
                    return true;
                }
            }
        }

        return false;
    }
}
