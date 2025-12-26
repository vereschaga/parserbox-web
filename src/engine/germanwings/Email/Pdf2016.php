<?php

namespace AwardWallet\Engine\germanwings\Email;

// bcdtravel
// In one pdf format from another provider
use AwardWallet\Schema\Parser\Email\Email;

class Pdf2016 extends \TAccountChecker
{
    public $mailFiles = "germanwings/it-11613058.eml, germanwings/it-15949298.eml, germanwings/it-5667523.eml, germanwings/it-73413876.eml";
    private $detectLang = [
        'de' => 'Rechnungsnummer',
        'en' => 'Invoice Number',
    ];
    private $lang;
    private static $dict = [
        'de' => [
            'Fluge'                           => ['Fluge', 'Flüge', 'Flug'],
            'Keine Fluge'                     => ['Keine Fluge', 'Keine Flüge', 'Keine Flug'],
            'Passagiere'                      => ['Passagiere', 'Passagier'],
            'Kostenloser Informationsservice' => ['Kostenloser Informationsservice', 'Alle Zeitangaben beziehen'],
        ],
        'en' => [
            'Wir danken Ihnen' => 'Many thanks for booking your flight with us',
            'Fluge'            => 'Flight',
            //            'Keine Fluge' => '',
            'Passagiere'                      => ['Passengers', 'Passenger'],
            'Kostenloser Informationsservice' => ['Free Information Service', 'All listed times are local times'],
            'Rechnungsnummer'                 => 'Invoice Number',
            'Tag der Buchung'                 => 'Date of booking',
            'Gesamtbetrag'                    => 'Total flight fare',
            'Flugpreis'                       => 'Flight Price',
            'durchgeführt von'               => 'operated by',
        ],
    ];
    private $junkDetect = false;

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName('.+?\.pdf');

        if (count($pdfs) === 0) {
            $this->logger->debug('Pdf is not found or is empty!');

            return $email;
        }

        foreach ($pdfs as $value) {
            $text = str_replace(' ', ' ', \PDF::convertToText($parser->getAttachmentBody($value)));
            $this->lang = '';

            if (stripos($text, 'Eurowings') !== false && stripos($text, 'Passenger Receipt') !== false
                && $this->assignLang($text)
            ) {
                $this->parseReservationEurowings($this->findСutSection($text, null, $this->t('Wir danken Ihnen')),
                    $email);
            } elseif (stripos($text, 'AUSTRIAN') !== false && stripos($text,
                    'Please print this receipt and retain') !== false
            ) {
                $this->parseReservationAustrian($this->findСutSection($text, null, ['Payment Details']), $email);
            }
        }

        if ($this->junkDetect && count($email->getItineraries()) === 0) {
            $email->setIsJunk(true);
        }

