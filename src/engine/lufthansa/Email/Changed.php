<?php

namespace AwardWallet\Engine\lufthansa\Email;

use AwardWallet\Schema\Parser\Email\Email;

class Changed extends \TAccountChecker
{
    public $mailFiles = "lufthansa/it-142331485.eml, lufthansa/it-142851102.eml, lufthansa/it-309427335.eml";

    private $detectFrom = ["flightupdate@your.lufthansa-group.com", 'flight.service@information.lufthansa.com'];
    private $detectSubject = [
        // en
        'New gate', // New gate Z13 for your flight LH980 on 28.12.2021 from Frankfurt/Main to Dublin
        'Update on your flight', // Update on your flight LH445 on 29.12.2021 from Atlanta to Frankfurt/Main. New departure time 18:00 hours
        'Important information for your flight',
        'is delayed. New departure time:',
        'Baggage tracing on your flight from',
        'Departure gate ', // + other lang
        // de
        'Neues Gate',
        'Verspätung Ihres Fluges',
        'Abflug-Gate',
    ];
    private $detectBody = [
        'en' => [
            'is further delayed.',
            'The departure gate for your flight',
            'has been further delayed',
            ' hours has been delayed.',
            'Please inform yourself about the current entry regulations for your destination.',
            'Information about your departure gate',
        ],
        'de' => [
            'Das Abfluggate für Ihren Flug',
            'Uhr ist verspätet.',
            'ist weiterhin verspätet',
            'Informationen zu Ihrem Abflug-Gate',
        ],
        'it' => [
            'Informazioni sul suo di gate di partenza',
        ],
        'fr' => [
            'Informations relatives à votre porte d’embarquement',
        ],
        'es' => [
            'Información sobre su puerta de embarque',
        ],
    ];

    private $subject;

