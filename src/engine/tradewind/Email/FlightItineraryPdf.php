<?php

namespace AwardWallet\Engine\tradewind\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class FlightItineraryPdf extends \TAccountChecker
{
    public $mailFiles = "tradewind/it-255021841.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'confNumber'  => ['Booking Reference:', 'Booking Reference :'],
            'segmentsEnd' => ['Manage Flights'],
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '//www.flytradewind.com') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (!$textPdf) {
                continue;
            }

            if (stripos($textPdf, 'www.flytradewind.com') === false && stripos($textPdf, '@flytradewind.com') === false
                && stripos($textPdf, 'Thank you for booking with Tradewind') === false
                && stripos($textPdf, 'Please review your Tradewind') === false
            ) {
                continue;
            }

            if ($this->assignLang($textPdf)) {
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

        $email->setType('FlightItineraryPdf' . ucfirst($this->lang));

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
            'time'           => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.
            'travellerName'  => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
            'travellerName2' => '[[:upper:]]+(?: [[:upper:]]+)*[ ]*\/[ ]*(?:[[:upper:]]+ )*[[:upper:]]+', // KOH / KIM LENG MR
            'eTicket'        => '\d{3}(?: | ?- ?)?\d{5,}(?: | ?- ?)?\d{1,3}', // 075-2345005149-02    |    0167544038003-004
        ];

        $f = $email->add()->flight();

        if (preg_match("/[ ]{2}({$this->opt($this->t('confNumber'))})[ :]*([A-Z\d]{5,})$/m", $text, $m)) {
            $f->general()->confirmation($m[2], rtrim($m[1], ': '));
        }

        if (preg_match("/^(?<header>[ ]*{$this->opt($this->t('Passenger'))}[ ]+{$this->opt($this->t('Email Contact'))}[ ]+{$this->opt($this->t('E-ticket Numbers'))})\n+(?<body>[\s\S]+?)\n+.+ {$this->opt($this->t('Flight'))} .+ {$this->opt($this->t('Arrive'))}$/m", $text, $m)) {
            $passengersHeader = $m['header'];
            $passengersBody = $m['body'];
        } else {
            $passengersHeader = $passengersBody = null;
        }

        $travellers = $tickets = [];
        $tablePos = $this->rowColsPos($passengersHeader);
        $tablePos[0] = 0;
        $passengersRows = $this->splitText($passengersBody, "/^(.+ {$patterns['eTicket']}(?:\/1)?)$/m", true);

        foreach ($passengersRows as $pRow) {
            $table = $this->splitCols($pRow, $tablePos);

            if (count($table) !== 3) {
                $this->logger->debug('Wrong passengers table!');
                $travellers = $tickets = [];

                break;
            }

            $table[0] = preg_replace('/\s+/', ' ', trim($table[0]));

            if (preg_match("/^{$patterns['travellerName']}$/u", $table[0])) {
                $travellers[] = $table[0];
            } elseif (preg_match("/^({$patterns['travellerName2']}?)[ ]*(?:MRS|MR)$/u", $table[0], $m)) {
                $travellers[] = $m[1];
            } else {
                $this->logger->debug('Wrong traveller name value!');
                $travellers = $tickets = [];

                break;
            }

            if (!preg_match("/^{$patterns['eTicket']}(?:\/1)?$/u", $table[2])) {
                $this->logger->debug('Wrong ticket number value!');
                $travellers = $tickets = [];

                break;
            }

            $tickets[] = $table[2];
        }

        $f->general()->travellers($travellers, true);
        $f->issued()->tickets($tickets, false);

        if (preg_match("/^(?<header>.+ {$this->opt($this->t('Flight'))} .+ {$this->opt($this->t('Arrive'))})\n+(?<body>[\s\S]+?)\n+[ ]*{$this->opt($this->t('Manage Flights'))}$/m", $text, $m)) {
            $segmentsHeader = preg_replace("/ ({$this->opt($this->t('Depart'))}) ({$this->opt($this->t('To'))}) /", ' $1  $2', $m['header']);
            $segmentsBody = preg_replace("/\n+[ ]*{$this->opt($this->t('Fare Rules:'))}[\s\S]*$/", '', $m['body']);
        } else {
            $segmentsHeader = $segmentsBody = null;
        }

        $tablePos = $this->rowColsPos($segmentsHeader);
        $tablePos[0] = 0;
        $segments = $this->splitText($segmentsBody, "/^([ ]*\d{1,2}[ ]+[[:alpha:]]+[ ]+\d{2,4}\b.*)$/mu", true);

        foreach ($segments as $sText) {
            $table = $this->splitCols($sText, $tablePos);

            if (count($table) !== 6) {
                $this->logger->debug('Wrong segments table!');
                $travellers = $tickets = [];

                break;
            }

            $s = $f->addSegment();

            $date = strtotime($table[0]);

            if (preg_match("/^\s*(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<number>\d+)\s*$/", $table[1], $m)) {
                $s->airline()->name($m['name'])->number($m['number']);
            }

            $s->departure()->name(preg_replace('/\s+/', ' ', trim($table[2])))->noCode();
            $s->arrival()->name(preg_replace('/\s+/', ' ', trim($table[4])))->noCode();

            $table[3] = preg_replace('/\s+/', ' ', $table[3]);
            $table[5] = preg_replace('/\s+/', ' ', $table[5]);

            if (preg_match("/^\s*({$patterns['time']})/", $table[3], $m)) {
                $s->departure()->date(strtotime($m[1], $date));
            }

            if (preg_match("/^\s*({$patterns['time']})/", $table[5], $m)) {
                $s->arrival()->date(strtotime($m[1], $date));
            }
        }

        if (preg_match("/^(?<header>.+ {$this->opt($this->t('Currency'))}[ ]+{$this->opt($this->t('Price'))})\n+(?<body>[\s\S]+?)\n+[ ]*{$this->opt($this->t('Payments'))} .+ {$this->opt($this->t('Amount'))}$/m", $text, $m)) {
            $priceHeader = $m['header'];
            $priceBody = preg_replace("/\n+[ ]*{$this->opt($this->t('Fare Rules:'))}[\s\S]*$/", '', $m['body']);
        } else {
            $priceHeader = $priceBody = null;
        }

        $tablePos = $this->rowColsPos($priceHeader);
        $tablePos[0] = 0;
        $priceRows = $this->splitText($priceBody, "/^(.*?\S[ ]{2,}[^\-\d)(]*[ ]{2,}\d[,.‘\'\d ]*)$/mu", true);

        if (count($priceRows) === 0) {
            $this->logger->debug('Price not found!');

            return;
        }

        $totalRow = array_pop($priceRows);
        $totalTable = $this->splitCols($totalRow, $tablePos);

        if (count($totalTable) === 3 && preg_match("/^\s*{$this->opt($this->t('Total'))}\s*$/", $totalTable[0])) {
            $totalCurrency = preg_replace('/\s+/', ' ', trim($totalTable[1]));
            $totalAmount = preg_replace('/\s+/', ' ', trim($totalTable[2]));

            if (preg_match('/^[^\-\d)(]+$/', $totalCurrency) && preg_match('/^\d[,.‘\'\d ]*$/u', $totalAmount)) {
                $currencyCode = preg_match('/^[A-Z]{3}$/', $totalCurrency) ? $totalCurrency : null;
                $f->price()->currency($totalCurrency)->total(PriceHelper::parse($totalAmount, $currencyCode));

                // cost
                $fareRow = array_shift($priceRows);
                $fareTable = $this->splitCols($fareRow, $tablePos);

                if (count($fareTable) === 3 && preg_match("/^\s*{$this->opt($this->t('Fare'))}\s*$/", $fareTable[0])) {
                    $fareCurrency = preg_replace('/\s+/', ' ', trim($fareTable[1]));
                    $fareAmount = preg_replace('/\s+/', ' ', trim($fareTable[2]));

                    if (($fareCurrency === '' || $fareCurrency === $totalCurrency)
                        && preg_match('/^\d[,.‘\'\d ]*$/u', $fareAmount)
                    ) {
                        $f->price()->cost(PriceHelper::parse($fareAmount, $currencyCode));
                    }
                }

                // fees
                foreach ($priceRows as $feeRow) {
                    $feeTable = $this->splitCols($feeRow, $tablePos);

                    if (count($feeTable) === 3) {
                        $feeCurrency = preg_replace('/\s+/', ' ', trim($feeTable[1]));
                        $feeAmount = preg_replace('/\s+/', ' ', trim($feeTable[2]));

                        if (($feeCurrency === '' || $feeCurrency === $totalCurrency)
                            && preg_match('/^\d[,.‘\'\d ]*$/u', $feeAmount)
                        ) {
                            $f->price()->fee(preg_replace('/\s+/', ' ', trim($feeTable[0])), PriceHelper::parse($feeAmount, $currencyCode));
                        }
                    }
                }
            }
        }
    }

    private function assignLang(?string $text): bool
    {
        if (empty($text) || !isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['confNumber']) || empty($phrases['segmentsEnd'])) {
                continue;
            }

            if ($this->strposArray($text, $phrases['confNumber']) !== false
                && $this->strposArray($text, $phrases['segmentsEnd']) !== false
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
}
