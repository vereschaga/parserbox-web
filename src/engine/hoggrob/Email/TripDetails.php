<?php

namespace AwardWallet\Engine\hoggrob\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Schema\Parser\Email\Email;

class TripDetails extends \TAccountChecker
{
    public $mailFiles = "hoggrob/it-27717370.eml";

    private $langDetectors = [
        'en' => ['Rail DNR:'],
    ];
    private $lang = '';
    private static $dict = [
        'en' => [],
    ];

    private $providerCode = '';

    // Standard Methods

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        // Detecting Provider
        if ($this->assignProvider($parser->getHeaders()) === false) {
            return false;
        }

        // Detecting Language
        return $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        // Detecting Provider
        $this->assignProvider($parser->getHeaders());

        // Detecting Language
        if (!$this->assignLang()) {
            $this->logger->notice("Can't determine a language!");

            return $email;
        }
        $this->parseEmail($email);
        $email->setType('TripDetails' . ucfirst($this->lang));
        $email->setProviderCode($this->providerCode);

        return $email;
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
        return ['hoggrob', 'amadeus'];
    }

    private function parseEmail(Email $email)
    {
        $email->ota();

        $t = $email->add()->train();

        $departureDate = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Departure:'))}]/following::text()[normalize-space(.)][1]");
        $dateRelative = $departureDate ? strtotime($departureDate . ' -1 days') : 0;

        // confirmation number
        $confirmationNumberTitle = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Rail DNR:'))}]");
        $confirmationNumber = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Rail DNR:'))}]/ancestor::td[1]/following-sibling::*[normalize-space(.)][1]");
        $t->general()->confirmation($confirmationNumber, preg_replace('/\s*:\s*$/', '', $confirmationNumberTitle));

        // travellers
        $passenger = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Passenger Info'))}]/following::text()[normalize-space(.)][1]/ancestor::tr[1]", null, true, "/^([[:alpha:]][-.\'[:alpha:]\s]*[[:alpha:]])$/u");
        $t->general()->traveller($passenger);

        $segments = $this->http->XPath->query("//tr[ ./*[1][{$this->eq($this->t('From/To'))}] ]/following-sibling::tr[normalize-space(.)]");

        foreach ($segments as $segment) {
            $s = $t->addSegment();

            // depName
            // arrName
            $route = $this->http->FindSingleNode('./*[1]', $segment);

            if (preg_match('/^(.{3,})\s+-\s+(.{3,})$/', $route, $m)) {
                $s->departure()->name($m[1]);
                $s->arrival()->name($m[2]);
            }

            // number
            $train = $this->http->FindSingleNode('./*[2]', $segment);

            if (preg_match('/\b(\d+)$/', $train, $matches)) {
                $s->extra()->number($matches[1]);
            }

            $patterns['time'] = '\d{1,2}(?:[.:]\d{2})?(?:\s*[AaPp]\.?[Mm]\.?)?'; // 4:19PM    |    2:00 p.m.    |    3pm    |    3.00pm

            $date = $this->http->FindSingleNode('./*[3]', $segment); // 26/10

            // depDate
            $timeDep = $this->http->FindSingleNode('./*[4]', $segment, true, "/^({$patterns['time']})$/");

            if ($dateRelative && $date && $timeDep) {
                $dateDep = EmailDateHelper::parseDateRelative(preg_replace('/^(\d{1,2})\/(\d{1,2})$/', '$2/$1', $date), $dateRelative, true, '%D%/%Y%');
                $s->departure()->date(strtotime($timeDep, $dateDep));
            }

            // arrDate
            $timeArr = $this->http->FindSingleNode('./*[5]', $segment, true, "/^({$patterns['time']})$/");

            if ($dateRelative && $date && $timeArr) {
                $dateArr = EmailDateHelper::parseDateRelative(preg_replace('/^(\d{1,2})\/(\d{1,2})$/', '$2/$1', $date), $dateRelative, true, '%D%/%Y%');
                $s->arrival()->date(strtotime($timeArr, $dateArr));
            }

            // cabin
            $class = $this->http->FindSingleNode("./*[6][{$this->contains($this->t('Class'))}]", $segment);
            $s->extra()->cabin($class);

            // carNumber
            // seats
            $seatInfo = $this->http->FindSingleNode('./*[7]', $segment);

            if (preg_match("/{$this->opt($this->t('Coach:'))}\s*(\d{1,5})[,\s]+{$this->opt($this->t('Seat:'))}\s*([A-Z\d]{1,5})$/", $seatInfo, $m)) {
                // Coach: 17 Seat: 031
                $s->extra()
                    ->car($m[1])
                    ->seat($m[2]);
            }
        }

        // cancellation
        $cancellationText = $this->http->FindSingleNode("/descendant::text()[{$this->eq($this->t('Refund Conditions'))}][1]/ancestor::td[1]/following-sibling::*[normalize-space(.)][last()]");
        $t->general()->cancellation($cancellationText);
    }

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'normalize-space(' . $node . ")='" . $s . "'"; }, $field)) . ')';
    }

    private function starts($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'starts-with(normalize-space(' . $node . "),'" . $s . "')"; }, $field)) . ')';
    }

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'contains(normalize-space(' . $node . "),'" . $s . "')"; }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) { return preg_quote($s, '/'); }, $field)) . ')';
    }

    private function t($phrase)
    {
        if (!isset(self::$dict[$this->lang]) || !isset(self::$dict[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dict[$this->lang][$phrase];
    }

    private function assignProvider($headers): bool
    {
        $condition1 = strpos($headers['from'], 'HRG Belgium') !== false || stripos($headers['from'], '@hrgworldwide.com') !== false;
        $condition2 = $this->http->XPath->query('//node()[contains(normalize-space(.),"HRG Belgium") or contains(.,"@hrgworldwide.com")]')->length > 0;

        if ($condition1 || $condition2) {
            $this->providerCode = 'hoggrob';

            return true;
        }

        $condition1 = stripos($headers['from'], '@amadeus.be') !== false;
        $condition2 = $this->http->XPath->query('//node()[contains(.,"@amadeus.be")]')->length > 0;

        if ($condition1 || $condition2) {
            $this->providerCode = 'amadeus';

            return true;
        }

        return false;
    }

    private function assignLang(): bool
    {
        foreach ($this->langDetectors as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if ($this->http->XPath->query('//node()[contains(normalize-space(.),"' . $phrase . '")]')->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }
}
