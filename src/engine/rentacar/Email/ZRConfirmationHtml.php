<?php

namespace AwardWallet\Engine\rentacar\Email;

use AwardWallet\Schema\Parser\Email\Email;

class ZRConfirmationHtml extends \TAccountChecker
{
    public $mailFiles = "rentacar/it-243551729.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'pickUpLocation' => ['Pick-Up Location'],
            'pickUpDate'     => ['Pick-Up Date'],
            'hello'          => ['Hello', 'Dear'],
        ],
    ];

    private $detectors = [
        'en' => ['PICK-UP DETAILS'],
    ];

    private $enDatesInverted = false;
    private $providerCode = '';

    public function detectEmailByHeaders(array $headers)
    {
        return preg_match('/Enterprise Rent-A-Car Reservation Confirmation .{5,} at .{2}/i', $headers['subject']) > 0;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return $this->assignProvider($parser->getHeaders()) && $this->detectBody() && $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }
        $email->setType('ZRConfirmationHtml' . ucfirst($this->lang));

        $this->assignProvider($parser->getHeaders());
        $email->setProviderCode($this->providerCode);

        $r = $email->add()->rental();

        $traveller = null;
        $travellerNames = array_filter($this->http->FindNodes("//*[not(self::tr) and not(self::div) and {$this->contains($this->t('hello'))}]", null, "/(?:^|\/\s*){$this->opt($this->t('hello'))}[,\s]+([[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])(?:\s*[,;:!?]|$)/u"));

        if (count(array_unique($travellerNames)) === 1) {
            $traveller = array_shift($travellerNames);
        }
        $r->general()->traveller($traveller);

        $detailsText = $this->htmlToText($this->http->FindHTMLByXpath("//node()[{$this->eq($this->t('PICK-UP DETAILS'))}]/ancestor::tr[ descendant::br and descendant::text()[normalize-space()][2] ][1]"));
        // $this->logger->debug($detailsText);

        if (preg_match("/({$this->opt($this->t('Reservation Number'))})[ ]*[:]+[ ]*([-A-z\d]{5,}?)[ ]*$/m", $detailsText, $m)) {
            $r->general()->confirmation($m[2], $m[1]);
        }

        $pickUpLocation = $this->re("/(?:^|\/)[ ]*{$this->opt($this->t('pickUpLocation'))}[ ]*[:]+[ ]*(.{3,}?)[ ]*$/m", $detailsText);
        $r->pickup()->location($pickUpLocation);

        if (preg_match("/(?:^|\/)[ ]*{$this->opt($this->t('pickUpDate'))}[ ]*[:]+[ ]*(.*\d.*?)[ ]*$/m", $detailsText, $mD)
            && preg_match("/(?:^|\/)[ ]*{$this->opt($this->t('Pick-Up Time'))}[ ]*[:]+[ ]*(\d{1,2}(?:[:：]\d{2})?(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?)[ ]*$/m", $detailsText, $mT)
        ) {
            if (preg_match_all('/\b(\d{1,2})\s*\/\s*\d{1,2}\s*\/\s*\d{4}\b/', $mD[1], $dateMatches)) {
                foreach ($dateMatches[1] as $simpleDate) {
                    if ($simpleDate > 12) {
                        $this->enDatesInverted = true;

                        break;
                    }
                }
            }
            $date = strtotime($this->normalizeDate($mD[1]));
            $r->pickup()->date(strtotime($mT[1], $date));
            $r->dropoff()->noDate();
        }

        if (!empty($r->getPickUpLocation())) {
            $r->dropoff()->noLocation();
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

    public static function getEmailProviders()
    {
        return ['alamo', 'rentacar'];
    }

    private function assignProvider($headers): bool
    {
        if (stripos($headers['subject'], 'Alamo Rent A Car Reservation Confirmation') !== false
            || $this->http->XPath->query('//*[contains(normalize-space(),"Alamo Rent A Car. All rights Reserved")]')->length > 0
        ) {
            $this->providerCode = 'alamo';

            return true;
        }

        if (stripos($headers['subject'], 'Enterprise Rent-A-Car Reservation Confirmation') !== false
            || $this->http->XPath->query('//a[contains(@href,".enterprise.com.tr/") or contains(@href,"www.enterprise.com.tr")]')->length > 0
            || $this->http->XPath->query('//*[contains(normalize-space(),"Thank you for making a reservation with Enterprise Rent-A-Car") or contains(normalize-space(),"Enterprise Rent-A-Car. All rights Reserved")]')->length > 0
        ) {
            $this->providerCode = 'rentacar';

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
            if (!is_string($lang) || empty($phrases['pickUpLocation']) || empty($phrases['pickUpDate'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[{$this->contains($phrases['pickUpLocation'])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->contains($phrases['pickUpDate'])}]")->length > 0
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

    private function re($re, $str, $c = 1): ?string
    {
        if (preg_match($re, $str, $m) && array_key_exists($c, $m)) {
            return $m[$c];
        }

        return null;
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
     * @param string|null $text Unformatted string with date
     */
    private function normalizeDate(?string $text): string
    {
        if (!is_string($text) || empty($text)) {
            return '';
        }
        $in = [
            // 16/12/2022
            '/^(\d{1,2})\/(\d{1,2})\/(\d{2,4})$/u',
        ];
        $out[0] = $this->enDatesInverted ? '$2/$1/$3' : '$1/$2/$3';

        return preg_replace($in, $out, $text);
    }
}
