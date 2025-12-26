<?php

namespace AwardWallet\Engine\vacasa\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class SummaryReservation extends \TAccountChecker
{
    public $mailFiles = "vacasa/it-161314231.eml, vacasa/it-60651275.eml, vacasa/it-99906447.eml, vacasa/it-656987298.eml, vacasa/it-657824108.eml";

    private $detectSubject = [
        "en" => "Reservation Confirmation - ", //Reservation Confirmation - Welches - Wednesday, June 17, 2020 - Saturday, June 20, 2020
        "New booking confirmed starting on ",
    ];

    private $date;
    private $lang = '';
    private static $dictionary = [
        'en' => [
            "confNumber" => ["Confirmation:", "Confirmation code:", "Confirmation", "CONFIRMATION"],
            "checkIn"    => ["Check in", "CHECK IN", "Check-in", "CHECK-IN"],
            "checkOut"   => ["Check out", "CHECK OUT", "Check-out", "CHECK-OUT"],
            "Guests"     => ["Guests", "GUESTS"],
            //            "adult" => "",
            //            "children" => "",
            //            "Total cost" => "",
            "statusPhrases"  => ["You're", "New booking"],
            "statusVariants" => ["confirmed", "cancelled", "canceled"],
        ],
    ];

    private $patterns = [
        'dateShort' => '\b(?<wday>[-[:alpha:]]+)\s*,\s*(?<date>[[:alpha:]]+\s+\d{1,2}|\d{1,2}\s+[[:alpha:]]+)\b', // Fri, Apr 26    |    Fri, 26 Apr
        'time'      => '\d{1,2}(?:[:：]\d{2})?(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.    |    3pm
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();
        $this->date = strtotime($parser->getHeader('date'));

        if ($this->http->XPath->query("//h1[{$this->starts($this->t('New booking'))}]")->length > 0) {
            $this->parseHotelNewBooking($email);
        } elseif ($this->http->XPath->query("//*[{$this->eq($this->t('Get Directions'))}]")->length > 0) {
            $this->parseHotel2024($email);
        } else {
            $this->parseHotel2019($email);
        }

        $email->setType('SummaryReservation' . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[-.@]vacasa\.com$/i', $from) > 0;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,".vacasa.com/") or contains(@href,"www.vacasa.com") or contains(@href,"click.vacasa.com")]')->length === 0
            && $this->http->XPath->query('//text()[starts-with(normalize-space(),"©") and contains(normalize-space(),"Vacasa")]')->length === 0
        ) {
            return false;
        }

