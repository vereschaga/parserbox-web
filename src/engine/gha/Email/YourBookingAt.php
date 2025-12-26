<?php

namespace AwardWallet\Engine\gha\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class YourBookingAt extends \TAccountCheckerExtended
{
    public $mailFiles = "gha/it-226153603.eml, gha/it-226153604.eml";

    public $detectSubject = [
        // en: Your booking at Pan Pacific Singapore is confirmed – Confirmation #16494041
        'is confirmed – Confirmation #',
        // Your booking at Mysk by Shaza Al Mouj Muscat has been cancelled
        'has been cancelled', 'has been canceled',
    ];

    public $detectBody = [
        "en" => [
            'Details of your stay', 'Please take a moment to review the details of your cancellation',
            'Please take a moment to review the details of your cancelation',
        ],
    ];

    public $lang = "";
    public static $dictionary = [
        "en" => [
            'statusPhrases'      => ['Your booking is', 'Your booking has been'],
            'statusVariants'     => ['confirmed', 'cancelled', 'canceled'],
            'has been cancelled' => [
                'has been cancelled', 'has been canceled',
                'Please take a moment to review the details of your cancellation',
                'Please take a moment to review the details of your cancelation',
            ],
            'cancellBy' => ['Cancell by', 'Cancel by'],
        ],
    ];

