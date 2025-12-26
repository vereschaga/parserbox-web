<?php

namespace AwardWallet\Engine\tcase\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class eInvoicePdf extends \TAccountChecker
{
    public $mailFiles = "tcase/it-467259206.eml, tcase/it-597155141.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'INVOICE ISSUE DATE' => ['INVOICE ISSUE DATE'],
            'RECORD LOCATOR'     => ['RECORD LOCATOR'],
            'DATE:'              => ['DATE:'],
            'Class'              => ['Class', 'Cabin'],
            'segmentsEnd'        => ['Ticket Information'],
            'baseFare'           => ['Total base fare amount', 'SubTotal'],
        ],
    ];

    private $pdfPattern = 'eInvoice(?:.*pdf|)';

    public function detectEmailFromProvider($from)
    {
        return stripos($from, "@tripcase.com") !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider(rtrim($headers['from'], '> ')) !== true) {
            return false;
        }

        return preg_match('/\bTravel Reservation to on [[:alpha:]]+ \d{1,2} for [[:upper:]]/', $headers['subject']) > 0;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (!$textPdf) {
                continue;
            }

            if ($this->assignLang($textPdf)) {
                return true;
            }
        }

        $pdfs = $parser->searchAttachmentByName('.*\.pdf.*');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (!$textPdf) {
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
        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        if (count($pdfs) === 0) {
            $pdfs = $parser->searchAttachmentByName('.*\.pdf');
        }

        $pdfTexts = [];

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (!$textPdf) {
                continue;
            }

            if ($this->assignLang($textPdf)) {
                $pdfTexts[] = $textPdf;
            }
        }

        $pdfTexts = array_unique($pdfTexts);

        if (count($pdfTexts) > 0) {
            $this->logger->debug('Found ' . count($pdfTexts) . ' unique PDF-document(s).');
        }

        $recordLocators = $currencies = $totalAmounts = [];

        foreach ($pdfTexts as $textPdf) {
            if (!$this->parsePdf($email, $textPdf)) {
                continue;
            }

            $recordLocators[] = $this->re("/^[ ]*{$this->opt($this->t('RECORD LOCATOR'))}[ ]{2,}([A-Z\d]{5,})\n/m", $textPdf);

            $priceText = $this->re("/(^[ ]{22,}{$this->opt($this->t('baseFare'))}.*(?:\n+.+){1,10}\n+[ ]{22,}{$this->opt($this->t('Total Amount Due'))}.*)/m", $textPdf);
            $totalPrice = $this->re("/^[ ]{22,}{$this->opt($this->t('Net Credit Card Billing'))}[:* ]+(.*)$/m", $priceText);

            if (preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $totalPrice, $matches)) {
                // USD 562.80
                $currencies[] = $matches['currency'];
                $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
                $totalAmounts[] = PriceHelper::parse($matches['amount'], $currencyCode);
            }
        }

        $recordLocators = array_unique($recordLocators);

        foreach ($recordLocators as $rl) {
            $email->ota()->confirmation($rl);
        }

        if (count(array_unique($currencies)) === 1) {
            $email->price()->currency($currencies[0])->total(array_sum($totalAmounts));
        }

        $email->setType('eInvoicePdf' . ucfirst($this->lang));

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

    private function parsePdf(Email $email, string $text): bool
    {
        $patterns = [
            'time'          => '\d{1,2}(?:[:：]\d{2})?(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?|[ ]*noon|[ ]*午[前後])?', // 4:19PM    |    2:00 p. m.    |    3pm    |    12 noon    |    3:10 午後
            'travellerName' => '[[:upper:]]+(?: [[:upper:]]+)*[ ]*\/[ ]*(?:[[:upper:]]+ )*[[:upper:]]+', // REEVES/CHARLOTTE K
            'eTicket'       => '\d{3}(?: | ?- ?)?\d{5,}(?: | ?- ?)?\d{1,3}', // 075-2345005149-02    |    0167544038003-004
        ];

        $passengers = [];
        $passengersText = $this->re("/^[ ]*{$this->opt($this->t('Prepared For:'))}\n+([\s\S]+?)\n+[ ]*{$this->opt($this->t('SALES PERSON'))}\s/m", $text);
        $passengerRows = preg_split("/([ ]*\n+[ ]*)+/", $passengersText);

        foreach ($passengerRows as $pRow) {
            if (preg_match("/^{$patterns['travellerName']}$/u", $pRow)) {
                $passengers[] = $pRow;
            } else {
                $this->logger->debug('Found wrong passenger!');
                $passengers = [];

                break;
            }
        }

        $dateInvoice = strtotime($this->re("/^[ ]*{$this->opt($this->t('INVOICE ISSUE DATE'))}[ ]{2,}(.{6,})\n/m", $text));

        $tripSegmentsText = preg_replace("/^.+?\n([ ]*{$this->opt($this->t('DATE:'))} .+)$/s", '$1', $text);
        $tripSegmentsText = preg_replace("/^(.+?)\n+(?:[ ]*{$this->opt($this->t('segmentsEnd'))}\n|[ ]{22,}{$this->opt($this->t('baseFare'))}).*$/s", '$1', $tripSegmentsText);
        $tripSegments = $this->splitText($tripSegmentsText, "/^([ ]*{$this->opt($this->t('DATE:'))} )/m", true);

        $detectItineraries = false;
        $flightSegments = $hotels = $cars = $trainSegments = $busSegments = []; // etc.

        foreach ($tripSegments as $tSegment) {
            if (preg_match("/^[ ]*{$this->opt($this->t('DATE:'))}.*\n+[ ]*{$this->opt($this->t('Flight'))}[: ]/m", $tSegment)) {
                $detectItineraries = true;
                $flightSegments[] = $tSegment;
            } elseif (preg_match("/^[ ]*{$this->opt($this->t('DATE:'))}.*\n+[ ]*{$this->opt($this->t('Hotel'))}[: ]/m", $tSegment)) {
                $detectItineraries = true;
                $hotels[] = $tSegment;
            } elseif (strpos(trim($tSegment), "\n") === false
                || preg_match("/^[ ]*{$this->opt($this->t('DATE:'))}.*\n+[ ]*(?i)(?:Others\n|Insurance[ ]*:.)/m", $tSegment)
            ) {
                $this->logger->debug('Found other segment.');
            } else {
                $this->logger->debug('Found unknown segment!');
                // $this->logger->debug('$tSegment = '.print_r( $tSegment,true));
                $email->add()->parking(); // for 100% fail
            }
        }

        if (count($flightSegments) > 0) {
            $f = $email->add()->flight();
            $f->general()->noConfirmation();

            if (count($passengers) > 0) {
                $f->general()->travellers($passengers, true);
            }

            foreach ($flightSegments as $seg) {
                $s = $f->addSegment();

                $date = 0;
                $dateVal = $this->re("/^[ ]*{$this->opt($this->t('DATE:'))}[: ]*(.+)\n/m", $seg);
                $dateVal = preg_replace("/\b([[:alpha:]]) ?([[:alpha:]]) ?([[:alpha:]])\b/u", '$1$2$3', $dateVal); // Wed, M ar 16    ->    Wed, Mar 16

                if (preg_match("/^(?<wday>[-[:alpha:]]+)[, ]+(?<date>[[:alpha:]]+[ ]+\d{1,2}|\d{1,2}[ ]+[[:alpha:]]+)$/u", $dateVal, $m)) {
                    if ($dateInvoice) {
                        $year = date('Y', $dateInvoice);
                        $weekDateNumber = WeekTranslate::number1($m['wday']);
                        $date = EmailDateHelper::parseDateUsingWeekDay($m['date'] . ' ' . $year, $weekDateNumber);
                    }
                } else {
                    $date = strtotime($dateVal);
                }

                if (preg_match("/^[ ]*{$this->opt($this->t('DATE:'))}.*\n+[ ]*{$this->opt($this->t('Flight'))}[: ]+(?<name>.{2,}?)[ ]+(?<number>\d+)(?:\n| *{$this->opt($this->t('Operated by'))})/m", $seg, $m)) {
                    $s->airline()->name($m['name'])->number($m['number']);
                }

                $tablePos = [0];

                if (preg_match("/^(.{30,} ){$this->opt($this->t('Departs'))}[:\s]+/m", $seg, $matches)) {
                    $tablePos[] = mb_strlen($matches[1]);
                }
                $table = $this->splitCols($seg, $tablePos);

                if (count($table) !== 2) {
                    $this->logger->debug('Wrong table in flight segment!');

                    continue;
                }

                if (preg_match("/^[ ]*{$this->opt($this->t('From'))}[ ]+([\s\S]{3,}?)\n+[ ]*{$this->opt($this->t('To'))}[ ]/m", $table[0], $m)) {
                    $s->departure()->name(preg_replace('/\s+/', ' ', $m[1]))->noCode();
                }

                if (preg_match("/^[ ]*{$this->opt($this->t('To'))}[ ]+([\s\S]{3,}?)\n+[ ]*(?:{$this->opt($this->t('Departure Terminal'))}|{$this->opt($this->t('Duration'))})/m", $table[0], $m)) {
                    $s->arrival()->name(preg_replace('/\s+/', ' ', $m[1]))->noCode();
                }

                if ($s->getDepName() == $s->getArrName()
                    && preg_match("/^\s*{$this->opt($this->t('Departs'))}\n {0,3}[^\d\s]+\n/", $table[1])
                    && preg_match("/\n[ ]*{$this->opt($this->t('Arrives'))}\s*\n+ {0,3}[^\d\s]+\n/", $table[1])
                ) {
                    $f->removeSegment($s);

                    continue;
                }

                if (preg_match("/^[ ]*{$this->opt($this->t('Departure Terminal'))}[ ]+(\S[\s\S]*?)\n+[ ]*{$this->opt($this->t('Duration'))}[ ]/m", $table[0], $m)) {
                    $s->departure()->terminal(preg_replace('/\s+/', ' ', $m[1]));
                }

                if (preg_match("/^[ ]*{$this->opt($this->t('Arrival Terminal'))}[ ]+(\S[\s\S]*?)\n+(?: *{$this->opt($this->t('Class'))}| *{$this->opt($this->t('Meal'))}| {0,3}\S+)[ ]/m", $table[1], $m)) {
                    $s->arrival()->terminal(preg_replace('/\s+/', ' ', $m[1]));
                }

                if (preg_match("/^[ ]*{$this->opt($this->t('Duration'))}[ ]+(?i)(\d[\d)(hrs min]+)$/m", $table[0], $m)) {
                    $s->extra()->duration($m[1]);
                }

                if (preg_match("/^[ ]*{$this->opt($this->t('Stop(s)'))}[ ]+(?i)(\d|non[- ]*stop)$/m", $table[0], $m)) {
                    $s->extra()->stops(preg_match("/^non[- ]*stop$/i", $m[1]) > 0 ? 0 : $m[1]);
                }

                if ($date) {
                    if (preg_match("/^[ ]*{$this->opt($this->t('Departs'))}[ ]+({$patterns['time']}).*\n/m", $table[1], $m)) {
                        $s->departure()->date(strtotime($m[1], $date));
                    }

                    if (preg_match("/^[ ]*{$this->opt($this->t('Arrives'))}[ ]+({$patterns['time']}).*\n/m", $table[1], $m)) {
                        $s->arrival()->date(strtotime($m[1], $date));
                    }
                }

                if (preg_match("/\n *{$this->opt($this->t('Class'))} ([\s\S]+?)(?:\n {0,10}\S|\s+{$this->opt($this->t('Meal'))}|\s+{$this->opt($this->t('Seat(s)'))})/", $table[1], $m)) {
                    $s->extra()->cabin($m[1]);
                }

                if (preg_match_all("/^.{40,} {$this->opt($this->t('Seat(s)'))} - (\d{1,3}[A-Z])(?:[ ]{2}|$|\n)/m", $seg, $m)) {
                    $s->extra()->seats($m[1]);
                }
            }

            if (preg_match_all("/^[ ]*{$this->opt($this->t('Ticket Number'))}[ ]+(?:(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]) ?)?({$patterns['eTicket']})(?: [ ]{2}|$)/m", $text, $ticketMatches)) {
                $f->issued()->tickets($ticketMatches[1], false);
            }
        }

        foreach ($hotels as $hText) {
            $h = $email->add()->hotel();

            $tablePos = [0];

            if (preg_match("/^(.{30,}  ){$this->opt($this->t('Check-Out'))}\s+/m", $hText, $matches)) {
                $tablePos[] = mb_strlen($matches[1]);
            }
            $table = $this->splitCols($this->re("/\n(.{30,} {$this->opt($this->t('Check-Out'))}\s+[\s\S]+(?:{$this->opt($this->t('Guarantee'))}|{$this->opt($this->t('Phone'))}).*\n)/", $hText), $tablePos);

            if (count($table) !== 2) {
                $this->logger->debug('Wrong table in hotel segment!');

                continue;
            }

            // General
            $h->general()
                ->confirmation($this->re("/\n *{$this->opt($this->t('Confirmation Number'))} +(\w+)\n/", $table[0]))
                ->travellers($passengers, true)
            ;

            // Hotel
            $h->hotel()
                ->name($this->re("/\n *{$this->opt($this->t('Hotel'))} ?: +(.+)/", $hText))
                ->phone($this->re("/\n *{$this->opt($this->t('Phone'))} +(.+)/", $table[1]), true, true)
            ;
            $address = preg_replace('/\s*\n\s*/', ', ', trim($this->re("/\n *{$this->opt($this->t('Hotel'))} ?: +.+\n([\s\S]+?)\s*\n\s*{$this->opt($this->t('Service City'))}/", $hText)));

            if (empty($address) && !empty($h->getHotelName())) {
                $h->hotel()->noAddress();
            } else {
                $h->hotel()->address($address);
            }

            // Booked
            $date = 0;
            $dateVal = $this->re("/^[ ]*{$this->opt($this->t('DATE:'))}[: ]*(.+)\n/m", $hText);
            $dateVal = preg_replace("/\b([[:alpha:]]) ?([[:alpha:]]) ?([[:alpha:]])\b/u", '$1$2$3', $dateVal); // Wed, M ar 16    ->    Wed, Mar 16

            if (preg_match("/^(?<wday>[-[:alpha:]]+)[, ]+(?<date>[[:alpha:]]+[ ]+\d{1,2}|\d{1,2}[ ]+[[:alpha:]]+)$/u", $dateVal, $m)) {
                if ($dateInvoice) {
                    $year = date('Y', $dateInvoice);
                    $weekDateNumber = WeekTranslate::number1($m['wday']);
                    $date = EmailDateHelper::parseDateUsingWeekDay($m['date'] . ' ' . $year, $weekDateNumber);
                }
            }
            $h->booked()
                ->checkIn($date);

            if (!empty($date)) {
                $COdateText = $this->re("/{$this->opt($this->t('Check-Out'))} +(.+)/", $table[1]);
                $h->booked()
                    ->checkOut(EmailDateHelper::parseDateRelative($COdateText, $date))
                ;
            }

            $h->booked()
                ->rooms($this->re("/\n *{$this->opt($this->t('Rooms(s)'))} *(\d)\n/", $table[0]));

            $h->addRoom()
                ->setDescription(preg_replace('/\s*\n\s*/', ' ', trim($this->re("/\n *{$this->opt($this->t('Room Details'))} +([\s\S]+?)\s*\n(?: {0,3}\S|\s*{$this->opt($this->t('Rate per Night'))})/", $table[1]))))
                ->setRate($this->re("/\n *{$this->opt($this->t('Rate per Night'))} +(.*\d.*)\s*\n/", $table[1]), true, true)
            ;
        }

        return $detectItineraries;
    }

    private function assignLang(string $textPdf): bool
    {
        $textPdf = mb_substr($textPdf, 0, 2000);

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['INVOICE ISSUE DATE']) || empty($phrases['RECORD LOCATOR']) || empty($phrases['DATE:'])) {
                continue;
            }

            if (preg_match("/\n[ ]*{$this->opt($phrases['INVOICE ISSUE DATE'])} /", $textPdf)
                && preg_match("/\n[ ]*{$this->opt($phrases['RECORD LOCATOR'])} /", $textPdf)
                && preg_match("/\n[ ]*{$this->opt($phrases['DATE:'])} /", $textPdf)
            ) {
                $this->lang = $lang;

                return true;
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
