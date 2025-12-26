<?php

namespace AwardWallet\Engine\oceania\Email;

use AwardWallet\Schema\Parser\Email\Email;

class BoardingPassPdf extends \TAccountChecker
{
    public $mailFiles = "oceania/it-630228504.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            // HTML
            'htmlTextPairs' => [
                ['BOARDING PASS FOR CRUISE TERMINAL CHECK-IN', 'your Boarding Pass is attached'],
            ],
            // PDF
            'guestName'       => ['Guest Name:', 'Guest Name :', 'Guest name:', 'Guest name :'],
            'terminalArrTime' => ['Terminal Arrival Time:', 'Terminal Arrival Time :'],
            'TAI-start'       => 'Terminal Arrival Information',
        ],
    ];

    private $patterns = [
        'time'          => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.
        'date'          => '\b[[:alpha:]]+[ ]+\d{1,2}[ ]*,[ ]*\d{4}', // JAN 28, 2024
        'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[.@]oceaniacruises\.com$/i', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return !empty($headers['subject']) && preg_match('/Oceania Cruises -.+ Boarding Passes/i', $headers['subject']) > 0;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,".oceaniacruises.com/") or contains(@href,"www.oceaniacruises.com")]')->length > 0
            && $this->assignLangHtml()
        ) {
            return true;
        }

        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (!$textPdf) {
                continue;
            }

            if ($this->assignLangPdf($textPdf)) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $textPdfFull = '';

        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (!$textPdf) {
                continue;
            }

            if ($this->assignLangPdf($textPdf)) {
                $textPdfFull .= $textPdf . "\n\n";
            }
        }

        if (empty($textPdfFull)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        $cruisesByPNR = [];
        $bPasses = $this->splitText($textPdfFull, "/^([ ]*{$this->opt($this->t('guestName'))})/m", true);

        foreach ($bPasses as $bpText) {
            $pnr = $this->re("/^[ ]*{$this->opt($this->t('Booking Number'))}[: ]+([-A-Z\d]{5,}?)(?:[ ]{2}|$)/m", $bpText);

            if (!$pnr) {
                $this->logger->debug('PNR not found!');

                return $email;
            }

            if (array_key_exists($pnr, $cruisesByPNR)) {
                $cruisesByPNR[$pnr][] = $bpText;
            } else {
                $cruisesByPNR[$pnr] = [$bpText];
            }
        }

        foreach ($cruisesByPNR as $pnr => $cruises) {
            foreach ($cruises as $i => $bpText) {
                $traveller = $this->re("/^[ ]*{$this->opt($this->t('guestName'))}[: ]*({$this->patterns['travellerName']})$/mu", $bpText);

                if ($i === 0) {
                    $cr = $email->add()->cruise();
                    $cr->general()->confirmation($pnr);
                    $this->parseCruise($cr, $bpText);
                }

                $cr->general()->traveller($traveller, true);
            }
        }

        $email->setType('BoardingPassPdf' . ucfirst($this->lang));

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

    private function parseCruise(\AwardWallet\Schema\Parser\Common\Cruise $cr, string $text): void
    {
        $ship = $this->re("/^[ ]*{$this->opt($this->t('Ship'))}[: ]+(\S.*?\S)[ ]+{$this->opt($this->t('Sail Date'))}/m", $text);
        $room = $this->re("/^[ ]*{$this->opt($this->t('Suite/Stateroom'))}[: ]+([A-Z\d]{1,6})(?:[ ]{2}|$)/m", $text);
        $cr->details()->room($room)->ship($ship);

        $terminalArrInfo = $this->re("/(.+ {$this->opt($this->t('TAI-start'))}\n[\s\S]+)/", $text);

        $tablePos = [0];

        if (preg_match("/^(.+ ){$this->opt($this->t('terminalArrTime'))}/m", $terminalArrInfo, $matches)) {
            $tablePos[] = mb_strlen($matches[1]);
        }

        $table = $this->splitCols($terminalArrInfo, $tablePos);

        if (count($table) !== 2) {
            $this->logger->debug('Wrong Terminal Arrival Information table!');

            return;
        }

        $s = $cr->addSegment();

        $timeArr = $this->re("/\n[ ]*{$this->opt($this->t('terminalArrTime'))}[: ]*({$this->patterns['time']})/", $table[1]);
        $dateArr = $this->re("/\n[ ]*{$this->opt($this->t('Date'))}[: ]+({$this->patterns['date']})\n/", $table[1]);

        if ($timeArr && $dateArr) {
            $s->setAboard(strtotime($timeArr, strtotime($dateArr)));
        }

        $portArr = $this->re("/\n[ ]*{$this->opt($this->t('Port'))}[: ]+([\s\S]{3,}?)\n+[ ]*{$this->opt($this->t('Terminal'))}[ ]*:/", $table[1]);
        $s->setName(preg_replace('/\s+/', ' ', $portArr));
    }

    private function assignLangHtml(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['htmlTextPairs']) || !is_array($phrases['htmlTextPairs'])) {
                continue;
            }

            foreach ($phrases['htmlTextPairs'] as $pair) {
                if (!is_array($pair) || count($pair) !== 2) {
                    continue;
                }

                if ($this->http->XPath->query("//*[{$this->contains($pair[0])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($pair[1])}]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
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
            if (!is_string($lang) || empty($phrases['guestName']) || empty($phrases['terminalArrTime'])) {
                continue;
            }

            if ($this->strposArray($text, $phrases['guestName']) !== false
                && $this->strposArray($text, $phrases['terminalArrTime']) !== false
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
