<?php

namespace AwardWallet\Engine\extended\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Common\Hotel;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class HotelCancellationPdf extends \TAccountChecker
{
	public $mailFiles = "extended/it-867835800.eml";
    public $subjects = [
        "Reservation Cancellation - ",
    ];

    public $pdfNamePattern = ".*pdf";

    public $lang = 'en';

    public static $dictionary = [
        'en' => [
            'Cancellation Letter' => 'Cancellation Letter',
            'Check In Date' => 'Check In Date',
            'Reservation Details' => 'Reservation Details',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@extendedstay.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (stripos($headers['subject'], $subject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]extendedstay\.com/', $from) > 0;
    }

    function detectProvider($text, $from)
    {
        if (stripos($from, 'extendedstay.com') !== false
        ) {
            return true;
        }

        $pos = strpos($text, 'ESA Suites - ');
        if (stripos($text, '@extendedstay.com') !== false
            || stripos($text, 'www.extendedstayamerica.com') !== false
            || $pos < 100 && $pos > 0) {
            return true;
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {

        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if ($this->detectProvider($text, $parser->getHeader('from')) !== true) {
                continue;
            }

            foreach (self::$dictionary as $dict) {
                if (!empty($dict['Cancellation Letter']) && strpos($text, $dict['Cancellation Letter']) !== false
                    && !empty($dict['Check In Date']) && strpos($text, $dict['Check In Date']) !== false
                    && !empty($dict['Reservation Details']) && strpos($text, $dict['Reservation Details']) !== false
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        $subject = $parser->getSubject();

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            $this->HotelConfirmation($email, $text, $subject);
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function HotelConfirmation(Email $email, $text, $subject)
    {
        $h = $email->add()->hotel();

        if (preg_match("/{$this->opt($this->t('Reservation Cancellation'))}[ ]*\-[ ]*([A-Z\d]+)[ ]*$/u", $subject, $c)){
            $h->general()
                ->confirmation($c[1]);
        }

        $h->general()
            ->traveller($this->re("/{$this->t('Guest Name')}[ ]+([[:alpha:]][-.\/\'’[:alpha:] ]*[[:alpha:]])[ ]+{$this->t('Number of Persons')}/u", $text), true)
            ->cancellationNumber($this->re("/{$this->t('Cancellation No')}[ ]*\:[ ]*([A-Z\d]+)\n/u", $text));

        if ($this->re("/({$this->opt($this->t('Cancellation Letter'))})/u", $text) !== null){
            $h->setStatus('Cancelled')->setCancelled(true);
        }

        $h->hotel()
            ->name($this->re("/{$this->opt($this->t('Cancellation Letter'))}\n+[ ]+(.+)[ ]{2,}{$this->opt($this->t('Cancellation No'))}/u", $text))
            ->address(preg_replace("/(\n[ ]+)/", ", ", trim($this->re("/{$this->opt($this->t('Cancellation No'))}[ ]*\:[ ]*[A-Z\d]+\n\s*(.*?)\n{2,}/su", $text))))
            ->phone($this->re("/{$this->opt($this->t('Phone'))}[ ]+([\+\(\)\-\d ]+)[ ]+{$this->opt($this->t('Check In Date'))}/u", $text));

        $h->booked()
            ->checkIn(strtotime($this->re("/{$this->opt($this->t('Check In Date'))}[ ]+([[:alpha:]]+[ ]+[0-9]{1,2}\,[ ]*[0-9]{4})\n/u", $text)))
            ->checkOut(strtotime($this->re("/{$this->opt($this->t('Check Out Date'))}[ ]+([[:alpha:]]+[ ]+[0-9]{1,2}\,[ ]*[0-9]{4})\n/u", $text)))
            ->guests($this->re("/{$this->opt($this->t('Number of Persons'))}[ ]+([0-9]+)[ ]+{$this->opt($this->t('Adults'))}(?:\n|[ ]+)/u", $text));

        //Start Room Info
        $r = $h->addRoom();

        $ratesText = $this->re("/{$this->opt($this->t('Cancelled Charges'))}.+{$this->opt($this->t('Total'))}\n[ ]+{$this->opt($this->t('Total'))}\n+(.+\n)[ ]+{$this->opt($this->t('Total'))}/su", $text);

        $ratesArray = $this->createTable($ratesText, $this->rowColumnPositions($this->inOneRow($ratesText)));

        $rateNum = 0;

        $rateValues = [];

        $ratesNames = preg_split("/(\n+)/", $ratesArray[0], null, PREG_SPLIT_NO_EMPTY);

        $ratesValues = preg_split("/(\n+)/", $ratesArray[5], null, PREG_SPLIT_NO_EMPTY);

        foreach ($ratesNames as $ratesName){
            if ($ratesName !== null && $ratesValues[$rateNum] !== null){
                $rateValues[] = $ratesName . ": " . $ratesValues[$rateNum];

                $rateNum++;
            }
        }

        if (!empty($rateValues)){
            $r->setRate(implode('. ', $rateValues));
        }
        //End Room Info
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
                return str_replace(' ', '\s+', preg_quote($s, '/'));
            }, $field)) . ')';
    }


    private function re($re, $str, $c = 1)
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

    private function createTable(?string $text, $pos = []): array
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->rowColumnPositions($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k => $p) {
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

    private function rowColumnPositions(?string $row): array
    {
        $head = array_filter(array_map('trim', explode("|", preg_replace("/\s{2,}/", "|", $row))));
        $pos = [];
        $lastpos = 0;

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }

    private function inOneRow($text)
    {
        $textRows = array_filter(explode("\n", $text));

        if (empty($textRows)) {
            return '';
        }
        $length = [];

        foreach ($textRows as $key => $row) {
            $length[] = mb_strlen($row);
        }
        $length = max($length);
        $oneRow = '';

        for ($l = 0; $l < $length; $l++) {
            $notspace = false;

            foreach ($textRows as $key => $row) {
                $sym = mb_substr($row, $l, 1);

                if ($sym !== false && trim($sym) !== '') {
                    $notspace = true;
                    $oneRow[$l] = 'a';
                }
            }

            if ($notspace == false) {
                $oneRow[$l] = ' ';
            }
        }

        return $oneRow;
    }
}
