<?php

namespace AwardWallet\Engine\marriott\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class ReservationShare extends \TAccountChecker
{
    public $mailFiles = "marriott/it-55157300.eml, marriott/it-55157328.eml, marriott/it-56130758.eml";
    public $reFrom = "marriott.com";
    public $reSubject = [
        'de' => "Diese Reservierung ist vielleicht auch für Sie/dich interessant",
        'en' => "Here's a reservation I wanted to share",
        "Airport hotel",
        "Confirmation number",
    ];

    public $reBody2 = [
        'de'  => 'Diese Reservierung ist vielleicht auch für',
        'en'  => "Here's a reservation I wanted to share",
        'en2' => 'If you are not the intended recipient of this e-mail',
    ];

    public $lang = '';

    public static $dictionary = [
        'de' => [
            'GUEST' => 'GAST',
            'Here'  => 'Diese',
            'DATES' => 'REISEDATEN',
            'to'    => 'für',
            //            'Check-in:' => '',
            //            'Check-out:' => '',
            //            'HOTEL' => '',
        ],
        'en' => [],
    ];

    public $travellers;
    public $checkIn;
    public $checkOut;

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang($this->http->Response['body']);

        $type = '';

        if ($this->http->XPath->query("//br[ following-sibling::node()[1][self::br] and following-sibling::node()[2][{$this->eq($this->t('HOTEL'))}] ]")->length === 1) {
            $type = 'Html';
            $this->parseHTML($email);
        } else {
            $type = 'Plain';
            $textHtml = str_replace(['‪', '‬'], '', $parser->getHTMLBody()); // remove hidden chars
            $this->http->SetEmailBody($textHtml);
            $xpath = "//text()[{$this->contains($this->t('GUEST'))}]/ancestor::*[ descendant::text()[{$this->contains($this->t('DATES'))}] ][1]";
            $emailHtml = $this->http->FindHTMLByXpath($xpath);
            $emailText = $this->htmlToText($emailHtml, $this->http->XPath->query($xpath . '/descendant::br')->length > 0);
            $this->parseText($email, $emailText);
        }

        $email->setType('ReservationShare' . $type . ucfirst($this->lang));

