<?php

namespace AwardWallet\Engine\obb\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class Booking extends \TAccountChecker
{
    public $mailFiles = "obb/it-139153720.eml, obb/it-141643249.eml, obb/it-142495477.eml";

    public $lang;
    public static $dictionary = [
        'en' => [
            // Html
            //            'Booking code:' => '',
            'Timetable' => ['Timetable', 'My Timetable'],
            //            'for' => '',
            //            'for' => '',
            //            'other' => '',
            // 'Day of travel' => '',
            //            'Booking Details' => '',
            // Pdf
            'Ticketcode' => '',
        ],
        'de' => [
            // Html
            'Booking code:'   => 'Buchungscode:',
            'Timetable'       => ['Fahrplan', 'Mein Fahrplan'],
            'for'             => 'für',
            'valid'           => 'gilt',
            'other'           => 'Weitere',
            // 'Day of travel' => '',
            'Booking Details' => 'Buchungsdetails',
            // pdf
            'Ticketcode' => 'Ticketcode',
        ],
    ];

    private $detectFrom = "tickets@oebb.at";
    private $detectSubject = [
        // en
        'ÖBB Booking – ',
        // de
        'ÖBB Buchung – ',
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true && stripos($headers["subject"], 'ÖBB')) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers["subject"], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName(".*\.pdf");

        if ($this->http->XPath->query("//a[{$this->contains(['.oebb.at'], '@href')}]")->length === 0
            && $this->containsText($parser->getPlainBody(), '.oebb.at/') !== true
            && count($pdfs) == 0
        ) {
            return false;
        }

        foreach (self::$dictionary as $dict) {
            if (!empty($dict['Timetable'])
                && $this->http->XPath->query("//tr[not(.//tr)][*[1][contains(normalize-space(), ' > ')] and *[2][{$this->starts($dict['Timetable'])}]]")->length > 0
            ) {
                return true;
            }
        }
        $plainBody = $parser->getPlainBody();

        foreach (self::$dictionary as $dict) {
            if (!empty($dict['Timetable']) && (
                $this->http->XPath->query("//tr[not(.//tr)][*[1][contains(normalize-space(), ' > ')] and *[2][{$this->starts($dict['Timetable'])}]]")->length > 0
                || $this->containsText($plainBody, $dict['Timetable'])
                )) {
                return true;
            }
        }

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if ($this->containsText($text, 'ÖBB') !== true) {
                continue;
            }

