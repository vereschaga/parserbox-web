<?php

namespace AwardWallet\Engine\signature\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Cruise extends \TAccountChecker
{
    public $mailFiles = "signature/it-490015982.eml, signature/it-779173307.eml, signature/it-782671064.eml, signature/it-784959863.eml, signature/it-786815498.eml, signature/it-786816274.eml, signature/it-789033078.eml, signature/it-791661763.eml";
    public $subjects = [
        'Confirmed - Your Cruise Booking:',
        'Your cruise has been confirmed and a deposit has been applied.',
        'Your cruise has been successfully cancelled.',
        'Your cruise has been confirmed and paid in full with the cruise line.',
    ];

    public $providerCode;
    public static $detectProvider = [
        'expediacruise' => [
            'from'     => '@expediacruises.com',
            'bodyText' => ['Expedia Cruises', '@expediacruises.com'],
        ],
        'signature' => [
            'from'     => '@signaturetravelnetwork.com',
            'bodyText' => ['Signature Travel Network', 'D&G Travel'],
        ],
    ];
    public $subject;

    public $lang = 'en';

    public $pdfNamePattern = ".*pdf";

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        $detectedFrom = false;

        if (isset($headers['from'])) {
            foreach (self::$detectProvider as $code => $detect) {
                if (!empty($detect['from']) && stripos($headers['from'], $detect['from']) !== false) {
                    $detectedFrom = true;
                    $this->providerCode = $code;

                    break;
                }
            }
        }

        if ($detectedFrom === false) {
            return false;
        }

