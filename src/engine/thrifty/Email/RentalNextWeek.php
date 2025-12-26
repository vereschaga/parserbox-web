<?php

namespace AwardWallet\Engine\thrifty\Email;

use AwardWallet\Schema\Parser\Email\Email;

class RentalNextWeek extends \TAccountChecker
{
    public $mailFiles = "thrifty/it-103739557.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'Drop-Off Date'    => ['Drop-Off Date'],
            'Pick-Up Location' => ['Pick-Up Location'],
        ],
    ];

    private $subjects = [
        'en' => ["Here's more info for your rental next week."],
    ];

    private $detectors = [
        'en' => ['Your Just Right rental is in one week', 'Your Thrifty Car Rental Reservation'],
    ];

    public function detectEmailFromProvider($from)
    {
        return strpos($from, 'Thrifty Car Rental') !== false
            || stripos($from, '@emails.thrifty.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
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
        if ($this->detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//a[contains(@href,".thrifty.com/") or contains(@href,"emails.thrifty.com")]')->length === 0
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
        $email->setType('RentalNextWeek' . ucfirst($this->lang));

        $this->parseCar($email);

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

    private function parseCar(Email $email): void
    {
        $car = $email->add()->rental();

        $traveller = $this->http->FindSingleNode("//*[count(tr[normalize-space()])=2 and tr[normalize-space()][1][{$this->starts($this->t('Name'))}]]/tr[normalize-space()][2]", null, true, "/^[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]$/u");
        $car->general()->traveller($traveller);

        $confirmation = $this->http->FindSingleNode("//*[count(tr[normalize-space()])=2 and tr[normalize-space()][1][{$this->starts($this->t('Confirmation No.'))}]]/tr[normalize-space()][2]", null, true, '/^[A-Z\d]{5,}$/');

        if ($confirmation) {
            $confirmationTitle = $this->http->FindSingleNode("//*[count(tr[normalize-space()])=2]/tr[normalize-space()][1][{$this->starts($this->t('Confirmation No.'))}]", null, true, '/^(.+?)[\s:：]*$/u');
            $car->general()->confirmation($confirmation, $confirmationTitle);
        }

        $datePickUp = $this->http->FindSingleNode("//*[count(tr[normalize-space()])=2 and tr[normalize-space()][1][{$this->starts($this->t('Pick-Up Date'))}]]/tr[normalize-space()][2]", null, true, '/^.*\d.*$/');
        $dateDropOff = $this->http->FindSingleNode("//*[count(tr[normalize-space()])=2 and tr[normalize-space()][1][{$this->starts($this->t('Drop-Off Date'))}]]/tr[normalize-space()][2]", null, true, '/^.*\d.*$/');

        $locationPickUp = $this->http->FindSingleNode("//*[count(tr[normalize-space()])=2 and tr[normalize-space()][1][{$this->starts($this->t('Pick-Up Location'))}]]/tr[normalize-space()][2]");

        $car->pickup()->date2($datePickUp)->location($locationPickUp);
        $car->dropoff()->date2($dateDropOff)->noLocation();

        $vehicleType = $this->http->FindSingleNode("//*[count(tr[normalize-space()])=2 and tr[normalize-space()][1][{$this->starts($this->t('Vehicle Type'))}]]/tr[normalize-space()][2]");

        if (preg_match("/^(.{2,}?)\s*–\s*(.{2,})$/", $vehicleType, $m)) {
            // Premium – Chevrolet Impala Or Similar
            $car->car()->type($m[1])->model($m[2]);
        } else {
            $car->car()->type($vehicleType);
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

                if ($this->http->XPath->query("//*[{$this->contains($phrase)}]")->length > 0) {
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
            if (!is_string($lang) || empty($phrases['Drop-Off Date']) || empty($phrases['Pick-Up Location'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[{$this->contains($phrases['Drop-Off Date'])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->contains($phrases['Pick-Up Location'])}]")->length > 0
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
}