    public function parseHtml(Email $email): void
    {
        $patterns = [
            'time' => '\d{1,2}(?:[:：]\d{2})?(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.    |    3pm
        ];

        $h = $email->add()->hotel();

        // General
        $statusTexts = array_filter($this->http->FindNodes("//text()[{$this->contains($this->t('statusPhrases'))}]", null, "/{$this->opt($this->t('statusPhrases'))}[:\s]+({$this->opt($this->t('statusVariants'))})(?:\s*[,.;:!?]|$)/i"));

        if (count(array_unique($statusTexts)) === 1) {
            $status = array_shift($statusTexts);
            $h->general()->status($status);
        }

        $h->general()
            ->confirmation($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Confirmation Number")) . "]/following::text()[normalize-space()][1]",
                null, true, "/^\s*\s*([A-Z\d]{5,})\s*$/"))
            ->traveller($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Guest Name:")) . "]/following::text()[normalize-space()][1]"), true)
        ;

        if ($this->http->XPath->query("descendant::*[{$this->contains($this->t('has been cancelled'))}]")->length > 0) {
            $h->general()->cancelled();

            if (empty($h->getStatus())) {
                $h->general()->status('Cancelled');
            }
        }

        // Hotel
        $h->hotel()
            ->name($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Check-in Date")) . "]/preceding::tr[not(.//tr)][normalize-space()][2][not(.//img)]"))
            ->address($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Check-in Date")) . "]/preceding::tr[not(.//tr)][normalize-space()][1][count(.//img) = 1]"))
        ;

        // Booked
        $h->booked()
            ->checkIn($this->normalizeDate($this->http->FindSingleNode("//text()[{$this->eq($this->t('Check-in Date'))}]/following::text()[normalize-space()][1]")))
            ->checkOut($this->normalizeDate($this->http->FindSingleNode("//text()[{$this->eq($this->t('Check-out Date'))}]/following::text()[normalize-space()][1]")))
        ;

        $details3Text = $this->htmlToText($this->http->FindHTMLByXpath("//tr/*[not(.//tr)][ descendant::text()[{$this->contains($this->t('ADULT'))} and {$this->contains($this->t('CHILD'))}] ]"));

        $h->booked()
            ->rooms($this->http->FindSingleNode("//text()[{$this->contains($this->t('Rooms:'))}]",
                null, true, "/{$this->opt($this->t("Rooms:"))}\s*(\d+)/ui"))
            ->guests($this->re("/\b(\d{1,3})[ ]+{$this->opt($this->t('ADULT'))}/i", $details3Text))
            ->kids($this->re("/[+ ]+(\d{1,3})[ ]+{$this->opt($this->t('CHILD'))}/i", $details3Text))
        ;

        $roomType = $this->re("/^(.{2,}?)[ ]*\n+.{2,}\n+.*(?:{$this->opt($this->t('ADULT'))}|{$this->opt($this->t('CHILD'))})/", $details3Text);

        if ($roomType) {
            $h->addRoom()->setType($roomType);
        }

        // Program
        $account = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Membership Number:'))}]/following::text()[normalize-space()][1]",
            null, true, "/^\s*\d{5,}\s*$/");

        if (!empty($account)) {
            $h->program()
                ->account($account, false);
        }
        // Price
        $xpath = "//tr[ ./td[1][ count(.//text()[normalize-space()]) = 2 ] [descendant::text()[normalize-space()][1][{$this->eq($this->t('SUBTOTAL:'))}] and descendant::text()[normalize-space()][2][{$this->eq($this->t('TAXES & FEES:'))}] ]]/td[2][ count(.//text()[normalize-space()]) = 2 ]";
        $cost = $this->http->FindSingleNode($xpath . "/descendant::text()[normalize-space()][1]");

        if (preg_match("#^\s*(?<currency>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $cost, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d\s]{1,5})\s*$#", $cost, $m)) {
            $h->price()
                ->cost(PriceHelper::parse($m['amount'], $m['currency']));
        }
        $taxes = $this->http->FindSingleNode($xpath . "/descendant::text()[normalize-space()][2]");

        if (preg_match("#^\s*(?<currency>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $taxes, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d\s]{1,5})\s*$#", $taxes, $m)) {
            $h->price()
                ->tax(PriceHelper::parse($m['amount'], $m['currency']));
        }
        $total = $this->http->FindSingleNode("//tr[./td[1][{$this->eq($this->t('ROOM TOTAL'))}]]/td[2]");

        if (preg_match("#^\s*(?<currency>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $total, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d\s]{1,5})\s*$#", $total, $m)) {
            $h->price()
                ->total(PriceHelper::parse($m['amount'], $m['currency']))
                ->currency($m['currency'])
            ;
        }

        $termsConditionsText = $this->htmlToText($this->http->FindHTMLByXpath("//tr/*[not(.//tr)][ descendant::text()[normalize-space()][1][{$this->eq($this->t('Terms and conditions'))}] ]"));
        $cancellation = $this->re("/^[ ]*(?:" . (empty($roomType) ? '' : $this->opt($roomType) . ' [-–]+ ') . ")?({$this->opt($this->t('cancellBy'))}\s+.+?)[ ]*$/im", $termsConditionsText);
        $h->general()->cancellation($cancellation, false, true);

        if (preg_match("/^Cancell? by (?<time>{$patterns['time']}) on (?<date>.{4,20}?\b\d{2,4}).{0,30} to avoid a penalty charge of /", $cancellation, $m) // en
        ) {
            $h->booked()->deadline(strtotime($m['time'], strtotime($m['date'])));
        }
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, '@email.ghadiscovery.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->detectSubject as $re) {
            if (strpos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href, '.ghadiscovery.com')]")->length === 0) {
            return false;
        }

        foreach ($this->detectBody as $dBody) {
            if ($this->http->XPath->query("//node()[{$this->contains($dBody)}]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        foreach ($this->detectBody as $lang => $dBody) {
            if ($this->http->XPath->query("//node()[{$this->contains($dBody)}]")->length > 0) {
                $this->lang = $lang;

                break;
            }
        }

        $this->parseHtml($email);

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

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($date)
    {
//        $this->logger->debug('$date = '.print_r( $date,true));
        $in = [
            // 19/09/22
            "/^\s*(\d{1,2})\\/(\d{2})\\/(\d{2})\s*$/ui",
        ];
        $out = [
            "$1.$2.20$3",
        ];
        $date = preg_replace($in, $out, $date);
//        $this->logger->debug('$date = '.print_r( $date,true));

        if (preg_match("#\d+\s+([[:alpha:]]+)\s+(?:\d{4}|%Y%)#u", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        return strtotime($date);
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) { return "normalize-space(.)=\"{$s}\""; }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) { return "contains(normalize-space(.), \"{$s}\")"; }, $field));
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }

    private function re($re, $str, $c = 1): ?string
    {
        if (preg_match($re, $str, $m) && array_key_exists($c, $m)) {
            return $m[$c];
        }

        return null;
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
}
