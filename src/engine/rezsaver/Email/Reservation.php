<?php

namespace AwardWallet\Engine\rezsaver\Email;

// TODO: delete what not use
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class Reservation extends \TAccountChecker
{
    public $mailFiles = "rezsaver/it-637700196.eml, rezsaver/it-637700280.eml";

    public $lang;
    public static $dictionary = [
        'en' => [
            'HOTEL DETAILS -' => 'HOTEL DETAILS -',
            'Property'        => 'Property',
            'Commission Info' => 'Commission Info',
            'Client'          => 'Client',
            'Price'           => 'Price',
        ],
    ];

    private $detectFrom = "@rezsaver.com";
    private $detectSubject = [
        // en
        'Reservation:',
    ];

    // Main Detects Methods
    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@.]rezsaver\.com\b/", $from) > 0;
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
        foreach (self::$dictionary as $dict) {
            if (!empty($dict['HOTEL DETAILS -']) && !empty($dict['Property'])
                && !empty($dict['Commission Info']) && !empty($dict['Client'])
                && !empty($dict['Price'])
                && $this->http->XPath->query("//node()[{$this->starts($dict['HOTEL DETAILS -'])}]/following::text()[normalize-space()][1][{$this->eq($dict['Property'])}]"
                    . "/following::*[{$this->eq($dict['Commission Info'])}]/following::tr[*[normalize-space()][1][{$this->eq($dict['Client'])}] and *[normalize-space()][4][{$this->eq($dict['Price'])}]]")->length > 0
            ) {
                return true;
            }

            if ($this->http->XPath->query("//*[{$this->starts('Hotel Cancellation Policy')}]/following-sibling::*[normalize-space()][1][{$this->starts('Rate Cancellation Policy')}]"
                    . "/following-sibling::*[normalize-space()][1][{$this->starts('Hotel Deposit Policy')}]/following-sibling::*[normalize-space()][1][{$this->starts('Rate Deposit Policy')}]")->length > 0
            ) {
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
            if (isset($dict["HOTEL DETAILS -"])) {
                if ($this->http->XPath->query("//*[{$this->contains($dict['HOTEL DETAILS -'])}]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function parseEmailHtml(Email $email)
    {
        $email->setSentToVendor(true);

        $h = $email->add()->hotel();

        // General
        $h->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->starts($this->t('HOTEL DETAILS -'))}]",
                null, true, "/{$this->opt($this->t('HOTEL DETAILS -'))}\s*([\dA-Z\-]{5,})\s*$/"))
            // ->confirmation($this->http->FindSingleNode("//tr[count(*) = 2 and *[1][{$this->eq($this->t('Hotel Phone'))}]]/*[2]"))
            ->traveller($this->http->FindSingleNode("//tr[*[1][{$this->eq($this->t('Client'))}] and *[normalize-space()][4][{$this->eq($this->t('Price'))}]]/following-sibling::tr[normalize-space()][1]/*[1]"), true)
            ->cancellation(implode('. ', [$this->http->FindSingleNode("//tr[count(*) = 2 and *[1][{$this->eq($this->t('Hotel Cancellation Policy'))}]]/*[2]"),
                $this->http->FindSingleNode("//tr[count(*) = 2 and *[1][{$this->eq($this->t('Rate Cancellation Policy'))}]]/*[2]"), ]))
        ;

        // Hotel
        $h->hotel()
            ->name($this->http->FindSingleNode("//tr[*[1][{$this->eq($this->t('Property'))}] and *[3][{$this->eq($this->t('Reservation #'))}]]/*[2]"))
            ->address($this->http->FindSingleNode("//tr[count(*[normalize-space()]) = 2 and *[1][{$this->eq($this->t('Hotel Address'))}]]/*[2]"))
            ->phone($this->http->FindSingleNode("//tr[count(*[normalize-space()]) = 2 and *[1][{$this->eq($this->t('Hotel Phone'))}]]/*[2]"))
        ;

        // Booked
        $h->booked()
            ->checkIn(strtotime($this->http->FindSingleNode("//tr[*[1][{$this->eq($this->t('Check In'))}] and *[3][{$this->eq($this->t('Check Out'))}]]/*[2]")))
            ->checkOut(strtotime($this->http->FindSingleNode("//tr[*[1][{$this->eq($this->t('Check In'))}] and *[3][{$this->eq($this->t('Check Out'))}]]/*[4]")))
            ->rooms($this->http->FindSingleNode("//tr[count(*[normalize-space()]) = 2 and *[1][{$this->eq($this->t('Number of Rooms'))}]]/*[2]"))
        ;

        // Rooms
        $roomType = $this->http->FindSingleNode("//tr[count(*) = 2 and *[1][{$this->eq($this->t('Room Type'))}]]/*[2]");

        if (!empty($roomType)) {
            $h->addRoom()
                ->setDescription($roomType);
        }

        // Price
        $currency = $this->http->FindSingleNode("//tr[count(*[normalize-space()]) = 2 and *[1][{$this->eq($this->t('Total'))}]]/*[normalize-space()][2]",
            null, true, "/\b([A-Z]{3})\b/");
        $total = $this->http->FindSingleNode("//tr[count(*[normalize-space()]) = 2 and *[1][{$this->eq($this->t('Total'))}]]/*[normalize-space()][2]",
            null, true, "/^\s*\D*(\d+[,. \d]*\d)*\D*\s*$/");
        $h->price()
            ->total(PriceHelper::parse($total, $currency))
            ->currency($currency)
        ;
        $cost = $this->http->FindSingleNode("//tr[count(*[normalize-space()]) = 2 and *[1][{$this->eq($this->t('Total'))}]]/preceding-sibling::tr[count(*) = 5 and count(*[normalize-space()]) = 2]/*[4]",
            null, true, "/^\s*\D*(\d+[,. \d]*\d)*\D*\s*$/");
        $tax = $this->http->FindSingleNode("//tr[count(*[normalize-space()]) = 2 and *[1][{$this->eq($this->t('Total'))}]]/preceding-sibling::tr[count(*) = 5 and count(*[normalize-space()]) = 2]/*[5]",
            null, true, "/^\s*\D*(\d+[,. \d]*\d)*\D*\s*$/");
        $h->price()
            ->cost(PriceHelper::parse($cost, $currency))
            ->tax(PriceHelper::parse($tax, $currency))
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
}
