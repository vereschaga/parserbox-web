<?php

namespace AwardWallet\Engine\fiesta\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class Reservation2 extends \TAccountChecker
{
    public $mailFiles = "fiesta/it-718620931.eml, fiesta/it-727415215.eml";

    public $lang;
    public static $dictionary = [
        'es' => [
            'Total por habitación' => 'Total por habitación',
        ],
    ];

    private $detectFrom = "@posadas.com";
    private $detectSubject = [
        // es
        'Confirmación de reserva',
    ];
    private $detectBody = [
        'es' => [
            'Estos son los detalles de tu compra:',
        ],
    ];

    // Main Detects Methods
    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@.]posadas\.com$/", $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers["from"], $this->detectFrom) === false) {
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
        if ($this->http->XPath->query("//a[{$this->contains(['fundacionposadas.'], '@href')}]")->length === 0) {
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
            if (!empty($dict["Total por habitación"])) {
                if ($this->http->XPath->query("//*[{$this->contains($dict['Total por habitación'])}]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function parseEmailHtml(Email $email)
    {
        $h = $email->add()->hotel();

        // General
        $h->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->starts($this->t('Código de reservación:'))}]",
                null, true, "/{$this->opt($this->t('Código de reservación:'))}\s*([A-Z\d]{5,})\s*$/"))
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('¡Reservación exitosa!'))}]/following::text()[normalize-space()][1]",
                null, true, "/^\s*([A-Z\d]{5,})\s*$/"))
            ->traveller($this->http->FindSingleNode("//text()[{$this->starts($this->t('Huésped titular:'))}]",
                null, true, "/{$this->opt($this->t('Huésped titular:'))}\s*(.+?)\s*$/"), true)
            ->cancellation($this->http->FindSingleNode("//tr[{$this->eq($this->t('Cancelaciones y políticas'))}]/following-sibling::*[1]"), true, true)
        ;

        // Hotel
        $h->hotel()
            ->name($this->http->FindSingleNode("//text()[{$this->starts($this->t('Total por habitación:'))}]/preceding::text()[normalize-space()][1]/ancestor::h2"))
            ->noAddress()
        ;

        // Booked
        $h->booked()
            ->checkIn($this->normalizeDate($this->http->FindSingleNode("//text()[{$this->starts($this->t('Huésped titular:'))}]/preceding::text()[normalize-space()][1]",
                null, true, "/^\s*(.+?)\s+{$this->opt($this->t('a'))}\s*.+?\s*\|/")))
            ->checkOut($this->normalizeDate($this->http->FindSingleNode("//text()[{$this->starts($this->t('Huésped titular:'))}]/preceding::text()[normalize-space()][1]",
                null, true, "/^\s*.+?\s+{$this->opt($this->t('a'))}\s*(.+?)\s*\|/")))
            ->guests($this->http->FindSingleNode("//text()[{$this->starts($this->t('Huésped titular:'))}]/preceding::text()[normalize-space()][position() < 5][{$this->contains($this->t('adult'))}]",
                null, true, "/^\s*(\d+)\s*{$this->opt($this->t('adult'))}/"))
            ->kids($this->http->FindSingleNode("//text()[{$this->starts($this->t('Huésped titular:'))}]/preceding::text()[normalize-space()][position() < 5][{$this->contains($this->t('niño'))}]",
                null, true, "/\b(\d+)\s*{$this->opt($this->t('niño'))}/"), true, true)
        ;

        // Rooms
        $type = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Habitación:'))}]",
            null, true, "/{$this->opt($this->t('Habitación:'))}\s*(.+)/");

        if (empty($type)) {
            $type = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Habitación:'))}]/following::text()[normalize-space()][1]");
        }
        $h->addRoom()
            ->setType($type);

        // Price
        $total = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Total por habitación:'))}]",
            null, true, "/^\s*{$this->opt($this->t('Total por habitación:'))}\s*(.+)\s*$/");

        if (preg_match("#^\s*(\d+)\s*PTS\s*$#", $total, $m)
        ) {
            $h->price()
                ->spentAwards($m[1]);
        } elseif (preg_match("#^\s*(?<currency>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $total, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d\s]{1,5})\s*$#", $total, $m)
            || preg_match("#^\s*\$\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d\s]{1,5})\s*$#", $total, $m)
        ) {
            $currency = $this->currency($m['currency']);
            $h->price()
                ->total(PriceHelper::parse($m['amount'], $currency))
                ->currency($currency)
            ;
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
        // $this->logger->debug('date begin = ' . print_r( $date, true));

        $in = [
            // sáb 02 de nov 2024
            '/^\s*[[:alpha:]]+\s+(\d{1,2})\s+de\s+([[:alpha:]]+)\s+(\d{4})\s*$/iu',
        ];
        $out = [
            '$1 $2 $3',
        ];

        $date = preg_replace($in, $out, $date);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#u", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        // $this->logger->debug('date end = ' . print_r( $date, true));

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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function currency($s)
    {
        if ($code = $this->re("#^\s*([A-Z]{3})\s*$#", $s)) {
            return $code;
        }
        $sym = [
            'N$' => 'NAD',
        ];

        foreach ($sym as $f => $r) {
            if ($s == $f) {
                return $r;
            }
        }

        return null;
    }
}
