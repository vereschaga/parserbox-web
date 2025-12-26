<?php

namespace AwardWallet\Engine\leadinghotels\Email;

use AwardWallet\Schema\Parser\Email\Email;

class ReservationDetails2 extends \TAccountChecker
{
    public $mailFiles = "leadinghotels/it-147858316.eml, leadinghotels/it-797929642.eml";

    public $reFrom = ["sacher.com", "@ihg.com", '@baglionihotels.com'];
    public $reBody = [
        'en' => [
            'Your Confirmation number is:',
            'Guest First Name:',
        ],
    ];

    public $reSubject = [
        'en' => ['Itinerary Confirmation'],
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
            'Room Rate:'              => ['Room Rate:', 'Room Rate (daily):'],
        ],
    ];

    private static $provDetect = [
        'ichotelsgroup' => ['@ihg.com'],
        'leadinghotels' => ['Sacher', 'Baglioni'],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        $a = explode('\\', __CLASS__);
        $class = end($a);
        $email->setType($class . ucfirst($this->lang));

        foreach (self::$provDetect as $prov => $reProv) {
            $re = (array) $reProv;

            foreach ($re as $r) {
                if (stripos($this->http->Response['body'], $r) !== false) {
                    $email->setProviderCode($prov);
                }
            }
        }

        if (!$this->parseEmail($email)) {
            return $email;
        }

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        // if ($this->http->XPath->query("//text()[contains(.,'ihg.com')] | //text()[contains(.,'Sacher')] | //a[contains(@href,'sacher.com')] | //img[contains(@alt,'Leading Hotels')] | //a[contains(@href,'katikiesgarden.com')] | //text()[contains(.,'www.lareserve-')]")->length > 0) {
        return $this->assignLang();
        // }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (
            self::detectEmailFromProvider($headers['from']) !== true
            && stripos($headers['subject'], 'Hotel Sacher Wien') === false
            && stripos($headers['subject'], 'InterContinental Los Angeles Century City') === false
        ) {
            return false;
        }

        foreach ($this->reSubject as $phrases) {
            foreach ($phrases as $phrase) {
                if (stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->reFrom as $reFrom) {
            if (stripos($from, $reFrom) !== false) {
                return true;
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    public static function getEmailProviders()
    {
        return array_keys(self::$provDetect);
    }

    private function nextField($field, $root = null, $num = 0): ?string
    {
        if ($num === 0) {
            return $this->http->FindSingleNode(".//text()[{$this->starts($field)}]/ancestor::td[1]/following-sibling::td[normalize-space(.)!=''][1]",
                $root);
        } elseif ($num > 0) {
            return $this->http->FindSingleNode(".//text()[{$this->starts($field)}]/ancestor::td[1]/following-sibling::td[normalize-space(.)!=''][1]/descendant::text()[normalize-space(.)!=''][{$num}]",
                $root);
        } else {
            return null;
        }
    }

    private function nextNode($field, $root = null, $regexp = null)
    {
        return $this->http->FindSingleNode(".//text()[{$this->starts($field)}]/following::*[normalize-space(.)!=''][1]",
            $root, true, $regexp);
    }

    private function parseEmail(Email $email): bool
    {
        $xpath = "//text()[{$this->eq($this->t('Reservation Details'))}]/following::text()[normalize-space()][1]/ancestor::*[not(.//text()[{$this->eq($this->t('Reservation Details'))}])][last()]";
        $this->logger->debug('$xpath = ' . print_r($xpath, true));
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $h = $email->add()->hotel();

            // General
            $confNumber = $this->http->FindSingleNode("preceding::text()[normalize-space()][position() < 8][{$this->eq($this->t('Your Confirmation number is:'))}]/following::*[normalize-space(.)][1]",
                $root, true, '/^[A-Z\d]{5,}$/');

            $h->general()
                ->confirmation($confNumber);

            $name = $this->http->FindSingleNode("preceding::text()[normalize-space()][position() < 8][{$this->eq($this->t('Thank you for making a reservation at'))}]/following::*[normalize-space(.)][1]",
                $root);
            $sites = [
                'sacher.com',
                'continental.com',
                'www.ihg.com',
                'intercontinentallosangeles.com',
                'icsydney.com',
                'reservations@lareserve-paris.com',
                'www.katikies.com',
                'www.lareserve-zurich.com',
                'www.lareserve-geneve.com',
            ];
            $contacts = implode("\n",
                $this->http->FindNodes("//text()[({$this->contains($sites)}) and not(contains(.,'@'))]/ancestor::table[1][{$this->contains($this->t('Tel'))}]/descendant::text()[normalize-space()]"));

            /*Katikies Garden Santorini - Fira Town, Santorini, 84700 Cyclades Islands, Greece
            +30 22864 40900
            -
            info@katikiesgarden.com
            www.katikies.com*/

            // $this->logger->debug($contacts);

            if (preg_match("/^\s*({$name}+)\s*\-\s*(.+)\n([+]\s*[\d\s]+)\n/u", $contacts, $m)) {
                $address = $m[2];
                $tel = $m[3];
            }

            $h->hotel()
                ->name($name);

            if (!empty($tel)) {
                $h->hotel()
                    ->phone($tel);
            }

            if (!empty($address)) {
                $h->hotel()
                    ->address($address);
            } else {
                $h->hotel()
                    ->noAddress();
            }

            // $firstName = $this->http->FindSingleNode(".//text()[normalize-space()='Guest First Name:']/ancestor::tr[1]/descendant::td[2]", $root);
            $firstName = $this->nextField($this->t('Guest First Name:'), $root);
            $lastName = $this->nextField($this->t('Guest Last Name:'), $root);
            // $lastName = $this->http->FindSingleNode(".//text()[normalize-space()='Guest Last Name:']/ancestor::tr[1]/descendant::td[2]", $root);

            $h->general()->traveller($firstName . ' ' . $lastName);

            $r = $h->addRoom();
            $r
                ->setType($this->nextField($this->t('Room Type:'), $root), true, true)
                ->setDescription($this->nextField($this->t('Room Description:'), $root))
                ->setRate($this->nextField($this->t('Room Rate:'), $root))
                ->setRateType($this->nextField($this->t('Rate Name:'), $root))
            ;

            $h->booked()
                ->guests($this->nextField($this->t('Number of guests:'), $root))
            ;
            $ciDate = strtotime($this->nextField($this->t('Arrival Date:'), $root));
            $ciTime = $this->nextField($this->t('Check In Time:'), $root, "/^(.+?)\s*(?:\(|$)/");

            if (!empty($ciTime) && !empty($ciDate)) {
                $ciDate = strtotime($ciTime, $ciDate);
            }
            $h->booked()
                ->checkIn($ciDate);
            $coDate = strtotime($this->nextField($this->t('Departure Date:'), $root));
            $coTime = $this->nextField($this->t('Check In Time:'), $root, "/^(.+?)\s*(?:\(|$)/");

            if (!empty($coTime) && !empty($coDate)) {
                $coDate = strtotime($coTime, $coDate);
            }
            $h->booked()
                ->checkOut($coDate);

            // cancellation
            $cancellationPolicy = $this->nextField($this->t('Cancel Policy:'), $root);

            $h->general()->cancellation($cancellationPolicy, false, true);

            $patterns['time'] = '\d{1,2}:\d{2}(?:\s*[ap]\.?m\.?)?'; // 6:00 PM
        }

        return true;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang()
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if ($this->http->XPath->query("//*[{$this->contains($reBody[0])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($reBody[1])}]")->length > 0
                ) {
                    $this->lang = substr($lang, 0, 2);

                    return true;
                }
            }
        }

        return false;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }
}
