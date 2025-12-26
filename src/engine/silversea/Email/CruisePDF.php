<?php

namespace AwardWallet\Engine\silversea\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class CruisePDF extends \TAccountChecker
{
    public $mailFiles = "silversea/it-136028515.eml, silversea/it-180933994.eml, silversea/it-881329364.eml";
    public $subjects = [
        'en' => [' VOYAGE CONFIRMATION ', ' VOYAGE CANCELLATION ', ' VOYAGE CANCELATION '],
    ];

    public $lang = 'en';
    public $pdfNamePattern = ".*pdf";
    public $firstSegment = 0;
    public $retryPoin = 0;
    public $lastDateTime;
    public $lastSegmentName;
    public $pdfConfirmation;

    public $currentDate;
    public $subject;

    public static $dictionary = [
        "en" => [
            'TRAVEL AGENT COPY' => ['TRAVEL AGENT COPY', 'TRAVEL ADVISOR COPY'],
            'TOTAL CHARGE'      => ['TOTAL CHARGE', 'TOTAL CHARGE:'],
        ],
    ];

    private $patterns = [
        'time' => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (preg_match('/ Silverseas Booking$/i', $headers['subject']) > 0) {
            return true;
        }

        if ($this->detectEmailFromProvider(rtrim($headers['from'], '> ')) !== true
            && stripos($headers['subject'], 'SILVERSEA ') === false
        ) {
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

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $detectProvider = $this->detectEmailFromProvider($parser->getHeader('from')) === true;

        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (!$textPdf) {
                continue;
            }

            if ($detectProvider === false && stripos($textPdf, 'Silversea.com') === false
                && stripos($textPdf, 'www.traveldocs.com/silversea') === false
                && stripos($textPdf, 'to Silversea Term') === false
            ) {
                continue;
            }

            if (strpos($textPdf, 'BOOKING DETAILS') !== false
                && strpos($textPdf, 'FINANCIAL SUMMARY AND GUEST INFORMATION') !== false
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]silversea\.com$/', $from) > 0;
    }

    public function ParseCruisePDF(Email $email, $text): void
    {
        $totalPrice = $this->re("/[ ]{10}{$this->opt($this->t('TOTAL CHARGE'))}[ ]+(.*\d.*)/", $text)
            ?? $this->re("/\n{$this->opt($this->t('TOTAL CHARGE'))}[ ]+(.*\d.*)/", $text)
        ;

        if (preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $totalPrice, $matches)) {
            // $ 131,562.00    |    C$ 57,474.00
            $currency = $this->normalizeCurrency($matches['currency']);
            $currencyCode = preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null;
            $email->price()->currency($currency)->total(PriceHelper::parse($matches['amount'], $currencyCode));

            $priceTable = $this->re("/{$this->opt($this->t('FINANCIAL SUMMARY AND GUEST INFORMATION'))}[\s\S]+?\n([ ]{0,10}\S.+{$this->opt($this->t('FARE:'))} *.*\d.+[\S\s]+?)\n.*{$this->opt($this->t('TOTAL CHARGE'))}/", $text);
            $priceRows = $this->split("/^ {0,10}(\S.+ {3,}\S)/m", $priceTable);
            $discount = 0.0;

            foreach ($priceRows as $i => $row) {
                $table = $this->SplitCols($row);

                if (count($table) < 2) {
                    continue;
                }

                $name = trim(preg_replace('/\s+/', ' ', $table[0]), ': ');
                $value = null;

                for ($ti = 1; $ti < count($table); $ti++) {
                    $v = $this->re("/^\D*(\d.+?)\D*$/", $table[$ti]);

                    if (!empty($v) && preg_match("/^\D*(\d.+?)\-\s*$/u", $table[$ti])) {
                        $discount += PriceHelper::parse($v, $currencyCode);
                        $v = null;
                    }

                    if (!empty($v)) {
                        $value = (($value === null) ? 0.0 : $value) + PriceHelper::parse($v, $currencyCode);
                    }
                }

                if ($i === 0) {
                    $email->price()->cost($value);

                    continue;
                }

                if (!empty($value)) {
                    $email->price()->fee($name, $value);
                }

                if (!empty($discount)) {
                    $email->price()->discount($discount);
                }
            }

            if (preg_match("/[ ]{10,}{$this->opt($this->t('TOTAL CHARGE'))}[ ]+.*\d.*\n+[ ]{10,}{$this->opt($this->t('COMMISSION:'))}[ ]*(.*\d.*)/", $text, $m)
                && preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $m[1], $m2)
            ) {
                $email->price()->fee(trim($this->re("/[ ]{10,}{$this->opt($this->t('TOTAL CHARGE'))}[ ]+.*\d.*\n+[ ]{10,}({$this->opt($this->t('COMMISSION:'))})[ ]*.*\d.*/", $text), ':'), PriceHelper::parse($m2['amount'], $currencyCode));
            }
        }