        return $email;
    }

    public function parseHTML(Email $email): void
    {
        $h = $email->add()->hotel();

        $travellers = array_filter($this->http->FindNodes("//text()[{$this->contains($this->t('GUEST'))}]/ancestor::*/descendant::text()[{$this->starts($this->t('DATES'))}]/preceding-sibling::text()[not({$this->contains($this->t('GUEST'))}) and not({$this->contains($this->t('Here'))})]"));

        $h->general()
            ->noConfirmation()
            ->travellers($travellers, true);

        $dateText = $this->http->FindSingleNode("//text()[{$this->contains($this->t('DATES'))}]/following::text()[normalize-space()][1]");
        $dates = preg_split("/\s+{$this->opt($this->t('to'))}\s+/", $dateText);

        if (count($dates) !== 2) {
            $this->logger->debug('Wrong date format!');

            return;
        }

        $this->checkOut = strtotime($this->normalizeDate($dates[1]));

        if (preg_match('/^[-[:alpha:]]{2,}\s*,\s*(\d{1,2})\.?$/u', $dates[0], $m) && !empty($this->checkOut)) {
            // Thursday, 18
            $this->checkIn = strtotime($m[1] . ' ' . date('M Y', $this->checkOut));
        } elseif (!preg_match('/\d{4}$/', $dates[0]) && !empty($this->checkOut)) {
            // Thursday, 18 June    |    Thursday, June 18
            $this->checkIn = strtotime($this->normalizeDate($dates[0] . ', ' . date('Y', $this->checkOut)));
        }

        $timeStart = $this->http->FindSingleNode("//text()[{$this->contains($this->t('DATES'))}]/following::text()[{$this->starts($this->t('Check-in:'))}][1]", null, true, "/{$this->opt($this->t('Check-in:'))}\s*([\d\:]+\s*?(?:AM|PM)?)$/");

        if (!empty($this->checkIn) && !empty($timeStart)) {
            $this->checkIn = strtotime($timeStart, $this->checkIn);
        }

        $timeEnd = $this->http->FindSingleNode("//text()[{$this->contains($this->t('DATES'))}]/following::text()[{$this->starts($this->t('Check-out:'))}][1]", null, true, "/{$this->opt($this->t('Check-out:'))}\s*([\d\:]+\s*?(?:AM|PM)?)$/");

        if (!empty($this->checkOut) && !empty($timeEnd)) {
            $this->checkOut = strtotime($timeEnd, $this->checkOut);
        }

        $h->booked()
            ->checkIn($this->checkIn)
            ->checkOut($this->checkOut);

        $hotelName = $this->http->FindSingleNode("//text()[{$this->contains($this->t('HOTEL'))}]/following::text()[1]");
        $hotelAddressPart1 = $this->http->FindSingleNode("//text()[{$this->contains($this->t('HOTEL'))}]/following::text()[1]/following::text()[1]");
        $hotelAddressPart2 = $this->http->FindSingleNode("//text()[{$this->contains($this->t('HOTEL'))}]/following::text()[1]/following::text()[2]", null, true, "#^\s*(\w.+)#");
        $hotelPhone = $this->http->FindSingleNode("//text()[{$this->contains($this->t('HOTEL'))}]/following::text()[position()<5][contains(normalize-space(), '+')]");

        $h->hotel()
            ->name($hotelName)
            ->address(implode(' ', array_filter([$hotelAddressPart1, $hotelAddressPart2])))
            ->phone(str_replace(['‪', '‬'], '', $hotelPhone));
    }

    public function parseText(Email $email, string $emailText): void
    {
        $h = $email->add()->hotel();

        //Travellers
        $travelText = $this->re("/^[ ]*{$this->opt($this->t('GUEST'))}[ ]*$\s+(.{2,}?)\s+^[ ]*{$this->opt($this->t('DATES'))}[ ]*$/ms", $emailText);
        $travellers = preg_split('/[ ]*\n+[ ]*/', $travelText);
        $h->general()
            ->noConfirmation()
            ->travellers($travellers, true);

        $patterns['time'] = '\d{1,2}(?:[:]\d{2})?(?:\s*[AaPp]\.?[Mm]\.?)?';

        //Booked
        $dateText = $this->re("/^[ ]*{$this->opt($this->t('DATES'))}[ ]*$\s+(.{6,}?)\s+^[ ]*{$this->opt($this->t('HOTEL'))}[ ]*$/ms", $emailText);
        $dateText = str_replace(['&thinsp;', ' '], [' ', ' '], $dateText);

        /*
            Tuesday, April 28 to Wednesday, April 29, 2020
            Check-out: 12:00 PM
        */

        /*
            Wednesday, 26 February to Friday, 6 March 2020
            Check-in: 3:00 PM
            Check-out: 12:00 PM
        */

        /*
            Donnerstag, 19. für Samstag, 21. März 2020
            Check-out: 12:00
        */

        $patternDate = "/^\s*[-[:alpha:]]{2,}\s*,?\s*(?<date1>.{3,}?)\s+{$this->opt($this->t('to'))}\s+[-[:alpha:]]{2,}\s*,\s*(?<date2>.{3,}?)[ ]*\n/u";

        if (preg_match($patternDate, $dateText, $m)) {
            $date1 = 0;
            $date2 = strtotime($this->normalizeDate($m['date2']));

            $m['date1'] = trim($m['date1'], ',. ');

            if (preg_match('/^\d+$/', $m['date1']) && $date2) {
                // 19.
                $date1 = strtotime($m['date1'] . ' ' . date('M Y', $date2));
            } else {
                $m['date1'] = $this->normalizeDate($m['date1']);

                if ($m['date1'] && !preg_match('/\d{4}$/', $m['date1']) && $date2) {
                    $date1 = strtotime($m['date1'] . ' ' . date('Y', $date2));
                } elseif ($m['date1']) {
                    $date1 = strtotime($m['date1']);
                }
            }

            $h->booked()
                ->checkIn($date1)
                ->checkOut($date2);
        }

        if (!empty($h->getCheckInDate())
            && preg_match("/^[ ]*{$this->opt($this->t('Check-in:'))}\s*({$patterns['time']})[ ]*$/m", $dateText, $m)
        ) {
            $h->booked()->checkIn(strtotime($m[1], $h->getCheckInDate()));
        }

        if (!empty($h->getCheckOutDate())
            && preg_match("/^[ ]*{$this->opt($this->t('Check-out:'))}\s*({$patterns['time']})[ ]*$/m", $dateText, $m)
        ) {
            $h->booked()->checkOut(strtotime($m[1], $h->getCheckOutDate()));
        }

        //Hotels
        $hotelText = $this->re("/^[ ]*({$this->opt($this->t('HOTEL'))}[ ]*$\s+.{3,}?)\s+^[ ]*(?i)http/ms", $emailText);
        $patternHotel = "/^{$this->opt($this->t('HOTEL'))}[ ]*\n+[ ]*(?<name>.{3,}?)[ ]*(?<address>(?:\n+[ ]*.{2,}){1,2})\n+[ ]*(?<phone>[+(\d][-+. \d)(]{5,}[\d)])[ ]*(?:\n|$)/";

        if (preg_match($patternHotel, $hotelText, $m)) {
            $h->hotel()
                ->name($m['name'])
                ->address(preg_replace('/\s+/', ' ', trim($m['address'])))
                ->phone($m['phone']);
        }
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            if (stripos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();
        $body = str_replace("&#39;", "'", $body);

        if (strpos($body, 'marriott.com') === false && strpos($body, 'marriott.de') === false) {
            return false;
        }

        return $this->assignLang($body);
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function assignLang(?string $text): bool
    {
        if (empty($text) || !isset($this->reBody2, $this->lang)) {
            return false;
        }

        foreach ($this->reBody2 as $lang => $re) {
            if (strpos($text, $re) !== false) {
                $this->lang = substr($lang, 0, 2);

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
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ')="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field, string $node = ''): string
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
     */
    private function normalizeDate(?string $text): ?string
    {
        if (preg_match('/\b([[:alpha:]]{3,})\s+(\d{1,2})\s*,\s*(\d{4})$/u', $text, $m)) {
            // March 3, 2020
            $month = $m[1];
            $day = $m[2];
            $year = $m[3];
        } elseif (preg_match('/^([[:alpha:]]{3,})\s+(\d{1,2})$/u', $text, $m)) {
            // February 29
            $month = $m[1];
            $day = $m[2];
            $year = '';
        } elseif (preg_match('/\b(\d{1,2})[.\s]+([[:alpha:]]{3,})[,.\s]+(\d{4})$/u', $text, $m)) {
            // 21. März 2020    |    3 Mar, 2020
            $day = $m[1];
            $month = $m[2];
            $year = $m[3];
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
