<?php

namespace AwardWallet\Engine\guruwalk\Email;

// TODO: delete what not use
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class CancelledBooking extends \TAccountChecker
{
    public $mailFiles = "guruwalk/it-629880362.eml";

    public $lang;
    public static $dictionary = [
        'en' => [
            'Hi '                         => 'Hi ',
            'adult'                       => 'adult',
            'Indications from your guide' => 'Indications from your guide',
            'Address'                     => 'Address',
            'Date and time:'              => 'Date and time:',
        ],
        'es' => [
            'Hi '          => 'Hola ',
            'adult'        => 'adult',
            'Address'      => 'Dirección',
            'Booking code' => 'Código de reserva',
        ],
        'it' => [
            'Hi '          => 'Ciao ',
            'adult'        => 'adult',
            'Address'      => 'Indirizzo',
            'Booking code' => 'Codice di prenotazione',
        ],
    ];

    private $detectFrom = "no-reply@guruwalk.com";
    private $detectSubject = [
        // en
        'You have cancelled your booking at GuruWalk',
    ];
    private $detectBody = [
        'en' => [
            'you have cancelled your booking',
        ],
    ];

    // Main Detects Methods
    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@.]guruwalk\.com$/", $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers["from"], $this->detectFrom) === false
            && strpos($headers["subject"], 'GuruWalk') === false
        ) {
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
        if (
            $this->http->XPath->query("//a[{$this->contains(['guruwalk'], '@href')}]")->length === 0
            && $this->http->XPath->query("//*[{$this->contains(['Guruwalk SL'])}]")->length === 0
        ) {
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
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("can't determine a language");

            return $email;
        }
        $this->parseEmailHtml($email);

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

    private function assignLang()
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict["Date and time:"]) && $this->http->XPath->query("//*[{$this->contains($dict['Date and time:'])}]")->length > 0) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function parseEmailHtml(Email $email)
    {
        $ev = $email->add()->event();

        $ev->type()
            ->event();

        $ev->general()
            ->noConfirmation()
            ->traveller($this->http->FindSingleNode("//text()[{$this->eq($this->t('GuruWalk:'))}]/preceding::text()[{$this->starts($this->t('Hi '))}][1]",
                null, true, "/^\s*{$this->opt($this->t('Hi '))}\s*([A-Z][[:alpha:] \-]{3,})\s*,\s*$/"), false)
            ->cancelled()
            ->status('Cancelled')
        ;

        $ev->place()
            ->name($this->http->FindSingleNode("//text()[{$this->eq($this->t('GuruWalk:'))}]/following::text()[normalize-space()][1]"))
        ;

        $ev->booked()
            ->start($this->normalizeDate($this->http->FindSingleNode("//text()[{$this->eq($this->t('Date and time:'))}]/following::text()[normalize-space()][1]")))
            ->noEnd()
        ;

        return true;
    }

    private function t($s)
    {
        if (!isset($this->lang, self::$dictionary[$this->lang], self::$dictionary[$this->lang][$s])) {
            return $s;
        }

        return self::$dictionary[$this->lang][$s];
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

    private function normalizeDate(?string $date): ?int
    {
        // $this->logger->debug('date begin = ' . print_r($date, true));
        $in = [
            // December 29 2023, 15:30h
            // Saturday, January 06 2024 at 11:00
            '/^\s*[[:alpha:]]+\s*[, ]+\s*([[:alpha:]]+)\s+(\d{1,2})\s+(\d{4})\s+[[:alpha:]]+\s+(\d{1,2}:\d{2})\s*$/ui',
        ];
        $out = [
            '$2 $1 $3, $4',
        ];

        $date = preg_replace($in, $out, $date);

        if (preg_match("/^\s*\d+\s+([[:alpha:]]+)\s+\d{4}/", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        // $this->logger->debug('date end = ' . print_r($date, true));

        return strtotime($date);
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
}
