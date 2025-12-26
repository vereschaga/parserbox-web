<?php

namespace AwardWallet\Engine\talixo\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class Booking extends \TAccountChecker
{
    public $mailFiles = "talixo/it-293134404.eml, talixo/it-310431257.eml, talixo/it-320101242-es.eml";

    public $lang = '';

    public static $dictionary = [
        'es' => [
            'confNumber' => ['N?mero de reservaci?n'],
            // 'Partner booking number' => '',
            'Vehicle:' => 'Categor?a de reserva:',
            'From:'    => 'Desde:',
            'To:'      => 'A:',
            'Date:'    => 'Fecha:',
            // 'Pickup Time' => '',
            // 'Distance:' => '',
            // 'Estimated duration' => '',
            // 'Estimated arrival time' => '',
            // 'Passengers:' => '',
            // 'Payment details' => '',
            // 'Price:' => '',
            // 'Cancellation policy:' => '',
            // 'Passenger details' => '',
            'Passenger:' => 'Pasajero:',
            // 'Name:' => '',
            // 'Your PIN for this trip is:' => '',
        ],
        'en' => [
            'confNumber' => ['Booking number'],
            'Pickup Time' => ['Pickup Time', 'Pickup time', 'Scheduled pickup time'],
            'Estimated arrival time' => ['Estimated arrival time', 'Estimated drop-off time'],
        ],
    ];

