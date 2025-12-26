<?php

namespace AwardWallet\Engine\skyexp\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class YourReservation extends \TAccountChecker
{
    public $mailFiles = "skyexp/it-112482037.eml, skyexp/it-142303390.eml, skyexp/it-447512142.eml, skyexp/it-673314871.eml, skyexp/it-714775512-el.eml";

    public $lang = '';
    public $pdfNamePattern = ".*pdf";

    public static $dictionary = [
        'el' => [
            // PDF
            'flightInfo'          => 'Πληροφορίες Πτήσης',
            'confNumber'          => 'Αριθμός Κράτησης:',
            'PASSENGER:'          => 'ΕΠΙΒΑΤΗΣ:',
            'Passengers'          => 'Επιβάτες',
            'Baggage information' => 'Πληροφορίες αποσκευών',
            'Travel time:'        => 'Διάρκεια πτήσης:',
            'Flight number:'      => 'Αριθμός πτήσης:',
            'SEAT'                => 'ΘΕΣΗ',
            'TOTAL'               => 'ΣΎΝΟΛΟ',
            'FARE'                => 'ΝΑΥΛΟΣ',
            'feeNames'            => ['ΥΠΗΡΕΣΙΕΣ', 'ΚΟΣΤΟΣ', 'ΦΟΡΟΙ'],

            // HTML
            'TYPE:'     => 'ΗΛΙΚΙΑ:',
            'TOTAL:'    => 'ΣΎΝΟΛΟ:',
            'SERVICES:' => 'ΥΠΗΡΕΣΙΕΣ:',
            'FEES:'     => 'ΚΟΣΤΟΣ:',
            'TAXES:'    => 'ΦΟΡΟΙ:',
            'FARE:'     => 'ΝΑΥΛΟΣ:',
            'SEAT:'     => 'ΘΕΣΗ:',
            'CLASS:'    => 'ΚΑΤΗΓΟΡΙΑ:',
        ],
        'en' => [
            // PDF
            'flightInfo' => 'Flight information',
            'confNumber' => 'Confirmation Number:',
            // 'PASSENGER:' => '',
            // 'Passengers' => '',
            // 'Baggage information' => '',
            // 'Travel time:' => '',
            // 'Flight number:' => '',
            // 'SEAT' => '',
            // 'TOTAL' => '',
            // 'FARE' => '',
            'feeNames' => ['SERVICES', 'FEES', 'TAXES'],

            // HTML
            // 'TYPE:' => '',
            // 'TOTAL:' => '',
            // 'SERVICES:' => '',
            // 'FEES:' => '',
            // 'TAXES:' => '',
            // 'FARE:' => '',
            // 'SEAT:' => '',
            // 'CLASS:' => '',
        ],
    ];

    private $patterns = [
        'time'          => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.
        'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
    ];

