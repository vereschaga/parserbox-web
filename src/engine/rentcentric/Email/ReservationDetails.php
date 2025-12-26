<?php

namespace AwardWallet\Engine\rentcentric\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class ReservationDetails extends \TAccountChecker
{
    public $mailFiles = "rentcentric/it-667384387.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'confNumber' => ['Res Number:', 'Res Number :'],
            'fullName'   => ['Full Name:', 'Full Name :'],
        ],
    ];

    private $subjects = [
        'en' => ['Reservation Confirmation'],
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[.@]rentcentric\.com$/i', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider(rtrim($headers['from'], '> ')) !== true) {
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
        if ($this->detectEmailFromProvider(rtrim($parser->getHeader('from'), '> ')) !== true
            && $this->http->XPath->query('//a[contains(@href,".rentcentric.com/") or contains(@href,"www.rentcentric.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(.,"@rentcentric.com")]')->length === 0
        ) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }
        $email->setType('ReservationDetails' . ucfirst($this->lang));

        $xpathTime = 'contains(translate(.,"0123456789：.Hh ","∆∆∆∆∆∆∆∆∆∆::::"),"∆:∆∆")';

        $patterns = [
            'date'          => '\b[[:alpha:]]+\s*,\s*[[:alpha:]]+\s+\d{1,2}\s*,\s*\d{4}\b', // Sunday, September 03, 2023
            'time'          => '\d{1,2}(?:[:：]\d{2})?(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.    |    3pm
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        ];

        $email->ota(); // because Rent Centric is software company

        $r = $email->add()->rental();

        $datesText = implode("\n", $this->http->FindNodes("//tr[ count(*)=2 and *[1][descendant::img and normalize-space()=''] and *[2][{$xpathTime}] ]/*[2]/descendant::text()[normalize-space()]"));

        if (preg_match("/^(?<dateTime1>{$patterns['date']}\s+{$patterns['time']})\n(?<location1>.{3,}?)\n(?<dateTime2>{$patterns['date']}\s+{$patterns['time']})\n(?<location2>.{3,})$/s", $datesText, $m)) {
            $datePickup = strtotime(preg_replace('/\s+/', ' ', $m['dateTime1']));
            $r->pickup()->date($datePickup)->location(preg_replace('/\s+/', ' ', $m['location1']));
            $dateDropoff = strtotime(preg_replace('/\s+/', ' ', $m['dateTime2']));
            $r->dropoff()->date($dateDropoff)->location(preg_replace('/\s+/', ' ', $m['location2']));
        }

        $xpathImgModel = "//tr[ count(*)=2 and normalize-space() and *[1][descendant::img and normalize-space()=''] and *[2][not({$xpathTime})] and following::text()[normalize-space()][1][{$this->contains($this->t('∆ Door'), 'translate(normalize-space(),"0123456789","∆∆∆∆∆∆∆∆∆∆")')}] ]";

        $img = $this->http->FindSingleNode($xpathImgModel . "/*[1]/descendant::img[normalize-space(@src)]/@src");
        $model = $this->http->FindSingleNode($xpathImgModel . "/*[2]/descendant::text()[normalize-space()][1]");
        $r->car()->image($img)->model($model);

        $confirmation = $this->http->FindSingleNode("//text()[{$this->eq($this->t('confNumber'))}]/following::text()[normalize-space()][1]", null, true, '/^[A-Z\d]{4,}$/');

        if ($confirmation) {
            $confirmationTitle = $this->http->FindSingleNode("//text()[{$this->eq($this->t('confNumber'))}]", null, true, '/^(.+?)[\s:：]*$/u');
            $r->general()->confirmation($confirmation, $confirmationTitle);
        }

        $resDate = $this->http->FindSingleNode("//text()[{$this->eq($this->t('confNumber'))}]/following::text()[{$this->eq($this->t('Date:'))}][1]/following::text()[normalize-space()][1]", null, true, "/.*{$patterns['date']}.*/u");
        $r->general()->date2($resDate);

        $traveller = $this->http->FindSingleNode("//text()[{$this->eq($this->t('fullName'))}]/following::text()[normalize-space()][1]", null, true, "/^{$patterns['travellerName']}$/u");
        $r->general()->traveller($traveller, true);

        $totalPrice = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Total'))}] ]/*[normalize-space()][2]", null, true, '/^.*\d.*$/');

        if (preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $totalPrice, $matches)) {
            // $180.10
            $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
            $r->price()->currency($matches['currency'])->total(PriceHelper::parse($matches['amount'], $currencyCode));

            $baseFare = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Sub Total'))}] ]/*[normalize-space()][2]", null, true, '/^.*\d.*$/');

            if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $baseFare, $m)) {
                $r->price()->cost(PriceHelper::parse($m['amount'], $currencyCode));
            }

            $feeRows = $this->http->XPath->query("//tr[ preceding-sibling::tr[*[1][{$this->eq($this->t('Sub Total'))}]] and following-sibling::tr[*[1][{$this->eq($this->t('Total'))}]] and *[1][normalize-space()] and *[4][normalize-space()] ]");

            foreach ($feeRows as $feeRow) {
                $feeCharge = $this->http->FindSingleNode('*[4]', $feeRow, true, '/^(.*?\d.*?)\s*(?:\(|$)/');

                if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $feeCharge, $m)) {
                    $feeName = $this->http->FindSingleNode('*[1]', $feeRow, true, '/^(.+?)[\s:：]*$/u');
                    $r->price()->fee($feeName, PriceHelper::parse($m['amount'], $currencyCode));
                }
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

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['confNumber']) || empty($phrases['fullName'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[{$this->contains($phrases['confNumber'])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->contains($phrases['fullName'])}]")->length > 0
            ) {
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
}
