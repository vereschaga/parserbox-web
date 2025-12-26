<?php

namespace AwardWallet\Engine\poltrain\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class TrainConfirmed extends \TAccountChecker
{
    public $mailFiles = "poltrain/it-467184891.eml";
    public $subjects = [
        'PolishTrains - Booking confirmation number:',
    ];

    public $pdfNamePattern = ".*pdf";
    public $lang = 'en';
    public $date;

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@europodroze.pl') !== false) {
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

            if ($this->detectPdf($text) === true) {
                return true;
            }
        }

        if (count($pdfs) === 0
            && (!preg_match("/^Fwd/", $parser->getSubject()))
            && $this->http->XPath->query("//text()[contains(normalize-space(), 'PolishTrains')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Please note that this confirmation is not a ticket.'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('TICKET TYPE'))}]")->length > 0) {
            return true;
        }

        return false;
    }

    public function detectPdf($text)
    {
        if (empty($text)) {
            return false;
        }

        if (strpos($text, "Sprzedawca: PKP Intercity S.A.") === false) {
            return false;
        }

        if (strpos($text, 'Bilet') !== false
            && stripos($text, 'Informacje o podróży') !== false
            && stripos($text, 'Informacje rozliczeniowe') !== false
        ) {
            return true;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]europodroze.pl$/', $from) > 0;
    }

    public function ParseTrainHTML(Email $email)
    {
        $confirmation = $this->http->FindSingleNode("//text()[normalize-space()='Booking confirmation']/following::text()[normalize-space()][1]", null, true, "/^([A-Z\d]{6,})$/");
        $traveller = $this->normalizeTravellers($this->http->FindSingleNode("//text()[normalize-space()='LEAD PASSENGER']/following::text()[normalize-space()][1]"));

        if (empty($traveller)) {
            $traveller = $this->normalizeTravellers($this->http->FindSingleNode("//text()[normalize-space()='PASSENGERS DETAILS']/following::text()[normalize-space()][not(contains(normalize-space(), 'ADULT'))][1]"));
        }

        $xpath = "//text()[starts-with(normalize-space(), 'Start')]/ancestor::tr[1][contains(normalize-space(), 'Train')]/ancestor::table[1]/descendant::tr[contains(normalize-space(), ':')][not(contains(., 'Waiting time:'))]/preceding::text()[normalize-space()][1]/ancestor::tr[1]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $t = $email->add()->train();

            $t->general()
                ->confirmation($confirmation)
                ->traveller($traveller, true);

            $s = $t->addSegment();

            $s->departure()
                ->name(implode(", ", $this->http->FindNodes("./descendant::td[1]/descendant::text()[normalize-space()]", $root)));

            $s->arrival()
                ->name(implode(", ", $this->http->FindNodes("./descendant::td[2]/descendant::text()[normalize-space()]", $root)));

            $s->extra()
                ->duration($this->http->FindSingleNode("./descendant::td[3]", $root));

            //!ND in word Class symbol C, not correctly.
            $cabin = $this->http->FindSingleNode("./ancestor::table[2]/descendant::text()[contains(normalize-space(), 'Сlass:')][1]", $root);

            if (!empty($cabin)) {
                $s->extra()
                    ->cabin(trim($cabin, ':'));
            }

            $railInfo = $this->http->FindSingleNode("./descendant::td[4]", $root);

            if (preg_match("/^(?<serviceName>\D+)\s+(?<number>\d+)/", $railInfo, $m)) {
                $s->setNumber($m['number']);
                $s->setServiceName($m['serviceName']);
            }

            $depDate = $this->http->FindSingleNode("./following::tr[normalize-space()][1]/descendant::td[1]", $root);
            $s->departure()
                ->date($this->normalizeDate($depDate));
            $arrDate = $this->http->FindSingleNode("./following::tr[normalize-space()][1]/descendant::td[2]", $root);
            $s->arrival()
                ->date($this->normalizeDate($arrDate));

            $seatsInfo = $this->http->FindSingleNode("./ancestor::table[2]/descendant::text()[contains(normalize-space(), 'Coach number')][1]/ancestor::table[1]", $root);

            if (preg_match_all("/Coach\s*number\:\s*(\d+)/", $seatsInfo, $match)) {
                $carNumberArray = $match[1];
                $s->setCarNumber(implode(', ', array_unique($carNumberArray)));
            }

            if (preg_match_all("/Seat\s*number\:\s*(\d+)\//", $seatsInfo, $match)) {
                $seatArray = $match[1];
                $s->setSeats(array_unique($seatArray));
            }

            $status = $this->http->FindSingleNode("./ancestor::table[2]/descendant::text()[contains(normalize-space(), 'Status')][1]/following::text()[normalize-space()][1]", $root);

            if (!empty($status)) {
                $t->setStatus($status);
            }
        }
    }

    public function ParseTrainPDF(Email $email, $text)
    {
        $this->logger->error(__METHOD__);

        /*$this->logger->debug($text);
        $this->logger->debug('-------------------------------');*/

        $t = $email->add()->train();

        $t->general()
            ->confirmation($this->re("/NR REFERENCYJNY:\s*(\d+)/", $text))
            ->traveller($this->re("/^\s*([[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])\s+to Twój plan podróży\:/mu", $text), true)
            ->date(strtotime($this->re("/Dokument wygenerowano\:\s*(\d+\.\d+\.\d{4}\s*\d+\:\d+)/", $text)));

        if (preg_match_all("/NR BILETU\:\s*([A-Z\d]{8,})\s*\,/", $text, $m)) {
            $t->setTicketNumbers(array_unique($m[1]), false);
        }

        $total = $this->re("/Razem:\s+([\d\.\,]+)/", $text);

        if (!empty($total)) {
            $currency = 'PLN';
            $t->price()
                ->total(PriceHelper::parse($total, $currency))
                ->currency($currency);
        }

        $segText = $this->re("/\n\s+relacja.*miejsca\n+((?:.+\n){2,})\n\n\n*/", $text);
        //$this->logger->error($segText);
        $segTable = $this->splitCols($segText, [0, 3, 30, 60, 80, 105, 120, 127]);
        //$this->logger->warning(var_export($segTable, true));

        $s = $t->addSegment();

        if (preg_match_all("/(\d+)/", $segTable[7], $m)) {
            $s->setSeats($m[1]);
        }

        $s->departure()
            ->name(preg_replace("/\s+/", " ", $segTable[1]));
        $s->arrival()
            ->name(preg_replace("/\s+/", " ", $segTable[2]));

        if (preg_match("/PKP\s*(?<date>\d+\.\d+\.\d{4})\s*(?<serviceName>[A-Z\d]+)/", $segTable[4], $m)) {
            $s->setServiceName($m['serviceName']);
            $date = $m['date'];

            if (preg_match("/(?<depTime>\d+\:\d+)\s*\-\s*(?<arrTime>\d+\:\d+)/", $segTable[3], $m)) {
                $s->departure()
                    ->date(strtotime($date . ', ' . $m['depTime']));

                $s->arrival()
                    ->date(strtotime($date . ', ' . $m['arrTime']));
            }
        }

        if (preg_match("/[A-Z]{2,3}\s+(?<carNumber>\d+)\s*(?<number>\d+)/", $segTable[5], $m)) {
            $s->setNumber($m['number']);
            $s->setCarNumber($m['carNumber']);
        }
    }

    public function normalizeTravellers($travellers)
    {
        return preg_replace("/^(?:Mrs\.|Mr\.|Ms\.)/", "", $travellers);
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (count($pdfs) > 0) {
            foreach ($pdfs as $pdf) {
                $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

                if (stripos($text, 'Your journey plan') !== false) {
                } else {
                    $this->ParseTrainPDF($email, $text);
                }
            }
        } elseif (!preg_match("/^Fwd\:/", $parser->getSubject())) {
            $this->date = strtotime($parser->getDate());

            $price = $this->http->FindSingleNode("//text()[normalize-space()='Successful payment:']/following::text()[normalize-space()][1]");

            if (empty($price)) {
                $price = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'We have attached your mobile tickets to this email')]/following::text()[contains(normalize-space(), 'Сlass:')][1]/ancestor::tr[1]", null, true, "/\(\s*([\d\.\,]+\s*[A-Z]{3})\s*\)/");
            }

            if (preg_match("/^(?<total>[\d\.\,]+)\s*(?<currency>[A-Z]{3})$/", $price, $m)) {
                $email->price()
                    ->total(PriceHelper::parse($m['total'], $m['currency']))
                    ->currency($m['currency']);

                $fee = $this->http->FindSingleNode("//text()[normalize-space()='Successful payment:']/following::text()[starts-with(normalize-space(), 'including transaction fee')][1]", null, true, "/{$this->opt($this->t('including transaction fee'))}\s*([\d\.\,]+)/");

                if (!empty($fee)) {
                    $email->price()
                        ->fee('including transaction fee', PriceHelper::parse($fee));
                }
            }
            $this->ParseTrainHTML($email);
        } else {
            $email->setIsJunk(true);
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

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "starts-with(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(.), \"{$s}\")";
        }, $field));
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

    private function normalizeDate($str)
    {
        //$this->logger->debug('$str = ' . print_r($str, true));
        $year = date('Y', $this->date);

        $in = [
            "#^([\d\:]+)\s*\w+\s*(\d+\s*\w+)$#u", //16:13 Thu 29 Jun
        ];
        $out = [
            "$2 $year, $1",
        ];
        $str = preg_replace($in, $out, $str);
        //$this->logger->debug('$str = '.print_r( $str,true));

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
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
}
