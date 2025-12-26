<?php

namespace AwardWallet\Engine\klm\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class CheckInStarted extends \TAccountChecker
{
    public $mailFiles = "klm/it-8087465.eml, klm/it-105983520.eml, klm/it-107018397.eml, klm/it-106557619.eml";
    private $reSubject = [
        'Check in voor uw vlucht naar', //nl
        'Faça o check-in do seu voo para', // pt
        'Effettui il check-in per il volo per', // it
        'Checken Sie sich ein für Ihren Flug nach', // de
        'Check in for your flight to', //en
        'Sjekk inn for flyvningen din til', // no
        'Enregistrez-vous sur votre vol pour', // fr
        'Haga la facturación para su vuelo a', //es
        'Tee lentosi lähtöselvitys lennollesi kohteeseen', //fi
        'Зарегистрируйтесь на Ваш рейс в', //ru
        // sv
        'Checka in för ditt flyg till',
        // da
        'Check ind til din flyvning til',
        // ja
        '行きの便にチェックイン',
        //zh
        '的航班辦理報到',
        // pl
        'Proszę dokonać odprawy na lot do',
    ];

    private $reFrom = 'klm@klm-info.com';
    private $reProvider = 'klm-info.com';
    private $reBody = 'KLM';
    private $reBody2 = [
        "nl"     => "U kunt inchecken: print nu uw boarding pass.",
        "nl2"    => "Check nu in.",
        'pt'     => 'Faça o check-in agora',
        'pt2'    => 'Faça já o check-in e obtenha seu',
        'it'     => 'Effettui subito il check-in',
        'de'     => 'Checken Sie jetzt ein, um Ihre Bordkarte zu erhalten.',
        'de2'    => 'Jetzt einchecken.',
        "en"     => "Check-in has started: get your boarding pass now.",
        "en2"    => "Check in now to receive your boarding pass.",
        "en3"    => "We look forward to welcoming you on board soon",
        "no"     => "Sjekk inn for å få ombordstigningskortet ditt.",
        "no1"    => "Sjekk inn nå.",
        "fr"     => "Enregistrez-vous maintenant pour recevoir votre carte d’embarquement.",
        "fr2"    => "Enregistrez-vous dès maintenant.",
        "es"     => "Realice ya el check-in para recibir su tarjeta de embarque.",
        "es2"    => "Facture ahora: obtenga su tarjeta de embarque",
        "es3"    => "Realice el check-in ahora",
        "fi"     => "Tee lähtöselvitys nyt, niin saat tarkastuskorttisi.",
        "ru"     => "Теперь зарегистрируйтесь и получите Ваш посадочный талон.",
        "sv"     => "Checka in nu.",
        "da"     => "Check ind nu.",
        "ja"     => "今すぐチェックイン。",
        "zh"     => "立即辦理登機手續。",
        "pl"     => "Dokonaj odprawy teraz.",
    ];

