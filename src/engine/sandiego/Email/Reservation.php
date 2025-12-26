<?php

namespace AwardWallet\Engine\sandiego\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class Reservation extends \TAccountChecker
{
    public $mailFiles = "sandiego/it-166330638.eml, sandiego/it-166776272.eml, sandiego/it-772640865.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'confNumber'  => ['Reservation Number'],
            'bookedStart' => ['Parking Entry'],
            'parkingLot'  => ['Parking Lot', 'Parking Location'],
            'statusPhrases'  => ['reservation is'],
            'statusVariants'  => ['confirmed'],
            'addressStart'  => ['located at'],
            'addressEnd'  => ['You can find your reservation details'],
        ],
    ];

    private $subjects = [
        'en' => ['This is your confirmation email for booking'],
    ];

    private $detectors = [
        'en' => ['Reservation Details'],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@san.org') !== false;
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
        if ($this->http->XPath->query('//a[contains(@href,".san.org/") or contains(@href,"www.san.org") or contains(@href,"reservations.san.org")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"Email:reservations@san.org")]')->length === 0
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
        $email->setType('Reservation' . ucfirst($this->lang));

        $patterns = [
            'time'          => '\d{1,2}[:：]\d{2}(?:[:：]\d{2})?(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.    |    09:30:00 AM
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        ];

        $park = $email->add()->parking();

        $statusTexts = array_filter($this->http->FindNodes("//text()[{$this->contains($this->t('statusPhrases'))}]", null, "/{$this->opt($this->t('statusPhrases'))}[:\s]+({$this->opt($this->t('statusVariants'))})(?:\s*[,.;:!?]|$)/i"));

        if (count(array_unique(array_map('mb_strtolower', $statusTexts))) === 1) {
            $status = array_shift($statusTexts);
            $park->general()->status($status);
        }

        $parkingLot = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('parkingLot'), "translate(.,':', '')")}] ]/*[normalize-space()][2]");
        $park->place()->location($parkingLot);

        $confirmation = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('confNumber'), "translate(.,':', '')")}] ]/*[normalize-space()][2]", null, true, '/^[A-Z\d]{5,}$/');

        if ($confirmation) {
            $confirmationTitle = $this->http->FindSingleNode("//tr[count(*[normalize-space()])=2]/*[normalize-space()][1][{$this->eq($this->t('confNumber'), "translate(.,':', '')")}]", null, true, '/^(.+?)[\s:：]*$/u');
            $park->general()->confirmation($confirmation, $confirmationTitle);
        }

        $parkingEntry = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('bookedStart'), "translate(.,':', '')")}] ]/*[normalize-space()][2]", null, true, '/^.*\d.*$/');
        $parkingExit = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Parking Exit'), "translate(.,':', '')")}] ]/*[normalize-space()][2]", null, true, '/^.*\d.*$/');

        // 06:00:00 PM on Saturday, 18 June 2022
        $patterns['timeDate'] = "/^(?<time>{$patterns['time']})\s+{$this->opt($this->t('on'))}\s+(?<date>.{6,})$/";

        if (preg_match($patterns['timeDate'], $parkingEntry, $m)) {
            $park->booked()->start(strtotime($m['time'], strtotime($m['date'])));
        }

        if (preg_match($patterns['timeDate'], $parkingExit, $m)) {
            $park->booked()->end(strtotime($m['time'], strtotime($m['date'])));
        }

        $name = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Name'), "translate(.,':', '')")}] ]/*[normalize-space()][2]", null, true, "/^{$patterns['travellerName']}$/u");
        $park->general()->traveller($name);

        $plate = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('License Plate'), "translate(.,':', '')")}] ]/*[normalize-space()][2]");
        $park->booked()->plate($plate);

        $totalPrice = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Price'), "translate(.,':', '')")}] ]/*[normalize-space()][2]", null, true, '/^(.*\d.*?)(?:\s*\(\s*incl Tax\s*\))?$/i');

        if (preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*)$/', $totalPrice, $matches)) {
            // $320.00
            $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
            $park->price()->currency($matches['currency'])->total(PriceHelper::parse($matches['amount'], $currencyCode));
        }

        $directionsText = $this->htmlToText($this->http->FindHTMLByXpath("//tr[{$this->eq($this->t('Directions'))}]/ancestor-or-self::tr[ following-sibling::tr[normalize-space()] ]/following-sibling::tr[normalize-space()][1]"));
        $address = null;

        if (preg_match("/{$this->opt($this->t('Address'))}[ ]*[:]+\s*(?<address>.{3,80}?)[ ]*(?:[*].+|\n|\s*$)/", $directionsText, $m)
            || preg_match("/^[ ]*(?<address>.{3,50},[ ]*[A-Z]{2}[ ]+\d[-\d ]+\d)[ ]*$/m", $directionsText, $m)
        ) {
            $address = $m['address'];
        }

        $welcomeText = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Thank you for making a reservation for'))}]/ancestor::*[1]");

        if (empty($address)
            && preg_match("/{$this->opt($this->t('addressStart'))}\s+(?<address>.{3,80}?)[\s.;!]*(?:{$this->opt($this->t('addressEnd'))}|$)/", $welcomeText, $m)
        ) {
            // it-772640865.eml
            $address = $m['address'];
        }

        $park->place()->address($address);

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
            if (!is_string($lang) || empty($phrases['confNumber']) || empty($phrases['bookedStart'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[{$this->contains($phrases['confNumber'])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->contains($phrases['bookedStart'])}]")->length > 0
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
}
