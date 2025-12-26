<?php

namespace AwardWallet\Engine\seajets\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class Ticket extends \TAccountChecker
{
    public $mailFiles = "seajets/it-107455135.eml, seajets/it-107941670.eml, seajets/it-390240897.eml";

    public $pdfNamePattern = ".*\.pdf";
    public $pdfInfo;

    public $lang;
    public static $dictionary = [
        'en' => [
            'DATE/TIME' => 'DATE/TIME',
            "FROM"      => ["FROM", "FROM:"],
            "TO"        => ["TO", "TO:"],
        ],
    ];

    private $detectFrom = "reservations@seajets.gr";
    private $detectSubject = [
        // en
        'TICKET | BOARDING CARD ', // TICKET | BOARDING CARD -> PAPOUTSIS ALKIVIADIS (G1114218)
    ];

    private $detectBody = [
        'en' => [
            'Problem with QRCODE? Get it now by clicking here',
        ],
    ];

    private $detectBodyPdf = [
        'en' => [
            'TICKET / BOARDING PASS',
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers["subject"], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[{$this->contains(['.seajets.gr', '.seajets.com'], '@href')}]")->length === 0) {
            return false;
        }

        foreach ($this->detectBody as $detectBody) {
            if ($this->http->XPath->query("//*[{$this->contains($detectBody)}]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug("can't determine a language");

            return $email;
        }

        if ($this->http->FindSingleNode("//td[not(.//td)][" . $this->starts($this->t("VESSEL")) . "]/following::tr[not(.//tr)][normalize-space()][1][td[1][" . $this->eq($this->t("FROM")) . "] and td[3][" . $this->eq($this->t("TO")) . "]]")) {
            $this->parseEmailHtml($email);
        } else {
            $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

            foreach ($pdfs as $pdf) {
                $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

                if ($this->detectPdf($text) !== true) {
                    continue;
                }

                $this->parseEmailPdf($email, $text);
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
        return 2 * count(self::$dictionary); // html + pdf
    }

    public function detectPdf($text)
    {
        if (stripos($text, 'SEAJETS ΑΘΕΩΡΗΤΑ') === false) {
            return false;
        }

        foreach ($this->detectBodyPdf as $lang => $detectBody) {
            if ($this->containsText($text, $detectBody) === true) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function parseEmailHtml(Email $email)
    {
        $ferry = $email->add()->ferry();

        // General
        $ferry->general()
            ->confirmation($this->getTd($this->t("BOOKING REF"), 2, "/^\s*([\dA-Z]{5,})\s*$/"),
                $this->http->FindSingleNode("//td[not(.//td) and " . $this->eq($this->t("BOOKING REF")) . "]"))
        ;

        $traveller = $this->getTd($this->t("PASSENGER NAME"), 1, "/^\s*([[:alpha:] \-]+)\s*$/");

        if (!empty($traveller)) {
            $ferry->general()
                ->traveller($traveller);
        }

        // Ticket
        $ferry->setTicketNumbers([$this->getTd($this->t("TICKET"), 1, "/^\s*([\dA-Z]{5,})\s*$/")], false);
        // Segments
        $s = $ferry->addSegment();

        $car = $this->getTd($this->t("TYPE"), 1, "/^{$this->opt($this->t('CAR'))}\s*\d+$/");

        if (!empty($car)) {
            $vehicle = $s->addVehicle();
            $vehicle->setType($car);
        }

        $s->departure()
            ->name($this->getTd($this->t("FROM"), 1))
            ->date($this->normalizeDate($this->getTd($this->t("DATE/TIME"), 1)))
        ;
        $s->arrival()
            ->name($this->http->FindSingleNode("//tr[not(.//tr)][td[3][" . $this->eq($this->t("TO")) . "]]/following-sibling::tr/td[last()]"))
            ->noDate()
        ;
        $s->extra()
            ->vessel($this->http->FindSingleNode("//tr[not(.//tr)][td[1][" . $this->eq($this->t("FROM")) . "]]/preceding::td[not(.//td)][" . $this->starts($this->t("VESSEL")) . "][1]",
                null, true, "/" . $this->opt($this->t("VESSEL")) . "\s*(.+)/"))
        ;

        $category = str_replace('-', '', $this->getTd($this->t("CATEGORY"), 2));
        $seat = str_replace('-', '', $this->getTd($this->t("SEAT"), 3));

        if (!empty($category) && !empty($seat)) {
            $s->booked()
                ->accommodation($category . ', ' . $seat)
            ;
        }

        $adult = $this->getTd($this->t("AGE"), 3, "/(" . $this->opt($this->t("AD")) . ")/");
        $child = $this->getTd($this->t("AGE"), 3, "/(" . $this->opt($this->t("CLD")) . ")/");

        if (!empty($adult) && count($ferry->getTravellers()) === 1) {
            $s->booked()
                ->adults(1);
        }

        if (!empty($child) && count($ferry->getTravellers()) === 1) {
            $s->booked()
                ->kids(1);
        }

        return true;
    }

    private function parseEmailPdf(Email $email, ?string $textPdf = null)
    {
        $ferry = $email->add()->ferry();

        // General
        $ferry->general()
            ->noConfirmation()
            ->traveller($this->re("/\n +(?:\S* ){0,5}" . $this->opt($this->t("PASSENGER")) . "\n\s*(?<name>[[:alpha:] \-]+)\n\s*\n *SEAJETS/u", $textPdf))
        ;

        // Ticket
        $ferry->setTicketNumbers(str_replace(' ', '', [$this->re("/" . $this->opt($this->t("- TICKET NUMBER:")) . " *([A-Z]+ ?\d+)\s*\n/", $textPdf)]), false);

        // Segments
        $s = $ferry->addSegment();
        $route = $this->re("/\n.{0,20}" . $this->opt($this->t("ITINERARY")) . ".*\n {0,15}(.+?) {2,}/u", $textPdf);

        if (preg_match("/(.+)\/(.+)/", $route, $m)) {
            $s->departure()
                ->name($m[1])
            ;
            $s->arrival()
                ->name($m[2])
            ;
        }

        $info = $this->re("/\n.*" . $this->opt($this->t("VESSEL")) . ".*" . $this->opt($this->t("DEPARTURE")) . ".*\s*\n(.+\n.*\n)/u", $textPdf);

        if (preg_match("/^\s*(.+?) {2,}(.+) {2,}(.+?)\s*\n(.*)/", $info, $m)) {
            $s->departure()
                ->date($this->normalizeDate($m[2]))
            ;
            $s->arrival()
                ->noDate()
            ;

            if (preg_match("/^ {0,5}(\S.+?)(?: {2,}|$)/u", $m[4], $dves)) {
                $m[1] .= ' ' . $dves[1];
            }
            $s->extra()
                ->vessel($m[1]);

            if (preg_match("/\n(.+) {5}.*" . $this->opt($this->t("TYPE")) . "\s*\n(.+)\n/u", $textPdf, $mat)) {
                if (preg_match("/^.{" . mb_strlen($mat[1]) . ",} +([A-Z]+)\s*$/u", $mat[2], $mat2)
                ) {
                    $s->booked()
                        ->accommodation($mat2[1] . ', ' . $m[3]);
                }
            }
        }

        $adult = $this->getTd($this->t("AGE"), 3, "/(" . $this->opt($this->t("AD")) . ")/");
        $child = $this->getTd($this->t("AGE"), 3, "/(" . $this->opt($this->t("CLD")) . ")/");

        if (!empty($adult) && count($ferry->getTravellers()) === 1) {
            $s->booked()
                ->adults(1);
        }

        if (!empty($child) && count($ferry->getTravellers()) === 1) {
            $s->booked()
                ->kids(1);
        }

        return true;
    }

    private function assignLang(): bool
    {
        foreach (self::$dictionary as $lang => $words) {
            if (isset($words['DATE/TIME'])) {
                if ($this->http->XPath->query("//*[{$this->contains($words['DATE/TIME'])}]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function t($s)
    {
        if (!isset($this->lang, self::$dictionary[$this->lang], self::$dictionary[$this->lang][$s])) {
            return $s;
        }

        return self::$dictionary[$this->lang][$s];
    }

    private function getTd($fieldName, $column, $regexp = null)
    {
        return $this->http->FindSingleNode("//tr[not(.//tr)][td[{$column}][" . $this->eq($fieldName) . "]]/following-sibling::tr[1]/td[{$column}]",
            null, true, $regexp);
    }

    private function containsText($text, $needle): bool
    {
        if (empty($text) || empty($needle)) {
            return false;
        }

        if (is_array($needle)) {
            foreach ($needle as $n) {
                if (stripos($text, $n) !== false) {
                    return true;
                }
            }
        } elseif (is_string($needle) && stripos($text, $needle) !== false) {
            return true;
        }

        return false;
    }

    // additional methods
    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) {
            return 'contains(' . $text . ',"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function normalizeDate(?string $date): ?int
    {
        $this->logger->debug('date begin = ' . print_r($date, true));

        if (empty($date)) {
            return null;
        }

        $in = [
            // 20/06/2021
            '/^\s*(\d{1,2})\/(\d{2})\/(\d{4})\s*$/iu',
            // 20/06/2021 11:10
            '/^\s*(\d{1,2})\/(\d{2})\/(\d{4})\s+(\d{1,2}:\d{2})\s*$/iu',
        ];
        $out = [
            '$1.$2.$3',
            '$1.$2.$3, $4',
        ];

        $date = preg_replace($in, $out, $date);

//        $date = $this->dateTranslate($date);

        return strtotime($date);
    }

    private function dateTranslate($date)
    {
        if (preg_match('/[[:alpha:]]+/iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("/$monthNameOriginal/i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function opt($field, $delimiter = '/')
    {
        $field = (array) $field;

        if (empty($field)) {
            $field = ['false'];
        }

        return '(?:' . implode("|", array_map(function ($s) use ($delimiter) {
            return str_replace(' ', '\s+', preg_quote($s, $delimiter));
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
}
