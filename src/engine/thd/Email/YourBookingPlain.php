<?php

namespace AwardWallet\Engine\thd\Email;

use AwardWallet\Schema\Parser\Email\Email;

class YourBookingPlain extends \TAccountChecker
{
    public $mailFiles = "thd/it-30747097.eml, thd/it-30747096.eml";
    private $subjects = [
        'en' => ['Your Credit Card Has Declined', 'Unable to Confirm Your Reservation'],
    ];
    private $langDetectors = [
        'en' => [')Flt:', ') Flt:'],
    ];
    private $lang = '';
    private static $dict = [
        'en' => [
            'BOOKING NUMBER:' => ['BOOKING NUMBER:', 'BOOKING NUMBER :'],
        ],
    ];

    // Standard Methods

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@travelerhelpdesk.') !== false;
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
        $condition1 = $this->http->XPath->query('//node()[contains(normalize-space(.),"Your Traveler Help Desk Team") or contains(.,"@travelerhelpdesk.")]')->length === 0;
        $condition2 = self::detectEmailFromProvider($parser->getHeader('from')) !== true;

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

        if (empty($textBody = $parser->getPlainBody())) {
            $textBody = $this->htmlToText($parser->getHTMLBody());
        }
        $textBody = str_replace(['&nbsp;', chr(194) . chr(160), '&#160;'], ' ', $textBody);
        $this->http->SetEmailBody($textBody);

        $this->parseEmailPlain($email, $textBody);
        $email->setType('YourBookingPlain' . ucfirst($this->lang));

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

    private function parseEmailPlain(Email $email, $text)
    {
        $patterns = [
            'time' => '\d{1,2}:\d{2}(?::\d{2})?(?:\s*[AaPp]\.?[Mm]\.?)?', // 4:19PM    |    2:00 p.m.    |    4:25:00 PM
        ];

        $f = $email->add()->flight();

        // confirmation number
        if (preg_match("/^[> ]*({$this->opt($this->t('BOOKING NUMBER:'))})\s*([A-Z\d]{5,})\s*$/m", $text, $m)) {
            $f->general()->confirmation($m[2], preg_replace('/\s*:\s*$/', '', $m[1]));
        }

        // LATAM (LA)(LA)Flt: 8027
        // Dep: SANTIAGO, , (SCL)  5/19/2019 11:25:00 AM 11:25
        // Arr:  SAO PAULO, SP, (GRU)  5/19/2019 4:25:00 PM 16:25
        $patterns['segment'] = "/"
            . "^[> ]*.+\([ ]*(?<airline>[A-Z][A-Z\d]|[A-Z\d][A-Z])[ ]*\)[ ]*{$this->opt($this->t('Flt:'))}[ ]*(?<flightNumber>\d+).*$"
            . "[>\s]+^[> ]*{$this->opt($this->t('Dep:'))}.+\([ ]*(?<airportDep>[A-Z]{3})[ ]*\)[ ]*(?<dateTimeDep>.{6,}?[ ]+{$patterns['time']}).*$"
            . "[>\s]+^[> ]*{$this->opt($this->t('Arr:'))}.+\([ ]*(?<airportArr>[A-Z]{3})[ ]*\)[ ]*(?<dateTimeArr>.{6,}?[ ]+{$patterns['time']}).*$"
            . "/m";

        // segments
        $segments = $this->splitText($text, "/^(.+\)[ ]*{$this->opt($this->t('Flt:'))}[ ]*\d+.*)$/m", true);

        foreach ($segments as $segment) {
            $s = $f->addSegment();

            if (preg_match($patterns['segment'], $segment, $m)) {
                $s->airline()
                    ->name($m['airline'])
                    ->number($m['flightNumber'])
                ;

                $s->departure()
                    ->code($m['airportDep'])
                    ->date2($m['dateTimeDep'])
                ;

                $s->arrival()
                    ->code($m['airportArr'])
                    ->date2($m['dateTimeArr'])
                ;
            }
        }
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) { return preg_quote($s, '/'); }, $field)) . ')';
    }

    private function splitText($textSource = '', string $pattern, $saveDelimiter = false): array
    {
        $result = [];

        if ($saveDelimiter) {
            $textFragments = preg_split($pattern, $textSource, null, PREG_SPLIT_DELIM_CAPTURE);
            array_shift($textFragments);

            for ($i = 0; $i < count($textFragments) - 1; $i += 2) {
                $result[] = $textFragments[$i] . $textFragments[$i + 1];
            }
        } else {
            $result = preg_split($pattern, $textSource);
            array_shift($result);
        }

        return $result;
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

    private function t($phrase)
    {
        if (!isset(self::$dict[$this->lang]) || !isset(self::$dict[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dict[$this->lang][$phrase];
    }

    private function assignLang($text = ''): bool
    {
        foreach ($this->langDetectors as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if (empty($text) && $this->http->XPath->query('//node()[contains(normalize-space(.),"' . $phrase . '")]')->length > 0) {
                    $this->lang = $lang;

                    return true;
                } elseif (!empty($text) && strpos($text, $phrase) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }
}
