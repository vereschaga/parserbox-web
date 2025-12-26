<?php

namespace AwardWallet\Engine\cheaptickets\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class FlightPDF extends \TAccountChecker
{
    public $mailFiles = "cheaptickets/it-818475439.eml, cheaptickets/it-818905639.eml, cheaptickets/it-821518859.eml, cheaptickets/it-821642329.eml";
    public $lang = 'en';
    public $pdfNamePattern = ".*pdf";

    public $subjects = [
        'Your E-ticket(s)',
    ];

    public $detectLang = [
        "en" => ["Booking number", "Flight number:"],
        "de" => ["Buchungsnummer:", "Flugnummer:"],
    ];

    public static $dictionary = [
        "en" => [
        ],

        "de" => [
            "Booking number"                               => "Buchungsnummer:",
            "The E-ticket number is valid for all flights" => "Die E-Ticket-Nummer ist für alle Flüge gültig",
            "Departure"                                    => "Abreise",
            "Arrival"                                      => "Ankunft",
            "Online check-in number"                       => "Online-Check-in-Nummer",
            "Flight time:"                                 => "Flugzeit:",
            "Flight number:"                               => "Flugnummer:",
            "Class:"                                       => "Klasse:",
            //"E-ticket number:" => ""
            'Questions? We’re here to help!' => 'Haben Sie Fragen? Wir sind hier, um zu helfen!',
        ],
    ];

