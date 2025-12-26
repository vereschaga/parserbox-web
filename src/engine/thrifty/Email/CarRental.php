<?php

namespace AwardWallet\Engine\thrifty\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class CarRental extends \TAccountChecker
{
    public $mailFiles = "thrifty/it-83735424.eml";
    public $subjects = [
        'Thrifty Car Rental for ', 'Reminder for ',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public static $providerDetect = [
        'thrifty'      => ['Thrifty Car Rental'],
        'perfectdrive' => ['Budget Rent A Car'],
        'dollar'       => ['Dollar Rent A Car'],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@discounthawaiicarrental.com') !== false) {
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
        if ($this->http->XPath->query('//a[contains(@href,".discounthawaiicarrental.com/") or contains(@href,"www.discounthawaiicarrental.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(.,"www.discounthawaiicarrental.com") or contains(.,"www.DiscountHawaiiCarRental.com") or contains(., "@discounthawaiicarrental.com")]')->length === 0
        ) {
            return false;
        }

        return $this->http->XPath->query("//text()[{$this->contains($this->t('Reservation Details'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Manage/Review Your Reservation Details'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]discounthawaiicarrental\.com$/', $from) > 0;
    }

    public static function getEmailProviders()
    {
        return array_keys(self::$providerDetect);
    }

    public function ParseEmail(Email $email)
    {
        // Provider
        $providerText = $this->http->FindSingleNode("//tr[{$this->starts($this->t('Your reservation is with'))} and {$this->contains($this->t('confirmation #'))}]");

        if (preg_match("/{$this->opt($this->t('Your reservation is with'))}\s+(.+?)\s+{$this->opt($this->t('confirmation #'))}/", $providerText, $m)) {
            foreach (self::$providerDetect as $code => $detects) {
                foreach ($detects as $name) {
                    if ($name === $m[1]) {
                        $email->setProviderCode($code);
                    }
                }
            }
        }

        $r = $email->add()->rental();

        $confirmation = $this->http->FindSingleNode("//tr[{$this->starts($this->t('Your reservation is with'))} and {$this->contains($this->t('confirmation #'))}]");

        if (preg_match("/{$this->opt($this->t('Your reservation is with'))}\s+(.*{$this->opt($this->t('confirmation #'))})\s*([-A-Z\d]{5,})$/", $confirmation, $m)) {
            $r->general()->confirmation($m[2], $m[1]);
        }

        $r->general()->traveller($this->http->FindSingleNode("//text()[contains(normalize-space(), 'Primary Driver:')]", null, true, "/{$this->opt($this->t('Primary Driver:'))}\s*(.+)/"));

        $r->pickup()->date(strtotime($this->http->FindSingleNode("//text()[normalize-space()='Pickup']/ancestor::tr[1]/following::tr[1]/td[1]")));
        $r->dropoff()->date(strtotime($this->http->FindSingleNode("//text()[normalize-space()='Pickup']/ancestor::tr[1]/following::tr[1]/td[2]")));

        /*
            Maui Kahului Airport
            Kahului Airport (Maui) (OGGT01)
            101 Airport Access Road (Map)
            Kahului, HI, 96732
            (808) 871-8811
        */
        $patterns['locationPhone'] = "/^\s*(?<location>[\s\S]{3,}?)[ ]*\n+[ ]*(?<phone>[+(\d][-. \d)(]{5,}[\d)])(?:[ ]*\n|$)/";

        $pickupText = $this->htmlToText($this->http->FindHTMLByXpath("//text()[normalize-space()='Pickup']/ancestor::tr[1]/following::tr[2]/td[1]"));
        $pickupText = preg_replace('/[ ]*\n+[ ]*\*.+/s', '', $pickupText);

        if (preg_match($patterns['locationPhone'], $pickupText, $m)) {
            $r->pickup()->location(preg_replace('/\s+/', ' ', $m['location']))->phone($m['phone']);
        } elseif ($pickupText) {
            $r->pickup()->location(preg_replace('/\s+/', ' ', $pickupText));
        }

        $dropoffText = $this->htmlToText($this->http->FindHTMLByXpath("//text()[normalize-space()='Pickup']/ancestor::tr[1]/following::tr[2]/td[2]"));
        $dropoffText = preg_replace('/[ ]*\n+[ ]*\*.+/s', '', $dropoffText);

        if (preg_match($patterns['locationPhone'], $dropoffText, $m)) {
            $r->dropoff()->location(preg_replace('/\s+/', ' ', $m['location']))->phone($m['phone']);
        } elseif (preg_match("/^{$this->opt($this->t('Same as pick-up'))}/i", $dropoffText)) {
            $r->dropoff()->same();
        } elseif ($dropoffText) {
            $r->dropoff()->location(preg_replace('/\s+/', ' ', $dropoffText));
        }

        $r->car()
            ->type($this->http->FindSingleNode("//text()[normalize-space()='Vehicle']/ancestor::tr[1]/following::tr[1]/descendant::text()[normalize-space()][1]"))
            ->model($this->http->FindSingleNode("//text()[normalize-space()='Vehicle']/ancestor::tr[1]/following::tr[1]/descendant::text()[normalize-space()][2]"));

        $totalPrice = $this->http->FindSingleNode("//text()[starts-with(normalize-space(),'Estimated Total')]", null, true, "/Estimated Total\s*(.+)$/");

        if (preg_match('/\(\s*(?<currencyCode>[A-Z]{3})\s*\)\s*:\s*(?<currency>[^\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*)$/', $totalPrice, $matches)) {
            // (USD): $717.19
            $r->price()
                ->total($this->normalizeAmount($matches['amount']))
                ->currency($matches['currencyCode']);

            $matches['currency'] = trim($matches['currency']);
            $baseFare = $this->http->FindSingleNode("//text()[starts-with(normalize-space(),'Base Rate')]", null, true, "/Base Rate\s*\[.+?\]\s*:\s*(.+)$/");

            if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.\'\d ]*?)$/', $baseFare, $m)) {
                $r->price()->cost($this->normalizeAmount($m['amount']));
            }

            $feeRows = $this->http->XPath->query("//table[ preceding::text()[starts-with(normalize-space(),'Base Rate')] and following::text()[starts-with(normalize-space(),'Estimated Total')] ]/descendant::tr[*[normalize-space()][2]]");

            foreach ($feeRows as $feeRow) {
                $feeCharge = $this->http->FindSingleNode('*[2]', $feeRow);

                if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.\'\d ]*?)$/', $feeCharge, $m)) {
                    $feeName = $this->http->FindSingleNode('*[1]', $feeRow);
                    $r->price()->fee($feeName, $this->normalizeAmount($m['amount']));
                }
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->ParseEmail($email);

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

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
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
     * Formatting over 3 steps:
     * 11 507.00  ->  11507.00
     * 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
     * 18800,00   ->  18800.00  |  2777,0    ->  2777.0.
     *
     * @param string|null $s Unformatted string with amount
     * @param string|null $decimals Symbols floating-point when non-standard decimals (example: 1,258.943)
     */
    private function normalizeAmount(?string $s, ?string $decimals = null): ?float
    {
        if (!empty($decimals) && preg_match_all('/(?:\d+|' . preg_quote($decimals, '/') . ')/', $s, $m)) {
            $s = implode('', $m[0]);

            if ($decimals !== '.') {
                $s = str_replace($decimals, '.', $s);
            }
        } else {
            $s = preg_replace('/\s+/', '', $s);
            $s = preg_replace('/[,.\'](\d{3})/', '$1', $s);
            $s = preg_replace('/,(\d{1,2})$/', '.$1', $s);
        }

        return is_numeric($s) ? (float) $s : null;
    }
}
