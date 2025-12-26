<?php

namespace AwardWallet\Engine\tablethotels\Email;

use AwardWallet\Schema\Parser\Email\Email;

class YourReservation extends \TAccountChecker
{
    public $mailFiles = "tablethotels/it-67484655.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'confNumber' => ['Confirmation #'],
            'Stay Dates' => ['Stay Dates'],
        ],
    ];

    private $subjects = [
        'en' => ['Your Upcoming Reservation at'],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@tablethotels.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
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
        if ($this->http->XPath->query('//a[contains(@href,".tablethotels.com/") or contains(@href,"cb.tablethotels.com")]')->length === 0
            && $this->http->XPath->query('//node()[contains(normalize-space(),"Tablet Inc. All rights reserved")]')->length === 0
        ) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }
        $email->setType('YourReservation' . ucfirst($this->lang));

        $this->parseHotel($email);

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

    private function parseHotel(Email $email): void
    {
        $h = $email->add()->hotel();

        $xpathHotel = "//tr[ count(*)=2 and *[1][normalize-space()] and *[2][descendant::img and normalize-space()=''] ]/*[1]/descendant::p[normalize-space()]";

        $hotelName = $this->http->FindSingleNode($xpathHotel . '[1]');
        $address = implode(', ', $this->http->FindNodes($xpathHotel . '[2]/descendant::text()[normalize-space()]'));
        $h->hotel()
            ->name($hotelName)
            ->address($address);

        $confirmationHtml = $this->http->FindHTMLByXpath("//li[{$this->starts($this->t('confNumber'))}]");
        $confirmationText = $this->htmlToText($confirmationHtml);

        if (preg_match("/^({$this->opt($this->t('confNumber'))})[:\s]+([-A-Z\d]{5,})$/", $confirmationText, $m)) {
            $h->general()->confirmation($m[2], $m[1]);
        }

        $datesHtml = $this->http->FindHTMLByXpath("//li[{$this->starts($this->t('Stay Dates'))}]");
        $datesText = $this->htmlToText($datesHtml);

        if (preg_match("/^{$this->opt($this->t('Stay Dates'))}[:\s]+(.{6,}?)[ ]+-[ ]+(.{6,})$/", $datesText, $m)) {
            $h->booked()
                ->checkIn2($m[1])
                ->checkOut2($m[2]);
        }

        $travellerHtml = $this->http->FindHTMLByXpath("//li[{$this->starts($this->t('Guest Name'))}]");
        $travellerText = $this->htmlToText($travellerHtml);

        if (preg_match("/^{$this->opt($this->t('Guest Name'))}[:\s]+([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])$/u", $travellerText, $m)) {
            $h->general()->traveller($m[1]);
        }

        $roomHtml = $this->http->FindHTMLByXpath("//li[{$this->starts($this->t('Room'))}]");
        $roomText = $this->htmlToText($roomHtml);

        if (preg_match("/^{$this->opt($this->t('Room'))}[:\s]+(.{3,})$/u", $roomText, $m)) {
            $room = $h->addRoom();
            $room->setType($m[1]);
        }

        $checkInTimeHtml = $this->http->FindHTMLByXpath("//li[{$this->starts($this->t('Check-in After'))}]");
        $checkInTimeText = $this->htmlToText($checkInTimeHtml);

        if (preg_match("/^{$this->opt($this->t('Check-in After'))}[:\s]+(\d{1,2}(?:[:：]\d{2})?(?:\s*[AaPp]\.?[Mm]\.?)?)(?:\s*\(|$)/", $checkInTimeText, $m)
            && $h->getCheckInDate()
        ) {
            $h->booked()->checkIn(strtotime($m[1], $h->getCheckInDate()));
        }
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['confNumber']) || empty($phrases['Stay Dates'])) {
                continue;
            }

            if ($this->http->XPath->query("//node()[{$this->contains($phrases['confNumber'])}]")->length > 0
                && $this->http->XPath->query("//node()[{$this->contains($phrases['Stay Dates'])}]")->length > 0
            ) {
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
}
