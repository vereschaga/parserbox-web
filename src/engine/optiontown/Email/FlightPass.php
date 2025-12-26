<?php

namespace AwardWallet\Engine\optiontown\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class FlightPass extends \TAccountChecker
{
    public $mailFiles = "optiontown/it-13615219.eml, optiontown/it-31002447.eml";

    private $reSubject = [
        'en' => ['Flight Pass - Flight Confirmation'],
    ];
    private $froms = [
        'customercare@optiontown.com',
    ];

    private $reBody = [
        'Optiontown Customer Services',
    ];

    private $reBody2 = [
        'en' => [
            'Your confirmed flight',
        ],
    ];

    private static $dictionary = [
        'en' => [],
    ];

    private $lang = '';
    private $date;

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getHeader('date'));

        $body = html_entity_decode($this->http->Response["body"]);

        foreach ($this->reBody2 as $lang => $reBody) {
            foreach ($reBody as $re) {
                if (stripos($body, $re) !== false) {
                    $this->lang = $lang;
                }
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $ta = $email->ota();

        $confirmation = $this->http->FindSingleNode("(//text()[starts-with(normalize-space(), 'Confirmation number')]/ancestor::tr[1])");

        if (preg_match("#^\s*([^:]+)\s*:\s*([A-Z\d]{5,})\s*$#", $confirmation, $m)) {
            $email->ota()->confirmation($m[2], $m[1]);
        }

        $phone = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'If you have any questions ')]/ancestor::td[1]//text()[starts-with(normalize-space(), 'Phone')]/following::text()[normalize-space() != '' and normalize-space() != ':'][1]", null, true,
                "#^([\d\(\)\+\-\s\.]{5,})$#");

        if (!empty(trim($phone))) {
            $email->ota()->phone(trim($phone), 'Optiontown Customer Services');
        }

        $this->flight($email);

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->froms as $value) {
            if (stripos($from, $value) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        $head = false;

        foreach ($this->froms as $prov => $from) {
            if (strpos($headers["from"], $from) !== false) {
                $head = true;

                break;
            }
        }

        if ($head === false) {
            return false;
        }

        foreach ($this->reSubject as $reSubject) {
            foreach ($reSubject as $re) {
                if (stripos($headers["subject"], $re) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = html_entity_decode($parser->getHTMLBody());

        $head = false;

        foreach ($this->reBody as $reBody) {
            if (stripos($body, $reBody) !== false) {
                $head = true;

                break;
            }
        }

        if ($head === false) {
            return false;
        }

        foreach ($this->reBody2 as $reBody) {
            foreach ($reBody as $re) {
                if (stripos($body, $re) !== false) {
                    return true;
                }
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

    private function flight(Email $email)
    {
        $f = $email->add()->flight();

        $confirmation = $this->http->FindSingleNode("(//text()[starts-with(normalize-space(), 'Booking Reference')]/ancestor::tr[1])");

        if (preg_match("#^\s*([^:]+)\s*:\s*([A-Z\d]{5,})\s*$#", $confirmation, $m)) {
            $f->general()->confirmation($m[2], $m[1]);
        }

        // Passengers
        // don't know: <tr><td>Passenger 1</td></tr><tr><td>Passenger 2</td></tr>
        //          or <tr><td>Passenger 1</td><td>Passenger 2</td></tr>
        $passengers = array_filter($this->http->FindNodes("//text()[normalize-space() = 'Passenger details']/ancestor::tr[1]/following-sibling::tr/td[normalize-space()]", null, "#^\s*\d+\s*\.\s*(.+)#"));

        if (!empty($passengers)) {
            $f->general()->travellers($passengers, true);
        }

        $xpath = "//text()[normalize-space() = 'Flight']/ancestor::tr[1][contains(., 'Depart') or contains(., 'Departure')]/following-sibling::tr[normalize-space()]";
        $segments = $this->http->XPath->query($xpath);

        if ($segments->length === 0) {
            $this->logger->info("Segments root not found: {$xpath}");

            return $email;
        }

        foreach ($segments as $root) {
            $date = '';

            $s = $f->addSegment();

            $node = implode("\n", $this->http->FindNodes('./td[2]//text()[normalize-space()]', $root));

            if (preg_match('#^\s*([A-Z\d]{2})\s*(\d{1,5})\s*(.*)#s', $node, $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2]);

                if (!empty($m[3])) {
                    $s->extra()->cabin($m[3]);
                } else {
                    $node = implode("\n", $this->http->FindNodes('./td[6]//text()[normalize-space()]', $root));

                    if (preg_match("/non[\- ]*stop/i", $node)) {
                        $s->extra()->stops(0);
                    }

                    if (!empty($node = $this->http->FindSingleNode('./td[7]', $root))) {
                        $s->extra()->cabin($node);
                    }
                }
            }

            $node = implode("\n", $this->http->FindNodes('./td[3]//text()[normalize-space()]', $root));

            if (preg_match('#^\s*(.+?)\s*\(\s*([A-Z]{3})\s*\)\s+(.+)\s+(\d+:\d+.+)#s', $node, $m)) {
                $s->departure()
                    ->code($m[2])
                    ->name($m[1])
                    ->date($this->normalizeDate(trim($m[3]) . ' ' . trim($m[4])));
                $date = trim($m[3]);
            }

            $node = implode("\n", $this->http->FindNodes('./td[5]//text()[normalize-space()]', $root));

            if (preg_match('#^\s*(.+?)\s*\(\s*([A-Z]{3})\s*\)\s+(\d+:\d+.+)#s', $node, $m) && !empty($date)) {
                $s->arrival()
                    ->code($m[2])
                    ->name($m[1])
                    ->date($this->normalizeDate($date . ' ' . trim($m[3])));
            }
        }

        return $email;
    }

    private function normalizeDate($str)
    {
        $in = [
            '#^\s*([^\d\s\,\.]+)[.,]?\s*(\d{1,2})\s+([^\d\s\,\.]+)[.,]?\s+(\d+:\d+\s*[APM]{2})\s*$#i', //Sa 30 Dez 2017, ven. 15 déc. 2017
        ];
        $out = [
            '$1, $2 $3, $4',
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#^([^\d\s]+),\s+(\d+)\s+([^\d\s]+)(,\s+.+)#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[3], $this->lang)) {
                $str = str_replace($m[3], $en, $str);
            }
            $dayOfWeekInt = WeekTranslate::number1($m[1]);

            if (!empty($this->date)) {
                $date = $m[2] . ' ' . $m[3] . ' ' . date("Y", $this->date) . $m[4];
            } else {
                $date = $m[2] . ' ' . $m[3] . ' ' . date("Y", strtotime("now")) . $m[4];
            }

            $str = EmailDateHelper::parseDateUsingWeekDay($date, $dayOfWeekInt);
        } else {
            $str = strtotime($str);
        }

        return $str;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) use ($text) { return "contains({$text}, \"{$s}\")"; }, $field));
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
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

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return implode("|", array_map('preg_quote', $field));
    }
}
