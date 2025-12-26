<?php

namespace AwardWallet\Engine\mabuhay\Email;

use AwardWallet\Schema\Parser\Email\Email;

class YourBoardingPass extends \TAccountChecker
{
    public $mailFiles = "mabuhay/it-39099322.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'ROUTE'         => ['ROUTE'],
            'FLIGHT NUMBER' => ['FLIGHT NUMBER'],
            'ARRIVAL'       => ['ARRIVAL'],
        ],
    ];

    private $detectors = [
        'en' => ['BOARDING:'],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@philippineairlines.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['subject'], 'Your Boarding Pass For Your Philippine Airlines') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,"www.philippineairlines.com/")]')->length === 0
            && $this->http->XPath->query('//node()[contains(.,"www.philippineairlines.com")]')->length === 0
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
        $email->setType('YourBoardingPass' . ucfirst($this->lang));

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

        $confirmationNumber = $this->http->FindSingleNode("//text()[{$this->eq($this->t('BOOKING REFERENCE'))}]/preceding::text()[normalize-space()][1]", null, true, '/^[A-Z\d]{5,7}$/');

        if ($confirmationNumber) {
            $confirmationNumberTitle = $this->http->FindSingleNode("//text()[{$this->eq($this->t('BOOKING REFERENCE'))}]");
            $f->general()->confirmation($confirmationNumber, $confirmationNumberTitle);
        }

        $passengers = [];
        $ticketNumbers = [];

        $segments = $this->http->XPath->query("//tr[ *[normalize-space()][1][{$this->eq($this->t('DEPARTURE'))}] and *[normalize-space()][2][{$this->eq($this->t('ARRIVAL'))}] ]");

        foreach ($segments as $segment) {
            $s = $f->addSegment();

            $xpathRoute = "ancestor-or-self::tr[ preceding-sibling::tr[normalize-space()] ][1]/preceding-sibling::tr[normalize-space()][1]/descendant-or-self::tr[ *[normalize-space()][4] ]/*[normalize-space()]";

            /*
             * CEB
             * CEBU MACTAN INTERNATIONAL
             * Terminal 1
             */
            $patterns['airport'] = "/^"
                . "\s*(?<code>[A-Z]{3})[ ]*\n+"
                . "[ ]*(?<name>[\s\S]{3,}?)"
                . "(?:\s+{$this->opt($this->t('Terminal'))}[ ]+(?<terminal>[\s\S]+))?"
                . "$/";

            $departureHtml = $this->http->FindHTMLByXpath($xpathRoute . '[1]', null, $segment);
            $departure = $this->htmlToText($departureHtml);

            if (preg_match($patterns['airport'], $departure, $m)) {
                $s->departure()
                    ->code($m['code'])
                    ->name(preg_replace('/\s+/', ' ', $m['name']))
                    ->terminal(empty($m['terminal']) ? null : preg_replace('/\s+/', ' ', $m['terminal']), false, true);
            }

            $arrivalHtml = $this->http->FindHTMLByXpath($xpathRoute . '[3]', null, $segment);
            $arrival = $this->htmlToText($arrivalHtml);

            if (preg_match($patterns['airport'], $arrival, $m)) {
                $s->arrival()
                    ->code($m['code'])
                    ->name(preg_replace('/\s+/', ' ', $m['name']))
                    ->terminal(empty($m['terminal']) ? null : preg_replace('/\s+/', ' ', $m['terminal']), false, true);
            }

            $flightHtml = $this->http->FindHTMLByXpath($xpathRoute . '[4]', null, $segment);
            $flight = $this->htmlToText($flightHtml);

            if (preg_match("/^\s*(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<number>\d+)(?:\s+{$this->opt($this->t('Operated by'))}[ ]+(?<operator>[\s\S]+))?\s*(?:\n*Codeshare\:\s*.*)?$/", $flight, $m)) {
                $s->airline()
                    ->name($m['name'])
                    ->number($m['number'])
                    ->operator(empty($m['operator']) ? null : preg_replace('/\s+/', ' ', $m['operator']), false, true);
            }

            $xpathDates = "ancestor-or-self::tr[ following-sibling::tr[normalize-space()] ][1]/following-sibling::tr[normalize-space()][1]/descendant-or-self::tr[ *[normalize-space()][2] ]/*[normalize-space()]";

            /*
             * 0700H
             * 18 Apr 2019
             * Thursday
             */
            $patterns['timeDate'] = "/^"
                . "\s*(?<time>\d{4}[ ]*H)[ ]*\n+"
                . "[ ]*(?<date>.{6,})"
                . "\s*.*"
                . "$/i";

            $depDateHtml = $this->http->FindHTMLByXpath($xpathDates . '[1]', null, $segment);
            $depDate = $this->htmlToText($depDateHtml);

            if (preg_match($patterns['timeDate'], $depDate, $m)) {
                $s->departure()->date2($m['date'] . ' ' . $this->normalizeTime($m['time']));
            }

            $arrDateHtml = $this->http->FindHTMLByXpath($xpathDates . '[2]', null, $segment);
            $arrDate = $this->htmlToText($arrDateHtml);

            if (preg_match($patterns['timeDate'], $arrDate, $m)) {
                $s->arrival()->date2($m['date'] . ' ' . $this->normalizeTime($m['time']));
            }

            $xpathPassenger = "ancestor-or-self::tr[ following-sibling::tr[{$this->starts($this->t('PASSENGER NAME'))}] ][1]/following-sibling::tr[normalize-space()][position()<5][{$this->starts($this->t('PASSENGER NAME'))}][1]";

            $passenger = $this->http->FindSingleNode($xpathPassenger . "/following-sibling::tr[normalize-space()][1]/descendant-or-self::tr[count(*)=4]/*[1]", $segment, null, '/^(?:\d{1,3}\.\s*)?([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])$/u');

            if ($passenger) {
                $passengers[] = $passenger;
            }

            $seat = $this->http->FindSingleNode($xpathPassenger . "/following-sibling::tr[normalize-space()][1]/descendant-or-self::tr[count(*)=4]/*[2]", $segment, null, '/^\d{1,5}[A-Z]$/');

            if ($seat) {
                $s->extra()->seat($seat);
            }

            $ticketNumber = $this->http->FindSingleNode($xpathPassenger . "/following-sibling::tr[normalize-space()][1]/descendant-or-self::tr[count(*)=4]/*[3]", $segment, null, '/^\d{3}[- ]*\d{5,}[- ]*\d{1,2}$/');

            if ($ticketNumber) {
                $ticketNumbers[] = $ticketNumber;
            }

            $cabin = $this->http->FindSingleNode($xpathPassenger . "/following-sibling::tr[normalize-space()][1]/descendant-or-self::tr[count(*)=4]/*[4]", $segment, null, '/^[A-Z][A-Z\s]*$/');

            if ($cabin) {
                $s->extra()->cabin($cabin);
            }

            // Boarding Pass
            $barcode = $this->http->FindSingleNode($xpathPassenger . "/following-sibling::tr[normalize-space()][1]/following-sibling::tr[position()<5][1]/descendant::img/@src", $segment);

            if ($barcode) {
                $bp = $email->createBoardingPass();
                $bp->setUrl(str_replace(' ', '%20', $barcode));
                $bp->setDepCode($s->getDepCode());
                $bp->setFlightNumber($s->getFlightNumber());
                $bp->setDepDate($s->getDepDate());

                if (!empty($f->getConfirmationNumbers()[0])) {
                    $bp->setRecordLocator($f->getConfirmationNumbers()[0][0]);
                }

                if (!empty($passenger)) {
                    $bp->setTraveller($passenger);
                }
            }
        }

        if (count($passengers)) {
            $f->general()->travellers(array_unique($passengers));
        }

        if (count($ticketNumbers)) {
            $f->setTicketNumbers(array_unique($ticketNumbers), false);
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
            if (!is_string($lang) || empty($phrases['ROUTE']) || empty($phrases['FLIGHT NUMBER']) || empty($phrases['ARRIVAL'])) {
                continue;
            }

            if ($this->http->XPath->query("//node()[{$this->contains($phrases['ROUTE'])}]")->length > 0
                && $this->http->XPath->query("//node()[{$this->contains($phrases['FLIGHT NUMBER'])}]")->length > 0
                && $this->http->XPath->query("//node()[{$this->contains($phrases['ARRIVAL'])}]")->length > 0
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

    private function normalizeTime(string $s): string
    {
        $s = preg_replace('/^(\d{2})(\d{2})[ ]*H$/i', '$1:$2', $s); // 0700H    ->    07:00

        return $s;
    }
}
