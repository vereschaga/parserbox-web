<?php

namespace AwardWallet\Engine\laxparking\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

// TODO: merge with parsers houston/ParkingHtml, parkbost/ParkingReservation (in favor of laxparking/YourParking)

class YourParking extends \TAccountChecker
{
    public $mailFiles = "laxparking/it-531802321.eml, laxparking/it-531878285.eml, laxparking/it-532157216.eml, laxparking/it-649088641-cancelled.eml, laxparking/it-654232105-sfoparking.eml, laxparking/it-675671564-panynj.eml";
    public $subjects = [
        ' - Your Parking for ',
    ];

    public $lang = '';

    public static $dictionary = [
        "en" => [
            'entry'      => ['Entry:', 'Entry :'],
            'exit'       => ['Exit:', 'Exit :'],
            'Hello'      => ['Hello', 'Dear'],
            'confNumber' => [
                'Booking Reference:', 'Booking Reference :',
                'Booking reference:', 'Booking reference :',
                'Confirmation Number:', 'Confirmation Number :',
                'Confirmation number:', 'Confirmation number :',
            ],
            'parkingProduct'   => ['Parking Product', 'Parking product', 'Parking Location', 'Parking garage', 'Parking lot'],
            'License plate:'   => ['License plate:', 'License Plate:'],
            'totalPrice'       => ['Total:', 'Total :'],
            'cancelledPhrases' => [
                'Your Booked Parking Cancellation Confirmation',
                'Your Booked Parking Cancelation Confirmation',
            ],
            'nameStart' => [
                'Thank you for reserving your Premium parking space at',
                'Thank you for booking your parking at',
                'Your booking for parking at',
            ],
            'nameEnd'   => ['A summary', 'has been'],
        ],
    ];

    private $patterns = [
        'date' => '\b(?:\d{1,2}\/\d{1,2}\/\d{4}|[[:alpha:]]+\s+\d{1,2}[,\s]+\d{2,4})\b', // 10/15/2023  |  Oct 5, 2023
        'time' => '\b\d{1,2}[:：]\d{2}(?:[:：]\d{2})?(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 02:00 PM  |  11:30:00 PM
    ];

    private $providerCode = '';

