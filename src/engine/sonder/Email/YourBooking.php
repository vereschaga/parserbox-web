<?php

namespace AwardWallet\Engine\sonder\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class YourBooking extends \TAccountChecker
{
    public $mailFiles = "sonder/it-597437605.eml, sonder/it-595537455.eml, sonder/it-587448868.eml, sonder/it-600270711.eml, sonder/it-591065985-fr-cancelled.eml, sonder/it-607596711-it-cancelled.eml";

    public $lang = '';

    public static $dictionary = [
        'fr' => [
            'confNumber'       => ['Code de confirmation:', 'Code de confirmation :'],
            // 'nightsCount' => [''],
            'statusPhrases'    => ['Votre réservation a été'],
            'statusVariants'   => ['annulée'],
            'cancelledPhrases' => ['Votre réservation a été annulée.'],
        ],
        'it' => [
            'confNumber'       => ['Codice di conferma:', 'Codice di conferma :'],
            // 'nightsCount' => [''],
            'statusPhrases'    => ['La tua prenotazione è stata'],
            'statusVariants'   => ['cancellata'],
            'cancelledPhrases' => ['La tua prenotazione è stata cancellata.'],
        ],
        'en' => [
            'confNumber'       => ['Confirmation code:', 'Confirmation code :'],
            'nightsCount'      => ['( nights)', '( night)'],
            'statusPhrases'    => ['Your reservation has been'],
            'statusVariants'   => ['cancelled', 'canceled'],
            'cancelledPhrases' => ['Your reservation has been cancelled.'],
        ],
    ];

    private $xpath = [
        'withoutNumbers' => 'translate(.,"0123456789","")',
    ];

