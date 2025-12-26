<?php

namespace AwardWallet\Engine\ticketmaster\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Common\Event;
use AwardWallet\Schema\Parser\Email\Email;

class OrderConfirmation extends \TAccountChecker
{
    public $mailFiles = "ticketmaster/it-150836322.eml, ticketmaster/it-164011810.eml, ticketmaster/it-171322571.eml, ticketmaster/it-51569657.eml, ticketmaster/it-12318159.eml";

    public $lang = '';

    public static $dict = [
        'en' => [ // it-150836322.eml
            'Order number'     => ['Order number', 'Order Number:'],
            'Ticket Quantity:' => ['Ticket Quantity:', 'Items:'],
            'Total:'           => ['Total:', 'Payment Amount:'],
        ],
        'nl' => [ // it-12318159.eml, it-164011810.eml
            // 'Hi' => '',
            'Order number'            => ['Ordernummer:', 'Bestelling / Commande :'],
            'Your Order Confirmation' => ['Jouw orderbevestiging', 'Jouw bestelling / Votre commande'],
            'Ticket Quantity:'        => ['Aantal:', 'Aantal tickets / Nombre de tickets :'],
            'Total:'                  => ['Totaal:', 'Totaal / Total :'],
            // 'Order total:' => '', // it-51569657.eml
            'SEAT:'    => 'STOEL:',
            'SECTION:' => 'SECTIE:',
            'ROW:'     => 'RIJ:',
            // 'ZONE :' => '',
        ],
    ];
    private $from = [
        'Ticketmaster',
    ];
    private $subject = [
        'You’re In! Your ', // en
        'bestelbevestiging voor', // nl
        'Jouw bestelling / Votre commande', // nl
    ];
    private $body = [
        'en' => ['Thanks for ordering from Ticketmaster', 'Thank you for buying your tickets on Ticketmaster', 'Thank you for your order!'],
        'nl' => ['Bedankt voor je bestelling!', 'Bedankt voor je aankoop!'],
    ];

    private $patterns = [
        'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
    ];

    public function detectEmailFromProvider($from)
    {
        return $this->stripos($from, $this->from) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true && strpos($headers['subject'], 'Ticketmaster') === false) {
            return false;
        }

        return $this->stripos($headers['subject'], $this->subject) !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[{$this->contains($this->from)}]")->length === 0) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->alert("Can't determine a language!");