        $voyageNumbers = $roomNumbers = $roomClasses = [];

        $issueDate = $this->re("/{$this->opt($this->t('ISSUE DATE:'))}\s*([\d\/]+)/", $text);
        $embarkDate = $this->re("/{$this->opt($this->t('EMBARK DATE:'))}\s*([\d\/]+)/", $text);
        $dateArray = $this->DateFormatForHotels($issueDate, $embarkDate);
        $ship = $this->re("/{$this->opt($this->t('SHIP:'))}\s*(.+)[ ]{10,}{$this->opt($this->t('DURATION:'))}/", $text);

        if (preg_match("/(?:\n[ ]*|[ ]{2}){$this->opt($this->t('VOYAGE:'))}[ ]+([-A-Z\d]{5,})(?:[ ]{2}|\n)/", $text, $m)) {
            $voyageNumbers[] = $m[1];
        }

        if (preg_match_all("/(?:\n[ ]*|[ ]{2}){$this->opt($this->t('SUITE / CATEGORY:'))}[ ]+(?<suite>[-A-Z\d]+)[ ]*\/[ ]*(?<category>\S.*?)(?:[ ]{2}|\n)/", $text, $roomMatches)) {
            $roomNumbers = $roomMatches['suite'];
            $roomClasses = $roomMatches['category'];
        }

        $guestText = $this->re("/{$this->opt($this->t('FINANCIAL SUMMARY AND GUEST INFORMATION'))}.+\n([ ]{10,}{$this->opt($this->t('GUEST'))}\s*\d+.+)\n{$this->opt($this->t('VS #:'))}/s", $text);
        $guestTable = $this->SplitCols($guestText);
        $travellers = str_replace("\n", " ", preg_replace("/{$this->opt($this->t('GUEST'))}\s*\d+\s+/s", "", $guestTable));

        $this->retryPoin = 0;

        $c = $email->add()->cruise();

        if (is_array($this->pdfConfirmation) && !empty($this->pdfConfirmation['value'])) {
            $c->general()
                ->confirmation($this->pdfConfirmation['value'], $this->pdfConfirmation['title'] ?? null);
        }
        //it-180933994.eml
        if (stripos($this->subject, 'SILVERSEA VOYAGE CANCELLATION') !== false) {
            $c->general()
                ->cancelled();
        }
        $c->general()
            ->date($this->normalizeDate($dateArray[0]))
            ->travellers($travellers, true);
        $c->setShip($ship);

        $cruiseBlocks = preg_split("/WHAT'S INCLUDED/", $text);