    public static $providers = [
        'cheaptickets' => [
            'from' => ['@t.cheaptickets.sg'],
            'body' => ['CT_sg_logo', 'CT_ch_logo'],
        ],

        'budgetair' => [
            'from' => ['@t.budgetair.com'],
            'body' => ['BudgetAir_logo_S', 'BudgetAir_com_au_logo', 'BudgetAir_co_uk_Logo_S'],
        ],

        'vayama' => [
            'from' => ['@t.vayama.ie'],
            'body' => ['Vayama_logo_S'],
        ],

        'flugladen' => [
            'from' => ['@t.flugladen.de'],
            'body' => ['Flugladen_de_logo_S'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        foreach (self::$providers as $provArray) {
            if (isset($headers['from']) && preg_match("/{$this->opt($provArray['from'])}/", $headers['from'])) {
                foreach ($this->subjects as $subject) {
                    if (preg_match("/{$this->opt($subject)}/", $headers['subject'])) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    public function detectProviders()
    {
        foreach (self::$providers as $code => $provArray) {
            if ($this->http->XPath->query("//img[{$this->containsIMG($provArray['body'])}]")->length > 0) {
                return $code;
            }
        }

        return null;
    }

    public function assignLang($text)
    {
        foreach ($this->detectLang as $lang => $array) {
            foreach ($array as $word) {
                if (stripos($text, $word) !== false) {
                    $this->lang = $lang;

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

            $this->assignLang($text);

            if (empty($this->detectProviders())) {
                return false;
            }

            if (strpos($text, $this->t('Booking number')) !== false
                && strpos($text, $this->t('Online check-in number')) !== false
                && strpos($text, $this->t('Flight number:')) !== false
                && strpos($text, $this->t('Questions? We’re here to help!')) !== false
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]twiltravel\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $provCode = $this->detectProviders();

        if (!empty($provCode)) {
            $email->setProviderCode($provCode);
        }

        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            $this->assignLang($text);

            $this->ParseFlightPDF($email, $text);
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function ParseFlightPDF(Email $email, $text): void
    {
        $f = $email->add()->flight();

        if (preg_match("/(?<desc>{$this->opt($this->t('Booking number'))})\n\s+(?:{$this->opt($this->t('E-ticket'))}\s*)(?<confNumber>[A-Z]{2,4}\-\d{4,})\s*\n/iu", $text, $m)) {
            $f->general()->confirmation($m['confNumber'], $m['desc']);
        }

        if (preg_match_all("/[ ]*(?<traveller>[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])\n*(?:\s+{$this->t('E-ticket number:')}.*\n*)?(?:\s*{$this->t('The E-ticket number is valid for all flights')}\n*)?\s*Outbound\,/", $text, $m)) {
            $f->general()
                ->travellers(array_unique($m['traveller']));
        }

        if (preg_match_all("/{$this->t('E-ticket number:')}\s*([\d\-]+)/", $text, $m)) {
            foreach ($m[1] as $ticket) {
                $pax = $this->re("/[ ]*(?<traveller>[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])\n*\s*{$this->t('E-ticket number:')}\s*{$ticket}/", $text);

                if (empty($pax)) {
                    $pax = $this->re("/\s*{$this->t('E-ticket number:')}\s*{$ticket}\n*[ ]*(?<traveller>[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])\n*/", $text);
                }

                if (!empty($pax)) {
                    $f->addTicketNumber($ticket, false, $pax);
                } else {
                    $f->addTicketNumber($ticket, false);
                }
            }
        }

        $flightText = '';

        if (count($f->getTravellers()) > 1) {
            $flightText = $this->re("/^([ ]*{$this->t('Departure')}.+)\n\n\n\n.+{$f->getTravellers()[1][0]}/msu", $text);
        } else {
            $flightText = $this->re("/^([ ]*{$this->t('Departure')}.+)\n\n\n\n.+{$this->opt($this->t('Questions? We’re here to help!'))}/msu", $text);
        }

        if (!$flightText) {
            $this->logger->debug('Flight segments not found!');

            return;
        }

        $flightParts = $this->splitText($flightText, "/^([ ]*{$this->t('Departure')}.*)/m", true);

        foreach ($flightParts as $fPart) {
            $s = $f->addSegment();

            $flightTable = $this->splitCols($fPart);

            if (count($flightTable) !== 3) {
                $firstPos = strlen($this->re("/^(.+){$this->opt($this->t('Arrival'))}/m", $fPart)) - 1;
                $secondPos = strlen($this->re("/^(.+){$this->opt($this->t('Online check-in number'))}/m", $fPart)) - 1;

                if ($firstPos == -1) {
                    $firstPos = 30;
                    $secondPos = 62;
                }
                $flightTable = $this->splitCols($fPart, [0, $firstPos, $secondPos]);
            }

            if (preg_match("/(?:{$this->t('Online check-in number')})?\n+\s*(?<segConf>[A-Z\d]{6})\n+(.+)\n+\s*{$this->t('Flight time:')}\s+(?<duration>\d.+)\n+\s*{$this->t('Flight number:')}\s+(?<aName>(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]))(?<fNumber>\d{2,4})\n+\s*{$this->t('Class:')}\s*(?<cabin>.+)\n/", $flightTable[2], $m)) {
                $s->airline()
                    ->name($m['aName'])
                    ->number($m['fNumber']);

                $s->extra()
                    ->duration($m['duration'])
                    ->cabin($m['cabin']);

                $s->setConfirmation($m['segConf']);
            }

            if (preg_match("/{$this->t('Departure')}\n+\s*(?<depTime>[\d\:]+\s*A?P?M?)\n+\s*(?<depDate>.+\n*\s*\d{4})\n+\s*(?<depName>.+\n+.+\n+(?:.+\b\n.+\b\n|.+\b\n)?)/", $flightTable[0], $m)) {
                $depName = str_replace("\n", " ", $m['depName']);
                $depName = preg_replace("/[ ]{2,}/", " ", $depName);

                $s->departure()
                    ->name($depName)
                    ->noCode()
                    ->date($this->normalizeDate($m['depDate'] . ', ' . $m['depTime']));
            }

            if (preg_match("/(?:{$this->t('Arrival')}|{$this->t('E-ticket')})?\n+\s*(?<arrTime>[\d\:]+\s*A?P?M?)\n(?<arrDate>.+\n*\d{4})\n+(?<arrName>.+\n+.+\n+(?:.+\b\n)?)/", $flightTable[1], $m)) {
                $arrName = str_replace("\n", " ", $m['arrName']);
                $arrName = preg_replace("/[ ]{2,}/", " ", $arrName);

                $s->arrival()
                    ->name($arrName)
                    ->noCode()
                    ->date($this->normalizeDate($m['arrDate'] . ', ' . $m['arrTime']));
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

    public static function getEmailProviders()
    {
        return array_keys(self::$providers);
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

    private function normalizeDate($str)
    {
        $in = [
            "#^(\w+)\s+(\d+)\,\s+(\d{4})\,\s*([\d\:]+\s*A?P?M?)$#su", //January 30, 2025, 07:00
            "#^(\d+)\.\s*(\w+\s*\d+)\,\s+([\d\:]+\s*A?P?M?)$#su", //16. Dezember 2024, 11:55
        ];
        $out = [
            "$2 $1 $3, $4",
            "$1 $2, $3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function normalizeCurrency($string): ?string
    {
        $string = trim($string, '+-');
        $string = trim($string);
        $currencies = [
            'GBP' => ['£'],
            'EUR' => ['€', 'Euro', 'EU €'],
            'INR' => ['Rs.', 'Rs'],
            'AUD' => ['AU $'],
            'CAD' => ['CA $', 'Canadian dollars', 'CA $', 'Dollars canadiens'],
            'JPY' => ['円(日本)', 'JP ¥'],
            'USD' => ['US $', 'US dollars'],
        ];

        foreach ($currencies as $code => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $code;
                }
            }
        }

        if (preg_match("/\(([A-Z]{3})\)/", $string, $m)) {
            return $m[1];
        }

        if ($string === '(Canadian dollars)') {
            return 'CAD';
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

    private function containsIMG($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "contains(@src, \"{$s}\")";
        }, $field));
    }
}
