<?php

namespace AwardWallet\Engine\tock\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Common\Event;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class YourReservation extends \TAccountChecker
{
    public $mailFiles = "tock/it-108088158.eml, tock/it-108098543.eml, tock/it-343817430.eml, tock/it-352762358.eml, tock/it-352762359.eml, tock/it-354917442.eml, tock/it-355407731-junk.eml, tock/it-355537208-junk.eml, tock/it-355912381.eml, tock/it-503750502.eml, tock/it-506781340.eml";

    private $detectFrom = ["@exploretock.com"];
    private $detectSubject = [
        // en
        "reservation for",
        "Your receipt for",
        'Modified reservation for',
        "Cancellation confirmation for",
        'Reminder: upcoming order from',
        'Your tickets for',
    ];

    private $detectCompany = [
        'please email info@tockhq.com',
        'reach out to info@tockhq.com',
    ];

    /*
    private $detectBody = [
        "en" => ["Reservation confirmed", "Reservation reminder",
            "Reservation modified", "Your updated receipt is included below", "Modify reservation",
            "Reservation cancelled", 'Order reminder', 'Event confirmed', 'Reservation updated',
        ],
    ];
    */

    private $lang = '';
    private static $dictionary = [
        'en' => [
            'partyOfFor'         => ['Party of for'],
            'confNumber'         => ['Confirmation #'],
            'type_event_detect'  => ['Manage your tickets'],
            'cancelledPhrases'   => ['reservation has been cancelled', 'reservation has been canceled'],
            'btnText'            => ['Manage your reservation', 'Manage your tickets', 'Modify reservation', 'Cancell reservation', 'Cancel reservation'],
            'cancellationPolicy' => ['Cancellation policy', 'Cancelation policy'],
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();
        $email->setType('YourReservation' . ucfirst($this->lang));

        if (strpos($parser->getSubject(), 'order') !== false
            && $this->http->XPath->query("//text()[" . $this->starts('Order for') . "]")->length > 0
            && $this->http->XPath->query("//text()[" . $this->contains('Manage your order') . "]")->length > 0
        ) {
            $email->setIsJunk(true);

            return $email;
        }

        $this->parseEvent($email);

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return $this->striposAll($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider(rtrim($headers['from'], '> ')) !== true) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (mb_stripos($headers["subject"], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//*[" . $this->contains($this->detectCompany) . "]")->length === 0
            && $this->http->XPath->query("//img[contains(@src, 'googleapis.com/tock-public-assets') or contains(@src, '/tock-logo-color')]")->length === 0
            && $this->http->XPath->query("//text()[starts-with(normalize-space(),'Copyright © Tock LLC')] | //text()[starts-with(normalize-space(),'©') and contains(normalize-space(),'Tock LLC')]")->length === 0
        ) {
            return false;
        }

        return $this->assignLang();
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function parseEvent(Email $email): void
    {
        // Travel Agency
        $otaConfirmation = $this->http->FindSingleNode("//text()[{$this->starts($this->t('confNumber'))}]");

        if (preg_match("/({$this->preg_implode($this->t('confNumber'))})[:\s]+([A-Z\d\-]{5,30})\s*$/", $otaConfirmation, $m)) {
            $email->ota()->confirmation($m[2], $m[1]);
        }

        $event = $email->add()->event();

        // General

        $event->general()
            ->noConfirmation()
        ;
        $cancellation = $this->http->FindSingleNode("//tr[{$this->eq($this->t('cancellationPolicy'))}]/following::text()[normalize-space()][1]/ancestor::tr[1]");

        if (!empty($cancellation)) {
            $event->general()
                ->cancellation($cancellation);
        }

        if ($this->http->XPath->query("//*[{$this->contains($this->t('cancelledPhrases'))}]")->length > 0) {
            $event->general()
                ->status('Cancelled')
                ->cancelled();
        }

        $guests = $this->http->FindSingleNode("//text()[{$this->starts($this->t('confNumber'))}]/preceding::text()[{$this->starts($this->t('Party of'))}][1]");

        if (preg_match("/^\s*" . $this->preg_implode($this->t("Party of")) . "\s*(\d+)\s*" . $this->preg_implode($this->t("for")) . "\s+([[:alpha:] \-]+)\s*$/",
            $guests, $m)) {
            $event->general()
                ->traveller($m[2]);
            $event->booked()
                ->guests($m[1]);
        }

        // Booked
        $event->booked()
            ->start($this->normalizeDate($this->http->FindSingleNode("//text()[{$this->starts($this->t('confNumber'))}]/preceding::text()[{$this->starts($this->t('Party of'))}][1]/preceding::text()[normalize-space()][1]")))
            ->noEnd()
        ;
        $place = implode("\n", $this->http->FindNodes("//text()[" . $this->starts($this->t("Get directions")) . "]/ancestor::table[1]//text()[normalize-space()]"));

        if (
            preg_match("/^\s*(?<name>.+)\n(?<address>(?:.*\n){1,4}?)(?<phone>[\d+\- \(\)]{5,}\n)?" . $this->preg_implode($this->t("Get directions")) . "\s*$/", $place, $m)
            || preg_match("/^\s*Address\nDifferent from .+\n(?<name>.+)\n(?<address>(?:.*\n){1,4}?)(?<phone>[\d+\- \(\)]{5,}\n)?" . $this->preg_implode($this->t("Get directions")) . "\s*$/", $place, $m)
        ) {
            $event->place()
                ->name($m['name'])
                ->address(str_replace("\n", ', ', trim($m['address'])))
                ->phone($m['phone'] ?? null, true, true)
            ;

            if (!empty($this->http->FindSingleNode("(//text()[" . $this->contains($this->t('type_event_detect')) . "])[1]"))) {
                $event->place()
                    ->type(Event::TYPE_EVENT)
                ;
            } else {
                $event->place()
                    ->type(Event::TYPE_RESTAURANT)
                ;
            }
        } elseif (!empty($event->getTravellers()) && !empty($event->getStartDate()) && !empty($email->getTravelAgency()->getConfirmationNumbers())
            && $this->http->XPath->query("//text()[{$this->starts($this->t("Get directions"))}]")->length === 0
            && $this->http->XPath->query("//text()[{$this->eq($this->t('btnText'))}]/following::text()[normalize-space()][1][{$this->eq($this->t('cancellationPolicy'))}]")->length > 0
        ) {
            $email->removeItinerary($event);
            $email->setIsJunk(true, 'event address is empty');
        }

        // Price
        $total = $this->http->FindSingleNode("//td[not(.//td) and " . $this->eq($this->t("Total")) . "]/following::td[normalize-space()][1]");

        if (preg_match("#^\s*(?<curr>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $total, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<curr>[^\d\s]{1,5})\s*$#", $total, $m)) {
            $event = $event ?? $email->add()->event(); // if removeItinerary before
            $currency = $this->currency($m['curr']);
            $event->price()
                ->total(PriceHelper::parse($m['amount'], $currency))
                ->currency($currency)
            ;
        }

        $feesRoot = $this->http->XPath->query("//td[" . $this->eq($this->t("Subtotal")) . "]/ancestor::tr[1]/following-sibling::tr[normalize-space()]");

        foreach ($feesRoot as $froot) {
            $name = $this->http->FindSingleNode("td[normalize-space()][1]", $froot);

            if (in_array($name, (array) $this->t("Total"))) {
                break;
            }
            $event = $event ?? $email->add()->event(); // if removeItinerary before
            $amount = PriceHelper::parse($this->http->FindSingleNode("td[normalize-space()][last()]", $froot, null, "/^\D*([\d,.]+)\D*$/"), $currency);
            $event->price()
                ->fee($name, $amount);
        }
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['partyOfFor']) || empty($phrases['confNumber'])) {
                continue;
            }

            if ($this->http->XPath->query("//node()[{$this->starts($phrases['partyOfFor'], "translate(.,'0123456789','')")}]")->length > 0
                && $this->http->XPath->query("//node()[{$this->starts($phrases['confNumber'])}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($str)
    {
//        $this->logger->debug('$str = '.print_r( $str,true));
        $in = [
            // Mittwoch, 19. April 2023, 18:45
            // viernes, 3 de marzo de 2023, 18:30
            // samedi 1 avril 2023, 18:00
            // fredag den 7. april 2023, 20.30
            // vendredi 10 mars 2023, 20 h 45
            "#^\s*[[:alpha:] \-]+,?\s+(\d{1,2})\.?(?: de)? ([^\s\d]+)(?: de)? (\d{4})\s*,\s*(\d+) ?[:.h] ?(\d+(?:\s*[ap]m)?)(?:\(.*\))?\s*$#iu",
            // Saturday, April 8, 2023, 1:00 AM (Night of Friday, April 7, 2023)
            "#^\s*[[:alpha:] \-]+,?\s+([^\s\d]+)\s+(\d{1,2})\s*,\s*(\d{4})\s*,\s*(\d+:\d+(?:\s*[ap]m)?)\s*(?:\(.*\))?\s*$#iu",
            // 2023 Aug 29, Tue, 19:00
            "#^\s*(\d{4})\s+([[:alpha:] ]+)\s+(\d{1,2})\s*,\s*[^\s\d]+\s*,\s*(\d+:\d+(?:\s*[ap]m)?)\s*(?:\(.*\))?\s*$#iu",
        ];
        $out = [
            "$1 $2 $3, $4:$5",
            "$2 $1 $3, $4",
            "$3 $2 $1, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            } else {
                foreach (['de', 'es', 'fr', 'sv', 'pt', 'pl'] as $lang) {
                    if ($en = MonthTranslate::translate($m[1], $lang)) {
                        $str = str_replace($m[1], $en, $str);

                        break;
                    }
                }
            }
        }
//        $this->logger->debug('$str = '.print_r( $str,true));

        return (!empty($str)) ? strtotime($str) : null;
    }

    private function re($re, $str, $c = 1): ?string
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
            'NZ$'  => 'NZD',
            '€'    => 'EUR',
            '$'    => 'USD',
            '£'    => 'GBP',
            'CFPF' => 'XPF',
            '฿'    => 'THB',
            'CA$'  => 'CAD',
            'MX$'  => 'MXN',
            'R$'   => 'BRL',
            'A$'   => 'AUD',
        ];

        foreach ($sym as $f => $r) {
            if ($s == $f) {
                return $r;
            }
        }

        return null;
    }

    private function preg_implode($field): string
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '/'); }, $field)) . ')';
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
    }

    private function starts($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'starts-with(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) { return 'contains(' . $text . ',"' . $s . '")'; }, $field)) . ')';
    }

    private function striposAll($text, $needle): bool
    {
        if (empty($text) || empty($needle)) {
            return false;
        }

        if (is_array($needle)) {
            foreach ($needle as $n) {
                if (mb_stripos($text, $n) !== false) {
                    return true;
                }
            }
        } elseif (is_string($needle) && mb_stripos($text, $needle) !== false) {
            return true;
        }

        return false;
    }
}
