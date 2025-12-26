<?php

namespace AwardWallet\Engine\guruwalk\Email;

// TODO: delete what not use
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class Booking extends \TAccountChecker
{
    public $mailFiles = "guruwalk/it-628642734.eml, guruwalk/it-633047128.eml, guruwalk/it-633487029.eml";

    public $lang;
    public static $dictionary = [
        'en' => [
            'Hi '                         => 'Hi ',
            'adult'                       => 'adult',
            'Indications from your guide' => 'Indications from your guide',
            'Address'                     => 'Address',
            'Booking code'                => 'Booking code',
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
        'Details of your confirmed booking at GuruWalk',
        // es
        'Detalles de tu reserva confirmada en GuruWalk',
        // it
        'Dettagli della tua prenotazione confermata su Guruwalk',
    ];
    private $detectBody = [
        'en' => [
            'Your booking has been confirmed!',
        ],
        'es' => [
            '¡Tu reserva ha sido confirmada!',
        ],
        'it' => [
            'La tua prenotazione è stata confermata!',
        ],
    ];

    // Main Detects Methods
    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@.]guruwalk\.com\b/", $from) > 0;
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
            if (!empty($dict["Address"]) && $this->http->XPath->query("//*[{$this->contains($dict['Address'])}]")->length > 0) {
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

        $conf = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking code'))}]/following::text()[normalize-space()][1]",
            null, true, "/^\s*([[:alpha:]\d]{5,})\s*$/u");

        if (empty($conf) && empty($this->http->FindSingleNode("(//text()[{$this->contains($this->t('Booking code'))}])[1]"))) {
            $ev->general()
                ->noConfirmation();
        } else {
            $ev->general()
                ->confirmation($conf);
        }

        $traveller = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Hi '))}]",
            null, true, "/^\s*{$this->opt($this->t('Hi '))}\s*([A-Z][[:alpha:] \-]*?)\s*,\s*$/");

        if (strlen($traveller) > 3) {
            $ev->general()
                ->traveller($traveller, false);
        }

        $ev->place()
            ->name($this->http->FindSingleNode("//img[contains(@src, 'calendar')]/ancestor::*[normalize-space()][1]/ancestor::*[preceding-sibling::*[.//img][not(normalize-space())]][1]/descendant::td[not(.//td)][1]"))
            ->address($this->http->FindSingleNode("//img[contains(@src, 'place_')]/ancestor::tr[1]"))
        ;
        $info = $this->http->FindSingleNode("(//node()[preceding::text()[normalize-space()][1][{$this->eq($this->t('Indications from your guide'))}]][following::text()[normalize-space()][1][{$this->eq($this->t('Address'))}]])[1]");

        if (!empty($info)) {
            $ev->general()
                ->notes($info);
        }

        $ev->booked()
            ->start($this->normalizeDate($this->http->FindSingleNode("//img[contains(@src, 'calendar_')]/ancestor::tr[1]")))
            ->noEnd()
        ;
        $guests = $this->http->FindSingleNode("//img[contains(@src, 'people_')]/ancestor::tr[1]");

        if (preg_match("/^\s*(\d+)\s*{$this->opt($this->t('adult'))}[[:alpha:]]*(?:\s*,\s*(\d+) [[:alpha:]]+)?/u", $guests, $m)) {
            $ev->booked()
                ->guests($m[1]);

            if (!empty($m[2])) {
                $ev->booked()
                    ->kids($m[2]);
            }
        } else {
            $ev->booked()
                ->guests(null);
        }

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
            '/^\s*([[:alpha:]]+)\s+(\d{1,2})\s(\d{4})\s*,\s*(\d{1,2}:\d{2})h\s*$/ui',
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