        foreach ($cruiseBlocks as $cruiceBlock) {
            $month = $year = '';

            if (preg_match("/[ ]{10,}(?<month>\w+)\s*(?<day>\d+)\,\s*(?<year>\d{4})\s*Voyage\s*(?<confNumer>[A-Z\d]{7,})/", $cruiceBlock, $match)
            || preg_match("/[ ]{10,}(?<day>\d+)\s*(?<month>\w+)\s*(?<year>\d{4})\s*Voyage\s*(?<confNumer>[A-Z\d]{7,})/", $cruiceBlock, $match)
            ) {
                $month = $match['month'];
                $year = $match['year'];
                $voyageNumbers[] = $match['confNumer'];
            }

            $blockText = $this->re("/(.{10,} {$this->opt($this->t('Date'))}[ ]*{$this->opt($this->t('Arrive'))}[ ]*{$this->opt($this->t('Depart'))}\n[\s\S]+?)\n+[ ]*(?:SPECIALTY DINING RESERVATIONS|\d{1,2}[ ]*$)/", $cruiceBlock);

            if ($blockText) {
                $blockTablePos = [0];

                if (preg_match("/^(.+ ){$this->opt($this->t('Date'))}[ ]*{$this->opt($this->t('Arrive'))}[ ]*{$this->opt($this->t('Depart'))}$/m", $blockText, $matches)) {
                    $blockTablePos[] = mb_strlen($matches[1]);
                }

                $blockTable = $this->SplitCols($blockText, $blockTablePos);

                if (count($blockTable) !== 2) {
                    $this->logger->debug('Wrong cruise block table!');
                    $blockTable = ['', ''];
                }

                $pointText = str_replace("\n\n", "\n", $blockTable[1]);
                $pointText = preg_replace("/^[ ]*\d{1,2}\s*Day at sea\n/im", '', $pointText);

                if (preg_match_all("/(^[ ]*\d{1,2}[ ]+\D{2,}\s+(?:{$this->patterns['time']}\s+{$this->patterns['time']}\n|{$this->patterns['time']})(?:\s+\D{2,}\n)?)/m", $pointText, $segMatches)) {
                    foreach ($segMatches[1] as $seg) {
                        if (preg_match("/^[ ]*(?<day>\d{1,2})[ ]+(?<namePart1>\D{2,}?)\s+(?<timeDep>{$this->patterns['time']})\s+(?<timeArr>{$this->patterns['time']})\n*(?:(?<namePart2>\D{2,}))?$/u", $seg, $m) // if in row departure and arrival time
                            || preg_match("/^[ ]*(?<day>\d{1,2})[ ]+(?<namePart1>\D{2,}?)\s+(?<time>{$this->patterns['time']})\n*(?:(?<namePart2>\D{2,}))?$/u", $seg, $m) // if in row only departure or arrival time
                        ) {
                            $s = $c->addSegment();

                            $m['namePart1'] = preg_replace('/\s+/', ' ', trim($m['namePart1']));

                            //Departure and Arrival Name
                            if (!empty($m['namePart2'])) {
                                $s->setName($m['namePart1'] . ' ' . preg_replace('/\s+/', ' ', trim($m['namePart2'])));
                            } elseif (!empty($m['namePart1'])) {
                                $s->setName($m['namePart1']);
                            } else {
                                $c->removeSegment($s);
                            }

                            if ($this->lastSegmentName === $s->getName()) {
                                $c->removeSegment($s);

                                continue;
                            }

                            if ($this->firstSegment == 0 && !empty($m['time'])) {
                                $s->setAboard(strtotime($m['day'] . ' ' . $month . ' ' . $year . ', ' . $m['time']));
                                $this->firstSegment = 1;
                                $this->lastDateTime = $s->getAboard();
                            } elseif (!empty($m['timeDep']) && !empty($m['timeArr'])) {
                                if (empty($this->currentDate)) {
                                    $s->setAshore(strtotime($m['day'] . ' ' . $month . ' ' . $year . ', ' . $m['timeDep']));
                                    $s->setAboard(strtotime($m['day'] . ' ' . $month . ' ' . $year . ', ' . $m['timeArr']));
                                    $this->currentDate = $s->getAboard();
                                } else {
                                    $depDate = strtotime($m['day'] . ' ' . $month . ' ' . $year . ', ' . $m['timeDep']);

                                    if ($this->currentDate < $depDate) {
                                        $s->setAshore($depDate);
                                        $s->setAboard(strtotime($m['day'] . ' ' . $month . ' ' . $year . ', ' . $m['timeArr']));
                                        $this->currentDate = $s->getAboard();
                                    } else {
                                        $month = date('M', strtotime('+1 month', $depDate));
                                        $s->setAshore(strtotime($m['day'] . ' ' . $month . ' ' . $year . ', ' . $m['timeDep']));
                                        $s->setAboard(strtotime($m['day'] . ' ' . $month . ' ' . $year . ', ' . $m['timeArr']));
                                    }
                                }
                                $this->lastSegmentName = $s->getName();
                                $this->lastDateTime = $s->getAboard();
                                $this->firstSegment = 1;
                            } elseif (!empty($m['time'])) {
                                $date = strtotime($m['day'] . ' ' . $month . ' ' . $year . ', ' . $m['time']);

                                if ($this->lastDateTime == $date) {
                                    $c->removeSegment($s);

                                    continue;
                                } elseif ($this->retryPoin == 0) {
                                    $s->setAshore($date);
                                    $this->retryPoin = 1;
                                    $this->lastDateTime = $s->getAshore();
                                } else {
                                    $s->setAboard($date);
                                    $this->retryPoin = 0;
                                    $this->lastDateTime = $s->getAboard();
                                }
                            }
                        }
                    }
                }
            }
        }

