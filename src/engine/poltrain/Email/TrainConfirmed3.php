<?php

namespace AwardWallet\Engine\poltrain\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class TrainConfirmed3 extends \TAccountChecker
{
    public $mailFiles = "poltrain/it-474438415.eml";
    public $pdfNamePattern = ".*pdf";
    public $lang = 'en';
    public $subject;

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if ($this->detectPdf($text) === true) {
                return true;
            }
        }

        return false;
    }

    public function detectPdf($text)
    {
        if (empty($text)) {
            return false;
        }

        if (strpos($text, 'BILET') !== false
            && stripos($text, 'ROZKŁAD JAZDY') !== false
            && stripos($text, 'Właściciel biletu:') !== false
            && stripos($text, 'Informacja o cenie') !== false
        ) {
            return true;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]europodroze.pl$/', $from) > 0;
    }

    public function ParseTrainPDF(Email $email, $text)
    {
        $this->logger->error(__METHOD__);

        $t = $email->add()->train();

        $t->general()
            ->confirmation($this->re("/Booking confirmation number\:\s*([A-Z\d]+)/u", $this->subject))
            ->date(strtotime($this->re("/Wystawiono\:\s*(\d+\.\d+\.\d{4}\s*\d+\:\d+)/", $text)))
            ->traveller($this->re("/BILET\s*([[:alpha:]][-.\'[:alpha:]\s]*[[:alpha:]])\n/", $text));

        $price = $this->re("/Cena\s*([\d\.\,]+\s*\D{1,3})\n/", $text);

        if (preg_match("/^(?<total>[\d\.\,]+)\s*(?<currency>\D{1,3})$/", $price, $m)) {
            $currency = $this->normalizeCurrency($m['currency']);
            $this->price[] = PriceHelper::parse($m['total'], $currency);
            $email->price()
                ->total(array_sum($this->price))
                ->currency($currency);
        }

        $segText = $this->re("/ROZKŁAD JAZDY\nPODRÓŻ TAM\s*POCIĄG\n(.+)\n+Informacja o cenie/su", $text);
        $segTable = $this->splitCols($segText);
        $year = date('Y', $t->getReservationDate());
        $s = $t->addSegment();

        $date = $this->re("/^\s*(\d+\.\d+)/", $segTable[0]) . '.' . $year;
        $depTime = $this->re("/^\s*(\d+\:\d+\s*A?P?M?)/iu", $segTable[1]);

        $s->departure()
            ->name(trim($segTable[2]))
            ->date(strtotime($date . ', ' . $depTime));

        $arrTime = $this->re("/^\s*(\d+\:\d+\s*A?P?M?)/iu", $segTable[5]);
        $s->arrival()
            ->name(trim($segTable[4]))
            ->date(strtotime($date . ', ' . $arrTime));

        if (preg_match("/^\s*(?<serviceName>[A-Z]{2,})\s*(?<number>\d+)/", $segTable[6], $m)) {
            $s->setServiceName($m['serviceName']);
            $s->setNumber($m['number']);
        }

        $ticket = $this->re("/Numer biletu\:\n+.+\s[\d\.\,]+\s*[A-Z]{3}\s+([A-Z\d]{10})\n/", $text);

        if (!empty($ticket)) {
            $t->addTicketNumber($ticket, false);
        }

        $cabin = $this->re("/[*]\s*[*]\s*{$s->getDepName()}\s*->\s*{$s->getArrName()}\s*[*]\s*[*]\s*(\d+)/", $text);

        if (!empty($cabin)) {
            $s->setCabin('KL.' . $cabin);
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->subject = $parser->getSubject();

        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (count($pdfs) > 0) {
            foreach ($pdfs as $pdf) {
                $text = \PDF::convertToText($parser->getAttachmentBody($pdf));
                $this->ParseTrainPDF($email, $text);
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

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

    protected function splitter($regular, $text)
    {
        $result = [];

        $array = preg_split($regular, $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        array_shift($array);

        for ($i = 0; $i < count($array) - 1; $i += 2) {
            $result[] = $array[$i] . $array[$i + 1];
        }

        return $result;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
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

    private function normalizeCurrency($string)
    {
        $string = trim($string);
        $currencies = [
            'GBP' => ['£'],
            'AUD' => ['A$'],
            'EUR' => ['€', 'Euro'],
            'USD' => ['US Dollar'],
            'PLN' => ['zł'],
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
}
