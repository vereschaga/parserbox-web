<?php

namespace AwardWallet\Engine\easyjet\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class HolidayPdf extends \TAccountChecker
{
    public $mailFiles = "easyjet/it-137086637.eml, easyjet/it-141576457.eml, easyjet/it-742792067.eml, easyjet/it-746832370.eml, easyjet/it-749223124.eml";

    public $lang = '';

    public $detectHtmlFullItinerary = [
        // detect only for FULL Itinerary
        'en' => [
            'Check the details of your holiday below',
        ],
    ];
    public static $dictionary = [
        'en' => [
            'Holiday reference'     => ['Holiday reference'],
            'Guest details'         => ['Guest details', 'Guest DETAILS', 'GUEST DETAILS', 'Your details'],
            'Important information' => ['Important information', 'Important Information'],
            'paymentStart'          => ['Payment Details', 'Payment details'],
            'paymentEnd'            => ['Guest details', 'Guest DETAILS', 'Your details'],
            'guestsStart'           => ['Guest details', 'Guest DETAILS', 'Your details'],
            'guestsEnd'             => ['Your holiday summary', 'Your Holiday Summary', 'Your holiday'],
            'holidaySummaryStart'   => ['Your holiday summary', 'Your Holiday Summary', 'Your holiday'],
            'holidaySummaryEnd'     => ['Flights', 'Your flights'],
            'flightsStart'          => ['Flights', 'Your flights'],
            'flightsEnd'            => ['Included Luggage', 'Your bags'],

            // Html
            'Total price of your holiday' => 'Total price of your holiday',
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@easyjet.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return strpos($headers['subject'], 'easyJet holidays booking') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $detectProvider = $this->detectEmailFromProvider($parser->getHeader('from')) === true;

        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (!$textPdf) {
                continue;
            }

            if ($detectProvider === false && stripos($textPdf, 'easyJet holidays') === false) {
                continue;
            }

            if ($this->assignLang($textPdf)) {
                return true;
            }
        }

        if ($this->http->XPath->query("//a/@href[{$this->contains('.easyjet.com')}]")->length < 3) {
            return false;
        }

        foreach (self::$dictionary as $dict) {
            if (!empty($dict['Holiday reference']) && !empty($dict['Total price of your holiday'])
                && $this->http->XPath->query("//text()[{$this->contains($dict['Holiday reference'])}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($dict['Total price of your holiday'])}]")->length > 0
            ) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $type = '';

        foreach ($this->detectHtmlFullItinerary as $lang => $detect) {
            if ($this->http->XPath->query("//node()[{$this->contains($detect)}]")->length > 0) {
                $type = 'Html';
                $this->parseHtml($email);

                break;
            }
        }

        if (empty($email->getItineraries())) {
            $pdfs = $parser->searchAttachmentByName('.*pdf');

            foreach ($pdfs as $pdf) {
                $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

                if (!$textPdf) {
                    continue;
                }

                if ($this->assignLang($textPdf)) {
                    $type = 'Pdf';
                    $this->parsePdf($email, $textPdf);
                }
            }
        }

        if (empty($email->getItineraries()) && count($pdfs) === 0) {
            $type = 'Html';
            $this->parseHtml($email);
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

    private function parseHtml(Email $email): void
    {
        $email->obtainTravelAgency();
        $email->ota()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Holiday reference'))}]/following::text()[normalize-space()][1]",
                null, true, "/^\s*(\d{5,})\s*$/"));

        // Price
        $total = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Total price of your holiday'))}]/following::text()[normalize-space()][1]",
            null, true, "/^\s*(.*\d.*)\s*$/");

        if (preg_match("#^\s*(?<currency>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $total, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d\s]{1,5})\s*$#", $total, $m)
        ) {
            $email->price()
                ->total(PriceHelper::parse($m['amount']))
                ->currency($m['currency']);
        }

        $travellers = $this->http->FindNodes("//text()[{$this->eq($this->t('Your details'))}]/ancestor::*[not({$this->eq($this->t('Your details'))})][1]/descendant::text()[normalize-space()][position() > 1]");

        // Hotels
        $hXpath = "//text()[{$this->contains($this->t('guest(s) staying in'))}]/ancestor::*[following-sibling::*][count(.//img) = 1][1]";
        $hNodes = $this->http->XPath->query($hXpath);

        foreach ($hNodes as $hRoot) {
            $h = $email->add()->hotel();

            $h->general()
                ->noConfirmation();

            if (!empty($travellers)) {
                $h->general()
                    ->travellers($travellers, true);
            }

            // Hotel
            $h->hotel()
                ->name($this->http->FindSingleNode("preceding-sibling::*[normalize-space()][4]", $hRoot))
                ->address($this->http->FindSingleNode("preceding-sibling::*[normalize-space()][2]", $hRoot))
            ;

            // Booked
            $info = $this->http->FindSingleNode("preceding-sibling::*[normalize-space()][1]", $hRoot);

            if (preg_match("/^\s*(\d+) [[:alpha:]]+, [[:alpha:]]+ (.+)/", $info, $m)) {
                $date = strtotime($m[2]);

                if (!empty($date)) {
                    $h->booked()
                        ->checkIn($date)
                        ->checkOut(strtotime('+' . $m[1] . ' days', $date));
                }
            }

            $info = $this->http->FindSingleNode("*", $hRoot);

            if (preg_match("/^\s*(\d+) \D+ (\d+) \D+$/", $info, $m)) {
                $h->booked()
                    ->guests($m[1])
                    ->rooms($m[2])
                ;
            } else {
                $h->booked()
                    ->guests(null);
            }

            // Rooms
            $h->addRoom()
                ->setType($this->http->FindSingleNode("following-sibling::*[normalize-space()][1]", $hRoot));
        }

        // Flight
        $fXpath = "//img[contains(@src, 'icons/flight')]/ancestor::tr[1][not(following::text()[{$this->eq($this->t('New itinerary'))}])]";
        $fNodes = $this->http->XPath->query($fXpath);

        if ($fNodes->length > 0) {
            $f = $email->add()->flight();

            $conf = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Flight reference'))}]/following::text()[normalize-space()][1]",
                null, true, "/^\s*([A-Z\d]{5,})\s*$/");

            if (empty($conf) && $this->http->XPath->query("//node()[{$this->contains($this->t('Flight reference'))}]")->length === 0) {
                $f->general()
                    ->noConfirmation();
            } else {
                $f->general()
                    ->confirmation($conf);
            }

            if (!empty($travellers)) {
                $f->general()
                    ->travellers($travellers, true);
            }
        }

