<?php

namespace AwardWallet\Engine\preflight\Email;

use AwardWallet\Schema\Parser\Email\Email;

class ReservationCancellation extends \TAccountChecker
{
    public $mailFiles = "preflight/it-665080338.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'Reservation Start Date/Time' => 'Reservation Start Date/Time',
            'canceledText'                => ['Preflight Parking Reservation Cancelation', 'The reservation listed below has been canceled'],
        ],
    ];

    private $subjects = [
        'PreFlight Airport Parking Reservation Cancellation',
    ];

    private $detectors = [
        'en' => ['Preflight Parking Reservation Cancelation', 'The reservation listed below has been canceled'],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@preflightparking.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true
            && strpos($headers['subject'], 'PreFlight Airport Parking') === false
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

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//a[contains(@href,".preflightairportparking.com/") or contains(@href,"www.preflightairportparking.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"Thank you for parking with PreFlight") or contains(normalize-space(),"PreFlight LLC. All Right Reserved")]')->length === 0
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

        $this->parseParking($email);

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

    private function parseParking(Email $email): void
    {
        $p = $email->add()->parking();

        $confirmation = $this->http->FindSingleNode("//tr[ *[3][{$this->eq($this->t('Confirmation #'))}] ]/following-sibling::tr[normalize-space()][1]/*[3]", null, true, '/^[-A-Z\d]{5,}$/');
        $p->general()
            ->confirmation($confirmation);

        $name = implode(' ', $this->http->FindNodes("//tr[ *[1][{$this->eq($this->t('First Name'))}] ]/following::tr[normalize-space()][1]/descendant::text()[normalize-space()]"));
        $p->general()->traveller($name, true);

        if ($this->http->FindSingleNode("(//text()[{$this->contains($this->t('canceledText'))}])[1]")) {
            $p->general()
                ->cancelled()
                ->status('Canceled');
        }

        $p->place()
            ->location($this->http->FindSingleNode("//tr[{$this->eq($this->t('Preflight Facility Location'))}]/following-sibling::tr[normalize-space()][1]"))
            ->address($this->http->FindSingleNode("//tr[{$this->eq($this->t('Facility Address'))}]/following-sibling::tr[normalize-space()][1]"))
            ->phone($this->http->FindSingleNode("//tr[{$this->eq($this->t('Facility Phone'))}]/following-sibling::tr[normalize-space()][1]"));

        $dateDep = $this->http->FindSingleNode("//tr[ *[1][{$this->eq($this->t('Reservation Start Date/Time'))}] ]/following-sibling::tr[normalize-space()][1]/*[1]");
        $p->booked()->start($this->normalizeDate($dateDep));

        $dateArr = $this->http->FindSingleNode("//tr[ *[2][{$this->eq($this->t('Reservation End Date/Time'))}] ]/following-sibling::tr[normalize-space()][1]/*[2]");
        $p->booked()->end($this->normalizeDate($dateArr));
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
        foreach (self::$dictionary as $lang => $phrases) {
            if (!empty($phrases['Reservation Start Date/Time'])
                && $this->http->XPath->query("//*[{$this->eq($phrases['Reservation Start Date/Time'])}]")->length > 0
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

    private function normalizeDate($date)
    {
        // $this->logger->debug('$date = '.print_r( $date,true));
        $in = [
            // 04/18/24, 11:30 AM
            "/^\s*(\d{1,2})\/(\d{1,2})\/(\d{2})\s*,\s*(\d{1,2}:\d{2}(?:\s*[AP]M)?)\s*$/i",
        ];
        $out = [
            "$1/$2/20$3, $4",
        ];
        $date = preg_replace($in, $out, $date);
        // $this->logger->debug('$date = '.print_r( $date,true));

        return strtotime($date);
    }
}
