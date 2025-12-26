<?php

namespace AwardWallet\Engine\opentable\Email;

use PlancakeEmailParser;

class YourReservationConfirmation2 extends \TAccountCheckerExtended
{
    public $mailFiles = "opentable/it-1820391.eml, opentable/it-1871981.eml, opentable/it-2037349.eml, opentable/it-2215204.eml, opentable/it-2248067.eml, opentable/it-2327597.eml, opentable/it-2628847.eml, opentable/it-2717754.eml, opentable/it-2757219.eml, opentable/it-3080235.eml";

    private $lang = 'en';

    private $from = '/[@\.a-z]+opentable[a-z]*\.com/i';

    private $detects = [
        'en'  => 'Your reservation is confirmed for',
        'en2' => 'Your booking is confirmed for',
    ];

    private $prov = 'opentable';

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
                        $result = orval(
                            re('#You\s+can\s+reference\s+reservation\s+number\s+([\w\-]+)#i'),
                            re_white('Reservation Number: (\w+)')
                        );

                        if (empty($result) && (
                            $this->http->XPath->query('//node()[contains(normalize-space(.),"Your reservation is confirmed for")]')->length > 0
                                || $this->http->XPath->query('//node()[contains(normalize-space(.),"reservation number")]')->length == 0
                            )) {
                            $result = CONFNO_UNKNOWN;
                        }

                        return $result;
                    },

                    "Name" => function ($text = '', $node = null, $it = null) {
                        if ($n = node('//tr[(contains(normalize-space(.), "Your reservation is confirmed for") or contains(., "Your booking is confirmed for")) and not(.//tr)]/following-sibling::tr[1]')) {
                            return [
                                'Name'      => $n,
                                'DinerName' => nice(node("(//*[
															contains(text(), 'Party of') or
															contains(text(), 'Table for')
														]) [1] /preceding::div[1]")),
                            ];
                        } elseif ($n = re('#Your\s+reservation\s+is\s+confirmed\s+for\s+(.*?)\s+Thanks?\s+#si')) {
                            return [
                                'Name'      => nice($n),
                                'DinerName' => nice(re('#(.*)\s+Table\s+for\s+\d+#i')),
                            ];
                        }
                    },

                    "StartDate" => function ($text = '', $node = null, $it = null) {
                        $regex = white('(?: Party of | Table for ) (\d+) on (?:\w+,)? (.+?) at (\d+:\d+ (?:am|pm)?)');

                        if (preg_match("/$regex/isu", $text, $m)) {
                            return [
                                'Guests'    => $m[1],
                                'StartDate' => strtotime(nice($m[2] . ', ' . $m[3])),
                            ];
                        }
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        $s = implode("\n", nodes('//tr[normalize-space(.) = "Address" and not(.//tr)]/ancestor::*[1]//text()'));
                        $r = '#';
                        $r .= 'Address\s+((?s).*?)\s*';
                        $r .= (stripos($s, 'Cross Street') !== false) ? 'Cross\s+Street:.*?\s*' : '';
                        $r .= '\n\s*([\d \-\(\)x]+)\s+';
                        $r .= '(?:Transportation\s+&\s+details)?';
                        $r .= '#';

                        if (preg_match($r, $s, $m)) {
                            return [
                                'Address' => nice($m[1], ','),
                                'Phone'   => nice($m[2]),
                            ];
                        }
                    },

                    "EarnedAwards" => function ($text = '', $node = null, $it = null) {
                        return reni('Upon dining, you will receive (.+?) [.]');
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re('#Your\s+(?:reservation|booking)\s+is\s+(confirmed)\s+for#i');
                    },
                ],
            ],
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && preg_match($this->from, $headers['from']) > 0;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (false === stripos($body, $this->prov)) {
            return false;
        }

        foreach ($this->detects as $detect) {
            if (0 < $this->http->XPath->query("//node()[contains(normalize-space(.), '{$detect}')]")->length) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match($this->from, $from) > 0;
    }
}
