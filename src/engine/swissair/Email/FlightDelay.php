<?php

namespace AwardWallet\Engine\swissair\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Schema\Parser\Email\Email;

class FlightDelay extends \TAccountChecker
{
    public $mailFiles = "swissair/it-44680798.eml, swissair/it-44706927.eml, swissair/it-9969869.eml";

    public $reBody = [
        'en' => ['delay to your following flight', 'is delayed'],
    ];

    public $reSubject = [
        'en' => ['Flight Delay Information'],
    ];

    public $lang = '';
    public static $dict = [
        'en' => [],
    ];

    private $providerCode = '';
    private $dateRelative = 0;

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

        $this->dateRelative = EmailDateHelper::calculateOriginalDate($this, $parser);

        if ($this->dateRelative) {
            $this->dateRelative = strtotime('-1 day', $this->dateRelative);
        }

        $this->parseEmail($email);
        $email->setType('FlightDelay' . ucfirst($this->lang));
        $email->setProviderCode($this->providerCode);

        return $email;
    }

    public static function getEmailProviders()
    {
        return ['lufthansa', 'swissair'];
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'swiss.com') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        // Detecting Provider
        if ($this->assignProvider($parser->getHeaders()) === false) {
            return false;
        }

        // Detecting Language
        return $this->assignLang();
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (preg_match('/Delay of your flight LX[ ]*\d+/i', $headers['subject']) > 0) {
            return true;
        }

        if (self::detectEmailFromProvider($headers['from']) !== true && strpos($headers['subject'], 'SWISS') === false) {
            return false;
        }

        foreach ($this->reSubject as $ss) {
            if (stripos($headers['subject'], $ss[0]) !== false) {
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

    private function parseEmail(Email $email)
    {
        $f = $email->add()->flight();

        $confirmation = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Booking reference'))}]", null, true, "#.+:\s*([A-Z\d]{5,7})\b#");

        if ($confirmation) {
            $f->general()->confirmation($confirmation);
        } elseif ($this->http->XPath->query("//node()[{$this->contains($this->t('Please proceed with check-in online'))}]")->length > 0) {
            $f->general()->noConfirmation();
        }

        $segmentsHtml = $this->http->FindHTMLByXpath("descendant::td[{$this->contains($this->t(' from '))} and {$this->contains($this->t(' to '))}][last()]");
        $segmentsText = $this->htmlToText($segmentsHtml);

        $patterns['time'] = '\d{1,2}:\d{2}(?:\s*[AaPp]\.?[Mm]\.?)?';

        /*
            LX1583/17Nov2017 from Vienna (VIE) to Zurich (ZRH)
            New departure ex Vienna (VIE) is expected at 19:30.
        */
        $patterns['segment1'] = "/"
            . "(?<airline>[A-Z][A-Z\d]|[A-Z\d][A-Z])(?<flightnum>\d{1,5})\/(?<date>.{6,})"
            . "{$this->opt($this->t(' from '))}(?<depname>.+?)\s*\((?<depcode>[A-Z]{3})\){$this->opt($this->t(' to '))}(?<arrname>.+?)\s*\((?<arrcode>[A-Z]{3})\)"
            . "\s*New depart.+at (?<time>{$patterns['time']})"
            . "/";

        /*
            Your flight LX644 on 05 September from Zurich (ZRH) to Paris (CDG) is delayed.
            We apologise for any inconvenience.
            Your new departure time is 19:10 hrs.
        */
        $patterns['segment2'] = "/"
            . "Your flight (?<airline>[A-Z][A-Z\d]|[A-Z\d][A-Z])(?<flightnum>\d{1,5}) on (?<date>.{3,})"
            . "{$this->opt($this->t(' from '))}(?<depname>.+?)\s*\((?<depcode>[A-Z]{3})\){$this->opt($this->t(' to '))}(?<arrname>.+?)\s*\((?<arrcode>[A-Z]{3})\).*"
            . "(\s+.+)?"
            . "\s+Your new departure time is (?<time>{$patterns['time']})"
            . "/";

        if (!preg_match_all($patterns['segment1'], $segmentsText, $segmentsMatches, PREG_SET_ORDER) // it-9969869.eml
            && !preg_match_all($patterns['segment2'], $segmentsText, $segmentsMatches, PREG_SET_ORDER) // it-44680798.eml
        ) {
            $this->logger->debug("Segments root not found: $xpath");

            return;
        }

        foreach ($segmentsMatches as $m) {
            $s = $f->addSegment();

            $s->airline()
                ->name($m['airline'])
                ->number($m['flightnum']);

            $s->departure()
                ->name($m['depname'])
                ->code($m['depcode']);

            $s->arrival()
                ->name($m['arrname'])
                ->code($m['arrcode'])
                ->noDate();

            if (!preg_match("/^(?<day>\d{1,2})(?<month>[[:alpha:]]{3,})(?<year>\d{4})$/u", $m['date'], $matches) // 17Nov2017
                && !preg_match("/^(?<day>\d{1,2})\s*(?<month>[[:alpha:]]{3,})$/u", $m['date'], $matches) // 05 September
                && !preg_match("/^(?<day>\d{1,2})\.(?<month>\d{1,2})\.(?<year>\d{4})\b/", $m['date'], $matches) // 31.08.2019 at 17:10
            ) {
                continue;
            }

            $dateDep = empty($matches['year']) && $this->dateRelative
                ? strtotime($m['time'], EmailDateHelper::parseDateRelative($matches['day'] . ' ' . $matches['month'], $this->dateRelative))
                : strtotime($matches['day'] . '-' . $matches['month'] . '-' . $matches['year'] . ' ' . $m['time']);
            $s->departure()->date($dateDep);
        }
    }

    private function assignProvider($headers): bool
    {
        $condition1 = strpos($headers['from'], 'HRG Belgium') !== false || stripos($headers['from'], '@hrgworldwide.com') !== false;
        $condition2 = $this->http->XPath->query('//node()[contains(normalize-space(.),"HRG Belgium") or contains(.,"@hrgworldwide.com")]')->length > 0;

        if (stripos($headers['from'], 'lufthansa.com') !== false
            || preg_match('/Delay of your flight LH[ ]*\d+/i', $headers['subject']) > 0
            || $this->http->XPath->query('//a[contains(@href,".lufthansa.com/") or contains(@href,"www.lufthansa.com")]')->length > 0
            || $this->http->XPath->query('//node()[contains(normalize-space(),"Best regards, Your Lufthansa Team") or contains(.,"www.lufthansa.com")]')->length > 0
        ) {
            $this->providerCode = 'lufthansa';

            return true;
        }

        if (stripos($headers['from'], 'swiss.com') !== false
            || preg_match('/Delay of your flight LX[ ]*\d+/i', $headers['subject']) > 0
            || $this->http->XPath->query("//img[contains(@src,'swiss.com')]")->length > 0
        ) {
            $this->providerCode = 'swissair';

            return true;
        }

        return false;
    }

    private function assignLang(): bool
    {
        foreach ($this->reBody as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if ($this->http->XPath->query('//node()[contains(normalize-space(),"' . $phrase . '")]')->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function htmlToText($s, $brConvert = true): string
    {
        if (!is_string($s) || $s === '') {
            return '';
        }
        $s = preg_replace('/<!--.*?-->/s', '', $s); // comments

        if ($brConvert) {
            $s = str_replace("\n", '', $s);
            $s = preg_replace('/<[Bb][Rr]\b.*?\/?>/', "\n", $s); // only <br> tags
        }
        $s = preg_replace('/<[A-z][A-z:]*\b.*?\/?>/', '', $s); // opening tags
        $s = preg_replace('/<\/[A-z][A-z:]*\b[ ]*>/', '', $s); // closing tags
        $s = html_entity_decode($s);
        $s = str_replace(chr(194) . chr(160), ' ', $s); // NBSP to SPACE

        return trim($s);
    }

    private function contains($field, $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
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