        return $this->assignLang();
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider(rtrim($headers['from'], '> ')) !== true) {
            return false;
        }

        foreach ($this->detectSubject as $detectSubject) {
            if (stripos($headers['subject'], $detectSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function parseHotelNewBooking(Email $email): void
    {
        // examples: it-657824108.eml
        $this->logger->debug(__FUNCTION__);

        $h = $email->add()->hotel();

        $unitName = $this->http->FindSingleNode("//h1[{$this->starts($this->t('New booking'))}]/preceding::text()[normalize-space()][1][ preceding::node()[self::text()[normalize-space()] or self::img][1][self::img] ]");
        $h->hotel()->name($unitName)->house();

        $statusTexts = array_filter($this->http->FindNodes("//text()[{$this->contains($this->t('statusPhrases'))}]", null, "/{$this->preg_implode($this->t('statusPhrases'))}[:\s]+({$this->preg_implode($this->t('statusVariants'))})(?:\s+{$this->preg_implode($this->t('for'))}\s|\s*[,.;:!?]|$)/i"));

        if (count(array_unique(array_map('mb_strtolower', $statusTexts))) === 1) {
            $status = array_shift($statusTexts);
            $h->general()->status($status);
        }

        $xpathCheckIn = "//*[ count(*[normalize-space()])=3 and *[normalize-space()][1][{$this->eq($this->t('checkIn'))}] ]/*[normalize-space()]";
        $xpathCheckOut = "//*[ count(*[normalize-space()])=3 and *[normalize-space()][1][{$this->eq($this->t('checkOut'))}] ]/*[normalize-space()]";
        $dateCheckInVal = $this->http->FindSingleNode($xpathCheckIn . '[2]');
        $timeCheckIn = $this->http->FindSingleNode($xpathCheckIn . '[3]', null, true, "/^{$this->patterns['time']}/u");
        $dateCheckOutVal = $this->http->FindSingleNode($xpathCheckOut . '[2]');
        $timeCheckOut = $this->http->FindSingleNode($xpathCheckOut . '[3]', null, true, "/^{$this->patterns['time']}/u");

        $dateCheckIn = $this->normalizeDate($dateCheckInVal);
        $dateCheckOut = $this->normalizeDate($dateCheckOutVal);

        if ($dateCheckIn && $timeCheckIn) {
            $h->booked()->checkIn(strtotime($timeCheckIn, $dateCheckIn));
        }

        if ($dateCheckOut && $timeCheckOut) {
            $h->booked()->checkOut(strtotime($timeCheckOut, $dateCheckOut));
        }

        if ($unitName && !empty($h->getCheckInDate()) && !empty($h->getCheckOutDate())) {
            $h->hotel()->noAddress();
            $h->general()->noConfirmation();
        }
    }

    private function parseHotel2024(Email $email): void
    {
        // examples: it-656987298.eml
        $this->logger->debug(__FUNCTION__);

        $h = $email->add()->hotel();

        $statusTexts = array_filter($this->http->FindNodes("//text()[{$this->contains($this->t('statusPhrases'))}]", null, "/{$this->preg_implode($this->t('statusPhrases'))}[:\s]+({$this->preg_implode($this->t('statusVariants'))})(?:\s+{$this->preg_implode($this->t('for'))}\s|\s*[,.;:!?]|$)/i"));

        if (count(array_unique(array_map('mb_strtolower', $statusTexts))) === 1) {
            $status = array_shift($statusTexts);
            $h->general()->status($status);
        }

        $xpathAddress = "//*[ *[normalize-space()][3][{$this->eq($this->t('Get Directions'))}] ]/*[normalize-space()]";
        $h->hotel()->name($this->http->FindSingleNode($xpathAddress . '[1]'))->address(implode(', ', array_filter($this->http->FindNodes($xpathAddress . '[2]/descendant::text()[normalize-space()]', null, "/^[,\s]*([^,\s].*?)[,\s]*$/"))))->house();

        $checkInVal = $this->normalizeDate($this->http->FindSingleNode("//*[ count(node()[normalize-space()])=2 and node()[normalize-space()][1][{$this->eq($this->t('checkIn'))}] ]/node()[normalize-space()][2]"));
        $checkOutVal = $this->normalizeDate($this->http->FindSingleNode("//*[ count(node()[normalize-space()])=2 and node()[normalize-space()][1][{$this->eq($this->t('checkOut'))}] ]/node()[normalize-space()][2]"));
        $h->booked()->checkIn($checkInVal)->checkOut($checkOutVal);

        $confirmation = $this->http->FindSingleNode("//tr[{$this->eq($this->t('confNumber'))}]/following-sibling::tr[normalize-space()][1]", null, true, '/^[A-Z\d]{5,25}$/');

        if ($confirmation) {
            $confirmationTitle = $this->http->FindSingleNode("//tr[{$this->eq($this->t('confNumber'))}]", null, true, '/^(.+?)[\s:：]*$/u');
            $email->ota()->confirmation($confirmation, $confirmationTitle);
            $h->general()->noConfirmation();
        }

        $totalPrice = $this->http->FindSingleNode("//tr[{$this->eq($this->t('confNumber'))}]/following-sibling::tr[{$this->starts($this->t('Total cost'))}]", null, true, "/^{$this->preg_implode($this->t('Total cost'))}[:\s]+(.*\d.*)$/");

        if (preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*)$/', $totalPrice, $matches)
            || preg_match('/^(?<amount>\d[,.\'\d ]*)[ ]*(?<currency>[^\-\d)(]+?)$/', $totalPrice, $matches)
        ) {
            // $2,544.93
            $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
            $h->price()->currency($matches['currency'])->total(PriceHelper::parse($matches['amount'], $currencyCode));
        }

        $guestsVal = $this->http->FindSingleNode("//tr[{$this->eq($this->t('Guests'))}]/following-sibling::tr[normalize-space()][1]");

        if (preg_match("/\b(\d{1,3})\s*{$this->preg_implode($this->t('adult'))}/i", $guestsVal, $m)) {
            $h->booked()->guests($m[1]);
        }

        if (preg_match("/\b(\d{1,3})\s*{$this->preg_implode($this->t('child'))}/i", $guestsVal, $m)) {
            $h->booked()->kids($m[1]);
        }
    }

    private function parseHotel2019(Email $email): void
    {
        // examples: it-161314231.eml, it-60651275.eml, it-99906447.eml
        $this->logger->debug(__FUNCTION__);

        $xpathBold = '(self::h2 or self::b or self::strong or ancestor-or-self::*[contains(@style,"bold")])';

        // it-99906447.eml, it-60651275.eml
        $xpathHotel_1 = "//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1]/descendant::text()[normalize-space()][1]/ancestor::*[{$xpathBold}] and *[normalize-space()][2][{$this->starts($this->t('confNumber'))}] ]";

        // it-161314231.eml
        $xpathHotel_2 = "//*[ normalize-space() and preceding-sibling::*[normalize-space()][1][self::h2] and following::*[normalize-space()][1][{$this->starts($this->t('confNumber'))}] ]";

        // Travel Agency
        $confirmationCode = $this->http->FindSingleNode("//text()[{$this->eq($this->t("confNumber"))}]/following::text()[normalize-space()][1]",
            null, true, "/^\s*([A-Z\d]{5,})\s*$/");

        if (empty($confirmationCode)) {
            $confirmationCode = $this->http->FindSingleNode("//text()[{$this->starts($this->t("confNumber"))}]",
                null, true, "/{$this->preg_implode($this->t("confNumber"))}\s*([A-Z\d]{5,})$/");
        }

        if (empty($confirmationCode)) {
            $confirmationCode = $this->http->FindSingleNode("//meta[@itemprop = 'reservationNumber']/@content",
                null, true, "/^\s*([A-Z\d]{5,})$/");
        }

        if (!empty($confirmationCode)) {
            $confirmationName = $this->http->FindSingleNode("//text()[{$this->starts($this->t("confNumber"))}]",
                    null, true, "/{$this->preg_implode($this->t("confNumber"))}\s*/");
            $email->ota()->confirmation($confirmationCode, rtrim($confirmationName, ': '));
        }

        $h = $email->add()->hotel();

        // General
        $h->general()
            ->noConfirmation()
        ;
        $traveller = $this->http->FindSingleNode("//div[@itemprop = 'underName']/meta[@itemprop = 'name']/@content");

        if (!empty($traveller)) {
            $h->general()
                ->traveller($traveller, true);
        }

        // Hotel
        $hotelName = $address = null;
        $hotelText = $this->htmlToText($this->http->FindHTMLByXpath($xpathHotel_1 . "/*[normalize-space()][1]"));

        if (preg_match("/^\s*(?<name>.{2,}?)(?<address>(?:[ ]*\n+.{2,}){1,4}?)\s*$/", $hotelText, $m)) {
            $hotelName = $m['name'];
            $address = preg_replace('/[ ]*\n+[ ]*/', ', ', trim($m['address']));
        } else {
            $hotelName = $this->htmlToText($this->http->FindHTMLByXpath($xpathHotel_2 . "/preceding-sibling::*[normalize-space()][1]"));
            $addressVal = $this->htmlToText($this->http->FindHTMLByXpath($xpathHotel_2));
            $address = preg_replace('/[ ]*\n+[ ]*/', ', ', trim($addressVal));
        }
        $h->hotel()->name($hotelName)->address($address);

        // Booked
        $h->booked()
            ->checkIn($this->normalizeDate($this->http->FindSingleNode("//text()[{$this->eq($this->t("checkIn"))}]/following::text()[normalize-space()][1]")))
            ->checkOut($this->normalizeDate($this->http->FindSingleNode("//text()[{$this->eq($this->t("checkOut"))}]/following::text()[normalize-space()][1]")))
            ->guests($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Guests")) . "]/following::text()[normalize-space()][1]", null, true, "/\b(\d{1,3})\s*{$this->preg_implode($this->t("adult"))}/i"))
            ->kids($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Guests")) . "]/following::text()[normalize-space()][1]", null, true, "/\b(\d{1,3})\s*{$this->preg_implode($this->t("child"))}/i"), true, true);

        // Price
        $totalPrice = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Total cost'))}] ]/*[normalize-space()][2]", null, true, '/^.*\d.*$/');

        if (preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*)$/', $totalPrice, $matches)
            || preg_match('/^(?<amount>\d[,.\'\d ]*)[ ]*(?<currency>[^\-\d)(]+?)$/', $totalPrice, $matches)
        ) {
            // $4,085.75
            $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
            $h->price()->currency($matches['currency'])->total(PriceHelper::parse($matches['amount'], $currencyCode));
        }
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['checkIn']) || empty($phrases['checkOut'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[{$this->contains($phrases['checkIn'])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->contains($phrases['checkOut'])}]")->length > 0
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

    private function preg_implode($field): string
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '/'); }, $field)) . ')';
    }

    private function normalizeDate($date)
    {
//        $this->logger->debug('$date = '.print_r( $date,true));
        $year = date('Y', $this->date);
        $in = [
            //Tue, Jun 16, 4:00 pm
            '#^(\w+),\s*(\w+)\s+(\d+)\s*,\s*(\d{1,2}:\d{2}(?:\s*[ap]m))$#iu',
            // 4:00 PM, Thursday, December 02, 2021
            '#^\s*(\d{1,2}:\d{2}(?:\s*[ap]m))\s*(?:,|\s+)\s*\w+,\s*(\w+)\s+(\d{1,2})\s*,\s*(\d{4})\s*$#iu',
            // Tue, Jun 16
            '/^([-[:alpha:]]+)\s*,\s*([[:alpha:]]+)\s+(\d{1,2})$/u',
            // Tue, 16 Jun
            '/^([-[:alpha:]]+)\s*,\s*(\d{1,2})\s+([[:alpha:]]+)$/u',
        ];
        $out = [
            '$1, $3 $2 ' . $year . ', $4',
            '$3 $2 $4, $1',
            '$1, $3 $2 ' . $year,
            '$1, $2 $3 ' . $year,
        ];
        $date = preg_replace($in, $out, $date);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#u", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        if (preg_match("#^(?<week>\w+), (?<date>\d+ \w+ .+)#u", $date, $m)) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($m['week'], $this->lang));
            $date = EmailDateHelper::parseDateUsingWeekDay($m['date'], $weeknum);
        } elseif (preg_match("#\b\d{4}\b#", $date)) {
            $date = strtotime($date);
        } else {
            $date = null;
        }

        return $date;
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) {
            return $text . '="' . $s . '"';
        }, $field)) . ')';
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