            foreach (self::$dictionary as $dict) {
                if (!empty($dict['Ticketcode']) && $this->containsText($text, $dict['Ticketcode'])) {
                    return true;
                }
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $type = '';
        $plainBody = $parser->getPlainBody();

        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict['Timetable'])) {
                if ($this->http->XPath->query("//tr[not(.//tr)][*[1][contains(normalize-space(), ' > ')] and *[2][{$this->starts($dict['Timetable'])}]]")->length > 0
                ) {
                    $this->lang = $lang;
                    $type = 'Html';
                    $this->parseEmailHtml($email);

                    break;
                }

                if ($this->containsText($plainBody, $dict['Timetable']) && preg_match("/\b\d{1,2}:\d{2}\b/", $plainBody)) {
                    $this->lang = $lang;
                    $type = 'Plain';
                    $this->parseEmailPlain($email, $plainBody);

                    break;
                }
            }
        }

        if (empty($email->getItineraries())) {
            $pdfs = $parser->searchAttachmentByName(".*\.pdf");

            foreach ($pdfs as $pdf) {
                $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

                if ($this->containsText($text, 'ÖBB') !== true) {
                    continue;
                }

                foreach (self::$dictionary as $lang => $dict) {
                    if (!empty($dict['Ticketcode']) && $this->containsText($text, $dict['Ticketcode'])) {
                        $this->lang = $lang;
                        $type = 'Pdf';
                        $this->parseEmailPdf($email, $text);

                        break;
                    }
                }
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . $type . ucfirst($this->lang));

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    protected function res($re, $str, $c = 1)
    {
        preg_match_all($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function parseEmailHtml(Email $email)
    {
        $xpath = "//tr[not(.//tr)][*[1][contains(normalize-space(), ' > ')] and *[2][" . $this->starts($this->t("Timetable")) . "]]";

        $t = $email->add()->train();

        // General
        $t->general()
            ->confirmation(str_replace(' ', '', $this->http->FindSingleNode("//text()[{$this->eq($this->t("Booking code:"))}]/following::text()[normalize-space()][1]",
                null, true, "/^\s*(\d+(?: \d+)*)\s*$/")));
        $travellersStr = array_unique(array_filter($this->http->FindNodes($xpath . "/following-sibling::tr[normalize-space()][1]",
            null, "/\s+{$this->opt($this->t("for"))}\s+(.+)/")));

        if (empty($travellersStr)) {
            $travellersStr = array_unique(array_filter($this->http->FindNodes($xpath . "/following-sibling::tr[normalize-space()][1][following-sibling::tr[normalize-space()][1]/*[1][{$this->contains($this->t('valid'))}]]"
                . "[not({$this->contains(['journey', 'Fahrt'])})][not({$this->contains($this->t("for"))})]")));
        }
        $travellers = [];

        foreach ($travellersStr as $row) {
            $travellers += explode(",", $row);
        }
        $travellers = array_filter(preg_replace("/^\s*\d+ {$this->opt($this->t("other"))}.*$/i", '', $travellers));
        $travellers = array_unique(array_map('trim', $travellers));
        $t->general()
            ->travellers($travellers);

        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $s = $t->addSegment();

            $s->extra()
                ->noNumber();

            $route = $this->http->FindSingleNode("./*[1]", $root);

            if (preg_match("/^\s*([^>]+)\s*>\s*([^>]+)\s*$/", $route, $m)) {
                $s->departure()
                    ->name($m[1]);
                $s->arrival()
                    ->name($m[2]);
            }

            $dates = $this->http->FindSingleNode("following-sibling::tr[normalize-space()][2]/*[1]", $root);

            if (preg_match("/^\s*{$this->opt($this->t("valid"))}.* (\w+[.,]? \w+[.,]? \d{4}) \w+ (\d{1,2}:\d{2}(?:\s*[ap]m)?)(?:\s+-\s+\w+[.,]? \w+[.,]? \d{4} \w+ \d{1,2}:\d{2}(?:\s*[ap]m)?)?\s*$/ui", $dates, $m)) {
                $s->departure()
                    ->date($this->normalizeDate($m[1] . ', ' . $m[2]));
                $s->arrival()
                    ->noDate();
            } elseif (preg_match("/^\s*(?:{$this->opt($this->t("valid"))}|{$this->opt($this->t("Day of travel"))}).* 20\d{2}\b/i", $dates, $m)) {
                $s->departure()
                    ->noDate();
                $s->arrival()
                    ->noDate();
            }
        }

        return true;
    }

    private function parseEmailPlain(Email $email, $text)
    {
        $t = $email->add()->train();

        // General
        $t->general()
            ->confirmation(str_replace(' ', '', $this->re("/{$this->opt($this->t("Booking code:"))}\s*(\d+(?: \d+)*)\s*\n/", $text)));
        $travellersStr = array_unique($this->res("/{$this->opt($this->t("Timetable"))}(?:.*\n+){1,3}.+ {$this->opt($this->t("for"))}\s+([[:alpha:] \-,]+)/u", $text));
        $travellers = explode(",", implode(',', $travellersStr));
        $travellers = array_filter(preg_replace("/^\s*\d+ {$this->opt($this->t("other"))}.*$/i", '', $travellers));
        $travellers = array_unique(array_map('trim', $travellers));
        $t->general()
            ->travellers($travellers);

        $segments = $this->split("/([^>\n]+\s*>\s*[^>\n]+\s+{$this->opt($this->t("Timetable"))})\b/u", $text);

        foreach ($segments as $stext) {
            $stext = preg_replace("/^(?:[\s\S]*?\s*)([^>\n]+\s*>\s*[^>\n]+\s+{$this->opt($this->t("Timetable"))})/u", '$1', $stext);
            $stext = preg_replace("/^((.*\n+){10})[\s\S]*/", '$1', $stext);

            $s = $t->addSegment();

            $s->extra()
                ->noNumber();

            if (preg_match("/^\s*([^>\n]+)\s*>\s*([^>\n]+)\s+{$this->opt($this->t("Timetable"))}/", $stext, $m)) {
                $s->departure()
                    ->name($m[1]);
                $s->arrival()
                    ->name($m[2]);
            }

            if (preg_match("/\n\s*{$this->opt($this->t("valid"))}.* (\w+[.,]? \w+[.,]? \d{4}) \w+ (\d{1,2}:\d{2}(?:\s*[ap]m)?)\s*{$this->opt($this->t("Booking Details"))}/iu", $stext, $m)) {
                $s->departure()
                    ->date($this->normalizeDate($m[1] . ', ' . $m[2]));
                $s->arrival()
                    ->noDate();
            } elseif (preg_match("/\n\s*{$this->opt($this->t("valid"))}.* 20\d{2}\b/iu", $stext, $m)) {
                $s->departure()
                    ->noDate();
                $s->arrival()
                    ->noDate();
            }
        }

        return true;
    }

    private function parseEmailPdf(Email $email, $text)
    {
        $text = preg_replace('/ {5,}\.$/m', '', $text);
        $t = $email->add()->train();

        // General
        $t->general()
            ->confirmation(str_replace(' ', '', $this->re("/{$this->opt($this->t("Booking code:"))}\s*(\d+(?: \d+)*)\s*\n/", $text)));
        $travellersStr = array_unique($this->res("/{$this->opt($this->t("Ticketcode"))}.*\n\s*{$this->opt($this->t("for"))} ([[:alpha:] \-,]+)/u", $text));
        $travellers = array_filter(array_unique(array_map('trim', explode(",", implode(',', $travellersStr)))));

        if (!empty($travellers)) {
            $t->general()
                ->travellers($travellers);
        }
        $ticketStr = array_unique($this->res("/{$this->opt($this->t("Ticketcode"))} *(\d(?: ?\d)+)\s+/u", $text));
        $tickets = array_filter(array_unique(array_map('trim', explode(",", implode(',', $ticketStr)))));

        foreach ($tickets as $ticket) {
            $t->addTicketNumber($ticket, false);
        }

        $segments = $this->split("/\n(.*\b{$this->opt($this->t("Ticketcode"))})\b/u", $text);

        foreach ($segments as $stext) {
            $s = $t->addSegment();

            $s->extra()
                ->noNumber();

            if (preg_match("/{$this->opt($this->t("Ticketcode"))}(?:.*\n){2}\s*([^>\n]+)\s*>\s*([^>\n]+?), (?<class>\S(?: ?\S)+)/", $stext, $m)) {
                $s->departure()
                    ->name($m[1]);
                $s->arrival()
                    ->name($m[2]);

                $s->extra()
                    ->cabin($m['class']);
            }

            if (preg_match("/\n\s*{$this->opt($this->t("valid"))}.* (\w+[.,]? \w+[.,]? \d{4}) \w+ (\d{1,2}:\d{2}(?:\s*[ap]m)?)\s*(\n|{$this->opt($this->t("Booking Details"))})/ui", $stext, $m)) {
                $s->departure()
                    ->date($this->normalizeDate($m[1] . ', ' . $m[2]));
                $s->arrival()
                    ->noDate();
            } elseif (preg_match("/\n\s*{$this->opt($this->t("valid"))}.* 20\d{2}\b/iu", $stext, $m)) {
                $s->departure()
                    ->noDate();
                $s->arrival()
                    ->noDate();
            }
        }

        $total = $this->getTotal($this->re("/\n *{$this->opt($this->t('Gesamtbetrag'))} {2,}(.+)/", $text));
        $t->price()
            ->currency($total['currency'])
            ->total($total['amount']);

        return true;
    }

    private function t($s)
    {
        if (!isset($this->lang, self::$dictionary[$this->lang], self::$dictionary[$this->lang][$s])) {
            return $s;
        }

        return self::$dictionary[$this->lang][$s];
    }

    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) {
            return 'contains(' . $text . ',"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function normalizeDate(?string $date): ?int
    {
        if (empty($date)) {
            return null;
        }

        $in = [
            //            // 19. Mär 2022,  20:17
            "/^\s*(\d+)[,.]?\s*(\w+)[,.]?\s*(\d{4})[\s,]+(\d{1,2}:\d{2}(\s*[ap]m)?)\s*$/u",
        ];
        $out = [
            '$1 $2 $3, $4',
        ];
        $date = preg_replace($in, $out, $date);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        return strtotime($date);
    }

    private function opt($field, $delimiter = '/')
    {
        $field = (array) $field;

        if (empty($field)) {
            $field = ['false'];
        }

        return '(?:' . implode("|", array_map(function ($s) use ($delimiter) {
            return str_replace(' ', '\s+', preg_quote($s, $delimiter));
        }, $field)) . ')';
    }

    private function containsText($text, $needle): bool
    {
        if (empty($text) || empty($needle)) {
            return false;
        }

        if (is_array($needle)) {
            foreach ($needle as $n) {
                if (strpos($text, $n) !== false) {
                    return true;
                }
            }
        } elseif (is_string($needle) && strpos($text, $needle) !== false) {
            return true;
        }

        return false;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function split($re, $text)
    {
        $r = preg_split($re, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        $ret = [];

        if (count($r) > 1) {
            array_shift($r);

            for ($i = 0; $i < count($r) - 1; $i += 2) {
                $ret[] = $r[$i] . $r[$i + 1];
            }
        } elseif (count($r) == 1) {
            $ret[] = reset($r);
        }

        return $ret;
    }

    private function getTotal($text)
    {
        $result = ['amount' => null, 'currency' => null];

        if (preg_match("#^\s*(?<currency>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $text, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d\s]{1,5})\s*$#", $text, $m)
            // $232.83 USD
            || preg_match("#^\s*\D{1,5}(?<amount>\d[\d\., ]*)\s*(?<currency>[A-Z]{3})\s*$#", $text, $m)
        ) {
            $m['currency'] = $this->currency($m['currency']);
            $m['amount'] = PriceHelper::parse($m['amount']);

            if (is_numeric($m['amount'])) {
                $m['amount'] = (float) $m['amount'];
            } else {
                $m['amount'] = null;
            }
            $result = ['amount' => $m['amount'], 'currency' => $m['currency']];
        }

        return $result;
    }

    private function currency($s)
    {
        if ($code = $this->re("#^\s*([A-Z]{3})\s*$#", $s)) {
            return $code;
        }
        $sym = [
            '€'   => 'EUR',
        ];

        foreach ($sym as $f => $r) {
            if ($s == $f) {
                return $r;
            }
        }

        return null;
    }
}
