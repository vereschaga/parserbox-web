<?php

namespace AwardWallet\Engine\jazeera\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ItineraryConfirmation extends \TAccountChecker
{
    public $mailFiles = "jazeera/it-26693788.eml, jazeera/it-26944016.eml, jazeera/it-27051672.eml, jazeera/it-27400833.eml";

    private $detectFrom = ["itinerary@fly.jazeeraairways.com", "itinerary@jazeeraairways.com"];
    private $detectSubject = [
        'Itinerary Confirmation',
    ];

    private $detectBody = [
        'en' => [
            'Important information about your Jazeera Airways flight',
            'Your Jazeera Airways Itinerary',
        ],
    ];

    private $lang = 'en';
    private static $dictionary = [
        'en' => [
            "Flight" => ["Flight", "Flight Number"],
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $body = $this->http->Response['body'];

        if ($this->http->XPath->query("//a[contains(@href,'/www.boxbe.com/')]")->length > 0) {
            $this->changeBody($parser);
        }

        foreach ($this->detectBody as $lang => $detectBody) {
            foreach ($detectBody as $detect) {
                if (stripos($body, $detect) !== false) {
                    $this->lang = $lang;

                    break;
                }
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $this->flight($email);

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->detectFrom as $detectFrom) {
            if (stripos($from, $detectFrom) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $this->http->Response['body'];

        foreach ($this->detectBody as $detectBody) {
            foreach ($detectBody as $detect) {
                if (stripos($body, $detect) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        $found = false;

        foreach ($this->detectFrom as $detectFrom) {
            if (stripos($headers['from'], $detectFrom) !== false) {
                $found = true;
            }
        }

        if ($found == false) {
            return false;
        }

        foreach ($this->detectSubject as $detectSubject) {
            if (stripos($headers['subject'], $detectSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    protected function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function flight(Email $email)
    {
        $f = $email->add()->flight();

        // General
        $conf = $this->http->FindSingleNode("//text()[normalize-space()='Reservation Number']/ancestor::tr[1]/following-sibling::tr[1]/td[1]", null, true, "#^\s*([A-Z\d]{5,})\s*$#");

        if (empty($conf)) {
            $conf = $this->http->FindSingleNode("//text()[normalize-space()='Reservation Number']/ancestor::tr[2]/following-sibling::tr[1]/td[1]/descendant::td[1]", null, true, "#^\s*([A-Z\d]{5,})\s*$#");
        }
        $bookingDate = $this->http->FindSingleNode("//text()[normalize-space()='Booking Date']/ancestor::tr[1]/following-sibling::tr[1]/td[2]");

        if (empty($bookingDate)) {
            $bookingDate = $this->http->FindSingleNode("(//text()[normalize-space()='Date']/ancestor::tr[2]/following-sibling::tr[1]/td[1]/descendant::td[normalize-space() and not(.//td)])[2]");
        }

        $travellers = $this->http->FindNodes("//text()[normalize-space()='Name of passenger(s)']/ancestor::tr[1]/following-sibling::tr[1]/td[3]//tr[not(.//tr)][normalize-space()]", null, "#^\s*(?:\(\s*\+[^\)]*\))?\s*(.+)#");

        if (empty($travellers)) {
            $travellers = $this->http->FindNodes("//text()[normalize-space()='Name of passenger(s)']/ancestor::tr[1]/following-sibling::tr[1][./td[1]//table]/td[2]//text()[normalize-space()]", null, "#^\s*(?:\(\s*\+[^\)]*\))?\s*(.+)#");
        }

        $f->general()
            ->confirmation($conf, "Reservation Number")
            ->date($this->normalizeDate($bookingDate))
            ->travellers(array_filter($travellers));

        // Price
        $total = $this->http->FindSingleNode('//td[normalize-space() = "Payments"]/following-sibling::td[normalize-space()][1]');

        if (preg_match("#^\s*[A-Z]{3}\s*$#", $total)) {
            $f->price()
                ->total($this->amount($this->http->FindSingleNode('//td[normalize-space() = "Payments"]//ancestor::tr[1]/following-sibling::tr[1]/td[normalize-space()][1]')))
                ->currency(trim($total));
        } elseif (!empty($total) && (preg_match("#^\s*(?<amount>\d[\d\.]*)\s*(?<curr>[A-Z]{3})\s*$#", $total, $m)
                || preg_match("#^\s*(?<curr>[A-Z]{3})\s*(?<amount>\d[\d\.]*)\s*$#", $total, $m))) {
            $f->price()
                ->total($this->amount($m['amount']))
                ->currency($m['curr']);
        }

        $xpath = "//text()[normalize-space()='Departing']/ancestor::tr[1][contains(normalize-space(),'Arriving')]/following-sibling::tr[not(.//text()[normalize-space() = 'Arriving'])]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length > 0) {
            $columns = [
                'date'      => count($this->http->FindNodes("(//text()[normalize-space()='Date']/ancestor::td[1][ancestor::tr[1][contains(normalize-space(),'Arriving')]])[1]/preceding-sibling::td")),
                'flight'    => count($this->http->FindNodes("(//text()[" . $this->eq($this->t("Flight")) . "]/ancestor::td[1][ancestor::tr[1][contains(normalize-space(),'Arriving')]])[1]/preceding-sibling::td")),
                'departure' => count($this->http->FindNodes("(//text()[normalize-space()='Departing']/ancestor::td[1][ancestor::tr[1][contains(normalize-space(),'Arriving')]])[1]/preceding-sibling::td")),
                'arrival'   => count($this->http->FindNodes("(//text()[normalize-space()='Arriving']/ancestor::td[1][ancestor::tr[1][contains(normalize-space(),'Departing')]])[1]/preceding-sibling::td")),
                'class'     => count($this->http->FindNodes("(//text()[normalize-space()='Class']/ancestor::td[1][ancestor::tr[1][contains(normalize-space(),'Class')]])[1]/preceding-sibling::td")),
                'seat'      => count($this->http->FindNodes("(//text()[normalize-space()='Seat']/ancestor::td[1][ancestor::tr[1][contains(normalize-space(),'Seat')]])[1]/preceding-sibling::td")),
            ];
            $columns = array_map(function ($v) {return $v + 1; }, array_filter($columns));
        }

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $date = $this->normalizeDate($this->http->FindSingleNode("./td[" . $columns['date'] . "]", $root));

            if (empty($date)) {
                $this->logger->debug("date not found");

                return false;
            }
            // Airline
            $s->airline()
                ->name(!empty($columns['flight']) ? $this->http->FindSingleNode("./td[" . $columns['flight'] . "]", $root, true, "#^\s*([A-Z\d]{2})\s*\d{1,5}#") : null)
                ->number(!empty($columns['flight']) ? $this->http->FindSingleNode("./td[" . $columns['flight'] . "]", $root, true, "#^\s*[A-Z\d]{2}\s*(\d{1,5})#") : null)
            ;

            $regexp = "#(?<name>.+?)\n+(?<time>\d{4}\D{1,4})\n(?<name2>.+?)(?:\s+Terminal\s*(?<term>.*)|\s+T(?<term2>[\dA-Z]{1,4}))?$#s";
            // Departure
            $node = implode("\n", !empty($columns['flight']) ? $this->http->FindNodes("./td[" . $columns['departure'] . "]//text()", $root) : []);

            if (!empty($node) && preg_match($regexp, $node, $m)) {
                $s->departure()
                    ->noCode()
                    ->name(trim($m['name2']) . ', ' . trim($m['name']))
                    ->date(strtotime($this->normalizeTime($m['time']), $date))
                    ->terminal($m['term2'] ?? $m['term'] ?? null, true, true)
                ;
            }

            // Arrival
            $node = implode("\n", !empty($columns['flight']) ? $this->http->FindNodes("./td[" . $columns['arrival'] . "]//text()", $root) : []);

            if (!empty($node) && preg_match($regexp, $node, $m)) {
                $s->arrival()
                    ->noCode()
                    ->name(trim($m['name2']) . ', ' . trim($m['name']))
                    ->date(strtotime($this->normalizeTime($m['time']), $date))
                    ->terminal($m['term2'] ?? $m['term'] ?? null, true, true)
                ;
            }

            // Extra
            if (!empty($columns['class'])) {
                $s->extra()
                    ->cabin($this->http->FindSingleNode("./td[" . $columns['class'] . "]", $root), true);
            }

            if (!empty($columns['seat'])) {
                $seats = implode(" ", $this->http->FindNodes("./td[" . $columns['seat'] . "]//text()[normalize-space()]", $root));

                if (preg_match_all("#\b(\d{1,3}[A-Z])\b#", $seats, $m)) {
                    $s->extra()
                        ->seats($m[1]);
                }
            }
        }

        return $email;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function changeBody($parser)
    {
        $texts = implode("\n", $parser->getRawBody());

        if (substr_count($texts, 'Content-Type: text/') > 1) {
            $texts = preg_replace("#------=_NextPart.*#", "\n", $texts);
            $texts = preg_replace("#\n--[\S]+(=|--)\n#", "\n", $texts);
            $text = '';
            $posBegin1 = stripos($texts, "Content-Type: text/");
            $i = 0;

            while ($posBegin1 !== false && $i < 50) {
                $posBegin = stripos($texts, "\n\n", $posBegin1) + 2;
                $str = substr($texts, $posBegin1, $posBegin - $posBegin1);

                $posEnd = stripos($texts, "Content-Type: ", $posBegin);
                $block = substr($texts, $posBegin, $posEnd - $posBegin);
                $posEnd = strripos($block, "\n\n");
                $block = substr($texts, $posBegin, $posEnd);

                if (preg_match("#: base64#is", $str)) {
                    $block = trim($block);
                    $block = htmlspecialchars_decode(base64_decode($block));

                    if (($blockBegin = stripos($block, '<blockquote')) !== false) {
                        $blockEnd = strripos($block, '</blockquote>', $blockBegin) + strlen('</blockquote>');
                        $block = substr($block, $blockBegin, $blockEnd - $blockBegin);
                    }
                    $text .= $block;
                } elseif (preg_match("#quoted-printable#s", $str)) {
                    $text .= quoted_printable_decode($block);
                } else {
                    $text .= htmlspecialchars_decode($block);
                }
                $posBegin1 = stripos($texts, "Content-Type: text/", $posBegin);
                $i++;
            }
            $this->http->SetEmailBody($text, true);
        }
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'starts-with(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'contains(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function amount($s)
    {
        if (is_numeric($s)) {
            return (float) $s;
        }

        return null;
    }

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
    }

    private function normalizeDate($str)
    {
        $in = [
            "#^\s*(\d{1,2})\s*([^\s\d\,\.]+)\s*(\d{4})\s*$#u", // 14Jun2016
        ];
        $out = [
            "$1 $2 $3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            } elseif ($en = MonthTranslate::translate($m[1], 'ar')) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function normalizeTime($str)
    {
        $in = [
            "#^\s*(\d{1,2})(\d{2})[\shr]*$#", //2125hr
        ];
        $out = [
            "$1:$2",
        ];
        $str = preg_replace($in, $out, $str);

        return $str;
    }
}
