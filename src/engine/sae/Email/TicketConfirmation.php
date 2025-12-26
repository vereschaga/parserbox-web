<?php

namespace AwardWallet\Engine\sae\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class TicketConfirmation extends \TAccountChecker
{
    public $mailFiles = "sae/it-125596401.eml, sae/it-733206367.eml, sae/it-735250682.eml";

    public $lang = 'en';
    public static $providers = [
        'sae' => [
            'from'       => '@iflysouthern.com',
            'detectLink' => ['iflysouthern.com'],
        ],
        [
            'from'       => '@nauruairlines.com.au',
            'detectLink' => ['/NauruAirlines/'],
        ],
        [
            'from'       => '@lulutai-airlines.to',
            'detectLink' => ['.lulutai-airlines.to'],
        ],
        [
            'from'       => '@kenmoreair.com',
            'detectLink' => ['.kenmoreair.com', '/KenmoreAir/'],
        ],
    ];
    public static $dictionary = [
        'en' => [
            'Your reservation reference' => ['Your reservation reference', 'Your booking reference is :', 'Your booking reference is:',
                'Your reservation number is', ],
            'Total price of your booking :' => ['Total price of your booking :', 'Total price of your booking:', 'Total:'],
            'Your travel :'                 => ['Your travel :', 'Your travel:', 'Your itinerary'],
        ],
    ];

    private $detectFrom = '@iflysouthern.com';
    private $detectSubject = [
        // en
        'Ticket Confirmation - Booking n°',
    ];
    private $detectBody = [
        'en' => [
            ['Here is the confirmation of your reservation.', 'Your travel :'],
            ['Your booking is now confirmed.', 'Your travel :'],
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
        $detectedProvider = false;

        if ($this->http->XPath->query("//a[{$this->contains('ttinteractive.com/', '@href')}] | //img[{$this->contains('ttinteractive.com/', '@src')}]")->length > 0) {
            $detectedProvider = true;
        }

        if ($detectedProvider === false) {
            foreach (self::$providers as $detect) {
                if (!empty($detect['detectLink']) && $this->http->XPath->query("//a[{$this->contains($detect['detectLink'], '@href')}]")->length > 0) {
                    $detectedProvider = true;

                    break;
                }
            }
        }

        if ($detectedProvider === false) {
            return false;
        }

        foreach (self::$dictionary as $dict) {
            if (!empty($dict['Your reservation reference']) && !empty($dict['Your travel :'])
                && $this->http->XPath->query("//*[{$this->contains($dict['Your reservation reference'])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->eq($dict['Your travel :'])}]")->length > 0
            ) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
//        $this->assignLang();

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
        // TODO check count types
        return count(self::$dictionary);
    }

    private function parseEmailHtml(Email $email)
    {
        $f = $email->add()->flight();

        // General
        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Your reservation reference'))}]/following::text()[normalize-space()][1]",null, true,
                "/^\s*(?:N°)?\s*([A-Z\d]{5,7})\s*$/"))
        ;

        // Issued
        $tXpath = "//tr[descendant::*[1][normalize-space()='Passenger']]/following-sibling::*";
        $tNodes = $this->http->XPath->query($tXpath);

        foreach ($tNodes as $tRoot) {
            $name = $this->http->FindSingleNode("*[1]", $tRoot, true, "/^\s*(?:(?:Child|[[:alpha:]]{1,4}\.|Miss) )?(\S.+)/");
            $f->general()
                ->traveller($name);
            $ticket = $this->http->FindSingleNode("*[2]", $tRoot, true, "/^\s*(\d{10,})\s*$/");

            if (!empty($ticket)) {
                $f->issued()
                    ->ticket($ticket, false, $name);
            }
        }

        // Price
        $totalStr = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Total price of your booking :'))}]/following::text()[normalize-space()][1]");

        if (empty($totalStr)) {
            $totalStr = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Total price of your booking :'))}]",
                null, true, "/{$this->opt($this->t('Total price of your booking :'))}\s*(.+)/");
        }