        $email->setType('PDF2016' . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdf = $parser->searchAttachmentByName('.+?\.pdf');

        foreach ($pdf as $value) {
            $text = str_replace(' ', ' ', \PDF::convertToText($parser->getAttachmentBody($value)));

            if (stripos($text, 'Eurowings') !== false && stripos($text, 'Passenger Receipt') !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseReservationEurowings($text, Email $email): void
    {
        $this->logger->debug(__FUNCTION__);
        $f = $email->add()->flight();

        if (preg_match("/{$this->t('Rechnungsnummer')}:\s*([A-Z\d]{5,6})/", $text, $matches)) {
            $f->general()
                ->confirmation($matches[1]);
        }

        if (preg_match("/{$this->t('Tag der Buchung')}:\s*(\d+\.\d+\.\d+\s+\d+:\d+)/", $text, $matches)) {
            $f->general()
                ->date(strtotime($matches[1]));
        }

        if (preg_match_all('/\d+\s+([\w\s\-]+?)(?:\s{3,})|\n/',
            $this->findСutSection($text, $this->t('Passagiere'), $this->t('Fluge')), $matches)) {
            $f->general()
                ->travellers(array_values(array_map('trim', array_filter($matches[1]))));
        }

        if (preg_match("/\n\s*{$this->t('Gesamtbetrag')}\b.+?([\d.,]+)\s*(.{1,3})/", $text, $matches)) {
            $f->price()
                ->total((float) $matches[1])
                ->currency($this->currency($matches[2]));
        }

        if (preg_match("/\n\s*{$this->t('Flugpreis')}[ ]{2,}([\d.,]+)\s*(.{1,3})/", $text, $matches)) {
            $f->price()
                ->cost((float) $matches[1])
                ->currency($this->currency($matches[2]));
        }

        foreach (
            $this->splitter('/(\d+\.\d+\.\d+\s+)/',
            $this->findСutSection($text, $this->t('Fluge'), $this->t('Kostenloser Informationsservice'))
        ) as $value) {
            /*
            26.08.2018         EW 8463          14:35 London Heathrow                  17:25     Berlin-Tegel (K)
                                              operated by Germanwings
                                              Seat(s): 4D (1), 4E (2), 4F (3)
                                                                 All listed times are local times.
             * */
            //delete spaces after time
            $str = preg_replace("#(\d+:\d+)\s+(\w)#", '$1 $2', explode("\n", $value)[0]);

            $table = $this->splitCols($str);

            if (count($table) != 4 && count($table) != 6) {
                $this->logger->info("incorrect parse table");

                return;
            }
            $s = $f->addSegment();
            $date = strtotime($table[0]);

            if (preg_match("#^(\w{2})\s*(\d+)$#", $table[1], $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2]);
            }

            if (count($table) == 4) {
                if (preg_match("#^(\d+:\d+)\s+(.+)#", $table[2], $m)) {
                    $s->departure()
                        ->date(strtotime($m[1], $date))
                        ->name($m[2])
                        ->noCode();
                }

                if (preg_match("#^(\d+:\d+)\s+(.*?)(?:\s+\((.* )?\w\))?$#", $table[3], $m)) {
                    $s->arrival()
                        ->date(strtotime($m[1], $date))
                        ->name($m[2])
                        ->noCode();
                }
            } elseif (count($table) == 6) {
                $s->departure()
                    ->date(strtotime($table[2], $date))
                    ->name($table[3])
                    ->noCode();
                $s->arrival()
                    ->date(strtotime($table[4], $date))
                    ->noCode();

                if (preg_match("#^(.*?)(?:\s+\(.*? \w\))?$#", $table[5], $m)) {
                    $s->arrival()->name($m[1]);
                }
            }

            if (preg_match("#\((?:\w+ \w+ )?(\w+)\s+([A-Z])\)#", $value, $m)) {
                $s->extra()
                    ->cabin($m[1])
                    ->bookingCode($m[2]);
            } elseif (preg_match("#\(([A-Z]{1,2})\)#", $value, $m)) {
                $s->extra()
                    ->bookingCode($m[1]);
            }

            if (preg_match("#{$this->t('durchgeführt von')}\s+(.+)#", $value, $m)) {
                $s->airline()
                    ->operator(trim($m[1]));
            }
            $node = $this->re("#(?:Seat|Sitz)\([se]\):(.+)#", $value);

            if (preg_match_all("#\b(\d+[A-z])\b#", $node, $m)) {
                $s->extra()
                    ->seats($m[1]);
            }
        }

        if (count($f->getSegments()) === 0
            && preg_match("/\n[ ]*({$this->opt($this->t('Keine Fluge'))})\n/u",
                $this->re("/\n[ ]*{$this->opt($this->t('Passagiere'))}\n+(.{4,}?)\n+[ ]*{$this->opt($this->t('Gesamtbetrag'))}(?:[ ]{2}|\n)/us", $text)
                ?? $this->re("/\n[ ]*{$this->opt($this->t('Passagiere'))}((?:\n.*){1,30})/", $text), $m)
        ) {
            // it-73413876.eml
            $this->junkDetect = true;
            $email->removeItinerary($f);
            $this->logger->debug('Junk marker found: ' . $m[1]);
        }
    }

    private function parseReservationAustrian($text, Email $email): void
    {
        $this->logger->debug(__FUNCTION__);
        $f = $email->add()->flight();

        if (preg_match('#Name:\s+([\w\s/]+)\s+Booking code.+?:\s*([A-Z\d]{5,6})\s+Ticket number.+?:\s+([A-Z\d-]+)#',
            $text, $matches)) {
            $f->general()
                ->confirmation($matches[2])
                ->traveller($matches[1]);
            $f->issued()
                ->ticket($matches[3], false);
        }

        if (preg_match('/Rechnungssumme\s*([A-Z]{3})\s*([\d.,]+)/', $text, $matches)) {
            $f->price()
                ->total((float) $matches[1])
                ->currency($matches[2]);
        }

        foreach ($this->splitter('/((?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*\d+\s{2,}\d+ \w{3} \d+)/',
            $this->findСutSection($text, 'Flight Data', ['Invoice Information'])) as $value) {
            $s = $f->addSegment();

            if (preg_match('/([A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(\d+)\s+(\d+ \w{3} \d+)\s+(.+?)\s{3,}(.+?)\s+(\d+:\d+)\s+(\d+:\d+)\s*([A-Z])/us',
                $value, $matches)) {
                $s->airline()
                    ->name($matches[1])
                    ->number($matches[2]);
                $s->departure()
                    ->noCode()
                    ->date(strtotime($matches[3] . ',' . $matches[6]))
                    ->name($this->normalizeText($matches[4]));
                $s->arrival()
                    ->noCode()
                    ->date($this->increaseDate($s->getDepDate(), strtotime($matches[7], $s->getDepDate())))
                    ->name($this->normalizeText($matches[5]));
                $s->extra()
                    ->bookingCode($matches[8]);
            }

            if (preg_match('/operated by:\s*(.+?)\s{2,}/', $text, $matches)) {
                $s->airline()
                    ->operator($matches[1]);
            }
        }
    }

    //========================================
    // Auxiliary methods
    //========================================

    private function normalizeText($string)
    {
        return trim(preg_replace('/\s{2,}/', ' ', str_replace("\n", " ", $string)));
    }

    /**
     * Quick change preg_match. 1000 iterations takes 0.0008 seconds,
     * while similar <i>preg_match('/LEFT\n(.*)RIGHT')</i> 0.300 sec.
     * <p>
     * Example:
     * <b>LEFT</b> <i>cut text</i> <b>RIGHT</b>
     * </p>
     * <pre>
     * findСutSection($plainText, 'LEFT', 'RIGHT')
     * findСutSection($plainText, 'LEFT', ['RIGHT', PHP_EOL])
     * </pre>.
     */
    private function findСutSection($input, $searchStart, $searchFinish = null)
    {
        $inputResult = null;
        $startlen = 0;

        if (is_array($searchStart)) {
            foreach ($searchStart as $str) {
                if (strpos($input, $str) !== false) {
                    $input = mb_strstr($input, $str);
                    $startlen = mb_strlen($str);

                    break;
                }
            }
        } elseif (!empty($searchStart)) {
            $input = mb_strstr($input, $searchStart);
            $startlen = mb_strlen($searchStart);
        }

        if (is_array($searchFinish)) {
            $i = 0;

            while (empty($inputResult)) {
                if (isset($searchFinish[$i])) {
                    $inputResult = mb_strstr($input, $searchFinish[$i], true);
                } else {
                    return false;
                }
                $i++;
            }
        } elseif (!empty($searchFinish)) {
            $inputResult = mb_strstr($input, $searchFinish, true);
        } else {
            $inputResult = $input;
        }

        return mb_substr($inputResult, $startlen);
    }

    /**
     * <pre>
     * Example:
     * abc SPLIT text\n text1\n acv SPLIT text\n text1
     * /(SPLIT)/
     * [0] => SPLIT text text1 acv
     * [1] => SPLIT text text1
     * <pre>.
     */
    private function splitter($regular, $text)
    {
        $result = [];

        $array = preg_split($regular, $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        array_shift($array);

        for ($i = 0; $i < count($array) - 1; $i += 2) {
            $result[] = $array[$i] . $array[$i + 1];
        }

        return $result;
    }

    private function increaseDate($depDate, $arrDate)
    {
        while ($depDate > $arrDate) {
            $arrDate = strtotime('+1 day', $arrDate);
        }

        return $arrDate;
    }

    private function tableHeadPos($row)
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

    private function splitCols($text, $pos = false)
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->tableHeadPos($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k=>$p) {
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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function currency($s)
    {
        $sym = [
            '€'=> 'EUR',
            '$'=> 'USD',
            '£'=> 'GBP',
            'e'=> 'EUR',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }
        $s = $this->re("#([^\d\,\. ]+)#", $s);

        foreach ($sym as $f=> $r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

        return null;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang($body)
    {
        if (isset($this->detectLang)) {
            foreach ($this->detectLang as $lang => $reBody) {
                if (stripos($body, $reBody) !== false) {
                    $this->lang = substr($lang, 0, 2);

                    return true;
                }
            }
        }

        return false;
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
}
