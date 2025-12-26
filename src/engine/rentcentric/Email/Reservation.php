<?php

namespace AwardWallet\Engine\rentcentric\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class Reservation extends \TAccountChecker
{
    public $mailFiles = "rentcentric/it-669906677.eml, rentcentric/it-676817928-fr.eml";

    public $lang = '';

    public static $dictionary = [
        'fr' => [
            'confNumber'       => ['Numero de confirmation:', 'Numero de confirmation :'],
            'pickupLocation'   => ['Lieu de prise de possession:', 'Lieu de prise de possession :'],
            'Return Location:' => 'Lieu de remise:',
            'Pickup Date:'     => 'Date de prise de possession:',
            'Return Date:'     => 'Date de retour:',
            // 'Date:' => '',
            'First Name:'  => 'Nom:',
            'Last Name:'   => 'Prenom:',
            'Your Vehicle' => 'Categorie de vehicule',
            'Total'        => ['Total', 'Totale'],
            'Sub Total'    => ['Sub Total', 'Sous-total'],
        ],
        'en' => [
            'confNumber'     => ['Res Number:', 'Res Number :'],
            'pickupLocation' => ['Pickup Location:', 'Pickup Location :'],
            // 'Return Location:' => '',
            // 'Pickup Date:' => '',
            // 'Return Date:' => '',
            // 'Date:' => '',
            // 'First Name:' => '',
            // 'Last Name:' => '',
            // 'Your Vehicle' => '',
            // 'Total' => '',
            // 'Sub Total' => '',
        ],
    ];

    private $subjects = [
        'fr' => ['Confirmation de réservation auto'],
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
        $email->setType('Reservation' . ucfirst($this->lang));

        $patterns = [
            'date'          => '(?:\b[[:alpha:]]+\s*,\s*[[:alpha:]]+\s+\d{1,2}\s*,\s*\d{4}\b|\b\d{4}-\d{1,2}-\d{1,2}\b)', // Saturday, February 25, 2023    |    2024-08-24
            'time'          => '\d{1,2}(?:[:：]\d{2})?(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.    |    3pm
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        ];

        $email->ota(); // because Rent Centric is software company

        $r = $email->add()->rental();

        $confirmation = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('confNumber'))}] ]/*[normalize-space()][2]", null, true, '/^[-A-Z\d]{4,}$/');

        if ($confirmation) {
            $confirmationTitle = $this->http->FindSingleNode("//tr[count(*[normalize-space()])=2]/*[normalize-space()][1][{$this->eq($this->t('confNumber'))}]", null, true, '/^(.+?)[\s:：]*$/u');
            $r->general()->confirmation($confirmation, $confirmationTitle);
        }

        $resDate = $this->http->FindSingleNode("//tr/*[{$this->eq($this->t('confNumber'))}]/following::tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Date:'))}] ]/*[normalize-space()][2]", null, true, "/.*{$patterns['date']}.*/u");
        $r->general()->date2($resDate);

        $travellerParts = [];

        $firstName = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('First Name:'))}] ]/*[normalize-space()][2]", null, true, "/^{$patterns['travellerName']}$/u");

        if ($firstName) {
            $travellerParts[] = $firstName;
        }

        $lastName = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Last Name:'))}] ]/*[normalize-space()][2]", null, true, "/^{$patterns['travellerName']}$/u");

        if ($lastName) {
            $travellerParts[] = $lastName;
        }

        if (count($travellerParts) > 0) {
            $r->general()->traveller(implode(' ', $travellerParts), count($travellerParts) > 1);
        }

        $xpathModelImg = "//tr[{$this->eq($this->t('Your Vehicle'))}]/following::tr[count(*)=2 and normalize-space() and descendant::img][1]";

        $model = $this->http->FindSingleNode($xpathModelImg . "/*[1]/descendant-or-self::*[ normalize-space() and *[2] ][1]/*[1]");
        $img = $this->http->FindSingleNode($xpathModelImg . "/*[2][translate(normalize-space(),'\"','')='']/descendant::img[normalize-space(@src)]/@src");
        $r->car()->model($model)->image($img, false, true);

        $locationPickup = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('pickupLocation'))}] ]/*[normalize-space()][2]");
        $locationDropoff = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Return Location:'))}] ]/*[normalize-space()][2]");
        $datePickup = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Pickup Date:'))}] ]/*[normalize-space()][2]", null, true, "/^{$patterns['date']}(?:\s+{$patterns['time']}|$)/u");
        $dateDropoff = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Return Date:'))}] ]/*[normalize-space()][2]", null, true, "/^{$patterns['date']}(?:\s+{$patterns['time']}|$)/u");
        $r->pickup()->location($locationPickup)->date2($datePickup);
        $r->dropoff()->location($locationDropoff)->date2($dateDropoff);

        $totalPrice = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Total'))}] ]/*[normalize-space()][2]", null, true, '/^.*\d.*$/');

        if (preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $totalPrice, $matches)
            || preg_match('/^(?<amount>\d[,.‘\'\d ]*?)[ ]*(?<currency>[^\-\d)(]+)$/u', $totalPrice, $matches)
            || preg_match('/^(?<amount>\d[,.‘\'\d ]*)$/u', $totalPrice, $matches)
        ) {
            // €1,577.10    |    797,00 $    |    797,00
            if (!array_key_exists('currency', $matches)) {
                $matches['currency'] = null;
            }
            $currencyCode = !empty($matches['currency']) && preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
            $r->price()->currency($matches['currency'], false, true)->total(PriceHelper::parse($matches['amount'], $currencyCode));

            $baseFare = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Sub Total'))}] ]/*[normalize-space()][2]", null, true, '/^.*\d.*$/');

            if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $baseFare, $m)
                || preg_match('/^(?<amount>\d[,.‘\'\d ]*)[ ]*(?:' . preg_quote($matches['currency'], '/') . ')?$/u', $baseFare, $m)
            ) {
                $r->price()->cost(PriceHelper::parse($m['amount'], $currencyCode));
            }

            $feeRows = $this->http->XPath->query("//tr[ preceding-sibling::tr[*[1][{$this->eq($this->t('Sub Total'))}]] and following-sibling::tr[*[1][{$this->eq($this->t('Total'))}]] and *[1][normalize-space()] and *[4][normalize-space()] ]");

            foreach ($feeRows as $feeRow) {
                $feeCharge = $this->http->FindSingleNode('*[4]', $feeRow, true, '/^(.*?\d.*?)\s*(?:\(|$)/');

                if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $feeCharge, $m)
                    || preg_match('/^(?<amount>\d[,.‘\'\d ]*)[ ]*(?:' . preg_quote($matches['currency'], '/') . ')?$/u', $feeCharge, $m)
                ) {
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
            if (!is_string($lang) || empty($phrases['confNumber']) || empty($phrases['pickupLocation'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[{$this->contains($phrases['confNumber'])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->contains($phrases['pickupLocation'])}]")->length > 0
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
