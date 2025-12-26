<?php

namespace AwardWallet\Engine\telecharge\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Common\Event;
use AwardWallet\Schema\Parser\Email\Email;

class YourOrder extends \TAccountChecker
{
    public $mailFiles = "telecharge/it-124168161.eml, telecharge/it-197096251.eml, telecharge/it-2893569.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'confNumber'     => ['Order Number'],
            'statusPhrases'  => ['Your Order is'],
            'statusVariants' => ['confirmed'],
            'off aisle'      => ['off aisle', 'is on aisle'],
        ],
    ];

    private $subjects = [
        'en' => ['Your order has been confirmed'],
    ];

    private $detectors = [
        'en' => ['Your Order'],
    ];

    public function detectEmailFromProvider($from)
    {
        return strpos($from, 'Telecharge.com Customer Service') !== false
            || stripos($from, '@telecharge.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (is_string($phrase) && stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,".telecharge.com/") or contains(@href,"www.telecharge.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"Thank you for ordering from Telecharge") or contains(.,"@telecharge.com")]')->length === 0
        ) {
            return false;
        }

        return $this->detectBody() && $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }
        $email->setType('YourOrder' . ucfirst($this->lang));

        $this->parseEvent($email);

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

    private function parseEvent(Email $email): void
    {
        $event = $email->add()->event();
        $event->place()->type(Event::TYPE_SHOW);

        $status = $this->http->FindSingleNode("//text()[{$this->contains($this->t('statusPhrases'))}]", null, true, "/{$this->opt($this->t('statusPhrases'))}[:\s]+({$this->opt($this->t('statusVariants'))})(?:\s*[,.;:!?]|$)/i");
        $event->general()->status($status);

        $confirmationText = $this->http->FindSingleNode("//text()[{$this->contains($this->t('confNumber'))}]");

        if (empty($confirmationText)) {
            $confirmationText = $this->http->FindSingleNode("//text()[(contains(normalize-space(),'Order Number '))][not(contains(normalize-space(), 'Subject'))]");
        }

        if (preg_match("/({$this->opt($this->t('confNumber'))})\s*([-A-Z\d]{5,})$/", $confirmationText, $m)) {
            $confirmationTitle = $m[1];
            $confirmation = $m[2];
        } else {
            $confirmationTitle = $this->http->FindSingleNode("//text()[{$this->contains($this->t('confNumber'))}]", null, true, "/{$this->opt($this->t('confNumber'))}/");
            $confirmation = $this->http->FindSingleNode("//text()[{$this->contains($this->t('confNumber'))}]/following::text()[normalize-space()][1]", null, true, '/^[-A-Z\d]{5,}$/');
        }
        $event->general()->confirmation($confirmation, $confirmationTitle);

        $xpathAddress = "//text()[{$this->eq($this->t('View on map'))}]/ancestor::*[ descendant::text()[normalize-space()][2] ][1]";

        $name = $this->http->FindSingleNode($xpathAddress . "/preceding::*[self::p or self::div][1]");

        if (empty($name)) {
            $name = $this->http->FindSingleNode(" //a[contains(@href, 'map')]/ancestor::*[ descendant::text()[normalize-space()][2] ][1]/preceding::*[self::p or self::div][1]");
        }
        $event->place()->name($name);

        $addressText = $this->htmlToText($this->http->FindHTMLByXpath($xpathAddress));

        if (preg_match("/^\s*(.{3,}?)\s+{$this->opt($this->t('View on map'))}/s", $addressText, $m)) {
            $event->place()->address(preg_replace('/\s+/', ' ', $m[1]));
        }

        if (empty($event->getAddress())) {
            $address = $this->http->FindSingleNode("//a[contains(@href, 'map')]/ancestor::p[1]");

            if (!empty($address)) {
                $event->setAddress($address);
            }
        }

        $detailsText = $this->htmlToText($this->http->FindHTMLByXpath("//text()[{$this->contains($this->t('Total Cost:'))}]/ancestor::*[ descendant::text()[normalize-space()][2] ][1]"));

        if (preg_match("/^(?:\s*{$this->opt($this->t('Ticket Details'))})?\s*[-[:alpha:]]+\s*,\s*(?<date>[[:alpha:]]+\s+\d{1,2}\s*,\s*\d{4})\b.+?(?<time>\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?)[ ]*\n/u", $detailsText, $m)) {
            $event->booked()->start($this->normalizeDate($m['date'] . ', ' . $m['time']))->noEnd();
        }

        if (preg_match("/\n[ ]*(?<tickets>\d{1,3})\s*{$this->opt($this->t('Ticket(s)'))}\s*,\s*(?<sText>.+?)[ ]*\n+[ ]*{$this->opt($this->t('Total Cost:'))}/s", $detailsText, $m)
            && preg_match_all("/(.+?{$this->opt($this->t('off aisle'))})[,\s]*/", $m['sText'], $seatMatches)
            && count($seatMatches[1]) == $m['tickets']
        ) {
            // CENTER ORCHESTRA Row H, Seat 111, Seat 111 is 8 seats off aisle
            $event->booked()->seats(preg_replace("/^(.{2,}?)\s*,\s*{$this->opt($this->t('Seat'))}[^,]+{$this->opt($this->t('is'))}[^,]+$/", '$1', $seatMatches[1]));
        }

        if (preg_match("/{$this->opt($this->t('Total Cost:'))}[ ]*(.*\d.*?)(?:[ ]*\n|$)/", $detailsText, $m)
            && preg_match("/^(?<currency>[^\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*)$/", $m[1], $matches)
        ) {
            // $650.50
            $event->price()->currency($matches['currency'])->total(PriceHelper::parse($matches['amount']));
        }

        $traveller = null;
        $billingText = $this->htmlToText($this->http->FindHTMLByXpath("//text()[{$this->contains($this->t('Billing Information'))}]/ancestor::*[ descendant::text()[normalize-space()][2] ][1]"));

        if (preg_match("/^.*{$this->opt($this->t('Billing Information'))}.*\n+[ ]*([[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])[ ]*(?:\n|$)/u", $billingText, $m)) {
            $traveller = $m[1];
        }
        $event->general()->traveller($traveller, true);
    }

    private function detectBody(): bool
    {
        if (!isset($this->detectors)) {
            return false;
        }

        foreach ($this->detectors as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (!is_string($phrase)) {
                    continue;
                }

                if ($this->http->XPath->query("//*[{$this->contains($phrase)}]")->length > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['confNumber'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[{$this->contains($phrases['confNumber'])}]")->length > 0) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function t(string $phrase)
    {
        if (!isset(self::$dictionary, $this->lang) || empty(self::$dictionary[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$this->lang][$phrase];
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'contains(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'normalize-space(' . $node . ')=' . $s;
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

    private function htmlToText(?string $s, bool $brConvert = true): string
    {
        if (!is_string($s) || $s === '') {
            return '';
        }
        $s = str_replace("\r", '', $s);
        $s = preg_replace('/<!--.*?-->/s', '', $s); // comments

        if ($brConvert) {
            $s = preg_replace('/\s+/', ' ', $s);
            $s = preg_replace('/<[Bb][Rr]\b.*?\/?>/', "\n", $s); // only <br> tags
        }
        $s = preg_replace('/<[A-z][A-z\d:]*\b.*?\/?>/', '', $s); // opening tags
        $s = preg_replace('/<\/[A-z][A-z\d:]*\b[ ]*>/', '', $s); // closing tags
        $s = html_entity_decode($s);
        $s = str_replace(chr(194) . chr(160), ' ', $s); // NBSP to SPACE

        return trim($s);
    }

    private function normalizeDate($str)
    {
        $this->logger->debug('$str = ' . print_r($str, true));

        $in = [
            "#^(\w+)\s*(\d+)\,\s*(\d{4})\,\s*([\d\:]+\s*A?P?M)$#u", //November 12, 2022, 8:00 PM
        ];
        $out = [
            "$2 $1 $3, $4",
        ];
        $str = preg_replace($in, $out, $str);
//        $this->logger->debug('$str = '.print_r( $str,true));

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }
}