    private $lang;
    private static $dictionary = [
        'en' => [
            'nameTitle' => ['Mr', 'Mrs', 'Miss', 'Mstr', 'Ms', 'Dr', 'Г-н'],

            // New Gate
            //            'The departure gate for your flight' => '',
            '/regexpNewGate/' => "/flight (?<al>[A-Z\d][A-Z]|[A-Z][A-Z\d])(?<fn>\d{1,5}) on (?<date>[\d\.]+) at (?<time>\d{1,2}:\d{2}) from (?<dep>.+?) to (?<arr>.+?) has changed/",

            // Delayed
            'hours has been delayed'        => ['hours has been delayed', 'is further delayed.', 'has been further delayed'],
            'Your new departure time is on' => ['Your new departure time is', 'Your new departure time is on'],
            '/regexpDelayed/'               => "/flight (?<al>[A-Z\d][A-Z]|[A-Z][A-Z\d])(?<fn>\d{1,5}) on (?:[\d\.]+) from (?<dep>.+?)(?:\s*\((?<dCode>[A-Z]{3})\))? to (?<arr>.+?)(?:\s*\((?<aCode>[A-Z]{3})\))? (?:at (?:\d{1,2}:\d{2}) hours has been delayed|has been further delayed|is further delayed)/",

            // Departure gate
            // Your Lufthansa flight LH2558 from Munich to Tbilisi on 14.3.2023 at 21:55 will leave from departure gate: H44.
            //            'Dear ' => '',
            "will leave from departure gate:" => 'will leave from departure gate:',
            '/regexpDepartureGate/'           => "/flight (?<al>[A-Z\d][A-Z]|[A-Z][A-Z\d])(?<fn>\d{1,5}) from (?<dep>.+?) to (?<arr>.+?) on (?<date>[\d\.]+)(?: at (?<time>\d{1,2}:\d{2})) will leave/",

            // Formats from subject
            '/regexpImportantInformation/' => '/flight(?: Lufthansa)? (?<al>[A-Z\d][A-Z]|[A-Z][A-Z\d])(?<fn>\d{1,5}) (?<dep>.+?)\s*\((?<dCode>[A-Z]{3})\) - (?<arr>.+?)\s*\((?<aCode>[A-Z]{3})\), (?<date>[\d\.]+), (?<time>\d{1,2}:\d{2})/',
            '/regexpNewGateSubject/'       => "/New gate .* flight (?<al>[A-Z\d][A-Z]|[A-Z][A-Z\d])(?<fn>\d{1,5}) (?<dep>.+?) - (?<arr>.+?), (?<date>[\d\.]+), (?<time>\d{1,2}:\d{2})/",
        ],
        'de' => [
            'nameTitle' => ['Herr', 'Frau'],

            // New Gate
            'The departure gate for your flight' => 'Das Abfluggate für Ihren Flug',
            '/regexpNewGate/'                    => "/Flug (?<al>[A-Z\d][A-Z]|[A-Z][A-Z\d])(?<fn>\d{1,5}) am (?<date>[\d\.]+), (?<time>\d{1,2}:\d{2}) von (?<dep>.+?) nach (?<arr>.+?) hat sich geändert/",

            // Delayed
            'hours has been delayed'        => ['Uhr ist verspätet', 'ist weiterhin verspätet'],
            'Your new departure time is on' => 'Ihre neue Abflugzeit ist am',
            '/regexpDelayed/'               => "/Flug (?<al>[A-Z\d][A-Z]|[A-Z][A-Z\d])(?<fn>\d{1,5}) am (?:[\d\.]+) von (?<dep>.+?)\s*\((?<dCode>[A-Z]{3})\) nach (?<arr>.+?)\s*\((?<aCode>[A-Z]{3})\) (?:um (?:\d{1,2}:\d{2}) Uhr ist verspätet|ist weiterhin verspätet)/u",

            // Departure gate
            // Ihr Lufthansa Flug LH886 Flug von Frankfurt/Main nach Vilnius am 07.3.2023 um 11:25 startet von Abflug-Gate: A22.
            'Dear '                           => 'Guten Tag',
            "will leave from departure gate:" => 'startet von Abflug-Gate:',
            '/regexpDepartureGate/'           => "/Flug(?: Lufthansa)? (?<al>[A-Z\d][A-Z]|[A-Z][A-Z\d])(?<fn>\d{1,5}) Flug von (?<dep>.+?) nach (?<arr>.+?) am (?<date>[\d\.]+)(?: um (?<time>\d{1,2}:\d{2}))? startet/",

            // Formats from subject
            //            '/regexpImportantInformation/' => '/flight (?<al>[A-Z\d][A-Z]|[A-Z][A-Z\d])(?<fn>\d{1,5}) (?<dep>.+?)\s*\((?<dCode>[A-Z]{3})\) - (?<arr>.+?)\s*\((?<aCode>[A-Z]{3})\), (?<date>[\d\.]+), (?<time>\d{1,2}:\d{2})/',
            //            '/regexpNewGateSubject/' => "",
        ],
        'it' => [
            'nameTitle' => ['Signor', 'Signora'],

            // New Gate
            //            'The departure gate for your flight' => 'Das Abfluggate für Ihren Flug',
            //            '/regexpNewGate/' => "/Flug (?<al>[A-Z\d][A-Z]|[A-Z][A-Z\d])(?<fn>\d{1,5}) am (?<date>[\d\.]+), (?<time>\d{1,2}:\d{2}) von (?<dep>.+?) nach (?<arr>.+?) hat sich geändert/",

            // Delayed
            //            'hours has been delayed' => ['Uhr ist verspätet', 'ist weiterhin verspätet'],
            //            'Your new departure time is on' => 'Ihre neue Abflugzeit ist am',
            //            '/regexpDelayed/' => "/Flug (?<al>[A-Z\d][A-Z]|[A-Z][A-Z\d])(?<fn>\d{1,5}) am (?:[\d\.]+) von (?<dep>.+?)\s*\((?<dCode>[A-Z]{3})\) nach (?<arr>.+?)\s*\((?<aCode>[A-Z]{3})\) (?:um (?:\d{1,2}:\d{2}) Uhr ist verspätet|ist weiterhin verspätet)/u",

            // Departure gate
            // Il suo volo EN8244 da Monaco di Baviera per Bologna del 14.3.2023 alle 22:00 partirà dal gate: K24
            'Dear '                           => 'Buongiorno ',
            "will leave from departure gate:" => 'partirà dal gate:',
            '/regexpDepartureGate/'           => "/volo (?:Lufthansa )?(?<al>[A-Z\d][A-Z]|[A-Z][A-Z\d])(?<fn>\d{1,5}) da (?<dep>.+?) di (?<arr>.+?) del (?<date>[\d\.]+)(?: alle (?<time>\d{1,2}:\d{2}))? partirà dal/",

            // Formats from subject
            //            '/regexpImportantInformation/' => '/flight (?<al>[A-Z\d][A-Z]|[A-Z][A-Z\d])(?<fn>\d{1,5}) (?<dep>.+?)\s*\((?<dCode>[A-Z]{3})\) - (?<arr>.+?)\s*\((?<aCode>[A-Z]{3})\), (?<date>[\d\.]+), (?<time>\d{1,2}:\d{2})/',
            //            '/regexpNewGateSubject/' => "",
        ],
        'fr' => [
            'nameTitle' => ['Monsieur', 'Madame'],

            // New Gate
            //            'The departure gate for your flight' => 'Das Abfluggate für Ihren Flug',
            //            '/regexpNewGate/' => "/Flug (?<al>[A-Z\d][A-Z]|[A-Z][A-Z\d])(?<fn>\d{1,5}) am (?<date>[\d\.]+), (?<time>\d{1,2}:\d{2}) von (?<dep>.+?) nach (?<arr>.+?) hat sich geändert/",

            // Delayed
            //            'hours has been delayed' => ['Uhr ist verspätet', 'ist weiterhin verspätet'],
            //            'Your new departure time is on' => 'Ihre neue Abflugzeit ist am',
            //            '/regexpDelayed/' => "/Flug (?<al>[A-Z\d][A-Z]|[A-Z][A-Z\d])(?<fn>\d{1,5}) am (?:[\d\.]+) von (?<dep>.+?)\s*\((?<dCode>[A-Z]{3})\) nach (?<arr>.+?)\s*\((?<aCode>[A-Z]{3})\) (?:um (?:\d{1,2}:\d{2}) Uhr ist verspätet|ist weiterhin verspätet)/u",

            // Departure gate
            // Votre vol Lufthansa LH1127 de Barcelone à Francfort le 13.3.2023 à 13:29 partira de la porte d’embarquement: B62.
            'Dear '                           => 'Cher ',
            "will leave from departure gate:" => 'partira de la porte d’embarquement:',
            '/regexpDepartureGate/'           => "/ vol(?: Lufthansa)? (?<al>[A-Z\d][A-Z]|[A-Z][A-Z\d])(?<fn>\d{1,5}) de (?<dep>.+?) à (?<arr>.+?) le (?<date>[\d\.]+)(?: à (?<time>\d{1,2}:\d{2}))? partira/",

            // Formats from subject
            //            '/regexpImportantInformation/' => '/flight (?<al>[A-Z\d][A-Z]|[A-Z][A-Z\d])(?<fn>\d{1,5}) (?<dep>.+?)\s*\((?<dCode>[A-Z]{3})\) - (?<arr>.+?)\s*\((?<aCode>[A-Z]{3})\), (?<date>[\d\.]+), (?<time>\d{1,2}:\d{2})/',
            //            '/regexpNewGateSubject/' => "",
        ],
        'es' => [
            'nameTitle' => ['Señor', 'Señora'],

            // New Gate
            //            'The departure gate for your flight' => 'Das Abfluggate für Ihren Flug',
            //            '/regexpNewGate/' => "/Flug (?<al>[A-Z\d][A-Z]|[A-Z][A-Z\d])(?<fn>\d{1,5}) am (?<date>[\d\.]+), (?<time>\d{1,2}:\d{2}) von (?<dep>.+?) nach (?<arr>.+?) hat sich geändert/",

            // Delayed
            //            'hours has been delayed' => ['Uhr ist verspätet', 'ist weiterhin verspätet'],
            //            'Your new departure time is on' => 'Ihre neue Abflugzeit ist am',
            //            '/regexpDelayed/' => "/Flug (?<al>[A-Z\d][A-Z]|[A-Z][A-Z\d])(?<fn>\d{1,5}) am (?:[\d\.]+) von (?<dep>.+?)\s*\((?<dCode>[A-Z]{3})\) nach (?<arr>.+?)\s*\((?<aCode>[A-Z]{3})\) (?:um (?:\d{1,2}:\d{2}) Uhr ist verspätet|ist weiterhin verspätet)/u",

            // Departure gate
            // Su vuelo Lufthansa LH2292 de Munich a Bruselas con fecha de 04.3.2023 a las 18:15 tiene asignada la puerta de embarque: K11.
            'Dear '                           => 'Buenos días,',
            "will leave from departure gate:" => 'tiene asignada la puerta de embarque:',
            '/regexpDepartureGate/'           => "/ vuelo(?: Lufthansa)? (?<al>[A-Z\d][A-Z]|[A-Z][A-Z\d])(?<fn>\d{1,5}) de (?<dep>.+?) a (?<arr>.+?) con fecha de (?<date>[\d\.]+)(?: a las (?<time>\d{1,2}:\d{2}))? tiene asignada/",

            // Formats from subject
            //            '/regexpImportantInformation/' => '/flight (?<al>[A-Z\d][A-Z]|[A-Z][A-Z\d])(?<fn>\d{1,5}) (?<dep>.+?)\s*\((?<dCode>[A-Z]{3})\) - (?<arr>.+?)\s*\((?<aCode>[A-Z]{3})\), (?<date>[\d\.]+), (?<time>\d{1,2}:\d{2})/',
            //            '/regexpNewGateSubject/' => "",
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        foreach ($this->detectFrom as $dFrom) {
            if (stripos($from, $dFrom) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers["subject"], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[{$this->contains(['.lufthansa.com'], '@href')}]")->length === 0) {
            return false;
        }

        foreach ($this->detectBody as $detectBody) {
            if ($this->http->XPath->query("//*[{$this->contains($detectBody)}]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug("can't determine a language");

            return $email;
        }

        $this->subject = $parser->getSubject();
        $this->parseHtml($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

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

    private function parseHtml(Email $email)
    {
        $f = $email->add()->flight();

        // General
        $f->general()
            ->noConfirmation();

        $s = $f->addSegment();

        //  Important information for your flight
        if (preg_match($this->t('/regexpImportantInformation/'), $this->subject, $m) && !empty($m['al'])) {
            $s->airline()
                ->name($m['al'])
                ->number($m['fn'])
            ;

            $s->departure()
                ->name($m['dep'])
                ->code($m['dCode'])
                ->date(strtotime($m['date'] . ', ' . $m['time']));

            $s->arrival()
                ->name($m['arr'])
                ->code($m['aCode'])
                ->noDate();
        }
        //  New Gate from Subject
        if (preg_match($this->t('/regexpNewGateSubject/'), $this->subject, $m) && !empty($m['al'])) {
            $s->airline()
                ->name($m['al'])
                ->number($m['fn'])
            ;

            $s->departure()
                ->name($m['dep'])
                ->noCode()
                ->date(strtotime($m['date'] . ', ' . $m['time']));

            $s->arrival()
                ->name($m['arr'])
                ->noCode()
                ->noDate();
        }

        // New Gate
        $text = $this->http->FindSingleNode("//text()[" . $this->contains($this->t("The departure gate for your flight")) . "]");

        if (preg_match($this->t('/regexpNewGate/'), $text, $m) && !empty($m['al'])) {
            $s->airline()
                ->name($m['al'])
                ->number($m['fn'])
            ;

            $s->departure()
                ->name($m['dep'])
                ->noCode()
                ->date(strtotime($m['date'] . ', ' . $m['time']));

            $s->arrival()
                ->name($m['arr'])
                ->noCode()
                ->noDate();
        }

        // Delayed
        $text = $this->http->FindSingleNode("//text()[" . $this->contains($this->t("hours has been delayed")) . "]");
        $date = $this->http->FindSingleNode("//text()[" . $this->contains($this->t("Your new departure time is on")) . "]", null, true,
            "/" . $this->opt($this->t('Your new departure time is on')) . "\s*([\d\.]+(?: [[:alpha:]]+ |,\s+)\d{1,2}:\d{2}) [[:alpha:]]+\.?/u");

        if (preg_match($this->t('/regexpDelayed/'), $text, $m) && !empty($m['al']) && $date) {
            $date = preg_replace("/([\d\.]+) [[:alpha:]]+ (\d{1,2}:\d{2})/", '$1, $2', $date);
            $s->airline()
                ->name($m['al'])
                ->number($m['fn'])
            ;

            $s->departure()
                ->name($m['dep'])
                ->date(strtotime($date));

            if (!empty($m['dCode'])) {
                $s->departure()
                    ->code($m['dCode']);
            } else {
                $s->departure()
                    ->noCode();
            }

            $s->arrival()
                ->name($m['arr'])
                ->noDate();

            if (!empty($m['aCode'])) {
                $s->arrival()
                    ->code($m['aCode']);
            } else {
                $s->arrival()
                    ->noCode();
            }
        }

        // Departure gate
        $text = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("will leave from departure gate:")) . "]/ancestor::*[not(" . $this->starts($this->t("will leave from departure gate:")) . ")][1]");

        if (preg_match($this->t('/regexpDepartureGate/'), $text, $m) && !empty($m['al'])) {
            $s->airline()
                ->name($m['al'])
                ->number($m['fn'])
            ;

            $s->departure()
                ->name($m['dep'])
                ->noCode();

            if (!empty($m['time'])) {
                $s->departure()
                    ->date(strtotime($m['date'] . ', ' . $m['time']));
            } else {
                $s->departure()
                    ->day(strtotime($m['date']))
                    ->noDate()
                ;
            }

            $s->arrival()
                ->name($m['arr'])
                ->noCode()
                ->noDate();

            $name = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Dear '))}]", null, true,
                "/{$this->opt($this->t('Dear '))}\s*(.+),$/");
//            $name = preg_replace('/^\s*(Mr|Mrs|Miss|Mstr|Ms|Dr|Signor)[.]?\s+/', '', $name);
            $name = preg_replace("/^\s*{$this->opt($this->t('nameTitle'))}[.]?\s+/", '', $name);

            if (!empty($name)) {
                $f->general()
                    ->traveller($name);
            }
        }

        return true;
    }

    private function assignLang()
    {
        foreach ($this->detectBody as $lang => $detectBody) {
            if ($this->http->XPath->query("//*[{$this->contains($detectBody)}]")->length > 0) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function t($s)
    {
        if (!isset($this->lang, self::$dictionary[$this->lang], self::$dictionary[$this->lang][$s])) {
            return $s;
        }

        return self::$dictionary[$this->lang][$s];
    }

    // additional methods

    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) {
            return 'contains(' . $text . ',"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function opt($field, $delimiter = '/')
    {
        $field = (array) $field;

        if (empty($field)) {
            $field = ['false'];
        }

        return '(?:' . implode("|", array_map(function ($s) use ($delimiter) {
            return str_replace(' ', '\s+', preg_quote($s, $delimiter));
        }, $field)) . ')';
    }
}