    private $subjects = [
        'en' => ['Booking confirmation', 'New booking:'],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@talixo.') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (!array_key_exists('from', $headers) || $this->detectEmailFromProvider(rtrim($headers['from'], '> ')) !== true) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (is_string($phrase) && array_key_exists('subject', $headers) && stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//a[contains(@href,".talixo.com/") or contains(@href,".talixo.de/")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"Your Talixo Team") or contains(normalize-space(),"manage trips via the Talixo mobile apps")]')->length === 0
        ) {
            return false;
        }

        return $this->assignLang() && $this->findRoot()->length > 0;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }
        $email->setType('Booking' . ucfirst($this->lang));

        $roots = $this->findRoot();

        if ($roots->length !== 1) {
            $this->logger->debug('Wrong format!');

            return $email;
        }
        $root = $roots->item(0);

        $patterns = [
            'date'          => '\d{1,2}\s+[[:alpha:]]+\s+(?:\d{4}|\d{2})', // 21 Marzo 2023
            'time'          => '\d{1,2}(?:[:：]\d{2})?(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.    |    3pm
            'phone'         => '[+(\d][-+. \d)(]{5,}[\d)]', // +377 (93) 15 48 52    |    (+351) 21 342 09 07    |    713.680.2992
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        ];

        $tr = $email->add()->transfer();

        $confirmation = $this->http->FindSingleNode("*[normalize-space()][2]", $root, true, '/^[-A-Z\d]{5,}$/');

        if ($confirmation) {
            $confirmationTitle = $this->http->FindSingleNode("*[normalize-space()][1]", $root, true, '/^(.+?)[\s:：]*$/u');
            $tr->general()->confirmation($confirmation, $confirmationTitle);
        }

        $confirmation2 = $this->http->FindSingleNode("//*[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->starts($this->t('Partner booking number'))}] ]/*[normalize-space()][2]", null, true, '/^[-A-Z\d]{5,}$/');

        if ($confirmation2) {
            $confirmation2Title = $this->http->FindSingleNode("//*[count(*[normalize-space()])=2]/*[normalize-space()][1][{$this->starts($this->t('Partner booking number'))}]", null, true, '/^(.+?)[\s:：]*$/u');
            $tr->general()->confirmation($confirmation2, $confirmation2Title);
        }

        $s = $tr->addSegment();

        $vehicle = $this->http->FindSingleNode("following::*[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Vehicle:'))}] ]/*[normalize-space()][2]", $root);
        $s->extra()->type($vehicle);

        $from = $this->http->FindSingleNode("following::*[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('From:'))}] ]/*[normalize-space()][2]", $root);

        $patterns['nameCodeAddress'] = "/^(?<name>.{2,}?)\s*\(\s*(?<code>[A-Z]{3})\s*\)(?:\s*[,]+\s*(?<address>.{3,}))?$/"; // Punta Cana International Airport (PUJ)

        if (preg_match($patterns['nameCodeAddress'], $from, $m)) {
            $s->departure()->name($m['name'])->code($m['code']);

            if (!empty($m['address'])) {
                $s->departure()->address($m['address']);
            }
        } else {
            $s->departure()->name($from);
        }

        $to = $this->http->FindSingleNode("following::*[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('To:'))}] ]/*[normalize-space()][2]", $root);

        if (preg_match($patterns['nameCodeAddress'], $to, $m)) {
            $s->arrival()->name($m['name'])->code($m['code']);

            if (!empty($m['address'])) {
                $s->arrival()->address($m['address']);
            }
        } else {
            $s->arrival()->name($to);
        }

        $date = $timePickup = $timeArr = null;

        $dateVal = $this->http->FindSingleNode("following::*[ *[normalize-space()][1][{$this->eq($this->t('Date:'))}] ]/*[normalize-space()][2]", $root, true, "/^{$patterns['date']}.*/u");

        if (preg_match("/^(?<date>{$patterns['date']})[,.\s]+(?<time>{$patterns['time']})/u", $dateVal, $m)) {
            $date = strtotime($this->normalizeDate($m['date']));
            $timePickup = $m['time'];
        } else {
            $date = strtotime($this->normalizeDate($dateVal));
        }

        $timePickup = $this->http->FindSingleNode("following::*[ *[normalize-space()][1][{$this->eq($this->t('Pickup Time'), "translate(.,':','')")}] ]/*[normalize-space()][2]", $root, true, "/^{$patterns['time']}/") ?? $timePickup;

        if ($date && $timePickup) {
            $s->departure()->date(strtotime($timePickup, $date));
        }

        $distance = $this->http->FindSingleNode("following::*[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Distance:'))}] ]/*[normalize-space()][2]", $root, true, "/^\d.*(?:mi|km).*/i");
        $s->extra()->miles($distance, false, true);

        $duration = $this->http->FindSingleNode("following::*[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->starts($this->t('Estimated duration'))}] ]/*[normalize-space()][2]", $root, true, "/^\d.*/");
        $s->extra()->duration($duration, false, true);

        $timeArr = $this->http->FindSingleNode("following::*[ *[normalize-space()][1][{$this->eq($this->t('Estimated arrival time'), "translate(.,':','')")}] ]/*[normalize-space()][2]", $root, true, "/^{$patterns['time']}/");

        if ($date && $timeArr) {
            $s->arrival()->date(strtotime($timeArr, $date));
        } elseif ($date && $this->http->XPath->query("following::*[{$this->starts($this->t('Pickup Time'))}]", $root)->length === 0) {
            // it-320101242-es.eml
            $s->arrival()->noDate();
        }

        $passengersCount = $this->http->FindSingleNode("following::*[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Passengers:'))}] ]/*[normalize-space()][2]", $root, true, "/^\d{1,3}$/");
        $s->extra()->adults($passengersCount, false, true);

        $totalPrice = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Payment details'))}]/following::*[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Price:'))}] ]/*[normalize-space()][2]", null, true, '/^.*\d.*$/');

        if (preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $totalPrice, $matches)) {
            // USD 184.00
            $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
            $tr->price()->currency($matches['currency'])->total(PriceHelper::parse($matches['amount'], $currencyCode));
        }

        $cancellation = $this->http->FindSingleNode("//*[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Cancellation policy:'))}] ]/*[normalize-space()][2]");
        $tr->general()->cancellation($cancellation, false, true);

        $traveller = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Passenger details'))}]/following::*[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Name:'))}] ]/*[normalize-space()][2]", null, true, "/^{$patterns['travellerName']}$/")
            ?? $this->http->FindSingleNode("following::*[ *[normalize-space()][1][{$this->eq($this->t('Passenger:'))}] ]/*[normalize-space()][2]", $root, true, "/^({$patterns['travellerName']})(?:\s*{$patterns['phone']}|$)/")
        ;
        $tr->general()->traveller($traveller, true);

        $note = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Your PIN for this trip is:'))}]", null, true, "/^{$this->opt($this->t('Your PIN for this trip is:'))}[:\s]*[-A-z\d]{4,}$/");

        if ($note) {
            $tr->general()->notes($note);
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

    private function findRoot(): \DOMNodeList
    {
        return $this->http->XPath->query("//*[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->starts($this->t('confNumber'))}] ]");
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['confNumber'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[{$this->contains($phrases['confNumber'])}]")->length > 0) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function t(string $phrase, string $lang = '')
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return $phrase;
        }

        if ($lang === '') {
            $lang = $this->lang;
        }

        if (empty(self::$dictionary[$lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$lang][$phrase];
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

    /**
     * Dependencies `use AwardWallet\Engine\MonthTranslate` and `$this->lang`.
     *
     * @param string|null $text Unformatted string with date
     */
    private function normalizeDate(?string $text): ?string
    {
        if (preg_match('/^(\d{1,2})\s+([[:alpha:]]+)\s+(\d{2,4})\b/u', $text, $m)) {
            // 21 Marzo 2023    |    29 April 2023 (Saturday)
            $day = $m[1];
            $month = $m[2];
            $year = $m[3];
        }

        if (isset($day, $month, $year)) {
            if (preg_match('/^\s*(\d{1,2})\s*$/', $month, $m)) {
                return $m[1] . '/' . $day . ($year ? '/' . $year : '');
            }

            if (($monthNew = MonthTranslate::translate($month, $this->lang)) !== false) {
                $month = $monthNew;
            }

            return $day . ' ' . $month . ($year ? ' ' . $year : '');
        }

        return null;
    }
}