    private $patterns = [
        // November 30, 2023    |    21 novembre 2023    |    Thu, Nov 23, 2023    |    Fri, Nov 24
        'date' => '(?:\b[[:alpha:]]+\s+\d{1,2}\s*,\s*\d{4}\b|\b\d{1,2}\s+[[:alpha:]]+\s+\d{4}\b|\b[-[:alpha:]]+\s*,\s*[[:alpha:]]+\s+\d{1,2}(?:\s*,\s*\d{4})?\b)',
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@sonder.com') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,".sonder.com/") or contains(@href,".sonder-mail.com/") or contains(@href,"www.sonder.com") or contains(@href,"links.sonder-mail.com")]')->length === 0
            && $this->http->XPath->query('//text()[starts-with(normalize-space(),"©") and (contains(normalize-space(),"Sonder, Inc") or contains(normalize-space(),"Sonder Holdings Inc"))]')->length === 0
        ) {
            return false;
        }

        return $this->assignLang() && $this->findDates() !== null;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }
        $email->setType('YourBooking' . ucfirst($this->lang));

        $h = $email->add()->hotel();

        $statusTexts = array_filter($this->http->FindNodes("//text()[{$this->contains($this->t('statusPhrases'))}]", null, "/{$this->opt($this->t('statusPhrases'))}[:\s]+({$this->opt($this->t('statusVariants'))})(?:\s*[,.;:!?]|$)/i"));

        if (count(array_unique($statusTexts)) === 1) {
            $status = array_shift($statusTexts);
            $h->general()->status($status);
        }

        if ($this->http->XPath->query("//*[{$this->contains($this->t('cancelledPhrases'))}]")->length > 0) {
            $h->general()->cancelled();
        }

        $confirmation = $this->http->FindSingleNode("//text()[{$this->starts($this->t('confNumber'))}]");

        if (preg_match("/^({$this->opt($this->t('confNumber'))})[:\s]*([-A-Z\d]{7,16})$/", $confirmation, $m)) {
            $h->general()->confirmation($m[2], rtrim($m[1], ' :'));
        } elseif (empty($confirmation)
            && $this->http->XPath->query("//*[{$this->contains($this->t('confNumber'))}]")->length === 0
        ) {
            // it-600270711.eml
            $h->general()->noConfirmation();
        }

        $dates = $this->findDates();

        if ($dates === null) {
            return $email;
        }

        $roomType = null;
        $hotelNameTexts = [];
        $hotelNameRows = $this->http->XPath->query("//*/tr[{$this->starts($this->t('confNumber'))}]/preceding-sibling::tr[normalize-space()]");

        if ($hotelNameRows->length === 0
            && $this->http->XPath->query("//*[{$this->contains($this->t('confNumber'))}]")->length === 0
        ) {
            // it-600270711.eml
            $hotelNameRows = $this->http->XPath->query("//*/tr[{$this->starts($dates[0])} and {$this->contains($dates[1])} and {$this->contains($this->t('nightsCount'), $this->xpath['withoutNumbers'])}]/preceding-sibling::tr[normalize-space()]");
        }

        foreach ($hotelNameRows as $hnRow) {
            $rowText = $this->htmlToText($this->http->FindHTMLByXpath('.', null, $hnRow));
            $rowText = trim(preg_replace("/^[ ]*{$this->opt($dates[0])}.*/m", '', $rowText));

            if (empty($rowText)) {
                continue;
            }

            $hotelNameTexts[] = $rowText;
        }

        if (count($hotelNameTexts) === 2
            && preg_match("/^(?<roomType>.{2,}?)\s+{$this->opt($this->t('at'))}\s+(?<address>.{3,})$/", $hotelNameTexts[1], $m)
        ) {
            // it-597437605.eml
            $roomType = $m['roomType'];
            $h->hotel()->name($hotelNameTexts[0])->address($m['address']);
        } elseif (count($hotelNameTexts) === 2 && $h->getNoConfirmationNumber()) {
            // it-600270711.eml
            $h->hotel()->name($hotelNameTexts[0])->noAddress();
        } elseif (count($hotelNameTexts) === 2) {
            // it-591065985-fr-cancelled.eml
            $h->hotel()->name($hotelNameTexts[0])->address($hotelNameTexts[1]);
        } elseif (count($hotelNameTexts) === 3) {
            // it-595537455.eml
            $h->hotel()->name($hotelNameTexts[0])->address($hotelNameTexts[1] . ', ' . $hotelNameTexts[2]);
        }

        $h->hotel()->house();

        if ($roomType) {
            $room = $h->addRoom();
            $room->setType($roomType);
        }

        $year = $this->http->FindSingleNode('//text()[starts-with(normalize-space(),"©") and contains(normalize-space(),"Sonder")]', null, true, "/^©\s*(\d{4})\b/u");

        if (preg_match($pattern = "/^(?<wday>[-[:alpha:]]+)[, ]+(?<date>[[:alpha:]]+[ ]+\d{1,2})$/u", $dates[0], $matches)) {
            // it-595537455.eml
            $weekDateNumber = WeekTranslate::number1($matches['wday']);
            $dateValNormal = $this->normalizeDate($matches['date']);
            $dateCheckIn = EmailDateHelper::parseDateUsingWeekDay($dateValNormal . ' ' . $year, $weekDateNumber);
        } else {
            $dateCheckIn = strtotime($this->normalizeDate($dates[0]));
        }

        if (preg_match($pattern, $dates[1], $matches)) {
            $weekDateNumber = WeekTranslate::number1($matches['wday']);
            $dateValNormal = $this->normalizeDate($matches['date']);
            $dateCheckOut = EmailDateHelper::parseDateUsingWeekDay($dateValNormal . ' ' . $year, $weekDateNumber);
        } else {
            $dateCheckOut = strtotime($this->normalizeDate($dates[1]));
        }

        $h->booked()->checkIn($dateCheckIn)->checkOut($dateCheckOut);

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

    private function findDates(): ?array
    {
        $datesHtml = $this->http->FindHTMLByXpath("//text()[{$this->starts($this->t('confNumber'))}]/ancestor::*[ descendant::text()[normalize-space()][4] ][1]");

        if (empty($datesHtml)) {
            // it-600270711.eml
            $datesHtml = $this->http->FindHTMLByXpath("//text()[{$this->contains($this->t('nightsCount'), $this->xpath['withoutNumbers'])}]/ancestor::*[ descendant::text()[normalize-space()][3] ][1]");
        }

        if (empty($datesHtml)) {
            return null;
        }

        $datesText = $this->htmlToText($datesHtml);

        if (preg_match("/(?<date1>{$this->patterns['date']})[ ]+[-–]+[ ]+(?<date2>{$this->patterns['date']})/", $datesText, $m)) {
            return [$m['date1'], $m['date2']];
        }

        return null;
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang)) {
                continue;
            }

            if (!empty($phrases['confNumber']) && $this->http->XPath->query("//*[{$this->contains($phrases['confNumber'])}]")->length > 0
                || !empty($phrases['nightsCount']) && $this->http->XPath->query("//*[{$this->contains($phrases['nightsCount'], $this->xpath['withoutNumbers'])}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function t(string $phrase, string $lang = '')
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return $phrase;
        }

        if ($lang === '') {
            $lang = $this->lang;
        }

        if (empty(self::$dictionary[$lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$lang][$phrase];
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

    /**
     * Dependencies `use AwardWallet\Engine\MonthTranslate` and `$this->lang`.
     *
     * @param string|null $text Unformatted string with date
     */
    private function normalizeDate(?string $text): ?string
    {
        if (preg_match('/\b(\d{1,2})\s+([[:alpha:]]+)\s+(\d{4})$/u', $text, $m)) {
            // 17 novembre 2023
            $day = $m[1];
            $month = $m[2];
            $year = $m[3];
        } elseif (preg_match('/\b([[:alpha:]]+)\s+(\d{1,2})\s*,\s*(\d{4})$/u', $text, $m)) {
            // Sat, Jan 18, 2020
            $month = $m[1];
            $day = $m[2];
            $year = $m[3];
        } elseif (preg_match('/^([[:alpha:]]+)\s+(\d{1,2})$/u', $text, $m)) {
            // Jan 18
            $month = $m[1];
            $day = $m[2];
            $year = '';
        } elseif (preg_match('/^(\d{1,2})\s+([[:alpha:]]+)$/u', $text, $m)) {
            // 18 Jan
            $day = $m[1];
            $month = $m[2];
            $year = '';
        }

        if (isset($day, $month, $year)) {
            if (preg_match('/^\s*(\d{1,2})\s*$/', $month, $m)) {
                return $m[1] . '/' . $day . ($year ? '/' . $year : '');
            }

            if (($monthNew = MonthTranslate::translate($month, $this->lang)) !== false) {
                $month = $monthNew;
            }

            return $day . ' ' . $month . ($year ? ' ' . $year : '');
        }

        return null;
    }
}