            return $email;
        }

        if ($this->http->XPath->query("//text()[{$this->eq($this->t('SEAT:'))}]/ancestor::tr[{$this->contains($this->t('SECTION:'))} and {$this->contains($this->t('ROW:'))} and {$this->contains($this->t('SEAT:'))}][1]")->length > 0
            || $this->http->XPath->query("//text()[{$this->eq($this->t('ZONE :'))}]")->length > 0
            || $this->http->XPath->query("//text()[{$this->contains($this->t('Your order confirmation'))}]/following::text()[normalize-space()][5]/preceding::img[preceding::text()[{$this->contains($this->t('Your order confirmation'))}]]")->length === 0
        ) {
            $this->parseEvent2($email);
        } else {
            $this->parseEvent($email);
        }

        $a = explode('\\', __CLASS__);
        $email->setType($a[count($a) - 1] . ucfirst($this->lang));

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseEvent(Email $email): void
    {
        // it-51569657.eml

        $this->logger->warning('Type: parseEvent');
        $e = $email->add()->event();
        $confirmation = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Your order confirmation'))}]/ancestor::td[1]",
            null, false, '/([A-Z\d\-\\/]{5,})/');
        $desConfirm = $this->t('Your order confirmation');

        if (empty($confirmation)) {
            $confirmation = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Order number'))}]/ancestor::td[1]",
                null, false, '/([A-Z\d\-\\/]{5,})/');
            $desConfirm = 'Order number';
        }

        $e->general()
            ->confirmation($confirmation, $desConfirm);
        $e->general()->traveller(
            $this->http->FindSingleNode("//text()[{$this->contains($this->t('Thanks for ordering from Ticketmaster'))}]/preceding::text()[normalize-space()][1]", null, false, "/^{$this->opt($this->t('Hi'))}[,\s]+({$this->patterns['travellerName']})(?:\s*[,;:!?]|$)/u"));

        $root = $this->http->XPath->query("//text()[{$this->contains($this->t('Your order confirmation'))}]/ancestor::tr[1]/following-sibling::tr[1]//table[2]");

        if ($root->length) {
            $e->place()->name(preg_replace('/\d+ tickets for /', '', $this->http->FindSingleNode(".//p", $root->item(0))));
            $e->booked()->start2($this->normalizeDate($this->http->FindSingleNode("(.//img/ancestor::td[1]/following-sibling::td)[1]", $root->item(0))));
            $e->booked()->noEnd();
            $e->place()->address($this->http->FindSingleNode("(.//img/ancestor::td[1]/following-sibling::td)[2]", $root->item(0)));
            $e->place()->type(Event::TYPE_SHOW);
        }

        $price = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Order total:'))}]/ancestor::b[1]/following-sibling::text()");

        if (preg_match('/(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*)/', $price, $m)) {
            // €80.50 (incl. fees)
            $currency = $this->currency($m['currency']);
            $currencyCode = preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null;
            $e->price()->currency($currency)->total(PriceHelper::parse($m['amount'], $currencyCode));
        }
    }

    private function parseEvent2(Email $email): void
    {
        // it-150836322.eml, it-164011810.eml, it-171322571.eml, it-12318159.eml

        $this->logger->warning('Type: parseEvent2');
        $e = $email->add()->event();
        $e->place()->type(Event::TYPE_SHOW);

        $xpathNoEmpty = 'string-length(normalize-space())>1';

        $traveller = $isNameFull = null;
        $travellerNames = array_filter($this->http->FindNodes("//*[{$this->starts($this->t('Order number'))}]/preceding-sibling::*[{$this->starts($this->t('Hi'))}]", null, "/^{$this->opt($this->t('Hi'))}[,\s]+({$this->patterns['travellerName']})(?:\s*[,;:!?]|$)/u"));

        if (count(array_unique($travellerNames)) === 1) {
            $traveller = array_shift($travellerNames);
        }

        if (empty($traveller)) {
            // it-164011810.eml
            $travellerNames = array_filter($this->http->FindNodes("//*[{$this->starts($this->t('Order number'))}]/preceding-sibling::*[normalize-space()]", null, "/^({$this->patterns['travellerName']})\s*,$/u"));

            if (count(array_unique($travellerNames)) === 1) {
                $traveller = array_shift($travellerNames);
            }
        }

        if (empty($traveller)) {
            // it-171322571.eml
            $traveller = $this->http->FindSingleNode("//*[{$this->contains($this->t('Your Contact Details'))}]/following-sibling::*[normalize-space()][1]/descendant-or-self::*[ *[{$xpathNoEmpty}][2] ][1]/*[{$xpathNoEmpty}][1]", null, true, "/^{$this->patterns['travellerName']}$/u");
            $isNameFull = true;
        }

        $e->general()->traveller($traveller, $isNameFull);

        $confirmation = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Your order confirmation'))}]/ancestor::td[1]",
            null, false, '/([A-Z\d\-\\/]{5,})/');
        $desConfirm = $this->t('Your order confirmation');

        if (empty($confirmation)) {
            $confirmation = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Order number'))}]/ancestor::td[1]",
                null, false, '/([A-Z\d\-\\/]{5,})/');
            $desConfirm = 'Order number';
        }

        $e->general()
            ->confirmation($confirmation, $desConfirm);

        $f2 = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Your Order Confirmation'))}]/following::text()[string-length()>5][2]");
        $f3 = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Your Order Confirmation'))}]/following::text()[string-length()>5][3]");

        if (preg_match("/\b\d{1,2}:\d{2}\D*$/", $f2)) {
            $dateStart = $f2;
            $address = $f3;
        } else {
            $dateStart = $f3;
            $address = $f2;
        }
        $e->booked()
            ->start(strtotime($this->normalizeDate($dateStart)))
            ->noEnd()
            ->guests($this->http->FindSingleNode("//text()[{$this->eq($this->t('Ticket Quantity:'))}]/ancestor::td[1]", null, true, "/{$this->opt($this->t('Ticket Quantity:'))}\s*(\d+)/"));

        $e->setName($this->http->FindSingleNode("//text()[{$this->eq($this->t('Your Order Confirmation'))}]/following::text()[string-length()>5][1]"));
        $e->setAddress($address);

        $price = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Total:'))}]/ancestor::td[1]", null, true, "/{$this->opt($this->t('Total:'))}\s*(.+)/");

        if (preg_match("/^\s*(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*\b)/", $price, $m)
            || preg_match("/^\s*(?<amount>\d[,.\'\d ]*?)[ ]*(?<currency>[^\-\d)(]+)$/", $price, $m)
        ) {
            // €80.50 (incl. fees)    |    99,38€
            $currency = $this->currency($m['currency']);
            $currencyCode = preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null;
            $e->price()->currency($currency)->total(PriceHelper::parse($m['amount'], $currencyCode));
        }

        $seatsInfo = $this->http->FindSingleNode("//text()[{$this->eq($this->t('SEAT:'))}]/ancestor::tr[{$this->contains($this->t('SECTION:'))} and {$this->contains($this->t('ROW:'))} and {$this->contains($this->t('SEAT:'))}][1]");
        $seatsInfo = str_ireplace(['&#65279;', '﻿'], '', $seatsInfo);

        if (preg_match("/{$this->opt($this->t('SECTION:'))}\s+(.+)\s+{$this->opt($this->t('ROW:'))}\s+(.+)\s+{$this->opt($this->t('SEAT:'))}\s*([\d\-]+)$/u", $seatsInfo, $m)) {
            $e->booked()
                ->seat(trim($m[1]) . ', ' . trim($m[2]) . ', ' . trim($m[3]));
        }
    }

    private function assignLang(): bool
    {
        foreach ($this->body as $lang => $phrase) {
            if ($this->http->XPath->query("//*[{$this->contains($phrase)}]")->length > 0) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function normalizeDate($str): string
    {
        $this->logger->debug($str);
        $in = [
            // Sun 12 Jan 2020@ 1:00 pm
            // Zondag 3 juli 2022 0:00
            // Friday 23 September 2022 at 19:30
            // dinsdag 6 juni 2023 / 20:00
            '/^[-[:alpha:]]+[,\s]+(\d{1,2}[-.\s]*[[:alpha:]]+[-.\s]*\d{4})\s*(?:[@\/\s]|\s+at\s+)\s*(\d{1,2}:\d{2}(?:\s*[ap]m)?)$/iu',
        ];
        $out = [
            "$1, $2",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match('/\d+[-.\s]*([[:alpha:]]+)[-.\s]*\d{4}/u', $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return $str;
    }

    private function t(string $phrase)
    {
        if (!isset(self::$dict, $this->lang) || empty(self::$dict[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dict[$this->lang][$phrase];
    }

    private function currency(string $s): ?string
    {
        $sym = [
            '€' => 'EUR',
            '£' => 'GBP',
        ];

        if ($s === '$' && $this->http->XPath->query("//a/@href[{$this->contains(['.ticketmaster.co.nz/'])}]")) {
            return 'NZD';
        }

        $s = preg_replace('/(\S)(?:[ ]*\1)+/u', '$1', $s); // € €    ->    €

        foreach ($sym as $f => $r) {
            if ($s == $f) {
                return $r;
            }
        }

        return null;
    }

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ")='" . $s . "'";
        }, $field)) . ')';
    }

    private function contains($field, $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function starts($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'starts-with(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }

    private function stripos($haystack, $arrayNeedle)
    {
        $arrayNeedle = (array) $arrayNeedle;

        foreach ($arrayNeedle as $needle) {
            if (stripos($haystack, $needle) !== false) {
                return true;
            }
        }

        return false;
    }
}
