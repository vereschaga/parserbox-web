<?php

namespace AwardWallet\Engine\lufthansa\Email;

// TODO: delete what not use
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class SeatChanged extends \TAccountChecker
{
    public $mailFiles = "lufthansa/it-577084847.eml";

    public $providerCode;
    public $lang;
    public static $providers = [
        'austrian' => [
            'from'   => 'flight.service@information.austrian.com',
            'altImg' => 'Austrian Airline Logo',
            'text'   => ['Austrian.com', 'Your Austrian Team', 'Ваша команда Austrian Airlines'],
        ],
        'swissair' => [
            'from'   => 'flight.service@information.swiss.com',
            'altImg' => 'Swiss Airline Logo',
            'text'   => ['swiss.com', 'Your SWISS Team', 'Your SWISS team', 'Su equipo SWISS', 'Il suo Team SWISS', 'Ihr SWISS Team', 'Votre équipe SWISS', 'Votre SWISS Team'],
        ],
        'brussels' => [
            'from'   => 'flight.service@information.brusselsairlines.com',
            'altImg' => 'Brussels Airline Logo',
            'text'   => ['Brusselsairlines.com', 'Your Brussels Airlines Team'],
        ],
        'lufthansa' => [
            'from'   => 'flight.service@information.lufthansa.com',
            'altImg' => 'Lufthansa Logo',
            'text'   => ['Lufthansa.com', 'Your Lufthansa Team'],
        ],
    ];
    public static $dictionary = [
        'en' => [
            'Your seat has changed' => ['Your seat has changed', 'Your seats have changed'],
            'Your new seat is:'     => ['Your new seat is:'],
        ],
        'de' => [
            'Your seat has changed' => ['Ihr Sitzplatz hat sich geändert', 'Ihre Sitzplätze haben sich geändert'],
            'Your new seat is:'     => ['Ihr neuer Sitzplatz ist:', 'Ihre neuen Sitzplätze sind:'],
        ],
        'fr' => [
            'Your seat has changed' => ['Votre siège a été modifié.', 'Vos sièges ont été modifiés'],
            'Your new seat is:'     => ['Vos nouveaux sièges sont :', 'Votre nouveau siège est:'],
        ],
    ];

    private $detectFrom = "provider.url";
    private $detectSubject = [
        // en
        'Your seat has changed | Flight',
        // de
        'Ihr Sitzplatz hat sich geändert | Flug',
        // fr
        'Votre siège a été modifié. | Vol',
    ];

    // Main Detects Methods
    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@.]lufthansa\.com$/", $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        $detectedProvider = false;

        foreach (self::$providers as $code => $params) {
            if (!empty($params['from']) && stripos($headers["from"], $params['from']) !== false
            ) {
                $this->providerCode = $code;
                $detectedProvider = true;

                break;
            }
        }

        if ($detectedProvider === false) {
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
        $detectedProvider = false;

        foreach (self::$providers as $code => $params) {
            if (
                (!empty($params['altImg']) && $this->http->XPath->query("//img[{$this->eq($params['altImg'], '@alt')}]")->length > 0)
                || (!empty($params['text']) && $this->http->XPath->query("//*[{$this->eq($params['text'])}]")->length > 0)
            ) {
                $this->providerCode = $code;
                $detectedProvider = true;

                break;
            }
        }

        if ($detectedProvider === false) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("can't determine a language");

            return $email;
        }

        // en: Your seat has changed | Flight AC6783 from BCN to ZRH on 05 November 2023
        // de: Ihr Sitzplatz hat sich geändert | Flug Swiss International Air Lines LX2082 von ZRH nach LIS am 23 September 2023
        // fr: Votre siège a été modifié. | Vol Lufthansa German Airlines LH5746 de FRA à GVA le 15 novembre 2023
        if (preg_match("/\|.+ (?<al>[A-Z\d][A-Z]|[A-Z][A-Z\d])\s*(?<fn>\d{1,5}) [[:alpha:]]+ (?<dCode>[A-Z]{3}) [[:alpha:]]+ (?<aCode>[A-Z]{3}) [[:alpha:]]+ (?<date>.+)/u", $parser->getSubject(), $m)) {
            $f = $email->add()->flight();

            $f->general()->noConfirmation();

            $s = $f->addSegment();

            $s->airline()
                ->name($m['al'])
                ->number($m['fn'])
            ;
            $s->departure()
                ->code($m['dCode'])
                ->noDate()
                ->day($this->normalizeDate($m['date']))
            ;
            $s->arrival()
                ->code($m['aCode'])
                ->noDate()
            ;

            $text = implode("\n", $this->http->FindNodes("//text()[{$this->contains($this->t('Your new seat is:'))}]/following::text()[normalize-space()][1]/ancestor::*[1][{$this->contains($this->t('Your new seat is:'))}]//text()[normalize-space()]"));
            // $this->logger->debug('$text = '.print_r( $text,true));
            if (preg_match("/{$this->opt($this->t('Your new seat is:'))}\s*((?:[A-Z\W]*\s*:\s*\d{1,3}[A-Z]\n)+)/", $text . "\n\n", $mat)
                && preg_match_all("/^([A-Z\W]*?)\s*:\s*(\d{1,3}[A-Z])$/m", $text . "\n\n", $m2)
            ) {
                $f->general()
                    ->travellers($m2[1], true);
                $s->extra()
                    ->seats($m2[2]);
            } else {
                $f->general()->travellers([]);
            }
        }

        if (empty($this->providerCode)) {
            foreach (self::$providers as $code => $params) {
                if (
                    (!empty($params['from']) && $this->http->XPath->query("//*[{$this->contains($params['from'])}]")->length > 0)
                    || (!empty($params['altImg']) && $this->http->XPath->query("//img[{$this->eq($params['altImg'], '@src')}]")->length > 0)
                    || (!empty($params['text']) && $this->http->XPath->query("//*[{$this->eq($params['text'], '@src')}]")->length > 0)
                ) {
                    $this->providerCode = $code;

                    break;
                }
            }
        }

        if (!empty($this->providerCode)) {
            $email->setProviderCode($this->providerCode);
        }

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

    public static function getEmailProviders()
    {
        return array_keys(self::$providers);
    }

    private function assignLang()
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict["Your seat has changed"])
                && $this->http->XPath->query("//*[{$this->contains($dict['Your seat has changed'])}]")->length > 0
            ) {
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

    private function eq($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) {
            return $text . '="' . $s . '"';
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

    private function normalizeDate(?string $date): ?int
    {
        // $this->logger->debug('date begin = ' . print_r( $date, true));
        if (empty($date)) {
            return null;
        }

        $in = [
            //            // Apr 09
            //            '/^\s*(\w+)\s+(\d+)\s*$/iu',
            //            // Sun, Apr 09
            //            '/^\s*(\w+),\s*(\w+)\s+(\d+)\s*$/iu',
            //            // Tue Jul 03, 2018 at 1:43 PM
            //            '/^\s*[\w\-]+\s+(\w+)\s+(\d+),\s*(\d{4})\s+(\d{1,2}:\d{2}(?:\s*[ap]m)?)\s*$/ui',
        ];
        $out = [
            //            '$2 $1 %year%',
            //            '$1, $3 $2 ' . $year,
            //            '$2 $1 $3, $4',
        ];

        $date = preg_replace($in, $out, $date);

//        $this->logger->debug('date replace = ' . print_r( $date, true));
        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            } elseif ($en = MonthTranslate::translate($m[1], 'el')) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        // $this->logger->debug('date end = ' . print_r( $date, true));

        return strtotime($date);
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
