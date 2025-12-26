<?php

namespace AwardWallet\Engine\jambojet\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class FlightPDF extends \TAccountChecker
{
    public $mailFiles = "jambojet/it-742913838.eml, jambojet/it-751990681.eml, jambojet/it-761859906.eml, jambojet/it-766364128.eml";
    public $subjects = [
        'Your Jambojet Reservation',
    ];

    public $pdfNamePattern = ".*pdf";

    public $lang = 'en';

    public static $dictionary = [
        'en' => [],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], 'jambojet.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (stripos($headers['subject'], $subject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (strpos($text, $this->t('Thank you for travelling with Jambojet')) !== false
                && strpos($text, $this->t('Confirmation & Itinerary')) !== false
                && strpos($text, $this->t('Itinerary Details')) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]jambojet\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));
            $this->FlightPDF($email, $text);
        }
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function FlightPDF(Email $email, $text)
    {
        $f = $email->add()->flight();

        $f->general()
            ->confirmation($this->re("/{$this->t('Booking Reference')}\s*([A-Z\d]+)\n+/", $text))
            ->status($this->re("/{$this->t('Booking Status')}\:\s*(\w+)\n+/", $text))
            ->date(strtotime($this->re("/{$this->t('Booking Date')}\s*(\w+\s*\d+\s*\w+\s*\d+)\s/", $text)));

        $flightInfo = $this->re("/{$this->t('Itinerary Details')}\n+(.+)\n{4,}\s+{$this->t('Passengers')}/s", $text);

        $flightNodes = $this->splitText($flightInfo, "/^([ ]*FLIGHT NO.\s+.+ARRIVAL\n+)/m", false);

        foreach ($flightNodes as $node) {
            $s = $f->addSegment();

            if (mb_substr($node, 5, 1) == " ") {
                $node = preg_replace("/^\s{2}(\s{6})/", "Number", $node);
            }

            $flightArray = $this->splitCols($node);

            $airInfo = $this->re("/((?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*\d{1,4})/", $flightArray[0]);

            if (preg_match("/(?<aName>(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]))\s*(?<fNumber>\d{1,4})/", $airInfo, $m)) {
                $s->airline()
                    ->name($m['aName'])
                    ->number($m['fNumber']);
                $seatsInfo = $this->re("/{$this->t('Passengers')}\n+(.+)\n+\s*{$this->t('Contact Details')}/s", $text);

                $seatsNodes = $this->splitText($seatsInfo, "/^([ ]*NAME\s+FLIGHT NUMBER.+SPECIAL BAGGAGE)/m", true);

                foreach ($seatsNodes as $node) {
                    $seat = $this->re("/{$m['aName']}{$m['fNumber']}\s+(\d{1,2}\D)\s+/", $node);

                    if (!empty($seat)) {
                        $passengerArray = $this->splitCols($node);
                        $s->extra()
                            ->seat($seat, false, false, preg_replace("/j?a?mbojet/", "", str_replace("\n", " ", $this->re("/{$this->t('NAME')}\n+([[:alpha:]][-.\/\n\'’[:alpha:] ]*[[:alpha:]])(?:\+|\s|\n|$)/su", $passengerArray[0]))));
                    }
                }
            }

            $depDate = $this->re("/^Date\n*(\d+\s*\w+\s*\d+)/", $flightArray[1]);
            $depTime = $this->re("/^Time\n*([\d\:]+)/", $flightArray[2]);
            $depName = preg_replace("/(\n+)/", " ", $this->re("/^Depart\s*From\n*(.+)$/s", $flightArray[3]));

            $s->departure()
                ->name($depName)
                ->date(strtotime($depDate . ", " . $depTime));

            $depCode = $this->re("/$depName\s*\((\D{3})\)/", $text);

            if (!empty($depCode)) {
                $s->departure()
                    ->code($depCode);
            } else {
                $s->departure()
                    ->noCode();
            }

            $arrDate = $this->re("/^Date\n*(\d+\s*\w+\s*\d+)/", $flightArray[4]);
            $arrTime = $this->re("/^Time\n*([\d\:]+)/", $flightArray[5]);
            $arrName = preg_replace("/(\n+)/", " ", $this->re("/^Arrive\s*At\n*(.+)$/s", $flightArray[6]));

            $s->arrival()
                ->name($arrName)
                ->date(strtotime($arrDate . ", " . $arrTime));

            $arrCode = $this->re("/$arrName\s*\((\D{3})\)/", $text);

            if (!empty($arrCode)) {
                $s->arrival()
                    ->code($arrCode);
            } else {
                $s->arrival()
                    ->noCode();
            }
        }

        $priceInfo = $this->re("/{$this->t('Receipts & Payment Details')}\n+(.+)\n{4,}\s*{$this->t('MODE OF')}/s", $text);

        $priceArray = $this->splitCols($priceInfo, [0, 50]);

        $totalInfo = $this->re("/{$this->t('TOTAL')}\n+(\D{1,3}\s*[\d\.\,\']+)/", $priceArray[0]);

        if (preg_match("/^(?<currency>\D{1,3})\s*(?<price>[\d\.\,\']+)$/", $totalInfo, $m)) {
            $f->price()
                ->currency($m['currency'])
                ->total(PriceHelper::parse($m['price'], $m['currency']));

            foreach ($priceArray as $col) {
                $feesNodes = preg_split("/\n/", ($this->re("/{$this->t('Fare')}\s*\D{1,3}\s*[\d\.\,\']+\n+(.+)\n{2,}\s*[\w|$]?/s", $col)));

                foreach ($feesNodes as $root) {
                    $feeName = $this->re("/^(.+)\s+\D{1,3}\s*[\d\.\,\']+$/", $root);
                    $feeSum = $this->re("/\D{1,3}\s*([\d\.\,\']+)$/", $root);

                    if (!empty($feeName) && !empty($feeSum)) {
                        $f->price()
                        ->fee(($feeName), PriceHelper::parse($feeSum, $m['currency']));
                    }
                }
            }
        }

        $passengersInfo = $this->re("/{$this->t('Passengers')}\n+(.+)\n+\s*{$this->t('Contact Details')}/s", $text);

        $passengersNodes = $this->splitText($passengersInfo, "/^([ ]*NAME\s+FLIGHT NUMBER.+SPECIAL BAGGAGE)/m", true);

        foreach ($passengersNodes as $node) {
            $passengerArray = $this->splitCols($node);

            $traveller = $this->re("/{$this->t('NAME')}\n+([[:alpha:]][-.\/\n\'’[:alpha:] ]*[[:alpha:]])(?:\+|\s|\n|$)/su", $passengerArray[0]);
            $f->addTraveller(preg_replace("/j?a?mbojet/", "", str_replace("\n", " ", $traveller)));

            $infant = $this->re("/\+\s*([[:alpha:]][-.\/\n\'’[:alpha:] ]*[[:alpha:]])(?:\+|\s|\n|$)[jambojet]?/u", $passengerArray[0]); //it-766364128.eml

            if ($infant !== null) {
                $f->addInfant(preg_replace("/j?a?mbojet/", "", str_replace("\n", " ", $infant)));
            }
        }
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function splitCols($text, $pos = false)
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->rowColsPos($rows[0]);
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

    private function rowColsPos($row)
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