    public function detectEmailByHeaders(array $headers)
    {
        if (
            // sfoparking
            stripos($headers['subject'], 'SFO Parking Confirmation') !== false
            || stripos($headers['subject'], 'SFO Parking Cancellation Confirmation') !== false
            || stripos($headers['subject'], 'SFO Parking Cancelation Confirmation') !== false
            // panynj
            || stripos($headers['subject'], 'Newark Airport booking confirmation') !== false
            || stripos($headers['subject'], 'JFK Airport booking confirmation') !== false
        ) {
            return true;
        }

        if (isset($headers['from']) && stripos($headers['from'], '@flylax.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (stripos($headers['subject'], $subject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        return $this->assignProvider($parser->getHeaders()) && $this->assignLang();
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]flylax.com$/', $from) > 0;
    }

    public function ParseParking(Email $email): void
    {
        $p = $email->add()->parking();

        if ($this->http->XPath->query("//*[{$this->contains($this->t('cancelledPhrases'))}]")->length > 0
            || $this->http->FindSingleNode("//text()[starts-with(normalize-space(),'Your booking for parking at') and {$this->contains(['has been cancelled.', 'has been canceled.'])}]") !== null
        ) {
            $p->general()->cancelled();
        }

        $travellerNames = array_filter($this->http->FindNodes("//text()[{$this->starts($this->t('Hello'))}]", null, "/^{$this->opt($this->t('Hello'))}[,\s]+(?i)(?:Ms\/Mr\s+)?([[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])(?:\s*[,;:!?]|$)/u"));

        if (count(array_unique($travellerNames)) === 1) {
            $traveller = array_shift($travellerNames);
            $p->general()->traveller($traveller);
        }

        $confirmation = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('confNumber'))}] ]/*[normalize-space()][2]", null, true, '/^[-A-Z\d]{5,}$/');

        if ($confirmation) {
            $confirmationTitle = $this->http->FindSingleNode("//tr[count(*[normalize-space()])=2]/*[normalize-space()][1][{$this->eq($this->t('confNumber'))}]", null, true, '/^(.+?)[\s:：]*$/u');
            $p->general()->confirmation($confirmation, $confirmationTitle);
        }

        $location = null;

        foreach ((array) $this->t('parkingProduct') as $phrase) {
            $location = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->starts($phrase)}] ]/*[normalize-space()][2]");

            if ($location) {
                break;
            }
        }

        if (!empty($location)) {
            if (mb_strlen($location) > 2) {
                $p->setLocation($location);
            } else {
                $p->setLocation('Location: ' . $location);
            }
        }

        $plate = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('License plate:'))}] ]/*[normalize-space()][2]");
        $p->booked()->plate($plate, false, true);

        $dateStartVal = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('entry'))}] ]/*[normalize-space()][2]");
        $dateEndVal = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('exit'))}] ]/*[normalize-space()][2]");

        $pattern1 = "/^(?<date>{$this->patterns['date']})\s+at\s+(?<time>{$this->patterns['time']}).*$/iu"; // 10/15/2023 at 02:00 PM  |  Oct 5, 2023 at 11:30:00 PM
        $pattern2 = "/^(?<time>{$this->patterns['time']})\s+on\s+(?<date>{$this->patterns['date']}).*$/iu"; // 6:00 AM on 04/21/2024

        if (preg_match($pattern1, $dateStartVal, $m)
            || preg_match($pattern2, $dateStartVal, $m)
        ) {
            $dateStart = strtotime($this->normalizeDate($m['date']));
            $timeStart = $this->normalizeTime($m['time']);
        } else {
            $dateStart = $timeStart = null;
        }

        if (preg_match($pattern1, $dateEndVal, $m)
            || preg_match($pattern2, $dateEndVal, $m)
        ) {
            $dateEnd = strtotime($this->normalizeDate($m['date']));
            $timeEnd = $this->normalizeTime($m['time']);
        } else {
            $dateEnd = $timeEnd = null;
        }

        if ($dateStart && $timeStart) {
            $p->booked()->start(strtotime($timeStart, $dateStart));
        }

        if ($dateEnd && $timeEnd) {
            $p->booked()->end(strtotime($timeEnd, $dateEnd));
        }

        $address = $this->http->FindSingleNode("//text()[{$this->contains($this->t('is located at'))}]", null, true, "/{$this->opt($this->t('is located at'))}\s+([-,A-z\d\s]{3,75}?)\s*\./")
            ?? $this->http->FindSingleNode("//text()[{$this->contains($this->t('is located at'))}]/following::text()[normalize-space()][1]/ancestor::a[1]", null, true, "/^(.+?)[.\s]*$/")
            ?? $this->http->FindSingleNode("//text()[{$this->starts($this->t('nameStart'))} and {$this->contains($this->t('nameEnd'))}]", null, true, "/{$this->opt($this->t('nameStart'))}\s+(.{3,75}?)[.\s]+{$this->opt($this->t('nameEnd'))}/")
        ;
        $p->setAddress($address);

        $totalPrice = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('totalPrice'))}] ]/*[normalize-space()][2]");

        if (preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $totalPrice, $matches)) {
            // $ 162.00
            $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
            $p->price()->currency($matches['currency'])->total(PriceHelper::parse($matches['amount'], $currencyCode));
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();
        $this->assignProvider($parser->getHeaders());

        $this->ParseParking($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));
        $email->setProviderCode($this->providerCode);

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

    public static function getEmailProviders()
    {
        return [
            'laxparking',
            'sfoparking',
            'panynj',
        ];
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

    private function assignProvider($headers): bool
    {
        if ($this->http->XPath->query("//text()[contains(normalize-space(),'Los Angeles International Airport')]")->length > 0) {
            $this->providerCode = 'laxparking';

            return true;
        }

        if ($this->http->XPath->query("//text()[contains(normalize-space(),'San Francisco International Airport')]")->length > 0) {
            $this->providerCode = 'sfoparking';

            return true;
        }

        if (preg_match('/[.@](?:newarkairport|jfkairport)\.com$/i', rtrim($headers['from'], '> ')) > 0
            || stripos($headers['subject'], 'Newark Airport booking confirmation') !== false
            || stripos($headers['subject'], 'JFK Airport booking confirmation') !== false
            || $this->http->XPath->query("//a[contains(@href,'.panynj.gov/') or contains(@href,'www.panynj.gov')]")->length > 0
            || $this->http->XPath->query("//text()[contains(normalize-space(),'Newark Liberty International Airport') or contains(normalize-space(),'John F. Kennedy International Airport')]")->length > 0
        ) {
            $this->providerCode = 'panynj';

            return true;
        }

        return false;
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['entry']) || empty($phrases['exit'])) {
                continue;
            }

            if ($this->http->XPath->query("//tr/*[{$this->eq($phrases['entry'])}]")->length > 0
                && $this->http->XPath->query("//tr/*[{$this->eq($phrases['exit'])}]")->length > 0
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

    private function opt($field): string
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }

    private function normalizeDate(?string $str): string
    {
        $this->logger->debug('$str = ' . print_r($str, true));

        $in = [
            '/^([[:alpha:]]+)\s+(\d{1,2})[,\s]+(\d{4})$/u', // Oct 5, 2023
        ];
        $out = [
            '$2 $1 $3',
        ];
        $str = preg_replace($in, $out, $str);
//        $this->logger->debug('$str = '.print_r( $str,true));

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return $str;
    }

    private function normalizeTime(?string $s): string
    {
        $s = preg_replace([
            '/\b(\d{1,2}:\d{2}):\d{2}(\b|\D)/', // 11:30:00 PM    ->    11:30 PM
        ], [
            '$1$2',
        ], $s);

        return $s;
    }
}