        foreach ($this->subjects as $subject) {
            if (stripos($headers['subject'], $subject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (stripos($text, 'Supplier Reference:') !== false
                && stripos($text, 'Your Itinerary') !== false
                && stripos($text, 'Cruise Confirmation') !== false
                && stripos($text, 'Guest Information') !== false
            ) {
                return true;
            }
        }

        if (count($pdfs) === 0) {
            if ($this->http->XPath->query("//tr[{$this->starts('Cruise - ')}]/following-sibling::*[1][{$this->contains('Confirmation #:')}]/following-sibling::*[1][{$this->contains('Departure:')}]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]mail.signaturetravelnetwork.com$/', $from) > 0;
    }

    public function ParseCruise(Email $email, string $text)
    {
        // Travel Agency
        $email->obtainTravelAgency();
        $otaConf = $this->re("/Agency Confirmation\s*[#]\:\n+([A-Z\d]{6,})\n+/", $text);

        if (!empty($otaConf)) {
            $email->ota()
                ->confirmation($otaConf);
        }

        // Price
        if (preg_match("/Grand Total Prices are quoted in\s*(?<currency>[A-Z]{3})\s*\(.+\) +(?:[A-Z]{3})?\D{1,3}(?<total>\d[\d\.\,]*) *\D{1,5}\n/", $text, $m)) {
            $email->price()
                ->total(PriceHelper::parse($m['total'], $m['currency']))
                ->currency($m['currency']);
        } else {
            $email->price()
                ->total(null);
        }

        // Cruises

        $c = $email->add()->cruise();

        $confirmation = $this->re("/Supplier Reference: *([A-Z\d]{5,})\n/", $text);

        if (empty($confirmation)) {
            $confirmation = $this->re("/Cruise Confirmation\s*\n+\s*([A-Z\d]{5,})\s+/", $text);
        }

        if (empty($confirmation)) {
            $confirmation = $this->re("/Your Cruise Booking\:\s*([A-Z\d]{5,})\b/", $this->subject);
        }

        $c->general()
            ->confirmation($confirmation);

        $guestText = $this->re("/\n *\W* *(Guest Information\n[\s\S]+?)\n *\W* *(Insurance|Payment Schedule|$)/", $text);
        $guestSeg = preg_split("/ *\W* *Passenger\s*\d+\n/", $guestText);
        array_shift($guestSeg);
        $travellers = [];

        foreach ($guestSeg as $gs) {
            $traveller = '';

            if (preg_match("/(?:^|\n)\s*Name\:.+\n {0,15}(\S(?: ?\S)+)[ ]{10,}/u", $gs, $m)) {
                $traveller = preg_replace("/^(?:Mrs|Mr|Ms|Miss|Mstr|Dr)/", "", $m[1]);
            }
            // Account
            if (preg_match("/\n.{30,} {3,}Past Passenger:\n.{30,} {3,}(\d{5,})\n/", $gs, $m)) {
                $c->program()
                    ->account($m[1], false, $traveller, 'Past Passenger');
            } elseif (preg_match("/\n {0,15}Past Passenger:\n {0,15}(\d{5,})(?: {3,}|\n|\s*$)/", $gs, $m)) {
                $c->program()
                    ->account($m[1], false, $traveller, 'Past Passenger');
            }
            $travellers[] = $traveller;
        }
        $c->general()
            ->travellers(preg_replace("/^(?:Mrs|Mr|Ms|Miss|Mstr|Dr)/", "", $travellers), true);

        // Details
        if (preg_match("/\|\s*{$confirmation}\s*\|\s*(?<status>\w+)\n\s*(?<ship>.+)\n/", $text, $m)) {
            $c->general()
                ->status($m['status']);

            if (in_array($c->getStatus(), ['Cancelled', 'Canceled'])) {
                $c->general()
                    ->cancelled(true);
            }
            $c->details()
                ->ship($m['ship']);
        }

        $deck = $this->re("/ +Deck\:\s*(.+?)(?: {2,}|\n)/", $text);

        if (preg_match("/{$this->opt($this->t('Cabin:'))}\s+(\d+)\s+/", $deck, $m)) {
            $c->details()
                ->room($m[1]);
            $deck = $this->re("/^(.+){$this->opt($this->t('Cabin:'))}/", $deck);
        }

        if (stripos($deck, 'to be assigned') !== false) {
            $deck = null;
        }

        if (!empty($deck)) {
            $c->details()
                ->deck($deck);
        }

        $class = $this->re("/Category\: (.+\([A-Z\d]{1,2}\))/", $text);

        if (empty($class)) {
            $class = $this->re("/Category\:\n.{30,} {3,}(.+\([A-Z\d]{1,2}\))/", $text);
        }

        if (!empty($class)) {
            $c->details()
                ->roomClass($class);
        }

        $cruiseText = $this->re("/\n+( *\W* *Day\s*1\:.+)\n *\W* *Price Details/su", $text);

        $cruiseTable = $this->splitCols($cruiseText, [0, 68]);

        $cruiseOneColumn = $cruiseTable[0] . "\n" . $cruiseTable[1];

        $pointsArray = splitter("/\n *\W* *(Day \d+:)/", "\n" . $cruiseOneColumn . "\n");
        $points = [];

        foreach ($pointsArray as $point) {
            if (stripos($point, 'At Sea') !== false
                || stripos($point, 'Inside Passage') !== false
                || stripos($point, 'Cruising') !== false
            ) {
                continue;
            } else {
                $day = $this->re("/Day\s*(\d+)/", $point);

                if (!in_array($day, $points)) {
                    $points[$day] = $point;
                }
            }
        }

        ksort($points);

        $isCruise = false;

        foreach ($points as $i => $point) {
            $s = $c->addSegment();

            if (preg_match("/Day\s+\d+\:\s*(?<depName>.+)\n+\S*\s+\w*\s+(?<depDate>\w+\s+\d+\,\s*\d{4})\s*\|\s*Depart\s*(?<depTime>[\d\:]+\s*A?P?M)/u", $point, $m)) {
                $s->setName($m['depName']);
                $s->setAboard(strtotime($m['depDate'] . ', ' . $m['depTime']));
                $isCruise = true;
            } elseif (preg_match("/Day\s+\d+\:\s*(?<depName>.+)\n+\S*\s*\w*\s+(?<depDate>\w+\s+\d+\,\s*\d{4})\s*\|\s*Arrive\s*(?<depTime>[\d\:]+\s*A?P?M)/u", $point, $m)) {
                $s->setName($m['depName']);
                $s->setAshore(strtotime($m['depDate'] . ', ' . $m['depTime']));
            } elseif (preg_match("/Day\s+\d+\:\s*(?<name>.+)\n+\S*\s*\w*\s+(?<day>\w+\s+\d+\,\s*\d{4})\s*\|\s*(?<arrTime>[\d\:]+\s*A?P?M)\s*To\s*(?<depTime>[\d\:]+\s*A?P?M)/u", $point, $m)) {
                $s->setName($m['name']);
                $s->setAshore(strtotime($m['day'] . ', ' . $m['arrTime']));
                $s->setAboard(strtotime($m['day'] . ', ' . $m['depTime']));
            }

            if ($isCruise === false && preg_match("/Day\s+\d+\:\s*[\s\S]*\s*\|\s*Depart\s*/u", $point, $m)) {
                $isCruise = true;
            }

            if ($isCruise === false) {
                $c->removeSegment($s);
            }
        }

        if (empty($c->getSegments())
            && count($pointsArray) > 2
            && !preg_match("/\|\s*Depart/", $cruiseText)
            && !preg_match("/\|\s*Arrive/", $cruiseText)
            && !preg_match("/\d:\d{2}/", $cruiseText)
        ) {
            $shortFormatText = $this->re("/\n *Your Itinerary\s*\n[\s\S]+?\n(.*\d:\d{2}[\s\S]+?)\n.+ {5,}Destination:/", $text);
            $table = $this->splitCols($shortFormatText, $this->rowColsPos($this->inOneRow($shortFormatText)));

            if (count($table) === 4 && !preg_match("/Embark:/", $table[0])) {
                unset($table[0]);
                $table = array_values($table);
            }

            if (count($table) == 3
                && preg_match("/^\s*(?<time>.+)\n\s*(?<date>.+)\n\s*Embark:\s*(?<name>[\s\S]+)/", $table[0], $dm)
                && preg_match("/^\s*(?<time>.+)\n\s*(?<date>.+)\n\s*Disembark:\s*(?<name>[\s\S]+)/", $table[2], $am)
            ) {
                $s = $c->addSegment();
                $s->setName(preg_replace('/\s+/', ' ', trim($dm['name'])));
                $s->setAboard(strtotime($dm['date'] . ', ' . $dm['time']));

                $s = $c->addSegment();
                $s->setName(preg_replace('/\s+/', ' ', trim($am['name'])));
                $s->setAshore(strtotime($am['date'] . ', ' . $am['time']));
            }
        }
    }

    public function ParseCruiseHtml(Email $email)
    {
        // Travel Agency
        $email->obtainTravelAgency();
        $otaConf = $this->http->FindSingleNode("//tr[count(*) = 2][*[1][{$this->eq($this->t('Agency Confirmation #:'))}]]/*[2]",
            null, true, "/^\s*([A-Z\d]{5,})\s*$/");

        $email->ota()
            ->confirmation($otaConf);

        // Price
        $total = $this->http->FindSingleNode("//tr[count(*) = 2][*[1][{$this->eq($this->t('Grand Total:'))}]]/*[2]");

        if (preg_match("/^\s*(?<code>[A-Z]{3}\b)?\s*(?<currency>\D{1,3}?)\s*(?<total>\d[\d\.\,]*)\s*$/", $total, $m)) {
            $currency = $m['code'] ?? $this->currency($m['currency']);
            $email->price()
                ->total(PriceHelper::parse($m['total'], $currency))
                ->currency($currency);
        } else {
            $email->price()
                ->total(null);
        }

        // Cruises

        $c = $email->add()->cruise();

        $confirmation = $this->http->FindSingleNode("//tr[count(*) = 2][*[1][{$this->eq($this->t('Departure:'))}]]/preceding-sibling::tr[1][count(*) = 2][*[1][{$this->contains($this->t('Confirmation #:'))}]]/*[2]",
            null, true, "/^\s*([A-Z\d]{5,})\s*$/");

        $c->general()
            ->confirmation($confirmation);

        $traveller = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Agency Confirmation #:'))}]/preceding::text()[{$this->starts($this->t('Dear '))}]",
            null, true, "/^\s*{$this->opt($this->t('Dear '))}\s*(\D+?)\s*,\s*$/");
        $c->general()
            ->traveller(preg_replace("/^(?:Mrs|Mr|Ms|Miss|Mstr|Dr)/", "", $traveller), true);

        $c->general()
            ->status($this->http->FindSingleNode("//tr[count(*) = 2][*[1][{$this->eq($this->t('Booking Status:'))}]]/*[2]"));

        if (in_array($c->getStatus(), ['Cancelled', 'Canceled'])) {
            $c->general()
                ->cancelled(true);
        }

        // Details
        $c->details()
            ->ship($this->http->FindSingleNode("//tr[count(*) = 2][*[1][{$this->eq($this->t('Departure:'))}]]/preceding-sibling::tr[2][{$this->starts($this->t('Cruise - '))}]",
                null, true, "/^\s*{$this->opt($this->t('Cruise - '))}.+? - (.+)/"));

        $depart = $this->http->FindSingleNode("//tr[count(*) = 2][*[1][{$this->eq($this->t('Departure:'))}]]/*[2]");

        if (preg_match("/^\s*(?<name>.+) - (?<date>.+)/u", $depart, $m)) {
            $s = $c->addSegment();
            $s->setName($m['name']);
            $s->setAboard(strtotime($m['date']));
        }

        $arrival = $this->http->FindSingleNode("//tr[count(*) = 2][*[1][{$this->eq($this->t('Arrival:'))}]]/*[2]");

        if (preg_match("/^\s*(?<name>.+) - (?<date>.+)/u", $arrival, $m)) {
            $s = $c->addSegment();
            $s->setName($m['name']);
            $s->setAshore(strtotime($m['date']));
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->subject = $parser->getSubject();

        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (stripos($text, 'Your Itinerary') !== false
                && stripos($text, 'Cruise Confirmation') !== false
            ) {
                $this->ParseCruise($email, $text);
            }
        }

        if (count($pdfs) === 0) {
            $this->ParseCruiseHtml($email);
        }

        foreach (self::$detectProvider as $code => $detect) {
            if (!empty($detect['from']) && stripos($parser->getCleanFrom(), $detect['from']) !== false) {
                $this->providerCode = $code;

                break;
            }

            if (!empty($detect['bodyText']) && $this->http->XPath->query("//node()[{$this->contains($detect['bodyText'])}]")->length > 0) {
                $this->providerCode = $code;

                break;
            }
        }

        if (!empty($this->providerCode)) {
            $email->setProviderCode($this->providerCode);
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

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

    public static function getEmailProviders()
    {
        return array_keys(self::$detectProvider);
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "starts-with(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function splitCols($text, $pos = false)
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->rowColsPos($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k => $p) {
                $cols[$k][] = trim(mb_substr($row, $p, null, 'UTF-8'));
                $row = mb_substr($row, 0, $p, 'UTF-8');
            }
        }
        ksort($cols);

        foreach ($cols as &$col) {
            $col = implode("\n", $col);
        }

        return $cols;
    }

    private function rowColsPos($row)
    {
        $head = array_filter(array_map('trim', explode("|", preg_replace("#\s{2,}#", "|", $row))));
        $pos = [];
        $lastpos = 0;

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }

    private function inOneRow($text)
    {
        $textRows = array_filter(explode("\n", $text));

        if (empty($textRows)) {
            return '';
        }
        $length = [];

        foreach ($textRows as $key => $row) {
            $length[] = mb_strlen($row);
        }
        $length = max($length);
        $oneRow = '';

        for ($l = 0; $l < $length; $l++) {
            $notspace = false;

            foreach ($textRows as $key => $row) {
                $sym = mb_substr($row, $l, 1);

                if ($sym !== false && trim($sym) !== '') {
                    $notspace = true;
                    $oneRow[$l] = 'a';
                }
            }

            if ($notspace == false) {
                $oneRow[$l] = ' ';
            }
        }

        return $oneRow;
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
    }

    private function currency($s)
    {
        $sym = [
            '€'=> 'EUR',
            '$'=> 'USD',
        ];

        foreach ($sym as $f => $r) {
            if ($s === $f) {
                return $r;
            }
        }

        return $s;
    }
}
