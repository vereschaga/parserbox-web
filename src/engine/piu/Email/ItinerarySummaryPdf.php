<?php

namespace AwardWallet\Engine\piu\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class ItinerarySummaryPdf extends \TAccountChecker
{
    public $mailFiles = "piu/it-140895173.eml, piu/it-62038091.eml, piu/it-654133731.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'Ticket code'           => ['Ticket code', 'Ticket Code'],
            'Train'                 => ['Train'],
            'routes'                => ['Outward'],
            'ticketCodeStart'       => ['Summary'],
            'ticketCodeEnd'         => ['My itinerary', 'Itinerary'],
            'routesEnd'             => ['Total'],
            'passengerDetailsStart' => ['Passenger Details', 'Passengers Details'],
            'Carriage'              => ['Carriage', 'Coach'],
        ],

        'it' => [
            'Ticket code'           => ['Codice biglietto'],
            'Train'                 => ['Treno'],
            'routes'                => ['Andata'],
            'ticketCodeStart'       => ['Riepilogo'],
            'ticketCodeEnd'         => ['Itinerario'],
            'routesEnd'             => ['Totale'],
            'passengerDetailsStart' => ['Passeggero'],
            'Carriage'              => ['Carrozza'],
            'Seat'                  => ['Posto'],
            'Total'                 => 'Totale',
        ],
    ];

    private $detectors = [
        'en' => ['Summary –', 'My itinerary'],
        'it' => ['Codice biglietto'],
    ];

    private $year = null;

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (!$textPdf) {
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
                $header = $parser->getAttachmentHeader($pdf, 'Content-Type');

                if (preg_match("/_\d{1,2}_[[:alpha:]]{3,}_(\d{4})_/u", $header, $m)) {
                    $this->year = $m[1];
                } else {
                    $this->year = getdate(strtotime($parser->getHeader('date')))['year'];
                }
                $this->parseTrain($email, $textPdf);
            }
        }

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        $email->setType('ItinerarySummaryPdf' . ucfirst($this->lang));

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

    private function parseTrain(Email $email, ?string $text): void
    {
        $train = $email->add()->train();

        $ticketCodeText = $this->re("/(?:^\s*|\n[ ]{0,20}){$this->opt($this->t('ticketCodeStart'))}.*\n+([\s\S]+?)\n+[ ]{0,20}{$this->opt($this->t('ticketCodeEnd'))}/", $text);
        $ticketCodeRows = explode("\n", $ticketCodeText);
        array_walk($ticketCodeRows, function (&$item) {
            $item = mb_strlen($item);
        });
        natsort($ticketCodeRows);
        $ticketCodeRows = array_reverse($ticketCodeRows);
        $tablePos = [0];

        if (!empty($ticketCodeRows) && $ticketCodeRows[0] > 1) {
            $tablePos[] = intdiv($ticketCodeRows[0], 2);
        }
        $table = $this->splitCols($ticketCodeText, $tablePos);

        if (preg_match("/^[ ]*(?<desc>{$this->opt($this->t('Ticket code'))})[ ]+(?<number>[A-Z\d]{5,})(?:[ ]{2}|$)/m", $table[0], $m)
            || preg_match("/^[ ]*(?<desc>{$this->opt($this->t('Ticket code'))}).*\n+.*?[[:lower:]].*?[ ]+(?<number>[A-Z\d]{5,})(?:[ ]{2}|$)/mu", $table[0], $m)
            || preg_match("/^[ ]{0,50}(?<number>[A-Z\d]{5,})\n+[ ]*(?<desc>{$this->opt($this->t('Ticket code'))})(?:[ ]{2}|$)/m", $table[0], $m)
        ) {
            $train->general()->confirmation($m['number'], $m['desc']);
        }

        $passengers = [];

        $patterns['time'] = '\d{1,2}(?:[:：]\d{2})?(?:\s*[AaPp]\.?[Mm]\.?)?';

        // 9907 09:30 Roma Termini → 10:43 Napoli Centrale
        // 8908 (8908) 09:55 Roma Termini - 11:31 Firenze S.M.Novella
        $patterns['train'] = "(?<number>\d+)(?: ?\( ?\d+ ?\))?[ ]+(?<time1>{$patterns['time']})[ ]+(?<name1>.{3,}?)[ ]+[->→][ ]+(?<time2>{$patterns['time']})[ ]+(?<name2>.{3,})";

        $routesText = $this->re("/\n([ ]*{$this->opt($this->t('routes'))}[: ]+.+[->→].+\n[\s\S]+?)(?:\n+[ ]*{$this->opt($this->t('routesEnd'))}[ ]{2}|$)/", $text);
        $trainRoutes = $this->splitText($routesText, "/^([ ]*{$this->opt($this->t('routes'))}[: ]+.+[->→].+)/m", true);

        foreach ($trainRoutes as $tRoute) {
            $date = 0;

            if (preg_match("/^.+ (\d[\d\/]*\d|[-[:alpha:]]+ \d{1,2} [[:alpha:]]+) {$patterns['time']}[ ]*[->→][ ]*{$patterns['time']}/u", $tRoute, $matches)) {
                if (preg_match("/^(?<wday>[-[:alpha:]]+)\s+(?<date>\d{1,2}\s+[[:alpha:]]+)$/u", $matches[1], $m)) {
                    // 03 Jun
                    $weekDateNumber = WeekTranslate::number1($m['wday']);
                    $dateValue = $this->normalizeDate($m['date']);

                    if ($weekDateNumber && $dateValue && $this->year) {
                        $date = EmailDateHelper::parseDateUsingWeekDay($dateValue . ' ' . $this->year, $weekDateNumber);
                    }
                } else {
                    // 14/08
                    $dateValue = $this->normalizeDate($matches[1]);

                    if ($dateValue && !preg_match('/\b\d{4}\s*$/', $dateValue) && $this->year) {
                        $dateValue .= '/' . $this->year;
                    }

                    if ($dateValue) {
                        $date = strtotime($dateValue);
                    }
                }
            }

            $trainSegments = $this->splitText($tRoute, "/^[ ]*{$this->opt($this->t('Train'))}.*?[:]+[ ]*(\d.+)/m", true);

            foreach ($trainSegments as $tSegment) {
                $s = $train->addSegment();

                if (preg_match("/^{$patterns['train']}/u", $tSegment, $m)) {
                    $s->extra()->number($m['number']);

                    // for google, to help find correct address of stations
                    if (strpos($text, 'Riepilogo') !== false || strpos($text, 'Codice biglietto') !== false) {
                        // https://en.wikipedia.org/wiki/Nuovo_Trasporto_Viaggiatori
                        $region = ', IT';
                    } else {
                        $region = '';
                    }

                    $s->departure()
                        ->date(strtotime($m['time1'], $date))
                        ->name($m['name1'] . $region);
                    $s->arrival()
                        ->date(strtotime($m['time2'], $date))
                        ->name($m['name2'] . $region);
                }

                $cars = [];

                $passengerDetails = $this->re("/^[ ]*{$this->opt($this->t('passengerDetailsStart'))}.+{$this->opt($this->t('Seat'))}.*\n+([\s\S]+)/m", $tSegment);
                $passengerRows = $this->splitText($passengerDetails, '/^([ ]*\d{1,3}\. [[:alpha:]])/mu', true);

                foreach ($passengerRows as $pRow) {
                    $pTablePos = [0];

                    if (preg_match("/^(.+? )(?:{$this->opt($this->t('Carriage'))}|{$this->opt($this->t('Seat'))}) /", $passengerDetails, $m)) {
                        $pTablePos[] = mb_strlen($m[1]);
                    }
                    $pTable = $this->splitCols($pRow, $pTablePos);

                    if (!empty($pTable[0]) && preg_match("/^\s*\d{1,3}\.[ ]*([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])\s*$/u", $pTable[0], $m)) {
                        // 1. MADELEINE SARAH BROWN
                        $passengers[] = $m[1];
                    }

                    if (!empty($pTable[1]) && preg_match("/{$this->opt($this->t('Carriage'))} (\d{1,3}) {$this->opt($this->t('Seat'))} (\d{1,3})\b/", $pTable[1], $m)) {
                        // Carriage 4 Seat 9
                        $cars[] = $m[1];
                        $s->extra()->seat($m[2]);
                    }
                }

                if (count(array_unique($cars)) === 1) {
                    $s->extra()->car($cars[0]);
                }
            }
        }

        if (count($passengers)) {
            $train->general()->travellers(array_unique($passengers), true);
        }

        if (preg_match("/^[ ]*{$this->opt($this->t('Total'))}[ ]{2,}(.+)$/m", $text, $matches)
            && preg_match('/^(?<amount>\d[,.\'\d]*)[ ]*(?<currency>[^\d)(]+)$/', $matches[1], $m)
        ) {
            // 71,80 €
            $currencyCode = preg_match('/^[A-Z]{3}$/', $m['currency']) ? $m['currency'] : null;
            $train->price()
                ->total(PriceHelper::parse($m['amount'], $currencyCode))
                ->currency($this->normalizeCurrency($m['currency']));
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

    private function normalizeCurrency($string)
    {
        $string = trim($string);
        $currencies = [
            'GBP' => ['£'],
            'AUD' => ['A$'],
            'EUR' => ['€', 'Euro'],
            'USD' => ['US Dollar'],
        ];

        foreach ($currencies as $code => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $code;
                }
            }
        }

        return $string;
    }

    private function assignLang(?string $text): bool
    {
        if (empty($text) || !isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['Ticket code']) || empty($phrases['Train'])) {
                continue;
            }

            if ($this->strposArray($text, $phrases['Ticket code']) !== false
                && $this->strposArray($text, $phrases['Train']) !== false
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

    /**
     * Dependencies `use AwardWallet\Engine\MonthTranslate` and `$this->lang`.
     */
    private function normalizeDate(?string $text): ?string
    {
        if (preg_match('/^(\d{1,2})\s*\/\s*(\d{1,2})$/', $text, $m)) {
            // 14/08
            $day = $m[1];
            $month = $m[2];
            $year = '';
        } elseif (preg_match('/^(\d{1,2})\s+([[:alpha:]]+)$/u', $text, $m)) {
            // 07 Jun
            $day = $m[1];
            $month = $m[2];
            $year = '';
        }

        if (isset($day, $month, $year)) {
            if (preg_match('/^\s*(\d{1,2})\s*$/', $month, $m)) {
                return $m[1] . '/' . $day . ($year ? '/' . $year : '');
            }

            if (($monthNew = MonthTranslate::translate($month, $this->lang)) !== false) {
                $month = $monthNew;
            }

            return $day . ' ' . $month . ($year ? ' ' . $year : '');
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

    private function re($re, $str, $c = 1): ?string
    {
        preg_match($re, $str, $m);

        if (!empty($m[$c])) {
            return $m[$c];
        }

        return null;
    }
}
