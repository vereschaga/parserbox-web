<?php

namespace AwardWallet\Engine\psf\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Parking extends \TAccountChecker
{
    public $mailFiles = "psf/it-155861325.eml";
    public $subjects = [
        'en' => ['Reservation Receipt'],
    ];

    public $lang = '';

    public $detectLang = [
        'en'  => ['Payment Breakdown'],
    ];

    public static $dictionary = [
        "en" => [
        ],
    ];
    private $providerCode = '';

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers['subject'], 'Your ParkSleepFly.com Reservation') !== false
            || stripos($headers['subject'], 'Your AirportParkingReservations.com Reservation') !== false
        ) {
            return true;
        }

        if ($this->detectEmailFromProvider($headers['from']) !== true
            && strpos($headers['subject'], 'ParkSleepFly.com') === false
            && strpos($headers['subject'], 'AirportParkingReservations.com') === false
        ) {
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

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        return $this->assignProvider($parser->getHeaders())
            && $this->http->XPath->query("//text()[{$this->contains('Parking Lot Details')}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains('Parking Dates and Options')}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[.@]airportparkingreservations\.com/i', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignProvider($parser->getHeaders());
        $this->assignLang();
        $email->setType('Parking' . ucfirst($this->lang));

        $p = $email->add()->parking();

        if (!empty($this->providerCode)) {
            $p->setProviderCode($this->providerCode);
        }

        $p->general()
            ->confirmation($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Reservation ID:')]", null, true, "/{$this->opt($this->t('Reservation ID:'))}\s*(\d{6,})$/"))
            ->traveller($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Reservation Made By -')]", null, true, "/{$this->opt($this->t('Reservation Made By -'))}\s*(\D+)$/"));

        $p->setLocation($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Reservation ID:')]/following::text()[normalize-space()][1]/ancestor::table[1]/descendant::a[normalize-space()][1]"));
        $parkingText = implode("\n", $this->http->FindNodes("//text()[starts-with(normalize-space(), 'Reservation ID:')]/following::tr[not(.//tr) and normalize-space()][1][normalize-space()='Parking Lot Details']/following-sibling::tr[normalize-space()][1]/descendant::*[ tr[normalize-space()][2] ][1]/tr[normalize-space()]"));

        if (!empty($p->getLocation()) && preg_match("/^{$this->opt($p->getLocation())}\n(?<address>.{3,})\n(?<phone>[+(\d][-+. \d)(]{5,}[\d)])$/s", $parkingText, $m)) {
            $p->place()->address(preg_replace('/\s+/', ' ', $m['address']))->phone($m['phone']);
        }

        $p->setStartDate(strtotime($this->http->FindSingleNode("//text()[normalize-space()='Car drop-off:']/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Car drop-off:'))}\s+(.+)/")));
        $p->setEndDate(strtotime($this->http->FindSingleNode("//text()[normalize-space()='Car pick-up:']/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Car pick-up:'))}\s+(.+)/")));

        $totalPrice = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][normalize-space()='Total'] ]/*[normalize-space()][2]", null, true, '/^.*\d.*$/');

        if (preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $totalPrice, $matches)) {
            // $66.78
            $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
            $p->price()->currency($matches['currency'])->total(PriceHelper::parse($matches['amount'], $currencyCode));

            $baseFare = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][starts-with(normalize-space(),'Parking Price')] ]/*[normalize-space()][2]", null, true, '/^.*\d.*$/');

            if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.‘\'\d ]*?)$/u', $baseFare, $m)) {
                $p->price()->cost(PriceHelper::parse($m['amount'], $currencyCode));
            }

            $discountCharges = [];

            $feeRows = $this->http->XPath->query("//tr[ preceding-sibling::tr[*[1][starts-with(normalize-space(),'Parking Price')]] and following-sibling::tr[*[1][normalize-space()='Total']] and *[2][normalize-space()] ]");

            foreach ($feeRows as $feeRow) {
                $feeCharge = $this->http->FindSingleNode('*[2]', $feeRow, true, '/^(.+?)\s*(?:\(|$)/');

                if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $feeCharge, $m)) {
                    $feeName = $this->http->FindSingleNode('*[1]', $feeRow, true, '/^(.+?)[*\s:：]*$/u');
                    $p->price()->fee($feeName, PriceHelper::parse($m['amount'], $currencyCode));
                } elseif (preg_match('/^[-–]+\s*(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $feeCharge, $m)) {
                    $discountCharges[] = PriceHelper::parse($m['amount'], $currencyCode);
                }
            }

            if (count($discountCharges) > 0) {
                $p->price()->discount(array_sum($discountCharges));
            }
        }

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
        return ['psf', 'airport'];
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(.), \"{$s}\")";
        }, $field));
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

    private function assignProvider($headers): bool
    {
        if (stripos($headers['subject'], 'Your ParkSleepFly.com Reservation') !== false
            || $this->http->XPath->query('//*[contains(normalize-space(),"ParkSleepFly.com, Inc. All rights reserved") or contains(normalize-space(),"email us at Service@ParkSleepFly.com")]')->length > 0
        ) {
            $this->providerCode = 'psf';

            return true;
        }

        if (stripos($headers['subject'], 'Your AirportParkingReservations.com Reservation') !== false
            || $this->http->XPath->query('//*[contains(normalize-space(),"AirportParkingReservations.com, Inc. All rights reserved") or contains(normalize-space(),"email us at Service@AirportParkingReservations.com")]')->length > 0
        ) {
            $this->providerCode = 'airport';

            return true;
        }

        return false;
    }

    private function assignLang(): bool
    {
        foreach ($this->detectLang as $lang => $words) {
            foreach ($words as $word) {
                if ($this->http->XPath->query("//*[{$this->contains($word)}]")->length > 0) {
                    $this->lang = substr($lang, 0, 2);

                    return true;
                }
            }
        }

        return false;
    }
}
