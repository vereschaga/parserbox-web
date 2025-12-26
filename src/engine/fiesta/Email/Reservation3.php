<?php

namespace AwardWallet\Engine\fiesta\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class Reservation3 extends \TAccountChecker
{
    public $mailFiles = "fiesta/it-718853209.eml, fiesta/it-718866328.eml";

    public $lang;
    public static $dictionary = [
        'es' => [
            'Nombre del Acompañante' => 'Nombre del Acompañante',
        ],
    ];

    private $detectFrom = "@posadas.com";
    private $detectSubject = [
        // es
        'Reservacion :',
    ];
    private $detectBody = [
        'es' => [
            'Información de la Reservación',
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
        if ($this->http->XPath->query("//a[{$this->contains(['.fiestarewards.'], '@href')}]")->length === 0) {
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
            if (!empty($dict["Nombre del Acompañante"])) {
                if ($this->http->XPath->query("//*[{$this->contains($dict['Nombre del Acompañante'])}]")->length > 0
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
            ->confirmation($this->http->FindSingleNode("(//text()[{$this->eq($this->t('RESERVACIÓN'))}]/following::text()[normalize-space()][1][contains(., '#')])[1]",
                null, true, "/^\s*#\s*([A-Z\d]{5,})\s*$/"))
            ->traveller($this->http->FindSingleNode("//tr[*[1][{$this->eq($this->t('Nombre'))}]]/following-sibling::*[1]/*[1]"), true)
            ->cancellation($this->http->FindSingleNode("//tr[{$this->eq($this->t('Cancelaciones y políticas'))}]/following-sibling::*[1]"), true, true)
        ;
        $traveller = $this->http->FindSingleNode("//tr[*[1][{$this->eq($this->t('Nombre del Acompañante'))}]]/following-sibling::*[1]/*[1]");

        if (!empty($traveller)) {
            $h->general()
                ->traveller($traveller, true);
        }

        // Hotel
        $h->hotel()
            ->name($this->http->FindSingleNode("(//text()[{$this->eq($this->t('RESERVACIÓN'))}])[1]/preceding::text()[normalize-space()][1]"))
            ->address($this->http->FindSingleNode("//tr[*[1][{$this->eq($this->t('Dirección:'))}]]/following-sibling::*[1]/*[1]"))
        ;

        // Booked
        $h->booked()
            ->checkIn($this->normalizeDate($this->http->FindSingleNode("//tr[*[1][{$this->eq($this->t('Llegada'))}]]/following-sibling::*[1]/*[1]",
                null, true, "/^\s*(.+?)\s*(\*|$)/")))
            ->checkOut($this->normalizeDate($this->http->FindSingleNode("//tr[*[2][{$this->eq($this->t('Salida'))}]]/following-sibling::*[1]/*[2]",
                null, true, "/^\s*(.+?)\s*(\*|$)/")))
            ->guests($this->http->FindSingleNode("//tr[*[2][{$this->eq($this->t('Adultos'))}]]/following-sibling::*[1]/*[2]"))
            ->kids($this->http->FindSingleNode("//tr[*[3][{$this->eq($this->t('Niños'))}]]/following-sibling::*[1]/*[3]"))
        ;

        // Rooms
        $h->addRoom()
            ->setType($this->http->FindSingleNode("//tr[*[1][{$this->starts($this->t('Habitación'))}]]/following-sibling::*[1]/*[1]"));

        // Price
        $cost = $this->http->FindSingleNode("//td[descendant::tr[not(.//tr)][1][{$this->eq($this->t('Total de la Estancia:'))}]]/following-sibling::*[1]/descendant::tr[not(.//tr)][1]");

        if (preg_match("/^\s*(?<currency>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$/", $cost, $m)
            || preg_match("/^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d\s]{1,5})\s*$/", $cost, $m)
            || preg_match("/^\s*\\$\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d\s]{1,5})\s*$/", $cost, $m)
        ) {
            $h->price()
                ->cost(PriceHelper::parse($m['amount'], $this->currency($m['currency'])));
        }
        $tax = $this->http->FindSingleNode("//td[descendant::tr[not(.//tr)][3][{$this->eq($this->t('Impuestos:'))}]]/following-sibling::*[1]/descendant::tr[not(.//tr)][3]");

        if (preg_match("/^\s*(?<currency>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$/", $tax, $m)
            || preg_match("/^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d\s]{1,5})\s*$/", $tax, $m)
            || preg_match("/^\s*\\$\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d\s]{1,5})\s*$/", $tax, $m)
        ) {
            $h->price()
                ->tax(PriceHelper::parse($m['amount'], $this->currency($m['currency'])));
        }

        $total = $this->http->FindSingleNode("//td[descendant::tr[not(.//tr)][4][{$this->eq($this->t('Total con Impuestos:'))}]]/following-sibling::*[1]/descendant::tr[not(.//tr)][4]");
        $this->logger->debug('$total = ' . print_r($total, true));

        if (preg_match("/^\s*(?<currency>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$/", $total, $m)
            || preg_match("/^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d\s]{1,5})\s*$/", $total, $m)
            || preg_match("/^\s*\\$\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d\s]{1,5})\s*$/", $total, $m)
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

    private function nextTd($field, $regexp = null)
    {
        return $this->http->FindSingleNode("//tr[not(.//tr)][*[normalize-space()][1][{$this->eq($field)}]]/*[normalize-space()][2]",
            null, true, $regexp);
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
            // 05/12/2024 a las 16:00
            '/^\s*(\d{1,2})\/(\d{1,2})\/(\d{4})\s+\D+\s+(\d{1,2}:\d{2})\s*$/',
        ];
        $out = [
            '$1.$2.$3, $4',
        ];

        $date = preg_replace($in, $out, $date);

//        $this->logger->debug('date replace = ' . print_r( $date, true));

        $this->logger->debug('date end = ' . print_r($date, true));

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

    private function amount($price)
    {
        $price = str_replace([',', ' '], '', $price);

        if (is_numeric($price)) {
            return (float) $price;
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
