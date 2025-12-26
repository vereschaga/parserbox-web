<?php

namespace AwardWallet\Engine\ichotelsgroup\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class YourBill extends \TAccountChecker
{
    public $mailFiles = "ichotelsgroup/it-50729640.eml, ichotelsgroup/it-50913916.eml, ichotelsgroup/it-60710059.eml, ichotelsgroup/it-629540568.eml, ichotelsgroup/it-635740355.eml, ichotelsgroup/it-85032731.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'Hotel Information' => ['Hotel Information'],
            'confNumber'        => ['Confirmation Number:', 'Confirmation Number :'],
            'Member #'          => ['Member #', 'Spire Elite', 'Platinum Elite'],
            'Front Desk:'       => ['Front Desk:', 'Front desk:'],
            'Check-In Date:'    => ['Check-In Date:', 'Check-in Date:'],
            'Check-Out Date:'   => ['Check-Out Date:', 'Check-out Date:'],
        ],
    ];

    private $subjects = [
        'en' => ['Your Bill from'],
    ];

    private $detectors = [
        'en' => ['Room Summary Information'],
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[-.@]ihg\.com\b/i', $from) > 0;
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
        if (self::detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//a[contains(@href,".ihg.com/")]')->length === 0
            && $this->http->XPath->query('//node()[contains(normalize-space(),"You may contact IHG") or contains(.,"www.ihgrewardsclub.com")]')->length === 0
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

        $this->parseHotel($email);
        $email->setType('YourBill' . ucfirst($this->lang));

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

    private function parseHotel(Email $email)
    {
        $h = $email->add()->hotel();

        $userInfo = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Member #'))} and {$this->contains('|')}]");

        if (empty($userInfo)) {
            $userInfo = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Member #'))}]/ancestor::*[1]");
        }

        if (preg_match("/^(?<name>[[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])\s*\|?\s*{$this->opt($this->t('Member #'))}\s*(?<number>[-A-Z\d]{5,})\s*\|?/", $userInfo, $m)) {
            // David Williams Charneco | Member # 267674789 |
            $h->general()->traveller($m['name']);
            $h->program()->account($m['number'], false);
        } else {
            $traveller = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Billing Information'))}]/following::text()[normalize-space()][1]");

            if (!empty($traveller) && stripos($traveller, 'Folio Number') === false) {
                $h->general()
                    ->traveller($traveller, true);
            }
        }

        $hotelInfoHtml = $this->http->FindHTMLByXpath("//text()[{$this->eq($this->t('Hotel Information'))}]/ancestor::td[1]");
        $hotelInfo = $this->htmlToText($hotelInfoHtml);

        if ($hotelInfo == 'Hotel Information') {
            $hotelInfoHtml = $this->http->FindHTMLByXpath("//text()[{$this->eq($this->t('Hotel Information'))}]/ancestor::td[1]/following::td[1]");
            $hotelInfo = $this->htmlToText($hotelInfoHtml);
        }

        /*
            Hotel Information    Bryan-Montpelier
            13399 State Route 15
            Holiday City, OH 43543 US

            Front Desk: 11-419-4850008
        */

        if (preg_match("/^(?:Hotel Information)?\s*(?<name>.{3,}?)[ ]*\n(?<address>(?:.+\n*){1,3})(?:\n|\s*(?:Front Desk\:))/u", $hotelInfo, $m)) {
            $h->hotel()
                ->name($m['name'])
                ->address(preg_replace('/\s+/', ' ', $m['address']));
        }

        if (preg_match("/{$this->opt($this->t('Front Desk:'))}\s*([+(\d][-. \d)(]{5,}[\d)])[ ]*(?:\n|$)/i", $hotelInfo, $m)) {
            $h->hotel()->phone($m[1]);
        }

        $confirmation = $this->http->FindSingleNode("//td[{$this->eq($this->t('confNumber'))}]/following-sibling::td[normalize-space()][1]", null, true, '/^[A-Z\d]{5,}$/');

        if (empty($confirmation)) {
            $confirmation = $this->http->FindSingleNode("//text()[{$this->starts($this->t('confNumber'))}]/ancestor::tr[1]/descendant::text()[normalize-space()][last()]", null, true, '/^[A-Z\d]{5,}$/');
        }

        if ($confirmation) {
            $confirmationTitle = $this->http->FindSingleNode("//td[{$this->eq($this->t('confNumber'))}]", null, true, '/^(.+?)[\s:]*$/');
            $h->general()->confirmation($confirmation, $confirmationTitle);
        }

        $roomValue = $this->http->FindSingleNode("//td[{$this->eq($this->t('Room:'))}]/following-sibling::td[normalize-space()][1]");

        if (empty($roomValue)) {
            $roomValue = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Room:'))}]/ancestor::tr[1]/descendant::td[2]/descendant::text()[normalize-space()][1]");
        }

        if (!preg_match("/^\w+\s*\d+\s*\w+\s*\d{4}$/", $roomValue)) {
            $room = $h->addRoom();
            $room->setDescription($roomValue);
        }

        $dateCheckIn = $this->http->FindSingleNode("//td[{$this->eq($this->t('Check-In Date:'))}]/following-sibling::td[normalize-space()][1]");

        $rule = "count(descendant::text()[normalize-space()]) = 3 and descendant::text()[normalize-space()][2][{$this->starts($this->t('Check-In Date:'))}] and descendant::text()[normalize-space()][3][{$this->starts($this->t('Check-Out Date:'))}]";

        if (empty($dateCheckIn)) {
            $dateCheckIn = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Check-In Date:'))}]/ancestor::td[1][{$rule}]/following-sibling::td[1][count(descendant::text()[normalize-space()]) = 3 or count(descendant::text()[normalize-space()]) = 2]/descendant::text()[normalize-space()][last() - 1]");
        }
        $h->booked()->checkIn(strtotime($dateCheckIn));

        $dateCheckOut = $this->http->FindSingleNode("//td[{$this->eq($this->t('Check-Out Date:'))}]/following-sibling::td[normalize-space()][1]");

        if (empty($dateCheckOut)) {
            $dateCheckOut = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Check-Out Date:'))}]/ancestor::td[1][{$rule}]/following-sibling::td[1][count(descendant::text()[normalize-space()]) = 3 or count(descendant::text()[normalize-space()]) = 2]/descendant::text()[normalize-space()][last()]");
        }
        $h->booked()->checkOut(strtotime($dateCheckOut));

        $priceRowsXpath = "//tr[*[normalize-space()][1][{$this->eq($this->t('Date'))}] and *[normalize-space()][2][{$this->eq($this->t('Description'))}] ]/following::text()[normalize-space()][1]/ancestor::tr[count(.//td[not(.//td)][normalize-space()]) = 3]/preceding-sibling::*[{$this->starts($this->t('Date'))}]/following-sibling::*[normalize-space()]";
        $priceRows = $this->http->XPath->query($priceRowsXpath);
        $totalAmount = 0.0;
        $fees = [];
        $rates = [];

        foreach ($priceRows as $pRoot) {
            $name = $this->http->FindSingleNode("self::*[count(.//td[not(.//td)][normalize-space()]) = 3]/descendant::td[normalize-space()][2]", $pRoot);
            $valueText = $this->http->FindSingleNode("self::*[count(.//td[not(.//td)][normalize-space()]) = 3]/descendant::td[normalize-space()][3]", $pRoot);
            $value = PriceHelper::parse($this->re("/^\D*([\d\.\,\']+)/", trim($valueText, '-')));

            if (preg_match("/^\s*-\s*\d[\d., ]*\s*$/", $valueText)) {
                $totalAmount += $value;

                continue;
            }

            if (preg_match("/^\s*\*\s*(Accommodation|Room Rental)\s*$/", $name)) {
                $rates[] = $value;

                continue;
            }

            if (isset($fees[$name])) {
                $fees[$name] += $value;
            } else {
                $fees[$name] = $value;
            }
        }

        if (!empty($totalAmount)) {
            $h->price()->total($totalAmount);
        }

        if (!empty($h->getCheckInDate()) && !empty($h->getCheckOutDate())) {
            $nights = date_diff(
                date_create('@' . strtotime('00:00', $h->getCheckOutDate())),
                date_create('@' . strtotime('00:00', $h->getCheckInDate()))
            )->format('%a');

            if ($nights == count($rates)) {
                $h->price()->cost(array_sum($rates));

                if (isset($room)) {
                    $room->setRates($rates);
                } else {
                    $room = $h->addRoom()->setRates($rates);
                }
            }
        }

        foreach ($fees as $name => $fee) {
            $h->price()
                ->fee($name, $fee);
        }
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

                if ($this->http->XPath->query("//node()[{$this->contains($phrase)}]")->length > 0) {
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
            if (!is_string($lang) || empty($phrases['Hotel Information']) || empty($phrases['confNumber'])) {
                continue;
            }

            if ($this->http->XPath->query("//node()[{$this->contains($phrases['Hotel Information'])}]")->length > 0
                && $this->http->XPath->query("//node()[{$this->contains($phrases['confNumber'])}]")->length > 0
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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }
}