        if (preg_match("/^\s*(?<currency>USD)\s*\\$\s*(?<amount>\d[\d\., ]*)\s*$/", $totalStr, $m)
            || preg_match("/^\s*(?<currency>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$/", $totalStr, $m)
            || preg_match("/^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d\s]{1,5})\s*$/", $totalStr, $m)
        ) {
            $currency = $this->currency($m['currency']);
            $total = (float) PriceHelper::parse($m['amount'], $currency);
            $email->price()
                ->total($total)
                ->currency($currency)
            ;
        }

        // Segments
        $xpath = "//tr[*[1][normalize-space()='Flight number'] and *[2][normalize-space()='Departure date']]/following-sibling::tr[1]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            // Airline
            $s->airline()
                ->name($this->http->FindSingleNode("./*[1]", $root, true, "/^\s*([A-Z][A-Z\d]|[A-Z\d][A-Z])\s*\d{1,5}\s*$/"))
                ->number($this->http->FindSingleNode("./*[1]", $root, true, "/^\s*(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(\d{1,5})\s*$/"));

            $date = $this->http->FindSingleNode("./*[2]", $root);

            // Departure
            $departure = $this->http->FindSingleNode("./preceding::tr[not(.//tr)][2]/*[normalize-space()][1]", $root);

            if (preg_match("/^\s*(.+?)\s*\(([A-Z]{3})\)\s*$/", $departure, $m)) {
                $s->departure()
                    ->code($m[2])
                    ->name($m[1])
                ;
            } elseif (empty($this->http->FindSingleNode("(./preceding::tr[not(.//tr)][2]/*[normalize-space()][position() > 3])[1]", $root))) {
                $s->departure()
                    ->noCode()
                    ->name($departure)
                ;
            }

            $time = $this->http->FindSingleNode("./*[3]", $root);

            if (!empty($date) && !empty($time)) {
                $s->departure()
                    ->date(strtotime($date . ', ' . $time));
            }

            // Arrival
            $arrival = $this->http->FindSingleNode("./preceding::tr[not(.//tr)][2]/*[normalize-space()][2]", $root);

            if (preg_match("/^\s*(.+?)\s*\(([A-Z]{3})\)\s*$/", $arrival, $m)) {
                $s->arrival()
                    ->code($m[2])
                    ->name($m[1])
                ;
            } elseif (empty($this->http->FindSingleNode("(./preceding::tr[not(.//tr)][2]/*[normalize-space()][position() > 3])[1]", $root))) {
                $s->arrival()
                    ->noCode()
                    ->name($arrival)
                ;
            }
            $time = $this->http->FindSingleNode("./*[4]", $root);

            if (!empty($date) && !empty($time)) {
                $overnight = null;

                if (preg_match("/^\s*(?<time>\d+:\d+.*?)\s*\(\s*([+\-] ?\d+)\s*\)\s*$/", $time, $m)) {
                    $time = $m['time'];
                    $overnight = $m[2];
                }
                $aDate = strtotime($date . ', ' . $time);

                if (!empty($overnight) && !empty($aDate)) {
                    $aDate = strtotime($overnight . ' days', $aDate);
                }
                $s->arrival()
                    ->date($aDate);
            }
        }

        return true;
    }

    private function assignLang()
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (isset($dict["Flight number"])
                && $this->http->XPath->query("//*[{$this->contains($dict['Flight number'])}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
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

    private function currency($s)
    {
        if ($code = $this->re("#^\s*([A-Z]{3})\s*$#", $s)) {
            return $code;
        }
        $sym = [
            '€' => 'EUR',
            //            '$' => 'USD',
            //            '£' => 'GBP',
        ];

        foreach ($sym as $f => $r) {
            if ($s == $f) {
                return $r;
            }
        }

        return null;
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
