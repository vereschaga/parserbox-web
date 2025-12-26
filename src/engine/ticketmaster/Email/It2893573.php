<?php

namespace AwardWallet\Engine\ticketmaster\Email;

use PlancakeEmailParser;

class It2893573 extends \TAccountCheckerExtended
{
    public $mailFiles = "ticketmaster/it-2893573.eml, ticketmaster/it-8000627.eml";

    private $detects = [
        '- the event countdown is on!',
    ];

    private $subjects = [
        'Your Ticketmaster Order for',
    ];

    private $from = '#[@.]ticketmaster\.com#i';

    private $prov = 'ticketmaster';

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "E";
                    },

                    "ConfNo" => function ($text = '', $node = null, $it = null) {
                        $data = [];
                        $data['ConfNo'] = re("#\n\s*Order\s*\#:\s*([\w\-\/]+)\n(?:\s*Account.+\n)?[^\n]*\s+([^\n]+)\s+([^\n]+)#");
                        $data['ConfNo'] = preg_replace("#\/#", "--", $data['ConfNo']);
                        $data['Name'] = re(2);
                        $data['Address'] = nice(re(3), ",");

                        return $data;
                    },

                    "StartDate" => function ($text = '', $node = null, $it = null) {
                        return $this->normalizeDate(re("#\n\s*\w+,\s*(\w+.?\s+\d+,\s*\d{4}\s+\d+:\d+\s*\w{2,4})\s#"));
                    },

                    "DinerName" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Thanks\s*,?\s*([^\n]+?)\s+[-,.]#");
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return total(re("#Total\s+Charges:\s*([^\n]+?)\s*?\n#"), 'TotalCharge');
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        if (re("#the\s+event\s+countdown\s+is\s+on\b#i")) {
                            return "confirmed";
                        }
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

    private function normalizeDate(?string $s = null)
    {
        $in = [
            '/(\w+)\.\s+(\d{1,2}),\s+(\d{2,4})\s+(\d{1,2}:\d{2}\s*[AP]M)/', // Oct. 25, 2015 07:30 PM
        ];
        $out = [
            '$2 $1 $3, $4',
        ];

        return strtotime(preg_replace($in, $out, $s));
    }
}
