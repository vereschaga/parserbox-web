<?php

namespace AwardWallet\Engine\fiesta\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class Reservation extends \TAccountChecker
{
    public $mailFiles = "fiesta/it-718620930.eml, fiesta/it-722801166.eml";

    public $lang;
    public static $dictionary = [
        'es' => [
            'Tipo de habitación' => 'Tipo de habitación',
        ],
    ];

    private $detectFrom = "fiestarewards@posadas.com";
    private $detectSubject = [
        // es
        'Confirmación de reservación',
    ];
    private $detectBody = [
        'es' => [
            'Gracias por reservar con nosotros',
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
            if (!empty($dict["Tipo de habitación"])) {
                if ($this->http->XPath->query("//*[{$this->contains($dict['Tipo de habitación'])}]")->length > 0
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
            ->confirmation($this->nextTd($this->t('Número de confirmación')))
            ->traveller($this->nextTd($this->t('Huésped')))
        ;

        // Hotel
        $h->hotel()
            ->name($this->nextTd($this->t('Hotel')))
            ->noAddress()
        ;

        // Booked
        $h->booked()
            ->checkIn($this->normalizeDate($this->nextTd($this->t('Check-in'))))
            ->checkOut($this->normalizeDate($this->nextTd($this->t('Check-out'))))
            ->guests($this->nextTd($this->t('Número de adultos')))
            ->kids(($this->nextTd($this->t('Número de niños')) ?? 0) + ($this->nextTd($this->t('Número de infantes')) ?? 0))
        ;

        // Rooms
        $h->addRoom()
            ->setType($this->nextTd($this->t('Tipo de habitación')));

        // Price
        $total = $this->t($this->nextTd($this->t('Monto en pesos')));

        if (preg_match("#^\s*(?<currency>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $total, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d\s]{1,5})\s*$#", $total, $m)
        ) {
            $currency = $this->currency($m['currency']);
            $h->price()
                ->total(PriceHelper::parse($m['amount'], $currency))
                ->currency($currency)
            ;
        }

        $award = $this->t($this->nextTd($this->t('Puntos utilizados')));

        if (!empty($award)) {
            $h->price()
                ->spentAwards($award);
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
        // $this->logger->debug('date begin = ' . print_r( $date, true));

        $in = [
            // 01/09/2023
            '/^\s*(\d{1,2})\/(\d{1,2})\/(\d{4})\s*$/',
        ];
        $out = [
            '$1.$2.$3',
        ];

        $date = preg_replace($in, $out, $date);

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
