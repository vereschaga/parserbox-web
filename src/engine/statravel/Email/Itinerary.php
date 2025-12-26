<?php

namespace AwardWallet\Engine\statravel\Email;

use AwardWallet\Schema\Parser\Email\Email;

class Itinerary extends \TAccountChecker
{
    public $mailFiles = "statravel/it-40448572.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'Flights'        => ['Flights'],
            'Depart'         => ['Depart'],
            'Arrive'         => ['Arrive'],
            'statusVariants' => ['Confirmed'],
        ],
    ];

    private $detectors = [
        'en' => ["I've booked a trip", 'Bookings for flight', 'Start your own adventure'],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@statravel.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['subject'], 'Check out my Itinerary from STA Travel') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,"www.statravel.com")]')->length === 0
            && $this->http->XPath->query('//node()[contains(normalize-space(),"STA Travel Ltd")]')->length === 0
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

        $this->parseFlight($email);
        $email->setType('Itinerary' . ucfirst($this->lang));

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

    private function parseFlight(Email $email)
    {
        $xpathNoEmpty = 'string-length(normalize-space())>1';

        $email->ota(); // because STA Travel is travel agency

        $f = $email->add()->flight();

        $f->general()->noConfirmation();

        $traveller = $this->http->FindSingleNode("//tr[not(.//tr) and {$this->contains($this->t('Bookings for flight'))}]", null, true, "/{$this->opt($this->t('Bookings for flight'))}\s*([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])$/u");

        if ($traveller) {
            $f->general()->traveller($traveller);
        }

        $segments = $this->http->XPath->query("//tr[ count(*[$xpathNoEmpty])=2 and *[$xpathNoEmpty][1][{$this->contains($this->t('Depart'))}] and *[$xpathNoEmpty][2][{$this->contains($this->t('Arrive'))}] ]");

        foreach ($segments as $segment) {
            $s = $f->addSegment();

            $xpathRoute = "ancestor::tr[ preceding-sibling::tr[$xpathNoEmpty] ][1]/preceding-sibling::tr[$xpathNoEmpty][1]/descendant::td[{$this->eq($this->t('to'))}]";

            $flightHtml = $this->http->FindHTMLByXpath("ancestor::td[ following-sibling::td[$xpathNoEmpty] ][1]/following-sibling::td[$xpathNoEmpty]", null, $segment);

            if (preg_match("/^\s*(?<status>.+?)[ ]*\n+[ ]*{$this->opt($this->t('Flight'))}[ ]+(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])[ ]*(?<number>\d+)\s*$/", $this->htmlToText($flightHtml), $m)) {
                if (preg_match("/^{$this->opt($this->t('statusVariants'))}$/", $m['status'])) {
                    $s->extra()->status($m['status']);
                }
                $s->airline()
                    ->name($m['name'])
                    ->number($m['number']);
            }

            /*
                Tokyo Narita Apt (NRT)
                Terminal 1
             */
            $patterns['airport'] = "/^\s*"
                . "(?<name>.+?)[ ]+\((?<code>[A-Z]{3})\)[ ]*"
                . "(?:\n+[ ]*{$this->opt($this->t('Terminal'))}[ ]+(?<terminal>.+?))?"
                . "\s*$/";

            $airportDepHtml = $this->http->FindHTMLByXpath($xpathRoute . "/preceding-sibling::td[$xpathNoEmpty][1]", null, $segment);
            // $this->logger->error($s->getFlightNumber()." -> " . $this->htmlToText($airportDepHtml));
            if (preg_match($patterns['airport'], $this->htmlToText($airportDepHtml), $m)) {
                $s->departure()
                    ->name($m['name'])
                    ->code($m['code'])
                    ->terminal($m['terminal'], false, true);
            }

            $airportArrHtml = $this->http->FindHTMLByXpath($xpathRoute . "/following-sibling::td[$xpathNoEmpty]", null, $segment);

            if (preg_match($patterns['airport'], $this->htmlToText($airportArrHtml), $m)) {
                $s->arrival()
                    ->name($m['name'])
                    ->code($m['code'])
                    ->terminal($m['terminal'], false, true);
            }

            $dateDepHtml = $this->http->FindHTMLByXpath("*[$xpathNoEmpty][1]", null, $segment);
            $dateDep = preg_match("/{$this->opt($this->t('Depart'))}\s*([\s\S]{6,})/", $this->htmlToText($dateDepHtml), $m) ? preg_replace('/\s+/', ' ', $m[1]) : null;
            $s->departure()->date2($dateDep);

            $dateArrHtml = $this->http->FindHTMLByXpath("*[$xpathNoEmpty][2]", null, $segment);
            $dateArr = preg_match("/{$this->opt($this->t('Arrive'))}\s*([\s\S]{6,})/", $this->htmlToText($dateArrHtml), $m) ? preg_replace('/\s+/', ' ', $m[1]) : null;
            $s->arrival()->date2($dateArr);
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

                if ($this->http->XPath->query("//node()[{$this->contains($phrase)}]")->length > 0) {
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
            if (!is_string($lang) || empty($phrases['Flights']) || empty($phrases['Depart']) || empty($phrases['Arrive'])) {
                continue;
            }

            if ($this->http->XPath->query("//node()[{$this->contains($phrases['Flights'])}]")->length > 0
                && $this->http->XPath->query("//node()[{$this->contains($phrases['Depart'])}]")->length > 0
                && $this->http->XPath->query("//node()[{$this->contains($phrases['Arrive'])}]")->length > 0
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

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ')="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'starts-with(normalize-space(' . $node . '),"' . $s . '")';
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
        $s = preg_replace('/<[A-z]+\b.*?\/?>/', '', $s); // opening tags
        $s = preg_replace('/<\/[A-z]+\b[ ]*>/', '', $s); // closing tags
        $s = html_entity_decode($s);
        $s = str_replace(chr(194) . chr(160), ' ', $s); // NBSP to SPACE

        return trim($s);
    }
}
