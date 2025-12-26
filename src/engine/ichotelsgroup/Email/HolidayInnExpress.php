<?php

namespace AwardWallet\Engine\ichotelsgroup\Email;

use AwardWallet\Schema\Parser\Email\Email;
use AwardWallet\Common\Parser\Util\PriceHelper;

class HolidayInnExpress extends \TAccountChecker
{
    public $mailFiles = "ichotelsgroup/it-884816883.eml, ichotelsgroup/it-885178238.eml, ichotelsgroup/it-885206091.eml, ichotelsgroup/it-884940381.eml";

    private $subjects = [
        'en' => ['Your Reservation Confirmation #']
    ];

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'address' => ['Address'],
            'dates' => ['Dates'],
            'confNumber' => 'Confirmation #',
            'statusPhrases' => ['your reservation is'],
            'statusVariants' => ['confirmed'],
            'totalCharges' => ['Total Charges', 'Total charges'],
            // 'totalCCCharge' => ['Total Credit Card Charge', 'Total credit card charge'],
            'totalPoints' => ['Total Points Redeemed', 'Total points redeemed'],
            'nights' => ['nights stay', 'night stay'],
        ]
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[.@]tx\.ihg\.com$/i', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ((!array_key_exists('from', $headers) || $this->detectEmailFromProvider(rtrim($headers['from'], '> ')) !== true)
            && (!array_key_exists('subject', $headers) || strpos($headers['subject'], 'Holiday Inn Express') === false)
        ) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ((array)$phrases as $phrase) {
                if (is_string($phrase) && array_key_exists('subject', $headers) && stripos($headers['subject'], $phrase) !== false)
                    return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $href = ['.tx.ihg.com/', 'click.tx.ihg.com'];

        if ($this->detectEmailFromProvider($parser->getCleanFrom()) !== true
            && $this->http->XPath->query("//a[{$this->contains($href, '@href')} or {$this->contains($href, '@originalsrc')}]")->length === 0
            && $this->http->XPath->query('//text()[starts-with(normalize-space(translate(.,"Â","")),"©") and contains(normalize-space(translate(.,"Â","")),"InterContinental Hotels Group")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(translate(.,"Â","")),"You have received this email as a result of your recent transaction with Holiday Inn Express")]')->length === 0
        ) {
            return false;
        }
        return $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();
        if ( empty($this->lang) ) {
            $this->logger->debug("Can't determine a language!");
            return $email;
        }
        $email->setType('HolidayInnExpress' . ucfirst($this->lang));

        $textHtml = $this->http->Response['body'];

        if (strpos($textHtml, 'â') !== false) {
            $this->http->SetEmailBody(str_replace(['â', 'Â', ''], '', $textHtml)); // 0x8c
            $this->logger->debug('Found and removed bad simbols from HTML!');
        }

        $patterns = [
            'date' => '.{4,}?\b\d{4}\b', // 7 Mar 2025
            'time' => '\d{1,2}(?:[:：]\d{2})?(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM  |  2:00 p. m.  |  3pm
            'phone' => '[+(\d][-+. \d)(]{5,}[\d)]', // +377 (93) 15 48 52  |  (+351) 21 342 09 07  |  713.680.2992
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        ];

        $h = $email->add()->hotel();

        $accountInfo = implode(' ', $this->http->FindNodes("//tr[ *[{$this->eq($this->t('SIGN IN'))}] ]/*[normalize-space()][last()]/descendant::text()[normalize-space()]"));

        if (preg_match("/^(?:\D+\s)?(\d{3,})$/", $accountInfo, $m)) {
            // Platinum Elite 341675824‌
            $h->program()->account($m[1], false);
        }

        $statusTexts = array_filter($this->http->FindNodes("//text()[{$this->contains($this->t('statusPhrases'))}]", null, "/{$this->opt($this->t('statusPhrases'))}[:\s]+({$this->opt($this->t('statusVariants'))})(?:\s*[,.;:!?]|$)/i"));

        if (count(array_unique(array_map('mb_strtolower', $statusTexts))) === 1) {
            $status = array_shift($statusTexts);
            $h->general()->status($status);
        }

        $confirmation = $this->http->FindSingleNode("//text()[{$this->starts($this->t('confNumber'))}]");

        if (preg_match("/^({$this->opt($this->t('confNumber'))})[:#\s]*([-A-z\d]{4,25})$/", $confirmation, $m)) {
            $h->general()->confirmation($m[2], $m[1]);
        }

        $hotelName = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('address'), "translate(.,':','')")}] ]/preceding::text()[normalize-space()][1]/ancestor::tr[1]");
        $address = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('address'), "translate(.,':','')")}] ]/*[normalize-space()][2]");
        $phone = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Front Desk'), "translate(.,':','')")}] ]/*[normalize-space()][2]", null, true, "/^({$patterns['phone']})(?:(?:\s*[,\/]\s*)+{$patterns['phone']})*$/");

        $h->hotel()->name($hotelName)->address($address)->phone($phone, false, true);

        $dateCheckIn = $dateCheckOut = $timeCheckIn = $timeCheckOut = null;

        $datesVal = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('dates'), "translate(.,':','')")}] ]/*[normalize-space()][2]");
        $timesVal = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('dates'), "translate(.,':','')")}] ]/following-sibling::tr[normalize-space()][1]");

        if (preg_match("/^({$patterns['date']})\s*-\s*({$patterns['date']})$/", $datesVal, $m)) {
            // 7 Mar 2025 - 9 Mar 2025
            $dateCheckIn = strtotime($m[1]);
            $dateCheckOut = strtotime($m[2]);
        }

        if (preg_match("/^{$this->opt($this->t('Check in'))}[-:\s]*({$patterns['time']})/", $timesVal, $m)) {
            $timeCheckIn = $m[1];
        }
        
        if (preg_match("/(?:^|\/\s*){$this->opt($this->t('Check out'))}[-:\s]*({$patterns['time']})/", $timesVal, $m)) {
            $timeCheckOut = $m[1];
        }

        if ($dateCheckIn && $timeCheckIn) {
            $h->booked()->checkIn(strtotime($timeCheckIn, $dateCheckIn));
        }

        if ($dateCheckOut && $timeCheckOut) {
            $h->booked()->checkOut(strtotime($timeCheckOut, $dateCheckOut));
        }

        /* 1 Room, 2 Adults, 4 Child */
        $reservationInfo1 = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Reservation'), "translate(.,':','')")}] ]/*[normalize-space()][2]");

        if (preg_match("/(\b\d{1,3})[-\s]*{$this->opt($this->t('Room'))}/i", $reservationInfo1, $m)) {
            $h->booked()->rooms($m[1]);
        }

        if (preg_match("/(\b\d{1,3})[-\s]*{$this->opt($this->t('Adult'))}/i", $reservationInfo1, $m)) {
            $h->booked()->guests($m[1]);
        }

        if (preg_match("/(\b\d{1,3})[-\s]*{$this->opt($this->t('Child'))}/i", $reservationInfo1, $m)) {
            $h->booked()->kids($m[1]);
        }

        $travellers = [];

        $travellerRows = $this->http->FindNodes("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Reservation'), "translate(.,':','')")}] ]/following-sibling::tr[normalize-space()]");

        foreach ($travellerRows as $tRow) {
            if (preg_match("/^((?:{$patterns['travellerName']}\s*,\s*)+){$this->opt(['Primary', 'Additional Guest'])}$/iu", $tRow, $m)) {
                $travellerList = preg_split('/(?:\s*,\s*)+/', rtrim($m[1], ', '));

                foreach ($travellerList as $tName) {
                    if (!in_array($tName, $travellers)) {
                        $h->general()->traveller($tName, true);
                        $travellers[] = $tName;
                    }
                }
            }
        }

        $roomDetails = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Room details'), "translate(.,':','')")}] ]/*[normalize-space()][2]");

        $rateVal = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Rate'), "translate(.,':','')")}] ]/*[normalize-space()][2]");

        if ($roomDetails || $rateVal) {
            $room = $h->addRoom();
            $room->setType($roomDetails, false, true)->setRateType($rateVal, false, true);
        }

        $cancellation = $this->http->FindSingleNode("//p[ descendant::text()[normalize-space()][1][{$this->eq($this->t('Cancellation Policy'), "translate(.,':','')")}] ]", null, true, "/^{$this->opt($this->t('Cancellation Policy'))}[:\s]+(.{5,})$/");
        $h->general()->cancellation($cancellation, false, true);

        if (preg_match("/Cancell?ing (?i)your reservation before\s*(?<time>{$patterns['time']})(?:\s*\([^\d()]*\))?\s+on\s+(?<date>{$patterns['date']})\s+will result in no charge\s*(?:[.;!]|$)/", $cancellation, $m) // en
        ) {
            $dateDeadline = strtotime($m['date']);
            $h->booked()->deadline(strtotime($m['time'], $dateDeadline));
        }

        $freeNightValues = [];

        /* price */

        $xpathTotalCharges1 = "count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('totalCharges'), "translate(.,'*:','')")}]";
        // $xpathTotalCharges2 = "count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('totalCCCharge'), "translate(.,'*:','')")}]";

        $totalPoints = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('totalPoints'), "translate(.,'*:','')")}] ]/*[normalize-space()][2]", null, true, '/^.*\d.*$/');

        if (preg_match('/^\d[,.’‘\'\d ]*Points?$/iu', $totalPoints)) {
            // 138,000 Points
            $h->price()->spentAwards($totalPoints);
        }

        $totalCharges = $this->http->FindSingleNode("//tr[{$xpathTotalCharges1}]/*[normalize-space()][2]", null, true, '/^.*\d.*$/')
            // ?? $this->http->FindSingleNode("//tr[{$xpathTotalCharges2}]/*[normalize-space()][2]", null, true, '/^.*\d.*$/')
        ;

        if (preg_match('/^(?<amount>\d[,.’‘\'\d ]*?)[ ]*(?<currency>[^\-\d)(]+)$/u', $totalCharges, $matches)) {
            // 5,755.18 INR
            $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
            $h->price()->currency($matches['currency'])->total(PriceHelper::parse($matches['amount'], $currencyCode));

            $feeRows = $this->http->XPath->query("//tr[ preceding::tr[{$this->eq($this->t('Summary of charges'), "translate(.,'*:','')")}] and following::tr[{$xpathTotalCharges1}] and *[2][normalize-space()] ]");

            foreach ($feeRows as $feeRow) {
                $feeName = $this->http->FindSingleNode('*[1]', $feeRow, true, '/^[*\s]*(.+?)[\s:：]*$/u');
                $feeValue = $this->http->FindSingleNode('*[2]', $feeRow);

                if (preg_match("/^(\d+)\s*{$this->opt($this->t('nights'))}(?:\s*\(|$)/i", $feeName, $m)
                    && preg_match("/\b{$this->opt($this->t('Free Night'))}/i", $feeValue)
                ) {
                    $freeNightValues[] = $m[1];

                    continue;
                }

                $feeCharge = preg_match('/^(.*?\d.*?)\s*(?:\(|$)/', $feeValue, $m) ? $m[1] : null;

                if ( preg_match('/^(?<amount>\d[,.’‘\'\d ]*?)[ ]*(?:' . preg_quote($matches['currency'], '/') . ')?$/u', $feeCharge, $m) ) {
                    $feeAmount = PriceHelper::parse($m['amount'], $currencyCode);
                } else {
                    continue;
                }

                if (empty($h->getPrice()->getCost())
                    && preg_match("/^\d+\s*{$this->opt($this->t('nights'))}(?:\s*\(|$)/i", $feeName)
                ) {
                    $h->price()->cost($feeAmount);
                } else {
                    $h->price()->fee($feeName, $feeAmount);
                }
            }
        }

        $freeNights = count($freeNightValues) > 0 ? array_sum($freeNightValues) : null;
        $h->booked()->freeNights($freeNights, false, true); // it-884940381.eml

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

    private function assignLang(): bool
    {
        if ( !isset(self::$dictionary, $this->lang) ) {
            return false;
        }
        foreach (self::$dictionary as $lang => $phrases) {
            if ( !is_string($lang) || empty($phrases['address']) || empty($phrases['dates']) ) {
                continue;
            }
            if ($this->http->XPath->query("//*[{$this->eq($phrases['address'], "translate(.,':','')")}]")->length > 0
                && $this->http->XPath->query("//*[{$this->eq($phrases['dates'], "translate(.,':','')")}]")->length > 0
            ) {
                $this->lang = $lang;
                return true;
            }
        }
        return false;
    }

    private function t(string $phrase, string $lang = '')
    {
        if ( !isset(self::$dictionary, $this->lang) ) {
            return $phrase;
        }
        if ($lang === '') {
            $lang = $this->lang;
        }
        if ( empty(self::$dictionary[$lang][$phrase]) ) {
            return $phrase;
        }
        return self::$dictionary[$lang][$phrase];
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array)$field;
        if (count($field) === 0)
            return 'false()';
        return '(' . implode(' or ', array_map(function($s) use($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';
            return 'contains(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array)$field;
        if (count($field) === 0)
            return 'false()';
        return '(' . implode(' or ', array_map(function($s) use($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';
            return 'normalize-space(' . $node . ')=' . $s;
        }, $field)) . ')';
    }

    private function starts($field, string $node = ''): string
    {
        $field = (array)$field;
        if (count($field) === 0)
            return 'false()';
        return '(' . implode(' or ', array_map(function($s) use($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';
            return 'starts-with(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array)$field;
        if (count($field) === 0)
            return '';
        return '(?:' . implode('|', array_map(function($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }
}
