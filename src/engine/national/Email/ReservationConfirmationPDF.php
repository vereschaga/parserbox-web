<?php

namespace AwardWallet\Engine\national\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ReservationConfirmationPDF extends \TAccountChecker
{
    public $mailFiles = "national/it-795285943.eml";
    public $lang = 'en';
    public $pdfNamePattern = ".*pdf";
    public $company;

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

            if (strpos($text, 'National Car Rental') !== false
                && (strpos($text, 'Executive Area') !== false || strpos($text, 'Premium Elite') !== false)
                && strpos($text, 'PICK UP & RETURN LOCATION') !== false
                && strpos($text, 'PICK UP DATE & TIME') !== false
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]nationalcar\.com$/', $from) > 0;
    }

    public function ParseRentalPDF(Email $email, $text)
    {
        $r = $email->add()->rental();

        $driverInfo = $this->re("/^([ ]+DRIVER INFORMATION.+)\n[ ]*(?:ACCOUNT NAME|LOCATION)/msu", $text);
        $driverTable = $this->splitCols($driverInfo);

        $pax = $this->re("/DRIVER INFORMATION\n+\s*([[:alpha:]][-.\/\'’[:alpha:] ]*[[:alpha:]])/", $driverTable[0]);
        $account = $this->re("/EMERALD CLUB\n*NUMBER\n+(\d{5,})/su", $driverTable[1]);

        if (!empty($account)) {
            $r->addAccountNumber($account, false, $pax);
        }

        $r->general()
            ->traveller($pax)
            ->confirmation($this->re("/{$this->opt($this->t('Confirmation #'))}\s*(\d{6,})/", $text));

        if (preg_match("/YOUR ESTIMATED TOTAL\s+(?<currency>\D{1,3})\s+(?<total>[\d\.\,\']+)/", $text, $m)) {
            $currency = $m['currency'];
            $r->price()
                ->total(PriceHelper::parse($m['total'], $currency))
                ->currency($currency);

            $feesText = $this->re("/FEES\n+(.+)\n+[ ]+YOUR ESTIMATED TOTAL/su", $text);
            $feeRows = $this->splitText($feesText, "/^([ ]+.+(?:\b|\))\s+\D{1,3}\s+[\d\.\,\']+\n)/m", true);

            foreach ($feeRows as $feeRow) {
                if (preg_match("/^[ ]+(?<feeName>.+(?:\b|\)))\s+\D{1,3}\s+(?<feeSumm>[\d\.\,\']+)\n/", $feeRow, $m)) {
                    $r->price()
                        ->fee($m['feeName'], PriceHelper::parse($m['feeSumm'], $currency));
                }
            }
        }

        $year = $this->re("/Week\s+of\s+\w+\s+\d+\,\s*(\d{4})/", $text);

        $rentalText = $this->re("/^([ ]+(?:Executive Area|Premium Elite).+)\n\s+ADD ONS/ms", $text);
        $textPosition = strlen($this->re("/^([ ]+.+)PICK UP & RETURN LOCATION/m", $rentalText));
        $rentalColumns = $this->splitCols($rentalText, [0, $textPosition]);

        if (preg_match("/(?:Executive Area|Premium Elite)\n+\s+(?<type>.+)\n\s*(?<model>.+\n*\s+.+)\n/", $rentalColumns[0], $m)) {
            $r->car()
                ->type($m['type'])
                ->model(preg_replace("/\s+/", " ", $m['model']));
        }

        if (preg_match("/PICK UP & RETURN LOCATION\n+(?<depArrName>.+(?:\n+.+)?)\n+PICK UP DATE & TIME\s+RETURN DATE & TIME\n+(?<pickUpDate>\w+\,\s*\w+\s*\d*)\s*at\s*(?<pickUpTime>[\d\:]+\s*A?P?M)\s+(?<dropOffDate>\w+\,\s*\w+\s*\d*)\s*at\s*(?<dropOffTime>[\d\:]+\s*A?P?M)/", $rentalColumns[1], $m)) {
            $r->pickup()
                ->location(str_replace("\n", " ", $m['depArrName']))
                ->date(strtotime($m['pickUpDate'] . ' ' . $year . ', ' . $m['pickUpTime']));

            $r->dropoff()
                ->location(str_replace("\n", " ", $m['depArrName']))
                ->date(strtotime($m['dropOffDate'] . ' ' . $year . ', ' . $m['dropOffTime']));
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);
        $this->emailSubject = $parser->getSubject();

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            $this->ParseRentalPDF($email, $text);
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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
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
