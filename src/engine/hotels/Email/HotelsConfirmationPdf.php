<?php

namespace AwardWallet\Engine\hotels\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class HotelsConfirmationPdf extends \TAccountChecker
{
    public $mailFiles = "hotels/it-137832657.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'Hotels.com confirmation number' => ['Hotels.com confirmation number'],
            'Check-out'                      => ['Check-out'],
            'mainTableEnd'                   => ['Hotel Details', 'Room details', 'Unit Details', 'Payment details'],
            'mainTableFields'                => ['Hotels.com confirmation number', 'Check-in', 'Check-out', 'Your stay', 'Cancellation policy', 'Total price'],
            'room'                           => ['room', 'rooms', 'unit', 'units'],
            'hotelDetailsStart'              => ['Hotel Details'],
            'hotelDetailsEnd'                => ['Room details', 'Unit Details', 'Payment details'],
            'roomDetailsStart'               => ['Room details', 'Unit Details'],
            'roomDetailsEnd'                 => ['Payment details'],
            'roomDetailsFields'              => ['Guests', 'Preferences', 'Included with this room', 'Facilities', 'Cancellation policy'],
            'adult'                          => ['adult', 'adults'],
            'paymentDetailsStart'            => ['Payment details'],
        ],
    ];

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (!$textPdf) {
                continue;
            }

            if (strpos($textPdf, 'Hotels.com') === false && stripos($textPdf, 'mail.hotels.com') === false) {
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
                $this->parseHotel($email, $textPdf);
            }
        }

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        $email->setType('HotelsConfirmationPdf' . ucfirst($this->lang));

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

    private function parseHotel(Email $email, $text): void
    {
        $patterns = [
            'time'          => '\d{1,2}(?:[:：]\d{2})?(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?',
            'phone'         => '[+(\d][-+. \d)(]{5,}[\d)]',
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:]\s]*[[:alpha:]]',
        ];

        $h = $email->add()->hotel();

        // remove garbage
        $text = preg_replace([
            '/^[ ]*https?:.+$\n+/im',
            '/^.*\b(?:Gmail|JuiceLand Mail|ArtClass Mail) - .*$\n+/im',
        ], [
            '',
            '',
        ], $text);

        if (preg_match("/\n[ ]*{$this->opt($this->t('Manage booking'))}[ ]{2,}{$this->opt($this->t('View receipt'))}[^\n]*\n+(.+)/s", $text, $m)) {
            $text = $m[1];
        }

        if (preg_match("/^(.+)?\n+[ ]*{$this->opt('Taxes and fees are tax recovery charges paid to the hotel for its tax obligations.')}/s", $text, $m)) {
            $text = $m[1];
        }

        preg_match_all("/\n\n((?:[ ]*\S.{1,60}\n){3,10})\n\n/", $text, $addressMatches);

        foreach ($addressMatches[1] as $match) {
            if (preg_match("/^\s*(?<name>.{2,})\n[ ]*(?<address>[\s\S]{3,}?)\n[ ]*(?<phone>[+(\d][-+. \d)(]{5,}[\d)])\s*$/", $match, $m)) {
                $h->hotel()->name($m['name'])->address(preg_replace("/[ ]*\n+[ ]*/", ', ', $m['address']))->phone($m['phone']);
            }
        }

        /* main table */

        $mainTableText = $this->re("/\n([ ]*{$this->opt($this->t('Hotels.com confirmation number'))}[\s\S]*?)\n+[ ]*{$this->opt($this->t('mainTableEnd'))}\n/", $text);
        $mainTableRows = [];
        $fieldNames = (array) $this->t('mainTableFields');

        foreach ($fieldNames as $key => $fName) {
            if ($key === count($fieldNames) - 1) {
                if (preg_match("/(?:^\n*|\n)([ ]*{$this->opt($this->t($fName))} .*?)\s*$/s", $mainTableText, $m)) {
                    $mainTableRows[$fName] = $m[1];
                }

                break;
            }
            $nextNames = array_slice($fieldNames, $key + 1);

            foreach ($nextNames as $nName) {
                if (preg_match("/(?:^\n*|\n)([ ]*{$this->opt($this->t($fName))} .*?)\n+[ ]*{$this->opt($this->t($nName))}/s", $mainTableText, $m)) {
                    $mainTableRows[$fName] = $m[1];

                    break;
                }
            }
        }
        $mainTable = [];

        foreach ($mainTableRows as $fName => $rowText) {
            $tablePos = [0];

            if (preg_match("/^([ ]*{$this->opt($this->t($fName))}[ ]+)\S/m", $rowText, $matches)) {
                $tablePos[] = mb_strlen($matches[1]);
            }
            $table = $this->splitCols($rowText, $tablePos);

            if (count($table) === 2) {
                $mainTable[$fName] = $table[1];
            }
        }

        foreach ((array) $this->t('Hotels.com confirmation number') as $name) {
            if (!empty($mainTable[$name]) && preg_match("/^([-A-Z\d]{5,})(?:\n|$)/", $mainTable[$name], $m)) {
                $email->ota()->confirmation($m[1], $name);
                $h->general()->noConfirmation();
            }
        }

        foreach ((array) $this->t('Check-in') as $name) {
            if (!empty($mainTable[$name]) && preg_match("/^(?<date>.{6,}?)\s*\(\s*(?<time>[^)(]+?)\s*\)/", preg_replace('/\s+/', ' ', $mainTable[$name]), $m)) {
                $dateCheckIn = strtotime($m['date']);
                $m['time'] = preg_replace([
                    "/^{$this->opt($this->t('From'))}\s+/i",
                    "/\s+{$this->opt($this->t('local time'))}$/i",
                    "/^({$patterns['time']})\s*-.*$/",
                ], [
                    '',
                    '',
                    '$1',
                ], $m['time']);
                $h->booked()->checkIn(strtotime($m['time'], $dateCheckIn));
            }
        }

        foreach ((array) $this->t('Check-out') as $name) {
            if (!empty($mainTable[$name]) && preg_match("/^(?<date>.{6,}?)\s*\(\s*(?<time>[^)(]+?)\s*\)/", preg_replace('/\s+/', ' ', $mainTable[$name]), $m)) {
                $dateCheckOut = strtotime($m['date']);
                $m['time'] = preg_replace([
                    "/^{$this->opt($this->t('Before'))}\s+/i",
                    "/\s+{$this->opt($this->t('local time'))}$/i",
                    "/^.*-\s*({$patterns['time']})$/",
                    "/^{$this->opt($this->t('noon'))}$/i",
                ], [
                    '',
                    '',
                    '$1',
                    '12:00',
                ], $m['time']);
                $h->booked()->checkOut(strtotime($m['time'], $dateCheckOut));
            }
        }

        foreach ((array) $this->t('Your stay') as $name) {
            if (!empty($mainTable[$name]) && preg_match("/(?:^|,\s*)(\d{1,3})\s*{$this->opt($this->t('room'))}/i", $mainTable[$name], $m)) {
                $h->booked()->rooms($m[1]);
            }
        }

        foreach ((array) $this->t('Cancellation policy') as $name) {
            if (!empty($mainTable[$name])) {
                $h->general()->cancellation(preg_replace('/\s+/', ' ', $mainTable[$name]));
            }
        }

        foreach ((array) $this->t('Total price') as $name) {
            if (!empty($mainTable[$name]) && preg_match("/^(?<currency>[^\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*)(?:\n|$)/", $mainTable[$name], $matches)) {
                // $138.88
                $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
                $h->price()->currency($matches['currency'])->total(PriceHelper::parse($matches['amount'], $currencyCode));
            }
        }

        /* Hotel Details */

        // $hotelDetailsText = $this->re("/\n[ ]*{$this->opt($this->t('hotelDetailsStart'))}\n+([\s\S]+?)\n+[ ]*{$this->opt($this->t('hotelDetailsEnd'))}/", $text);

        /* Room details / Unit Details */

        $roomDetailsText = $this->re("/\n[ ]*{$this->opt($this->t('roomDetailsStart'))}\n+([\s\S]+?)\n+[ ]*{$this->opt($this->t('roomDetailsEnd'))}/", $text);
        $travellers = $adultsValues = $cancellationValues = [];
        $roomDetailsRows = [];
        $fieldNames = (array) $this->t('roomDetailsFields');
        $roomDetailsSegments = $this->splitText($roomDetailsText, "/(.{2,}\n+[ ]*{$this->opt($this->t('Guests'))}[ ]{2,}\S.+)/", true);

        foreach ($roomDetailsSegments as $sText) {
            foreach ($fieldNames as $key => $fName) {
                if ($key === count($fieldNames) - 1) {
                    if (preg_match("/(?:^\n*|\n)([ ]*{$this->opt($this->t($fName))} .*?)\s*$/s", $sText, $m)) {
                        $roomDetailsRows[$fName] = $m[1];
                    }

                    break;
                }
                $nextNames = array_slice($fieldNames, $key + 1);

                foreach ($nextNames as $nName) {
                    if (preg_match("/(?:^\n*|\n)([ ]*{$this->opt($this->t($fName))} .*?)\n+[ ]*{$this->opt($this->t($nName))}/s", $sText, $m)) {
                        $roomDetailsRows[$fName] = $m[1];

                        break;
                    }
                }
            }
            $roomDetails = [];

            foreach ($roomDetailsRows as $fName => $rowText) {
                $tablePos = [0];

                if (preg_match("/^([ ]*{$this->opt($this->t($fName))}[ ]+)\S/m", $rowText, $matches)) {
                    $tablePos[] = mb_strlen($matches[1]);
                }
                $table = $this->splitCols($rowText, $tablePos);

                if (count($table) === 2) {
                    $roomDetails[$fName] = $table[1];
                }
            }

            $roomType = $this->re("/^\s*(.{2,})\n\n/", $sText);

            if (strpos($roomType, '  ') === false) {
                $room = $h->addRoom();
                $room->setType($roomType);
            }

            foreach ((array) $this->t('Guests') as $name) {
                if (!empty($roomDetails[$name]) && preg_match("/^(?<traveller>{$patterns['travellerName']})\s*,\s*(?<adults>\d{1,3})\s*{$this->opt($this->t('adult'))}(?:\n|$)/u", $roomDetails[$name], $m)) {
                    $travellers[] = preg_replace('/\s+/', ' ', $m['traveller']);
                    $adultsValues[] = $m['adults'];
                }
            }

            foreach ((array) $this->t('Cancellation policy') as $name) {
                if (!empty($roomDetails[$name])) {
                    $cancellationValues[] = preg_replace([
                        '/\n\n[ ]*/',
                        '/([[:lower:])])\n[ ]*([[:upper:]][[:lower:] ])/u',
                        '/\s+/',
                    ], [
                        '; ',
                        '$1; $2',
                        ' ',
                    ], $roomDetails[$name]);
                }
            }
        }

        if (count($travellers) > 0) {
            $h->general()->travellers(array_unique($travellers), true);
        }

        if (count($adultsValues) > 0) {
            $h->booked()->guests(array_sum($adultsValues));
        }

        if (count(array_unique($cancellationValues)) === 1) {
            $h->general()->cancellation($cancellationValues[0]);
        }

        if (!empty($h->getCancellation())) {
            if (preg_match("/^Non-refundable reservation[.;!]/i", $h->getCancellation()) // en
            ) {
                $h->booked()->nonRefundable();
            } elseif (preg_match("/^Free cancell?ation until\s+(?<time>{$patterns['time']})\s*, (?<date>\d{1,2}\/\d{1,2}\/\d{2,4})\b/i", $h->getCancellation(), $m) // en
            ) {
                $h->booked()->deadline(strtotime($m['time'], strtotime($m['date'])));
            }
        }

        /* Payment details */

        $paymentDetailsText = $this->re("/\n[ ]*{$this->opt($this->t('paymentDetailsStart'))}\n+([\s\S]+?)(?:\n{4}|$)/", $text);

        $totalPrice = $this->re("/(?:^\s*|\n[ ]*){$this->opt($this->t('Total price'))}[ ]{2,}(\S.*)/", $paymentDetailsText);

        if (preg_match('/^(?<currency>[^\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*)$/', $totalPrice, $matches)) {
            // $138.88
            $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
            $h->price()->currency($matches['currency'])->total(PriceHelper::parse($matches['amount'], $currencyCode));

            $taxes = $this->re("/(?:^\s*|\n[ ]*){$this->opt($this->t('Taxes & fees'))}(?: \d{1,3})?[ ]{2,}(\S.*)/", $paymentDetailsText);

            if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.\'\d ]*?)$/', $taxes, $m)) {
                $h->price()->tax(PriceHelper::parse($m['amount'], $currencyCode));
            }
        }
    }

    private function assignLang($text): bool
    {
        if (empty($text) || !isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['Hotels.com confirmation number']) || empty($phrases['Check-out'])) {
                continue;
            }

            if ($this->strposArray($text, $phrases['Hotels.com confirmation number']) !== false
                && $this->strposArray($text, $phrases['Check-out']) !== false
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
