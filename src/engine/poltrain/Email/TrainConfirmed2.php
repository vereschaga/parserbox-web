<?php

namespace AwardWallet\Engine\poltrain\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class TrainConfirmed2 extends \TAccountChecker
{
    public $mailFiles = "poltrain/it-475026112.eml";
    public $pdfNamePattern = ".*pdf";
    public $lang = 'en';
    public $price = [];
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

        if (strpos($text, "Przewoźnik POLREGIO") === false) {
            return false;
        }

        if (strpos($text, 'Your journey plan') !== false
            && stripos($text, 'Train REGIO No.') !== false
            && stripos($text, 'Przedstawiony rozkład jazdy dotyczy połączenia wybranego') !== false
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
            ->date(strtotime($this->re("/Wystawiono dnia\:\s*(\d+\:\d+\s*\d+\.\d+\.\d{4})/", $text)));

        $ticketInfo = $this->re("/\n(\s*Ważny.+)\n+\s*Your journey plan/s", $text);
        $raw = $this->re("/(\s*Wystawiono dnia:)/", $ticketInfo);
        $colPos = stripos($raw, 'Wystawiono dnia:');
        $ticketTable = $this->splitCols($ticketInfo, [0, $colPos - 1]);

        if (preg_match("/\n+\s*(?<ticket>[A-Z\d]{8,})\n+\s*(?<traveller>[[:alpha:]][-.\'[:alpha:]\s]*[[:alpha:]])\n+Wystawiono dnia\:/", $ticketTable[1], $m)) {
            $t->general()
                ->traveller($m['traveller']);

            $t->setTicketNumbers([$m['ticket']], false);
        }

        $price = $this->re("/Opłata za przejazd:\s*([\d\.]+\s*\D{1,3})\n/", $ticketTable[0]);

        if (preg_match("/^(?<total>[\d\.]+)\s*(?<currency>\D{1,3})$/", $price, $m)) {
            $currency = $this->normalizeCurrency($m['currency']);
            $this->price[] = PriceHelper::parse($m['total'], $currency);
            $email->price()
                ->total(array_sum($this->price))
                ->currency($currency);
        }

        $segText = $this->re("/\s*Your journey plan(\n+.+)\s+Przedstawiony rozkład jazdy dotyczy połączenia wybranego/su", $text);
        $segments = array_filter(splitter("/^(\n)/mu", $segText));

        if (preg_match("/Ważny od\s*[\d\:]+\s*(?<depDay>[\d\.]+\d{4})\s*do\s*[\d\:]+\s*(?<arrDay>[\d\.]+\d{4})/", $text, $m)
        || preg_match("/Ważny\s*(?<depDay>[\d\.]+\d{4})\n/", $text, $m)) {
            if (!isset($m['arrDay'])) {
                $m['arrDay'] = $m['depDay'];
            }

            foreach ($segments as $segment) {
                if (preg_match("/\n*\s*(?<depTime>[\d\:]+)\s*(?<depName>.+)\s+(?<arrTime>[\d\:]+)\s*(?<arrName>.+)\n\s*Train\s*(?<serviceName>.+)\s+No\.\s*(?<number>\d+)/", $segment, $match)) {
                    $s = $t->addSegment();

                    $s->setServiceName($match['serviceName']);
                    $s->setNumber($match['number']);

                    $s->departure()
                        ->name($match['depName'])
                        ->date(strtotime($m['depDay'] . ', ' . $match['depTime']));

                    $s->arrival()
                        ->name($match['arrName'])
                        ->date(strtotime($m['arrDay'] . ', ' . $match['arrTime']));

                    $s->extra()
                        ->cabin($this->re("/\s+(Klasa\s*\d+)\s*[A-Z]\n+\s*Przewoźnik/", $ticketTable[0]));
                }
            }
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
