<?php

namespace AwardWallet\Engine\singaporeair\Email;

use AwardWallet\Schema\Parser\Email\Email;

class TicketingPlain extends \TAccountChecker
{
    public $mailFiles = "singaporeair/it-2143571.eml, singaporeair/it-29824565.eml";
    private $subjects = [
        'en' => ['Ticketing Time Limit', 'Booking ref:'],
    ];
    private $langDetectors = [
        'en' => ['Booking ref:', 'Flight No.'],
    ];
    private $lang = '';
    private static $dict = [
        'en' => [
            'Booking ref'    => ['Booking ref', 'Booking Reference Number'],
            'Departing Date' => ['Departing Date', 'Date'],
            'Departing Time' => ['Departing Time', 'Departure'],
        ],
    ];

    // Standard Methods

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@singaporeair.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ($phrases as $phrase) {
                if (stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $condition1 = $this->http->XPath->query('//node()[contains(normalize-space(.),"your local Singapore Airlines") or contains(normalize-space(.),"Thank you for choosing Singapore Airlines") or contains(normalize-space(.),"Yours sincerely, Singapore Airlines") or contains(normalize-space(.),"updates with the SingaporeAir") or contains(.,"www.singaporeair.com")]')->length === 0;
        $condition2 = $this->http->XPath->query('//a[contains(@href,"//www.singaporeair.com") or contains(@href,"//singaporeair.com")]')->length === 0;

        if ($condition1 && $condition2) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->notice("Can't determine a language!");

            return $email;
        }
        $textBody = $parser->getPlainBody();
        $this->parseEmail($email, $textBody);
        $email->setType('TicketingPlain' . ucfirst($this->lang));

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

    private function parseEmail(Email $email, $textBody)
    {
        $f = $email->add()->flight();

        if (empty($textBody)) {
            $roots = $this->http->XPath->query('//body');

            if ($roots->length === 0) {
                $this->logger->alert('Element <body> not found!');

                return false;
            }

            $root = $roots->item(0);
            $htmlBody = $root->ownerDocument->saveHTML($root);
            $textBody = $this->htmlToText($htmlBody);
        } elseif (preg_match('/<[A-z]+\b[ ]*\/?>/', $textBody)) {
            $textBody = $this->htmlToText($textBody, false);
        }

        // confirmation number
        if (preg_match("/({$this->opt($this->t('Booking ref'))})[ ]*:+[>\s]*([A-Z\d]{5,7})[ ]*$/m", $textBody, $m)) {
            $f->general()->confirmation($m[2], $m[1]);
        }

        // accountNumbers
        if (preg_match("/{$this->opt($this->t('KrisFlyer Number'))}[ ]*:+[>\s]*([-A-Z\d]{7,})[ ]*$/m", $textBody, $m)) {
            $f->addAccountNumber($m[1], false);
        }

        // travellers
        $passengersText = preg_match("/{$this->opt($this->t('Passenger Name(s)'))}[ ]*:+[ ]*(.+?)\n[> ]*\n/s", $textBody, $m) ? $m[1] : '';
        $passengerRows = preg_split('/\n+[> ]*/', $passengersText);

        foreach ($passengerRows as $passengerRow) {
            if (preg_match("/^[[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]]$/u", $passengerRow)) {
                $f->addTraveller($passengerRow);
            } else {
                break;
            }
        }

        // segments
        $patterns['flight'] = "/"
            . "^[> ]*{$this->opt($this->t('Flight No.'))}[ ]*:+[ ]*(?<airline>[A-Z][A-Z\d]|[A-Z\d][A-Z])[ ]*(?<flightNumber>\d+)[ ]*$" // Flight No.: SQ221
            . "(?:\s+^[> ]*{$this->opt($this->t('Aircraft'))}[ ]*:+[ ]*(?<aircraft>.+)[ ]*$)?" // Aircraft: B777-300
            . "\s+^[> ]*{$this->opt($this->t('From'))}[ ]*:+[ ]*(?<airportDep>.{3,})[ ]*$" // From: SIN (Changi Intl)    |    Bangkok (BKK - Suvarnabhumi Airport)
            . "\s+^[> ]*{$this->opt($this->t('To'))}[ ]*:+[ ]*(?<airportArr>.{3,})[ ]*$" // To: BOM (C Shivaji Intl)    |    New York (JFK - John F Kennedy Intl Terminal 4)
            . "\s+^[> ]*{$this->opt($this->t('Departing Date'))}[ ]*:+[ ]*(?<dateDep>.{6,})[ ]*$" // Departing Date: 29 Jan 2019
            . "\s+^[> ]*{$this->opt($this->t('Departing Time'))}[ ]*:+[ ]*(?<timeDep>\d{1,2}(?::\d{2})?(?:\s*[AaPp]\.?[Mm]\.?)?)[ ]*$" // Departing Time: 18:55
            . "/m";
        preg_match_all($patterns['flight'], $textBody, $flightMatches0, PREG_SET_ORDER);
        preg_match_all("/^[> ]*{$this->opt($this->t('Flight No.'))}[ ]*:.*\d.*$/m", $textBody, $flightMatches1, PREG_SET_ORDER);

        if (count($flightMatches0) !== count($flightMatches1)) {
            $this->logger->alert('Error segments count!');

            return false;
        }

        foreach ($flightMatches0 as $matches) {
            $s = $f->addSegment();

            // airlineName
            // flightNumber
            $s->airline()
                ->name($matches['airline'])
                ->number($matches['flightNumber'])
            ;

            // aircraft
            $s->extra()->aircraft($matches['aircraft'], true, false);

            // New York (JFK - John F Kennedy Intl Terminal 4)
            $patterns['codeNameTerminal1'] = '/\([ ]*(?<code>[A-Z]{3})[ ]*-[ ]*(?<name>[^)]+?)(?:[ ]*Terminal[ ]+(?<terminal>[^)]+?))?[ ]*\)/';
            // SIN (Changi Intl Terminal 4)
            $patterns['codeNameTerminal2'] = '/^(?<code>[A-Z]{3})[ ]*\([ ]*(?<name>[^)]+?)(?:[ ]*Terminal[ ]+(?<terminal>[^)]+?))?[ ]*\)/';

            // depCode
            // depName
            // depTerminal
            if (
                preg_match($patterns['codeNameTerminal1'], $matches['airportDep'], $m)
                || preg_match($patterns['codeNameTerminal2'], $matches['airportDep'], $m)
            ) {
                $s->departure()
                    ->code($m['code'])
                    ->name($m['name'])
                    ->terminal($m['terminal'] ?? null, false, true)
                ;
            }

            // arrCode
            // arrName
            // arrTerminal
            if (
                preg_match($patterns['codeNameTerminal1'], $matches['airportArr'], $m)
                || preg_match($patterns['codeNameTerminal2'], $matches['airportArr'], $m)
            ) {
                $s->arrival()
                    ->code($m['code'])
                    ->name($m['name'])
                    ->terminal($m['terminal'] ?? null, false, true)
                ;
            }

            // depDate
            $s->departure()->date2($matches['dateDep'] . ' ' . $matches['timeDep']);

            // arrDate
            $s->arrival()->noDate();
        }

        // cancellation
        $cancellation = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Your booking will be cancelled if'))}]");
        $f->general()->cancellation($cancellation, false, true);
    }

    private function htmlToText($s = '', $brConvert = true): string
    {
        if ($brConvert) {
            $s = str_replace("\n", '', $s);
            $s = preg_replace('/<[Bb][Rr]\b[ ]*\/?>/', "\n", $s); // only <br> tags
        }
        $s = preg_replace('/<[A-z]+\b.*?\/?>/', '', $s); // opening tags
        $s = preg_replace('/<\/[A-z]+\b[ ]*>/', '', $s); // closing tags
        $s = html_entity_decode($s);
        $s = str_replace(chr(194) . chr(160), ' ', $s); // NBSP to SPACE

        return trim($s);
    }

    private function starts($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'starts-with(normalize-space(' . $node . "),'" . $s . "')"; }, $field)) . ')';
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
