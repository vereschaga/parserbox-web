<?php

namespace AwardWallet\Engine\hhonors\Email;

use AwardWallet\Schema\Parser\Email\Email;

class Request extends \TAccountChecker
{
    public $mailFiles = "hhonors/it-67523949.eml";

    public $subjects = [
        'en' => 'Hilton Helsinki Strand Confirmation',
        'Cosmopolitan of Las Vegas Confirmation',
    ];

    public $lang = '';
    public static $dictionary = [
        'en' => [
            'confNumber' => ['Confirmation:'],
            'checkIn'    => ['Arrival:'],
        ],
    ];

    private $detectors = [
        'en' => ['eStandby Upgrade', 'eStandby Add-on Offer request'],
    ];

    private $providerCode = '';

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        // Detecting Provider
        $this->assignProvider($parser->getHeaders());

        // Detecting Language
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }
        $email->setType('Request' . ucfirst($this->lang));
        $email->setProviderCode($this->providerCode);

        $this->parseHotel($email);

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        // Detecting Provider
        if ($this->assignProvider($parser->getHeaders()) === false) {
            return false;
        }

        // Detecting Format and Language
        return $this->detectBody() && $this->assignLang();
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->subjects as $reSubject) {
            if (stripos($headers['subject'], $reSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'Hilton.HelsinkiStrand@nor1upgrades.com') !== false;
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
        return ['hhonors', 'cosmohotels'];
    }

    private function parseHotel(Email $email): void
    {
        $h = $email->add()->hotel();

        $h->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->contains($this->t('confNumber'))}]", null, true, "/{$this->opt($this->t('confNumber'))}\s*([-A-Z\d]{5,})(?:\s*\)|$)/"));

        $traveller = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Dear'))}]", null, true, "/{$this->opt($this->t('Dear'))}\s*([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])(?:\s*[,;:!?]|$)/");

        if (!empty($traveller)) {
            $h->general()
                ->traveller($traveller);
        }

        $hotelName = $this->http->FindSingleNode("//text()[starts-with(normalize-space(),'We have received your')]", null, true, "/We\s+have\s+received\s+your\s+(.+)\s+eStandby\s+Upgrade/");

        if (!$hotelName) {
            $hotelName = $this->http->FindSingleNode("//text()[starts-with(normalize-space(),'Warm Regards')]/following::text()[normalize-space()][1]");
        }
        $phone = $this->http->FindSingleNode("//text()[starts-with(normalize-space(),'Warm Regards')]/following::text()[normalize-space()][2]", null, true, "/[+(\d][-. \d)(]{5,}[\d)]/");
        $h->hotel()
            ->name($hotelName)
            ->phone($phone, false, true)
            ->noAddress();

        $roomType = $this->http->FindSingleNode("//text()[contains(normalize-space(),'Room Type:')]", null, true, "/Room Type:\s*(.+)/");

        if ($roomType) {
            $h->addRoom()->setType($roomType);
        }

        $h->booked()
            ->checkIn2($this->http->FindSingleNode("//text()[{$this->contains($this->t('checkIn'))}]", null, true, "/{$this->opt($this->t('checkIn'))}\s*(.{6,})/"))
            ->checkOut2($this->http->FindSingleNode("//text()[contains(.,'Departure:')]", null, true, "/Departure:\s*(.{6,})/"));

        $cancellation = $this->http->FindSingleNode("//tr[not(.//tr) and {$this->contains($this->t('CANCEL your request'))}]");

        if (empty($cancellation)) {
            $cancellation = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'You can')]/ancestor::tr[1]");
        }

        if ($cancellation && $h->getCheckInDate()) {
            $cancellation = str_replace('by clicking HERE', '', $cancellation);
            $h->general()
                ->cancellation($cancellation);

            if (preg_match("/CANCEL your request\(s\) up to (?<prior>\d{1,3} day\(s\)) prior to day of arrival/i", $cancellation, $m) // en
            ) {
                $m['prior'] = preg_replace('/\((s)\)/i', '$1', $m['prior']);
                $h->booked()->deadlineRelative($m['prior']);
            } elseif (preg_match("/up to (\d+\s*hour)/i", $cancellation, $m) // en
            ) {
                $h->booked()->deadlineRelative($m[1]);
            }
        }
    }

    private function assignProvider($headers): bool
    {
        if (self::detectEmailFromProvider($headers['from']) === true
            || strpos($headers['subject'], 'Hilton Helsinki Strand') !== false
            || $this->http->XPath->query('//*[contains(normalize-space(),"Hilton")]')->length > 0
        ) {
            $this->providerCode = 'hhonors';

            return true;
        }

        if (stripos($headers['from'], 'cosmopolitan.lasvegas@nor1upgrades.com') !== false
            || strpos($headers['subject'], 'Cosmopolitan of Las Vegas') !== false
            || $this->http->XPath->query('//*[contains(normalize-space(),"Enjoy Your Stay, The Cosmopolitan of Las Vegas")]')->length > 0
        ) {
            $this->providerCode = 'cosmohotels';

            return true;
        }

        return false;
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
            if (!is_string($lang) || empty($phrases['confNumber']) || empty($phrases['checkIn'])) {
                continue;
            }

            if ($this->http->XPath->query("//node()[{$this->contains($phrases['confNumber'])}]")->length > 0
                && $this->http->XPath->query("//node()[{$this->contains($phrases['checkIn'])}]")->length > 0
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
}