    private $lang = '';
    private $date;
    private static $dict = [
        'nl' => [
            "Your booking code"  => "Uw boekingscode",
            "Dear "              => ["Geachte ", 'Beste '],
            "Name Prefix"        => ['mevrouw', 'heer', 'Heer', 'meneer'],
            "reDate"             => "\w+\s*\d+\s*\w+\s*\d{2}\s*(?:om|at)\s*\d+:\d+",
        ],
        'pt' => [ // it-105983520.eml
            'Your booking code'  => 'Código de sua reserva',
            'Dear '              => ['Prezado Senhor/Prezada Senhora ', 'Prezada ', 'Prezado '],
            "Name Prefix"        => [
                'Senhora',
                'Senhor',
            ],
            'reDate'             => '\w+\s*\d+\s*\w+\s*\d{2}\s*às\s*\d+:\d+',
        ],
        'it' => [ // it-107018397.eml
            'Your booking code'  => 'Codice della prenotazione',
            'Dear '              => ['Egregio ', 'Gentile '],
            "Name Prefix"        => [
                'Signor',
                'Signora',
            ],
            'reDate'             => '\w+\s*\d+\s*\w+\s*\d{2}\s*alle ore\s*\d+:\d+',
        ],
        'de' => [ // it-106557619.eml
            'Your booking code'  => 'Ihr Buchungscode',
            'Dear '              => ['Sehr geehrter ', 'Sehr geehrte '],
            "Name Prefix"        => [
                'Herr',
                'Frau',
            ],
            'reDate'             => '\w+\s*\d+\s*\w+\s*\d{2}\s*um\s*\d+:\d+',
        ],
        'no' => [
            'Your booking code'  => 'Ditt referansenummer',
            'Dear '              => 'Kjære ',
            //            "Name Prefix"        => [],
            'reDate'             => '\w+\s*\d+\s*\w+\s*\d{2}\s*kl\s*\d+:\d+',
        ],
        'fr' => [
            'Your booking code'  => 'Votre code de réservation',
            'Dear '              => ['Cher ', 'Chère '],
            "Name Prefix"        => [
                'Monsieur',
                'Madame',
                'Mademoiselle',
            ],
            'reDate'             => '\w+\.?\s*\d+\s*\w+\s*\d{2}\s*à\s*\d+:\d+',
        ],
        'es' => [
            'Your booking code'  => 'Su código de reserva',
            'Dear '              => ['Estimada ', 'Estimado '],
            "Name Prefix"        => [
                'Señorita',
                'Señor',
                'Señora',
            ],
            'reDate'             => '\w+\.?\s*\d+\s*\w+\s*\d{2}\s*at\s*\d+:\d+',
        ],
        'fi' => [
            'Your booking code'  => 'Varauskoodisi',
            'Dear '              => 'Hei ',
            //            "Name Prefix"        => [],
            'reDate'             => '\w+\.?\s*\d+\s*\w+\s*\d{2}\s*klo\s*\d+:\d+',
        ],
        'ru' => [
            'Your booking code'  => 'Ваш код бронирования',
            'Dear '              => 'Уважаемый ',
            "Name Prefix"        => [
                'господин',
                'госпожа',
            ],
            'reDate'             => '\w+\.?\s*\d+\s*\w+\s*\d{2}\s*at\s*\d+:\d+',
        ],
        'sv' => [
            'Your booking code'  => 'Din bokningskod',
            'Dear '              => 'Hej ',
            //            "Name Prefix"              => [],
            'reDate'             => '\w+\s*\d+\s*\w+\s*\d{2}\s*på\s*\d+:\d+',
        ],
        'da' => [
            'Your booking code'  => 'Din reservationskode',
            'Dear '              => 'Kære ',
            //            "Name Prefix"              => [],
            'reDate'             => '\w+\s*\d+\s*\w+\s*\d{2}\s*kl\.\s*\d+:\d+',
        ],
        'ja' => [
            'Your booking code'  => 'お客様の予約コード',
            'Dear '              => '様,',
            //            "Name Prefix"              => [],
            'reDate'             => '\w+\s*\d+\s*\d+\s*\w+\s*\d{2}\s+\d+:\d+',
        ],
        'zh' => [
            'Your booking code'  => '您的預訂號碼',
            'Dear '              => '先生/女士,',
            //            "Name Prefix"              => [],
            'reDate'             => '\w+\s*\d+\s*\w+\s*\d{2}\s+\d+:\d+',
        ],
        'pl' => [
            'Your booking code'        => 'Państwa kod rezerwacji',
            'Dear '                    => 'Szanowny ',
            "Name Prefix"              => ['Panie'],
            'reDate'                   => '\w+\s*\d+\s*\w+\s*\d{2}\s+o\s+\d+:\d+',
        ],
        'en' => [ // it-8087465.eml
            "Name Prefix"  => [
                'Mrs/Mr', 'Mrs./Mr.',
            ],
            "reDate" => "\w+\s*\d+\s*\w+\s*\d{2}\s*at\s*\d+:\d+",
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $body = $parser->getHTMLBody();

        foreach ($this->reBody2 as $lang => $re) {
            if (strpos($body, $re) !== false) {
                $this->lang = substr($lang, 0, 2);

                break;
            }
        }
        $email->setType('CheckInStarted' . ucfirst($this->lang));
        $this->parseEmail($email);

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (strpos($body, $this->reBody) === false) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if (strpos($body, $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers["from"], $this->reFrom) === false) {
            return false;
        }

        foreach ($this->reSubject as $lang => $reSubject) {
            if (strpos($headers["subject"], $reSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->reProvider) !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    protected function normalizeDate($dateStr)
    {
        $this->logger->debug('$dateStr = ' . print_r($dateStr, true));
        $in = [
            // Fri 01 September17 at 17:20, Za 11 November 17 om 12:15
            "/^\s*[[:alpha:]]+\.?\s*(\d{1,2})\s*([[:alpha:]]+)\s*(\d{2})\s*(?:at|om|às|alle ore|um|kl|à|klo|på|kl\.|o)\s*(\d+:\d+)\s*$/iu",
            //金 22 4月 22 11:30
            "/^\s*[[:alpha:]]+\s*(\d{1,2})\s*(\d{1,2})[[:alpha:]]+\s*(\d{2})\s+(\d+:\d+)\s*$/iu",
        ];
        $out = [
            "$1 $2 20$3 $4",
            "20$3-$2-$1, $4",
        ];
        $dateStr = preg_replace($in, $out, $dateStr);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $dateStr, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $dateStr = str_replace($m[1], $en, $dateStr);
            }
        }

        return $dateStr;
    }

    private function parseEmail(Email $email): void
    {
        $f = $email->add()->flight();

        $traveller = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Dear '))}][1]", null, true, "/{$this->opt($this->t('Dear '))}(?:{$this->opt($this->t('Name Prefix'))}\s+)?([[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])(?:\s*[,;:!?]|$)/u");

        if (empty($traveller) && $this->lang) {
            $traveller = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Dear '))}][1]", null, true, "/^\s*([[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])\s*{$this->opt($this->t('Dear '))}$/u");
        }
        $f->general()->traveller($traveller);

        $confirmation = $this->http->FindSingleNode("//text()[starts-with(normalize-space(),'{$this->t('Your booking code')}')]");

        if (preg_match("/^({$this->opt($this->t('Your booking code'))})[:：\s]+([A-Z\d]{5,})$/u", $confirmation, $m)) {
            $f->general()->confirmation($m[2], $m[1]);
        }

        $flightText = $this->htmlToText($this->http->FindHTMLByXpath("//img[contains(@src,'icon-plane')]/ancestor::td[1]/following-sibling::td[normalize-space()][1]"));

        if (preg_match("/([A-Z][A-Z\d]|[A-Z\d][A-Z])[ ]*(\d+):\s*(.*?)\s*\(\s*([A-Z]{3})\s*\)\s*-\s*(.*?)\s*\(\s*([A-Z]{3})\s*\)\s*({$this->t('reDate')})/u", $flightText, $m)) {
            $s = $f->addSegment();
            $s->airline()->name($m[1])->number($m[2]);
            $s->departure()->name($m[3])->code($m[4])->date2($this->normalizeDate($m[7]));
            $s->arrival()->name($m[5])->code($m[6])->noDate();
        }
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
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

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) { return "starts-with(normalize-space(.), \"{$s}\")"; }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) { return "contains(normalize-space(.), \"{$s}\")"; }, $field));
    }
}
