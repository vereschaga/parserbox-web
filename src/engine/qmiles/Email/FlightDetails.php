<?php

namespace AwardWallet\Engine\qmiles\Email;

use AwardWallet\Schema\Parser\Email\Email;

class FlightDetails extends \TAccountChecker
{
    public $mailFiles = "qmiles/it-50431632.eml, qmiles/it-50701679.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'Departure'    => ['Departure'],
            'Arrival'      => ['Arrival'],
            'selectButton' => ['Change', 'Select'],
        ],
    ];

    private $subjects = [
        'en' => ['Are you ready for your flight to'],
    ];

    private $detectors = [
        'en' => ['Plan for your upcoming trip to', 'quick summary of your flight details'],
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[-.@]qatarairways\.com/i', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
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
        if ($this->http->XPath->query('//a[contains(@href,".qatarairways.com/") or contains(@href,"qr.qatarairways.com")]')->length === 0
            && $this->http->XPath->query('//node()[contains(normalize-space(),"emails from Qatar Airways") or contains(.,"@gotogate.com")]')->length === 0
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
        $email->setType('FlightDetails' . ucfirst($this->lang));

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
        $f = $email->add()->flight();

        $f->general()->noConfirmation();

        $segments = $this->http->XPath->query("//tr[ *[normalize-space()][1][{$this->starts($this->t('Departure'))}] and *[normalize-space()][3][{$this->starts($this->t('Arrival'))}] ]");

        foreach ($segments as $segment) {
            $s = $f->addSegment();

            $flightHtml = $this->http->FindHTMLByXpath('preceding-sibling::tr[normalize-space()][1]', null, $segment);
            $flightText = $this->htmlToText($flightHtml);

            if (preg_match('/^(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<number>\d+)[ ]*(?:\n+(?<class>.{2,})|$)/', $flightText, $m)) {
                /*
                    QR905
                    Economy Class
                */
                $s->airline()
                    ->name($m['name'])
                    ->number($m['number']);

                if (!empty($m['class'])) {
                    $s->extra()->cabin($m['class']);
                }
            }

            $departureHtml = $this->http->FindHTMLByXpath('*[normalize-space()][position()=1]', null, $segment);
            $departureText = $this->htmlToText($departureHtml);

            $extraText = implode("\n", $this->http->FindNodes('*[normalize-space()][position()>1 and not(position()=last())]/descendant::tr[not(.//tr) and normalize-space()]', $segment));

            $arrivalHtml = $this->http->FindHTMLByXpath('*[normalize-space()][position()>1][last()]', null, $segment);
            $arrivalText = $this->htmlToText($arrivalHtml);

            $nextRows = $this->http->XPath->query('following-sibling::tr[normalize-space()]', $segment);

            foreach ($nextRows as $row) {
                $depHtml = $this->http->FindHTMLByXpath('*[normalize-space()][position()=1]', null, $row);
                $departureText .= "\n" . $this->htmlToText($depHtml);

                $extraText .= "\n" . implode("\n", $this->http->FindNodes('*[normalize-space()][position()>1 and not(position()=last())]/descendant::tr[not(.//tr) and normalize-space()]', $row));

                $arrHtml = $this->http->FindHTMLByXpath('*[normalize-space()][position()>1][last()]', null, $row);
                $arrivalText .= "\n" . $this->htmlToText($arrHtml);
            }

            /*
                Departure
                MEL
                Melbourne
                22:20
                Wednesday
                20-Nov-2019
            */
            $pattern = "/"
                . "(?:{$this->opt($this->t('Departure'))}|{$this->opt($this->t('Arrival'))})[ ]*\n+"
                . "[ ]*(?<code>[A-Z]{3})[ ]*\n+"
                . "[ ]*(?<city>.{3,})[ ]*\n+"
                . "[ ]*(?<time>\d{1,2}(?:[:]\d{2})?(?:\s*[AaPp]\.?[Mm]\.?)?)[ ]*\n+"
                . "[ ]*[[:alpha:]]{2,}[ ]*\n+"
                . "[ ]*(?<date>.{6,})/u";

            if (preg_match($pattern, $departureText, $m)) {
                $s->departure()
                    ->code($m['code'])
                    ->name($m['city'])
                    ->date2($m['date'] . ' ' . $m['time']);
            }

            if (preg_match("/^\s*(\d+[A-Z])[ ]*\n+[ ]*{$this->opt($this->t('selectButton'))}/", $extraText, $m)) {
                /*
                    24D
                    Change
                */
                $s->extra()->seat($m[1]);
            }

            if (preg_match("/{$this->opt($this->t('selectButton'))}[> ]*\n+[ ]*(.{2,}?)[ ]*\n+[ ]*{$this->opt($this->t('selectButton'))}[>\s]*$/", $extraText, $m)) {
                /*
                    Vegetarian Meal - Vegan
                    Select >>
                */
                $s->extra()->meal($m[1]);
            }

            if (preg_match($pattern, $arrivalText, $m)) {
                $s->arrival()
                    ->code($m['code'])
                    ->name($m['city'])
                    ->date2($m['date'] . ' ' . $m['time']);
            }
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
            if (!is_string($lang) || empty($phrases['Departure']) || empty($phrases['Arrival'])) {
                continue;
            }

            if ($this->http->XPath->query("//node()[{$this->contains($phrases['Departure'])}]")->length > 0
                && $this->http->XPath->query("//node()[{$this->contains($phrases['Arrival'])}]")->length > 0
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