    public function detectEmailByHeaders(array $headers)
    {
        return preg_match('/Your Sky Express Reservation\s*:\s*[A-Z\d]{5}/i', $headers['subject']) > 0;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href,'.skyexpress.gr/') or contains(@href,'www.skyexpress.gr')] | //text()[contains(normalize-space(),'Sky Express') or contains(.,'skyexpress.gr')]")->length > 0
            && $this->assignLang()
        ) {
            return true;
        }

        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (empty($text)) {
                continue;
            }

            if (stripos($text, 'skyexpress.com') !== false && $this->assignLangPdf($text)) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]skyexpress\.gr$/', $from) > 0;
    }

    public function ParseFlightHtml(Email $email): void
    {
        $this->logger->debug(__FUNCTION__);
        $f = $email->add()->flight();

        $confirmation = $this->http->FindSingleNode("//text()[{$this->starts($this->t('confNumber'))}]/ancestor::tr[1]");

        if (preg_match("/({$this->opt($this->t('confNumber'))})[:\s]*([A-Z\d]{5,})$/", $confirmation, $m)) {
            $f->general()->confirmation($m[2], rtrim($m[1], ': '));
        }

        $travellers = array_filter($this->http->FindNodes("//*[ {$this->starts($this->t('PASSENGER:'))} and following-sibling::*[{$this->starts($this->t('TYPE:'))}] ]/descendant::*[{$this->eq($this->t('PASSENGER:'))}]/following-sibling::*[normalize-space()][1]", null, "/^{$this->patterns['travellerName']}$/u"));
        $f->general()->travellers(array_unique($travellers), true);

        $totalPrice = $this->http->FindSingleNode("//text()[{$this->starts($this->t('TOTAL:'))}]/ancestor::tr[1]/descendant::td[2]", null, true, '/^.*\d.*$/');

        if (preg_match('/^(?<amount>\d[,.\'\d ]*)[ ]*(?<currency>[^\d)(]+?)$/', $totalPrice, $matches)) {
            // 1,139.80 EUR
            $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
            $f->price()->currency($matches['currency'])->total(PriceHelper::parse($matches['amount'], $currencyCode));

            $tax = $this->http->FindSingleNode("//text()[{$this->starts($this->t('TAXES:'))}]/ancestor::tr[1]/descendant::td[2]", null, true, '/^(\d[,.\'\d ]*)[ ]*(?:' . preg_quote($matches['currency'], '/') . ')?/');

            if ($tax !== null) {
                $f->price()->tax(PriceHelper::parse($tax, $currencyCode));
            }

            $services = $this->http->FindSingleNode("//text()[{$this->starts($this->t('SERVICES:'))}]/ancestor::tr[1]/descendant::td[2]", null, true, '/^(\d[,.\'\d ]*)[ ]*(?:' . preg_quote($matches['currency'], '/') . ')?/');

            if ($services !== null) {
                $f->price()->fee('SERVICES', PriceHelper::parse($services, $currencyCode));
            }

            $fee = $this->http->FindSingleNode("//text()[{$this->starts($this->t('FEES:'))}]/ancestor::tr[1]/descendant::td[2]", null, true, '/^(\d[,.\'\d ]*)[ ]*(?:' . preg_quote($matches['currency'], '/') . ')?/');

            if ($fee !== null) {
                $f->price()->fee('FEES', PriceHelper::parse($fee, $currencyCode));
            }

            $cost = $this->http->FindSingleNode("//text()[{$this->starts($this->t('FARE:'))}]/ancestor::tr[1]/descendant::td[2]", null, true, '/^(\d[,.\'\d ]*)[ ]*(?:' . preg_quote($matches['currency'], '/') . ')?/');

            if ($cost !== null) {
                $f->price()->cost(PriceHelper::parse($cost, $currencyCode));
            }
        }

        $nodes = $this->http->XPath->query("//text()[{$this->eq($this->t('Flight number:'))}]");

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $segmnetTextP1 = implode(" ", $this->http->FindNodes("./ancestor::td[1]/preceding::img[1]/ancestor::table[2][count(.//img) = 2]", $root));
            $segmnetTextP2 = $this->http->FindSingleNode("./ancestor::td[1]", $root);

            if (preg_match("/^[-[:alpha:]]+\s*(?<depDate>\d{1,2}\s*\/\s*\d{1,2}\s*\/\s*\d{4})\s*(?<depTime>{$this->patterns['time']})\s*(?<depCode>[A-Z]{3})\s*(?<arrTime>{$this->patterns['time']})\s*(?<arrCode>[A-Z]{3})\s*{$this->opt($this->t('Travel time:'))}\s*(?<duration>\d.+)/u", $segmnetTextP1, $m)
                && preg_match("/^{$this->opt($this->t('Flight number:'))}\s*(?<airName>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<airNumber>\d+)$/", $segmnetTextP2, $match)
            ) {
                $terminal = $this->http->FindSingleNode("preceding::text()[normalize-space()][1]", $root, true, "/^TERMINAL\s+([A-Z\d][A-Z\d\s]*)$/i");
                $s->departure()->terminal($terminal, false, true);

                $s->airline()
                    ->name($match['airName'])
                    ->number($match['airNumber']);

                $s->departure()
                    ->name($this->http->FindSingleNode("./preceding::text()[contains(normalize-space(), '(')][1]", $root, true, "/^(.+)\s*\([A-Z]{3}\)\s*\-/"))
                    ->code($m['depCode'])
                    ->date(strtotime(str_replace([' / ', '/'], '.', $m['depDate']) . ', ' . $m['depTime']));

                $s->arrival()
                    ->name($this->http->FindSingleNode("./preceding::text()[contains(normalize-space(), '(')][1]", $root, true, "/\-\s*(.+)\s*\(/"))
                    ->code($m['arrCode'])
                    ->date(strtotime(str_replace([' / ', '/'], '.', $m['depDate']) . ', ' . $m['arrTime']));

                $s->extra()
                    ->duration($m['duration']);

                if (!empty($s->getDepName()) && !empty($s->getDepCode())) {
                    $xpathExtra = "//tr[not(.//tr) and ({$this->starts($s->getDepName())} or {$this->starts($s->getDepCode())})]/ancestor::table[1]/following::table[1]";

                    $seats = array_filter($this->http->FindNodes($xpathExtra . "/descendant::text()[{$this->starts($this->t('SEAT:'))}]/ancestor::td[1]", null, "/{$this->opt($this->t('SEAT:'))}\s*(\d+[A-Z])\s*$/"));

                    if (count($seats) > 0) {
                        $s->extra()->seats($seats);
                    }

                    $cabin = array_values(array_filter($this->http->FindNodes($xpathExtra . "/descendant::text()[{$this->starts($this->t('CLASS:'))}]/ancestor::td[1]", null, "/:\s*(\D+)/")));

                    if (count(array_unique($cabin)) === 1) {
                        $s->extra()->cabin($cabin[0]);
                    }
                }
            }
        }
    }

    public function ParsePDF(Email $email, string $text): void
    {
        $this->logger->debug(__FUNCTION__);
        $f = $email->add()->flight();

        if (preg_match("/(?:^\s*|[ ]{2})({$this->opt($this->t('confNumber'))})[:\s]*([A-Z\d]{5,7})$/m", $text, $m)) {
            $f->general()->confirmation($m[2], rtrim($m[1], ': '));
        }

        if (preg_match_all("/^\s*{$this->opt($this->t('PASSENGER:'))}.*\n[ ]*({$this->patterns['travellerName']})[ ]+(?:ADULT|CHILD|KID)(?: |$)/mu", $text, $m)) {
            $travellers = preg_replace("/^(?:MR|MRS|MSTR|MISS)\s+/i", '', $m[1]);
            $f->general()
                ->travellers(array_unique(array_filter($travellers)), true);
        }

        $passengersText = $this->re("/\n[ ]*{$this->opt($this->t('Passengers'))}\n+([\s\S]+?)\n+[ ]*{$this->opt($this->t('Baggage information'))}\n/", $text);
        $passengersSegments = $this->splitText($passengersText, "/^(.*\([ ]*[A-Z]{3}[ ]*\) - .*\([ ]*[A-Z]{3}[ ]*\)|[ ]*[A-Z]{3} - [A-Z]{3}\b)/m", true);

        $passengersByCodes = [];

        foreach ($passengersSegments as $pSeg) {
            if (preg_match("/^.*\([ ]*(?<codeDep>[A-Z]{3})[ ]*\) - .*\([ ]*(?<codeArr>[A-Z]{3})[ ]*\).*\n+(?<body>[\S\s]*)$/", $pSeg, $m)
                || preg_match("/^[ ]*(?<codeDep>[A-Z]{3}) - (?<codeArr>[A-Z]{3})\b.*\n+(?<body>[\S\s]*)$/", $pSeg, $m)
            ) {
                $passengersByCodes[$m['codeDep'] . '_' . $m['codeArr']] = $m['body'];
            }
        }

        $flightText = $this->re("/^[ ]*{$this->opt($this->t('flightInfo'))}\s*{$this->opt($this->t('confNumber'))}.*\n+(.*\([A-Z]{3}\)[ ]*-[ ]*.*\([A-Z]{3}\)[\s\S]+{$this->opt($this->t('Travel time:'))}[ ]*\d[\d hmωλ]+)/m", $text);
        $flights = array_filter(preg_split("/\n\n\n\n/", $flightText));

        foreach ($flights as $flight) {
            $s = $f->addSegment();

            $terminal = $this->re("/[ ]{2}TERMINAL ([A-Z\d])(?:[ ]{2}|$)/m", $flight);
            $s->departure()->terminal($terminal, false, true);

            if (preg_match("/ {$this->opt($this->t('Flight number:'))}\n+.{50,}[ ]{2}(?<airlineName>[A-Z][A-Z\d]|[A-Z\d][A-Z]) ?(?<flightNumber>\d+)$/m", $flight, $m)) {
                $s->airline()
                    ->name($m['airlineName'])
                    ->number($m['flightNumber']);
            }

            if (preg_match("/\s+[-[:alpha:]]+\s*(?<date>.+\b\d{4})\s+(?:.+\n){1,}\s*(?<depTime>{$this->patterns['time']})\s*(?<depCode>[A-Z]{3})\s+(?<arrTime>{$this->patterns['time']})\s*(?<arrCode>[A-Z]{3})(?:[ ]{2}.+)?\n+[ ]*{$this->opt($this->t('Travel time:'))}\s*(?<duration>\d.+)/u", $flight, $m)) {
                $date = preg_replace('#(\s*/\s*)#', '.', $m['date']);

                $s->departure()
                    ->code($m['depCode'])
                    ->date(strtotime($date . ', ' . $m['depTime']));

                $s->arrival()
                    ->code($m['arrCode'])
                    ->date(strtotime($date . ', ' . $m['arrTime']));

                $s->extra()
                    ->duration($m['duration']);
            }

            if (empty($s->getDepCode()) || empty($s->getArrCode()) || !array_key_exists($s->getDepCode() . '_' . $s->getArrCode(), $passengersByCodes)) {
                continue;
            }

            if (preg_match_all("/^[ ]*{$this->opt($this->t('SEAT'))} ?:[ ]{0,60}(\d+[A-Z])(?:[ ]{2}|$)/m", $passengersByCodes[$s->getDepCode() . '_' . $s->getArrCode()], $seatMatches)) {
                $s->extra()->seats($seatMatches[1]);
            }

            if (preg_match_all("/(?:ADULT|KIDS|CHILD)\D\s+(?i)(?<cabin>ECONOMY|BUSINESS)(?:[ ]{2}|$)/m", $passengersByCodes[$s->getDepCode() . '_' . $s->getArrCode()], $cabinMatches)) {
                $s->extra()->cabin(implode('; ', array_unique($cabinMatches['cabin'])));
            }
        }

        $totalPrice = $this->re("/^[ ]*{$this->opt($this->t('TOTAL'))} ?:[ ]{1,40}(\S.*?)(?:[ ]{2}|$)/m", $text);

        if (preg_match("/^(?<amount>\d[,.‘\'\d ]*?) ?(?<currency>[^\-\d)(]+)$/", $totalPrice, $matches)) {
            // 2,370.97 EUR
            $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
            $f->price()->total(PriceHelper::parse($matches['amount'], $currencyCode))->currency($matches['currency']);

            $baseFare = $this->re("/^[ ]*{$this->opt($this->t('FARE'))} ?:[ ]{1,40}(\S.*?)(?:[ ]{2}|$)/m", $text);

            if (preg_match('/^(?<amount>\d[,.‘\'\d ]*?)[ ]*(?:' . preg_quote($matches['currency'], '/') . ')?$/u', $baseFare, $m)) {
                $f->price()->cost(PriceHelper::parse($m['amount'], $currencyCode));
            }

            if (preg_match_all("/^[ ]*({$this->opt($this->t('feeNames'))}) ?:[ ]{1,40}(\S.*?)(?:[ ]{2}|$)/m", $text, $feeMatches, PREG_SET_ORDER)) {
                foreach ($feeMatches as $fMatches) {
                    if (preg_match('/^(?<amount>\d[,.‘\'\d ]*?)[ ]*(?:' . preg_quote($matches['currency'], '/') . ')?$/u', $fMatches[2], $m)) {
                        $f->price()->fee($fMatches[1], PriceHelper::parse($m['amount'], $currencyCode));
                    }
                }
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (count($pdfs) > 0) {
            foreach ($pdfs as $pdf) {
                $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

                if (empty($text) || !$this->assignLangPdf($text)
                    || !preg_match("/^[ ]*{$this->opt($this->t('flightInfo'))}(?:[ ]{2}|$)/m", $text)
                ) {
                    continue;
                }

                $this->ParsePDF($email, $text);
            }
        }

        if (count($pdfs) === 0 || count($email->getItineraries()) === 0) {
            $this->assignLang();
            $this->ParseFlightHtml($email); // it-142303390.eml
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

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['flightInfo']) || empty($phrases['confNumber'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[{$this->contains($phrases['flightInfo'])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->contains($phrases['confNumber'])}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function assignLangPdf(?string $text): bool
    {
        if (empty($text) || !isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['flightInfo']) || empty($phrases['confNumber'])) {
                continue;
            }

            if ($this->strposArray($text, $phrases['flightInfo']) !== false
                && $this->strposArray($text, $phrases['confNumber']) !== false
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function strposArray(?string $text, $phrases, bool $reversed = false)
    {
        if (empty($text)) {
            return false;
        }

        foreach ((array) $phrases as $phrase) {
            if (!is_string($phrase)) {
                continue;
            }
            $result = $reversed ? strrpos($text, $phrase) : strpos($text, $phrase);

            if ($result !== false) {
                return $result;
            }
        }

        return false;
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'contains(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'normalize-space(' . $node . ')=' . $s;
        }, $field)) . ')';
    }

    private function starts($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'starts-with(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function t(string $phrase, string $lang = '')
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return $phrase;
        }

        if ($lang === '') {
            $lang = $this->lang;
        }

        if (empty(self::$dictionary[$lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$lang][$phrase];
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
        if (preg_match($re, $str, $m) && array_key_exists($c, $m)) {
            return $m[$c];
        }

        return null;
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
}