        $voyageNumbers = array_unique($voyageNumbers);

        if (count($voyageNumbers) === 1) {
            $c->details()->number(array_shift($voyageNumbers));
        }

        if (count(array_unique($roomNumbers)) === 1) {
            $c->details()->room(array_shift($roomNumbers));
        }

        if (count(array_unique($roomClasses)) === 1) {
            $c->details()->roomClass(array_shift($roomClasses));
        }
    }

    public function ParseFlightPDF(Email $email, $text): void
    {
        $textFlight = $this->re("/AIR ARRANGEMENTS.+(GUEST\(S\) NAME\n+\s*.+)Baggage Policy\:/s", $text);
        $travellers = array_filter(preg_split("/[ ]{4,}/", $this->re("/GUEST\(S\) NAME\n(\D+)PRE-CRUISE FLIGHT/", $textFlight)));
        $segments = preg_split("/(?:PRE\-CRUISE|POST-CRUISE)/", $textFlight);

        foreach ($segments as $segment) {
            if (preg_match("/#\s*:\s*(?<airline>[A-Z][A-Z\d]|[A-Z\d][A-Z])(?<number>\d{1,5})\s*.+CLASS\s*:\s*(?<cabin>\w+)\s*PNR #\s*:\s*(?<conf>[A-Z\d]{5,7})\s*,\n+[ ]*DEPARTS:\s*(?<depDate>[\d\/]+)\s*(?<depTime>{$this->patterns['time']})\s*(?<depName>.+)\n[ ]*ARRIVES:\s*(?<arrDate>[\d\/]+)\s*(?<arrTime>{$this->patterns['time']})\s*(?<arrName>.+)/u", $segment, $m)) {
                if (!isset($f)) {
                    $f = $email->add()->flight();

                    $f->general()
                        ->noConfirmation()
                        ->travellers($travellers);

                    if (is_array($this->pdfConfirmation) && !empty($this->pdfConfirmation['value'])) {
                        $f->ota()
                            ->confirmation($this->pdfConfirmation['value'], $this->pdfConfirmation['title'] ?? null);
                    }
                }

                $s = $f->addSegment();

                $s->airline()
                    ->name($m['airline'])
                    ->number($m['number'])
                    ->confirmation($m['conf'])
                ;

                $s->departure()
                    ->noCode()
                    ->name($m['depName'])
                    ->date(strtotime($m['depDate'] . ', ' . $m['depTime']));

                $s->arrival()
                    ->noCode()
                    ->name($m['arrName'])
                    ->date(strtotime($m['arrDate'] . ', ' . $m['arrTime']));

                $s->extra()
                    ->cabin($m['cabin']);
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->subject = $parser->getSubject();

        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        $guestCopyTexts = $agentCopyTexts = [];

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (!$textPdf) {
                continue;
            }

            if (preg_match("/^[ ]*{$this->opt($this->t('GUEST COPY'))}$/m", $textPdf)) {
                $guestCopyTexts[] = $textPdf;
            } elseif (preg_match("/^[ ]*{$this->opt($this->t('TRAVEL AGENT COPY'))}$/m", $textPdf)) {
                $agentCopyTexts[] = $textPdf;
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $pdfTexts = count($guestCopyTexts) > 0 ? $guestCopyTexts : $agentCopyTexts;

        foreach ($pdfTexts as $pdfText) {
            $this->pdfConfirmation = null;

            if (preg_match("/^[ ]*({$this->opt($this->t('BOOKING #'))})[ ]*[:]+[ ]*([-A-Z\d]{5,})(?:[ ]{2}|$)/m", $pdfText, $m)) {
                $this->pdfConfirmation = ['title' => $m[1], 'value' => $m[2]];
            }

            $this->ParseCruisePDF($email, $pdfText);

            if (stripos($pdfText, 'AIR ARRANGEMENTS') !== false) {
                $this->ParseFlightPDF($email, $pdfText);
            }
        }

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

    private function re($re, $str, $c = 1): ?string
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function opt($field): string
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }

    private function normalizeDate($date)
    {
        //$this->logger->debug('$date = '.print_r( $date,true));

        $in = [
            // 10/26/2021
            "/^(\d+)\.(\d+)\.(\d{4})$/iu",
        ];
        $out = [
            "$1.$2.$3",
        ];
        $date = preg_replace($in, $out, $date);
//        $this->logger->debug('$date = '.print_r( $date,true));

        if (preg_match("/\d+\s+([[:alpha:]]+)\s+\d{4}/u", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        return strtotime($date);
    }

    /**
     * @param string $string Unformatted string with currency
     */
    private function normalizeCurrency(string $string): string
    {
        $string = trim($string);
        $currencies = [
            // do not add unused currency!
            'CAD' => ['C$'],
        ];

        foreach ($currencies as $currencyCode => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $currencyCode;
                }
            }
        }

        return $string;
    }

    private function SplitCols($text, $pos = false): array
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->rowColsPos($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k=>$p) {
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

    private function DateFormatForHotels($dateIN, $dateOut)
    {
        if (preg_match("#^(\d{1,2}[\/\.\-]\d{1,2}[\.\/\-])(\d{2})$#", $dateIN, $m)) {
            $dateIN = str_replace(" ", "", preg_replace("#^(\d{1,2}[\/\.\-]\d{1,2}[\.\/\-])(\d{2})$#", "$1 " . "20$2", $dateIN));
        }

        if (preg_match("#^(\d{1,2}[\/\.\-]\d{1,2}[\.\/\-])(\d{2})$#", $dateOut, $m)) {
            $dateOut = str_replace(" ", "", preg_replace("#^(\d{1,2}[\/\.\-]\d{1,2}[\.\/\-])(\d{2})$#", "$1 " . "20$2", $dateOut));
        }

        if ($this->identifyDateFormat($dateIN, $dateOut) === 1) {
            $dateIN = preg_replace("#(\d+)[\/\.](\d+)[\/\.](\d+)$#", "$2.$1.$3", $dateIN);
            $dateOut = preg_replace("#(\d+)[\/\.](\d+)[\/\.](\d+)$#", "$2.$1.$3", $dateOut);
        } else {
            $dateIN = preg_replace("#(\d+)[\/\.](\d+)[\/\.](\d+)$#", "$1.$2.$3", $dateIN);
            $dateOut = preg_replace("#(\d+)[\/\.](\d+)[\/\.](\d+)$#", "$1.$2.$3", $dateOut);
        }

        return [$dateIN, $dateOut];
    }

    private function identifyDateFormat($date1, $date2)
    {
        if (preg_match("#(\d{1,2})[\/\.\-](\d{1,2})[\.\/\-](\d{4})#", $date1, $m) && preg_match("#(\d{1,2})[\/\.\-](\d{1,2})[\.\/\-](\d{4})#", $date2, $m2)) {
            if (intval($m[1]) > 12 || intval($m2[1]) > 12) {
                return 0;
            } elseif (intval($m[2]) > 12 || intval($m2[2]) > 12) {
                return 1;
            } else {
                //try to guess format
                $diff = [];

                foreach (['$3-$2-$1', '$3-$1-$2'] as $i => $format) {
                    $tempdate1 = preg_replace("#(\d{1,2})[\/\.\-](\d{1,2})[\.\/\-](\d{4})#", $format, $date1);
                    $tempdate2 = preg_replace("#(\d{1,2})[\/\.\-](\d{1,2})[\.\/\-](\d{4})#", $format, $date2);

                    if (($tstd1 = strtotime($tempdate1)) !== false && ($tstd2 = strtotime($tempdate2)) !== false && ($tstd2 - $tstd1 > 0)) {
                        $diff[$i] = $tstd2 - $tstd1;
                    }
                }
                $min = min($diff);

                return array_flip($diff)[$min];
            }
        }

        return -1;
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
}