        foreach ($fNodes as $fRoot) {
            $s = $f->addSegment();

            $text = implode("\n", $this->http->FindNodes(".//text()[normalize-space()]", $fRoot)) . "\n";
            $re = "/^\s*(?:.+\n)?\w+: (?<al>[A-Z]{3}|[A-Z][A-Z\d]|[A-Z\d][A-Z])(?<fn>\d+)\n\s*(?<date>.+\d{4})\n(?<dName>.+)\s*\(\s*(?<dCode>[A-Z]{3})\s*\)\s+(?<dTime>\d{1,2}:\d{2}.*)\n(?<aName>.+)\s*\(\s*(?<aCode>[A-Z]{3})\s*\)\s+(?<aTime>\d{1,2}:\d{2}.*)\n/";

            if (preg_match($re, $text, $m)) {
                // Airline
                if (in_array($m['al'], ['EZY', 'EJU', 'EZS'])) {
                    // ‘EZY’ are operated by easyJet UK Limited, ‘EJU’ are operated by easyJet Europe Airline GmbH and ‘EZS’ are operated by easyJet Switzerland SA
                    $m['al'] = 'U2';
                }
                $s->airline()
                    ->name($m['al'])
                    ->number($m['fn']);

                // Departure
                $s->departure()
                    ->name($m['dName'])
                    ->code($m['dCode'])
                    ->date(strtotime($m['date'] . ', ' . $m['dTime']))
                ;
                // Arrival
                $s->arrival()
                    ->name($m['aName'])
                    ->code($m['aCode'])
                    ->date(strtotime($m['date'] . ', ' . $m['aTime']))
                ;
            }

            if (preg_match("/\n{$this->opt($this->t('Your seats'))}\n(((?:[[:alpha:]]+(?: [[:alpha:]]+)?\n)?(?:\d{1,3}[A-Z]\n)+)+)/", $text, $m)
            ) {
                /*  Extra Legroom
                    1C
                    Up Front
                    2C
                    2B
                    2A */
                preg_match_all("/^\d{1,3}[A-Z]$/m", $m[1], $seats);
                $s->extra()
                    ->seats($seats[0]);
            }
        }
    }

    private function parsePdf(Email $email, $text): void
    {
        $patterns = [
            'time'          => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?',
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]',
        ];

        // remove garbage
        $text = preg_replace("/\n[ ]*{$this->opt($this->t('Page'))}[ ]+\d{1,3}\b.*/", '', $text);

        if (preg_match("/^([\s\S]+?)\n+[ ]*{$this->opt($this->t('Important information'))}\n/", $text, $m)) {
            $text = $m[1];
        }

        $type = '';

        if (preg_match("/(?:^|\n)[ ]*({$this->opt($this->t('Holiday reference'))})[ ]*[:]+[ ]*([-A-Z\d]{5,})(?:[ ]{2}|\n)/", $text, $m)) {
            // it-137086637.eml
            $type = '1';
            $email->ota()->confirmation($m[2], $m[1]);
        } elseif (preg_match("/(?:^|\n)[ ]*({$this->opt($this->t('Holiday reference'))})[: ]*(?:[ ]{2}.+)?\n+[ ]*([-A-Z\d]{5,})(?:[ ]{2}|\n)/", $text, $m)) {
            // it-141576457.eml
            $type = '2';
            $email->ota()->confirmation($m[2], $m[1]);
        }
        $email->setType('HolidayPdf' . $type . ucfirst($this->lang));

        $h = $email->add()->hotel();
        $f = $email->add()->flight();

        if (preg_match("/[ ]{2}({$this->opt($this->t('Flight reference'))})[ ]*[:]+[ ]*([-A-Z\d]{5,})\n/", $text, $m)
            || preg_match("/[ ]{2}({$this->opt($this->t('Flight reference'))})[: ]*\n+[ ]*[-A-Z\d]{5,}[ ]{2,}([-A-Z\d]{5,})\n/", $text, $m)
        ) {
            $f->general()->confirmation($m[2], $m[1]);
        } elseif (preg_match("/Login to your account to view your flight references and find out how to check-in/i", $text)
            || !preg_match("/{$this->opt($this->t('Flight reference'))}/", $text)
        ) {
            $f->general()->noConfirmation();
        }

        if ($type === '2') {
            $tablePos = [0];

            if (preg_match("/^([ ]*{$this->opt($this->t('Cost of your holiday'))}[ ]{2,}){$this->opt($this->t('Flights'))}$/m", $text, $matches)) {
                $tablePos[] = mb_strlen($matches[1]);
            }
            $table = $this->splitCols($text, $tablePos);

            if (count($table) === 2) {
                $text = $table[0] . "\n\n" . $table[1];
            }
        }

        $paymentText = $this->re("/\n[ ]*{$this->opt($this->t('paymentStart'))}\n+([\s\S]+?)\n+[ ]*{$this->opt($this->t('paymentEnd'))}\n/", $text);
        $totalPrice = $this->re("/^[ ]*{$this->opt($this->t('Total price'))}[: ]+(.*\d.*)$/m", $paymentText);

        if (preg_match('/^\d[,.\'\d ]*$/', $totalPrice)) {
            $email->price()->total(PriceHelper::parse($totalPrice));
        } elseif (preg_match("#^\s*(?<currency>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $totalPrice, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d\s]{1,5})\s*$#", $totalPrice, $m)
        ) {
            $email->price()
                ->total(PriceHelper::parse($m['amount']))
                ->currency($m['currency']);
        } else {
            $email->price()
                ->total(null);
        }

        $guestNames = $travellers = [];
        $guestDetails = $this->re("/\n[ ]*{$this->opt($this->t('guestsStart'))}\n+([\s\S]+?)\n+[ ]*{$this->opt($this->t('guestsEnd'))}\n/", $text);

        if ($type === '1') {
            $guestNames = preg_split("/\s*[,]+\s*/", preg_replace('/\s+/', ' ', $guestDetails));
        } else {
            $guestNames = preg_split("/[ ]*\n+[ ]*/", $guestDetails);
        }

        foreach ($guestNames as $gName) {
            if (preg_match("/^{$patterns['travellerName']}$/u", $gName)) {
                $travellers[] = $gName;
            } else {
                $travellers = [];

                break;
            }
        }

        if (count($travellers) > 0) {
            $h->general()->travellers($travellers, true);
            $f->general()->travellers($travellers, true);
        }

        $holidaySummary = $this->re("/\n[ ]*{$this->opt($this->t('holidaySummaryStart'))}\n+([\s\S]+?)\n+[ ]*{$this->opt($this->t('holidaySummaryEnd'))}\n/", $text);

        if ($type === '1') {
            $tablePos = [0];

            if (preg_match("/^([ ]*\S.*?[ ]{2})\S.*$/m", $holidaySummary, $matches)) {
                $tablePos[] = mb_strlen($matches[1]);
            }
            $table = $this->splitCols($holidaySummary, $tablePos);

            if (count($table) === 2) {
                $holidaySummary = $table[0] . "\n\n" . $table[1];
            }
        }

        if (preg_match("/^\s*(?<firstLine>.{2,})\n+[ ]*(?<address>[\s\S]{3,}?)\n+.*\d[ ]*(?:{$this->opt($this->t('night'))}|{$this->opt($this->t('guest'))})/", $holidaySummary, $matches)) {
            $hotelName = $matches['firstLine'];
            $address = preg_replace('/[ ]*\n+[ ]*/', ', ', $matches['address']);

            if ($type === '1' && preg_match("/^(.{2,}?)\s*,\s*(.{3,})$/", $matches['firstLine'], $m)) {
                $hotelName = $m[1];
                $address = $m[2] . ', ' . $address;
            }
            $h->hotel()->name($hotelName)->address($address);
        }

        if (preg_match("/^[ ]*(?<nights>\d{1,3})\s*nights?, from\s+(?<date>.{6,})$/m", $holidaySummary, $m)) {
            $dateCheckIn = strtotime($m['date']);

            if ($dateCheckIn) {
                $h->booked()->checkIn($dateCheckIn)->checkOut(strtotime('+' . $m['nights'] . ' days', $dateCheckIn));
            }
        }

        if (preg_match("/^[ ]*(?<guests>\d{1,3})[ ]*guest\(s\) staying in (?<rooms>\d{1,3}) room\(s\)$/m", $holidaySummary, $m)) {
            $h->booked()->guests($m['guests'])->rooms($m['rooms']);
        }

        $flights = $this->re("/\n[ ]*{$this->opt($this->t('flightsStart'))}\n+([\s\S]+?)\n+[ ]*{$this->opt($this->t('flightsEnd'))}\n/", $text);

        if ($type === '1') {
            $tablePos = [0];

            if (preg_match("/^(.+[ ]{2}){$this->opt($this->t('Return'))}$/m", $flights, $matches)) {
                $tablePos[] = mb_strlen($matches[1]);
            }
            $table = $this->splitCols($flights, $tablePos);

            if (count($table) === 2) {
                $flights = $table[0] . "\n\n" . $table[1];
            }
        }

        /*
            Heraklion, Nikos Kazan
            Airport (HER)
            20:55
        */
        $patterns['airport'] = "/^\s*(?<name>[\s\S]{2,}?)\s*\(\s*(?<code>[A-Z]{3})\s*\)\s+(?<time>{$patterns['time']})/";

        $segments = $this->splitText($flights, "/(.{2,}\([ ]*(?:[A-Z]{3}|[A-Z][A-Z\d]|[A-Z\d][A-Z])\d+[ ]*\))/", true);

        foreach ($segments as $sText) {
            $s = $f->addSegment();

            $re = "/^\s*.{2,}\([ ]*(?<name>[A-Z]{3}|[A-Z][A-Z\d]|[A-Z\d][A-Z])(?<number>\d+)[ ]*\)(?:[ ]*{$this->opt($this->t('Operated by'))}[ ]+(?<operator>.{2,}))?\s+(?<date>\w+\W{1,3}\w+\W{1,3}\w+\W{1,3}\d{4})\n+(?<airports>[\s\S]+)/";

            if (preg_match($re, $sText, $m)) {
                /*
                    easyJet (EZY6951) Operated by easyJet UK Limited
                    Tue 24th May 2022
                    . . .
                */
                if (in_array($m['name'], ['EZY', 'EJU', 'EZS'])) {
                    // ‘EZY’ are operated by easyJet UK Limited, ‘EJU’ are operated by easyJet Europe Airline GmbH and ‘EZS’ are operated by easyJet Switzerland SA
                    $m['name'] = 'U2';
                }
                $s->airline()->name($m['name'])->number($m['number']);

                if (!empty($m['operator'])) {
                    $s->airline()->operator($m['operator']);
                }
                $dateValue = preg_replace('/\s+/', ' ', $m['date']);
                $airportsText = $m['airports'];
            } else {
                $dateValue = false;
                $airportsText = '';
            }

            $tablePos = [0];

            if (preg_match("/^(.+ ){$patterns['time']}$/m", $airportsText, $matches)) {
                $tablePos[] = mb_strlen($matches[1]);
            }
            $table = $this->splitCols($airportsText, $tablePos);

            if (count($table) !== 2) {
                continue;
            }

            $date = strtotime($dateValue);

            if (preg_match($patterns['airport'], $table[0], $m)) {
                $s->departure()->name(preg_replace('/\s+/', ' ', $m['name']))
                    ->code($m['code'])
                    ->date(!empty($date) ? strtotime($m['time'], $date) : null);
            }

            if (preg_match($patterns['airport'], $table[1], $m)) {
                $s->arrival()->name(preg_replace('/\s+/', ' ', $m['name']))
                    ->code($m['code'])
                    ->date(!empty($date) ? strtotime($m['time'], $date) : null);
            }

            if (preg_match("/\n *{$this->opt($this->t('Your seats'))}((\n+\s*[[:alpha:] ]+: *(\d{1,3}[A-Z](?: +\d{1,3}[A-Z])*))+)(?:\n|$)/", $sText, $m)) {
                $s->extra()
                    ->seats(preg_split("/ +/", trim(preg_replace(['/^\s*[[:alpha:] ]+:/m', '/\s+/'], ['', ' '], $m[1]))));
            }
        }

        $h->general()->noConfirmation();
    }

    private function assignLang($text): bool
    {
        if (empty($text) || !isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['Holiday reference']) || empty($phrases['Guest details'])) {
                continue;
            }

            if ($this->strposArray($text, $phrases['Holiday reference']) !== false
                && $this->strposArray($text, $phrases['Guest details']) !== false
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function strposArray($text, $phrases)
    {
        if (empty($text)) {
            return false;
        }

        foreach ((array) $phrases as $phrase) {
            if (!is_string($phrase)) {
                continue;
            }
            $result = strpos($text, $phrase);

            if ($result !== false) {
                return $result;
            }
        }

        return false;
    }

    private function t(string $phrase)
    {
        if (!isset(self::$dictionary, $this->lang) || empty(self::$dictionary[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$this->lang][$phrase];
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }

    private function re($re, $str, $c = 1): ?string
    {
        preg_match($re, $str, $m);

        if (!empty($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function rowColsPos(?string $row): array
    {
        $pos = [];
        $head = preg_split('/\s{2,}/', $row, -1, PREG_SPLIT_NO_EMPTY);
        $lastpos = 0;

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }

    private function splitCols(?string $text, ?array $pos = null): array
    {
        $cols = [];

        if ($text === null) {
            return $cols;
        }
        $rows = explode("\n", $text);

        if ($pos === null || count($pos) === 0) {
            $pos = $this->rowColsPos($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k => $p) {
                $cols[$k][] = rtrim(mb_substr($row, $p, null, 'UTF-8'));
                $row = mb_substr($row, 0, $p, 'UTF-8');
            }
        }
        ksort($cols);

        foreach ($cols as &$col) {
            $col = implode("\n", $col);
        }

        return $cols;
    }

    private function splitText(?string $textSource, string $pattern, bool $saveDelimiter = false): array
    {
        $result = [];

        if ($saveDelimiter) {
            $textFragments = preg_split($pattern, $textSource, -1, PREG_SPLIT_DELIM_CAPTURE);
            array_shift($textFragments);

            for ($i = 0; $i < count($textFragments) - 1; $i += 2) {
                $result[] = $textFragments[$i] . $textFragments[$i + 1];
            }
        } else {
            $result = preg_split($pattern, $textSource);
            array_shift($result);
        }

        return $result;
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'starts-with(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }
}
