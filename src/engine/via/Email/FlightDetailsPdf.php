<?php

namespace AwardWallet\Engine\via\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class FlightDetailsPdf extends \TAccountChecker
{
    public $mailFiles = "via/it-111068534.eml, via/it-112260368.eml, via/it-112873077.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'confNumber' => ['AIRLINE PNR'],
            'arriving'   => ['Arriving'],
        ],
    ];

    private $subjects = [
        'en' => ['- Booking confirmation Details'],
    ];

    private $detectors = [
        'en' => ['Flight Details'],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@via.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (is_string($phrase) && stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
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

            if ($detectProvider === false
                && stripos($textPdf, '@via.com') === false
                && strpos($textPdf, 'Via.com') === false
            ) {
                continue;
            }

            if ($this->detectBody($textPdf) && $this->assignLang($textPdf)) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (!$textPdf) {
                continue;
            }

            if ($this->assignLang($textPdf)) {
                $this->parsePdf($email, $textPdf);
            }
        }

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        $email->setType('FlightDetailsPdf' . ucfirst($this->lang));

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

    private function parsePdf(Email $email, $text): void
    {
        $patterns = [
            // Fri, 17 Sep 2021
            'date' => '[-[:alpha:]]+, \d{1,2} [[:alpha:]]{3,} \d{4}\b',
            // 4:19PM    |    2:00 p. m.
            'time' => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?',
            // Mr. Hao-Li Huang
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:]\s]*[[:alpha:]]',
            // 075-2345005149-02    |    0167544038003-004
            'eTicket' => '\d{3}(?: | ?- ?)?\d{5,}(?: | ?- ?)?\d{1,3}',
        ];

        $f = $email->add()->flight();

        if (preg_match("/^[ ]*({$this->opt($this->t('Booking ID'))})[ ]*:[ ]*([-A-Z\d]{5,})$/m", $text, $m)) {
            $email->ota()->confirmation($m[2], $m[1]);
        }

        if (preg_match("/^[ ]*{$this->opt($this->t('Booked on'))}[ ]*:[ ]*(.*\d.*)$/m", $text, $m)) {
            $f->general()->date2($m[1]);
        }

        if (preg_match("/[ ]{2}({$this->opt($this->t('AIRLINE PNR'))})\n+([\s\S]+?)\n+[ ]*{$this->opt($this->t('Onward Flight Details'))}/", $text, $m)
            && preg_match("/^[\s\S]+[ ]{2}([A-Z\d][A-Z\d ]{3,9}[A-Z\d])(?:\n|$)/", $m[2], $m2)
        ) {
            $f->general()->confirmation(str_replace(' ', '', $m2[1]), $m[1]);
        }

        // Passenger(s) Details

        $passengerDetails = preg_match("/\n[ ]*{$this->opt($this->t('Passenger(s) Details'))}.*\n+([\s\S]+?)\n+[ ]*{$this->opt($this->t('Payment Details'))}/", $text, $m) ? $m[1] : '';

        $passengerAddons = '';

        if (preg_match("/^([\s\S]+?)\n+[ ]*{$this->opt($this->t('Add-Ons'))}.*\n+([\s\S]+)$/", $passengerDetails, $m)) {
            $passengerDetails = $m[1];
            $passengerAddons = $m[2];
        }

        $tablePos = $this->rowColsPos(explode("\n", $passengerDetails)[0]);

        $passengerRows = $this->splitText($passengerDetails, '/^([ ]{0,20}\d{1,3}[ ]{2}.+)/m', true);

        foreach ($passengerRows as $pRow) {
            $table = $this->splitCols($pRow, $tablePos);

            if (!empty($table[1]) && preg_match("/^\s*({$patterns['travellerName']})\s*$/u", $table[1], $m)) {
                $m[1] = preg_replace('/\s+/', ' ', $m[1]);
                $m[1] = preg_replace('/^(?:Mr|Ms)[.\s]+/i', '', $m[1]);
                $f->general()->traveller($m[1], true);
            }

            if (!empty($table[3]) && preg_match("/^\s*({$patterns['eTicket']})\s*$/", $table[3], $m)) {
                $f->issued()->ticket($m[1], false);
            }
        }

        $extraByFlights = [];

        $paSegments = $this->splitText($passengerAddons, "/^([ ]{0,20}{$this->opt($this->t('Flight'))}[ ]*\d{1,3}[ ]*:.+)/m", true);

        foreach ($paSegments as $paSegment) {
            $table = $this->splitCols($paSegment);

            if (preg_match("/^[ ]*{$this->opt($this->t('Flight'))}[ ]*\d{1,3}[ ]*:[ ]*((?:[A-Z][A-Z\d]|[A-Z\d][A-Z])-\d+)\n/", $table[0], $matches)) {
                $extraByFlights[$matches[1]] = [
                    'meals' => [],
                    'seats' => [],
                ];
                // TODO: add parsing meal
                if (preg_match_all("/\b\d+[A-Z]\b/", $table[2], $seatMatches)) {
                    $extraByFlights[$matches[1]]['seats'] = array_merge($extraByFlights[$matches[1]]['seats'], $seatMatches[0]);
                }
            }
        }

        // Onward Flight Details

        /*
            Chennai (MAA)
            Terminal 1
            Fri, 17 Sep 2021, 03:20 PM
        */
        $patterns['airport'] = "/^\s*"
            . "(?<city>[\s\S]{2,}?)\s*\(\s*(?<code>[A-Z]{3})\s*\)"
            . "(?:\n+Terminal\s+(?<terminal>[-A-z\d\s]+?))?"
            . "\n+(?<date>{$patterns['date']}),\s+(?<time>{$patterns['time']})"
            . "/";

        $flightDetails = preg_match("/\n[ ]*{$this->opt($this->t('Onward Flight Details'))}.*\n+([\s\S]+?)\n+[ ]*{$this->opt($this->t('Passenger(s) Details'))}/", $text, $m) ? $m[1] : null;

        $segments = $this->splitText($flightDetails, "/^([ ]*{$this->opt($this->t('Flight'))} \d{1,3}(?:[ ]{2}|$))/m", true);

        foreach ($segments as $sText) {
            $s = $f->addSegment();

            $tablePos = [0];

            if (preg_match("/^((.+?[ ]{2}){$patterns['date']}.*?[ ]{2}){$patterns['date']}/m", $sText, $m)) {
                $tablePos[] = mb_strlen($m[2]);
                $tablePos[] = mb_strlen($m[1]);
            } elseif (preg_match_all("/^(.+?[ ]{2}){$patterns['date']}/mu", $sText, $sTableMatches)) {
                foreach ($sTableMatches[1] as $match) {
                    $tablePos[] = mb_strlen($match);
                }
                sort($tablePos);
            }

            if (preg_match('/^(.+[ ]{2})\d{2}:\d{2} Hrs$/im', $sText, $matches)) {
                $tablePos[] = mb_strlen($matches[1]);
            }
            $table = $this->splitCols($sText, $tablePos);

            if (count($table) !== 4) {
                $this->logger->debug('Wrong flight segment!');

                continue;
            }

            if (preg_match("/^[ ]*((?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])-(?<number>\d+))[ ]*$/m", $table[0], $m)) {
                $s->airline()->name($m['name'])->number($m['number']);

                if (!empty($extraByFlights[$m[1]])) {
                    $s->extra()->seats($extraByFlights[$m[1]]['seats']);
                }
            }

            if (preg_match("/\n[ ]*{$this->opt($this->t('Cabin'))}[ ]*:\s*([^:]+?)(?:\n|$)/", $table[0], $m)) {
                $s->extra()->cabin($m[1]);
            }

            if (preg_match($patterns['airport'], $table[1], $m)) {
                $s->departure()
                    ->code($m['code'])
                    ->date2($m['date'] . ' ' . $m['time'])
                ;

                if (!empty($m['terminal'])) {
                    $s->departure()->terminal($m['terminal']);
                }
            }

            if (preg_match($patterns['airport'], $table[2], $m)) {
                $s->arrival()
                    ->code($m['code'])
                    ->date2($m['date'] . ' ' . $m['time'])
                ;

                if (!empty($m['terminal'])) {
                    $s->arrival()->terminal($m['terminal']);
                }
            }

            if (preg_match("/^[ ]*{$this->opt($this->t('Non Stop'))}$/m", $table[3], $m)) {
                $s->extra()->stops(0);
            }

            if (preg_match("/^[ ]*(\d{2}:\d{2})\s*{$this->opt($this->t('Hrs'))}$/im", $table[3], $m)) {
                $s->extra()->duration($m[1]);
            }
        }

        // Payment Details

        $paymentDetails = preg_match("/\n([ ]*{$this->opt($this->t('Payment Details'))}.*\n+[\s\S]+?)(?:\n+[ ]*{$this->opt($this->t('Important Information'))}|$)/", $text, $m) ? $m[1] : null;

        $tablePos = [0];

        if (preg_match("/^(.+[ ]{2}){$this->opt($this->t('Flight Inclusions'))}/m", $paymentDetails, $matches)) {
            $tablePos[] = mb_strlen($matches[1]);
        }
        $table = $this->splitCols($paymentDetails, $tablePos);

        if (count($table) === 2) {
            $paymentDetails = $table[0];
        }

        $currencyCode = preg_match("/[ ]{2}{$this->opt($this->t('Amount'))}\s*\(\s*([A-Z]{3})\s*\)\n/", $paymentDetails, $m) ? $m[1] : null;

        if (preg_match('/\n[ ]*' . $this->opt($this->t('Air Fare')) . '[ ]+(?<amount>\d[,.\'\d ]*)\n/', $paymentDetails, $m)) {
            $f->price()->cost(PriceHelper::parse($m['amount'], $currencyCode));
        }

        if (preg_match("/\n[ ]*{$this->opt($this->t('Air Fare'))}.*\n+([\s\S]+)\n+[ ]*{$this->opt($this->t('Total'))}/", $paymentDetails, $matches)
            && preg_match_all("/^[ ]*(?<name>.+?)[ ]+(?<amount>\d[,.\'\d ]*)$/m", $matches[1], $feeMatches, PREG_SET_ORDER)
        ) {
            foreach ($feeMatches as $m) {
                $f->price()->fee($m['name'], PriceHelper::parse($m['amount'], $currencyCode));
            }
        }

        if (preg_match("/\n[ ]*{$this->opt($this->t('Total'))}[ ]+(?<amount>\d[,.\'\d\s]*?)$/", $paymentDetails, $matches)) {
            $f->price()->total(PriceHelper::parse($matches['amount'], $currencyCode))->currency($currencyCode);
        }
    }

    private function detectBody(?string $text): bool
    {
        if (empty($text) || !isset($this->detectors)) {
            return false;
        }

        foreach ($this->detectors as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (!is_string($phrase)) {
                    continue;
                }

                if (strpos($text, $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    private function assignLang(?string $text): bool
    {
        if (empty($text) || !isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['confNumber']) || empty($phrases['arriving'])) {
                continue;
            }

            if ($this->strposArray($text, $phrases['confNumber']) !== false
                && $this->strposArray($text, $phrases['arriving']) !== false
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
}
