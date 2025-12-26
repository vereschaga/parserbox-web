<?php

namespace AwardWallet\Engine\zenchef\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ReservationConfirmation extends \TAccountChecker
{
    public $mailFiles = "zenchef/it-708714532.eml, zenchef/it-709475045.eml, zenchef/it-709507306.eml, zenchef/it-736230933.eml, zenchef/it-774249880.eml, zenchef/it-774284339.eml, zenchef/it-777675632.eml, zenchef/it-778127586.eml, zenchef/it-778865894.eml, zenchef/it-783891006.eml";
    public $subjects = [
        'Reservation confirmation',
        'Reconfirmation request',
        'Booking reminder for',
        '] Booking confirmation',
        // fr
        'Réservation confirmée suite au dépôt de votre empreinte bancaire',
        // pt
        '] Confirmação da sua reserva',
        // es
        '] Confirmación de reserva',
        // de
        '] Bestätigung Ihrer Reservierung',
    ];

    public $emailSubject = '';
    public $lang = '';

    public $detectLang = [
        "en" => ['Hello'],
        "fr" => ['Bonjour'],
        "pt" => ['Olá,'],
        "nl" => ['Met vriendelijke groeten'],
        "es" => ['Hola,'],
        "de" => ['Hallo'],
    ];

    public static $dictionary = [
        "en" => [
            'We are pleased to confirm your reservation for' => [
                'We are pleased to confirm your reservation for',
                'Upon, your booking will take place on',
                'We ask our guests to reconfirm their reservation before the time of their arrival',
                'We are pleased to confirm your booking for',
                'To validate your booking',
                'Following the validation of your credit card guarantee, we are pleased to confirm your reservation on',
                'upon, your booking will take place on',
                'We are pleased to confirm your reservation on',
                'We look forward to seeing you on',
                'We regret to inform you that we can no longer maintain your option for the',
                'We would like to take the opportunity to thank you again for the interest you have shown in our gastronomic restaurant',
                'Following the validation of you credit card deposit, we are pleased to confirm your booking of the',
                'We regret to inform you that we cannot confirm your reservation for',
            ],
            //'Hello' => '',
            'See you soon!' => ['See you soon!', 'Regards,', 'Best regards,'],
            'people)'       => ['people)', 'person(s))'],

            'CancelledText' => ['We regret to inform you that we can no longer maintain your option for the', 'Cancellation reasons : either we have decided to cancel',
                'We regret to inform you that we cannot confirm your reservation for',
            ],
        ],

        "fr" => [
            'We are pleased to confirm your reservation for' => [
                'Nous avons le plaisir de confirmer votre réservation du',
                'Nous avons pour habitude de demander à nos clients de reconfirmer',
                'Comme convenu, nous avons hâte de vous accueillir dans notre',
                'Suite au dépôt de votre empreinte bancaire, nous avons le plaisir de confirmer votre réservation du',
                'Nous avons pris bonne note de votre réservation du',
                'Votre réservation a été modifiée pour le',
                'Confirmation de votre réservation du',
                '] Confirmation de votre réservation',
            ],
            'Hello'         => 'Bonjour',
            'See you soon!' => ['À très bientôt !', 'Cordialement,', 'Bien cordialement,', 'A Presto !', 'Nous sommes impatients de vous accueillir !', 'À très bientôt chez', 'A prestissimo !'],
            'people)'       => ['personnes)', 'personne(s))'],
        ],

        "pt" => [
            'We are pleased to confirm your reservation for' => [
                'Costumamos pedir aos nossos clientes que reconfirmem a sua reserva antes da hora da sua',
                'Temos o prazer de confirmar a sua reserva de',
                'Após o depósito de pré-autorização do cartão bancário, temos o prazer de confirmar a sua reserva para',
            ],
            'Hello'                                          => 'Olá,',
            'See you soon!'                                  => ['Atentamente,', 'Até breve!'],
            'people)'                                        => ['pessoa(s))', 'pessoas)'],
        ],

        "nl" => [
            'We are pleased to confirm your reservation for' => [
                'Graag bevestigen we je reservering op',
            ],
            'Hello'         => 'Beste',
            'See you soon!' => ['Tot binnenkort!'],
            'people)'       => ['personen)'],
        ],
        "es" => [
            'We are pleased to confirm your reservation for' => [
                'Estamos encantados de confirmar su reserva para el',
            ],
            'Hello'         => 'Hola,',
            'See you soon!' => ['¡Nos vemos pronto!'],
            'people)'       => ['persons)'],
        ],
        "de" => [
            'We are pleased to confirm your reservation for' => [
                'Gerne bestätigen wir Ihre Reservierung vom',
            ],
            'Hello'         => 'Hallo',
            // 'See you soon!' => ['¡Nos vemos pronto!'],
            'people)'       => ['Personen)'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@mg.zenchefrestaurants.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (stripos($headers['subject'], $subject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $this->assignLang();

        if (($this->http->XPath->query("//a[contains(@href, 'mg.zenchefrestaurants.com')]")->length > 0
            || $this->http->XPath->query("//img[contains(@src, 'mg.zenchefrestaurants.com')]")->length > 0)
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Confirm my attendance'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Manage my reservation'))}]")->length > 0) {
            return true;
        }

        return ($this->http->XPath->query("//a[contains(@href, 'mg.zenchefrestaurants.com')]")->length > 0
            || $this->http->XPath->query("//img[contains(@src, 'mg.zenchefrestaurants.com')]")->length > 0)
            && ($this->http->XPath->query("//text()[{$this->contains($this->t('We are pleased to confirm your reservation for'))}]")->length > 0);
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]mg\.zenchefrestaurants\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        $this->emailSubject = $parser->getSubject();

        $this->Event($email);
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function Event(Email $email)
    {
        $e = $email->add()->event();

        $e->type()
            ->restaurant();

        $e->general()
            ->traveller(preg_replace('/^\s*(Monsieur)\s+/', '',
                $this->http->FindSingleNode("//text()[{$this->starts($this->t('Hello'))}]", null, true, "/{$this->opt($this->t('Hello'))}\s*(.+)[\,:]/")))
            ->noConfirmation();

        if ($this->http->XPath->query("//node()[{$this->contains($this->t('CancelledText'))}]")->length > 0
        ) {
            $e->general()
                ->status('Cancelled')
                ->cancelled();
        }

        $name = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Hello'))}]/preceding::text()[position() < 5][normalize-space()][not(ancestor::style)][1]");

        if (empty($name) && preg_match('/\[\s*(.+?)\s*\]/', $this->emailSubject, $m)
            && $this->http->XPath->query("//text()[{$this->eq($m[1])}]")->length > 0
        ) {
            $name = $m[1];
        }
        $e->place()
            ->name($name);

        if (!empty($name)) {
            $altNames = [
                'RESTAURANT LA RAPIERE' => ["L'équipe du Restaurant"],
            ];
            $names = $altNames[$name] ?? [];
            $names[] = $name;

            $address = null;
            $addresses = array_values(array_filter($this->http->FindNodes("//text()[{$this->starts($this->t('See you soon!'))}]/following::text()[normalize-space()][position() < 3]/ancestor::p[1]",
                null, "/{$this->opt($names)}\s*(\S.+)/isu")));

            if (count($addresses) === 1) {
                $address = $addresses[0];
            }

            if (empty($address)) {
                $htmlNames = array_unique($this->http->FindPregAll("/\>({$this->opt($names)})\</"));
                $addresses = array_values(array_filter($this->http->FindNodes("//text()[{$this->starts($this->t('Hello'))}]/following::text()[{$this->eq($htmlNames)}]/ancestor::p[1][descendant::text()[normalize-space()][{$this->eq($htmlNames)}]]",
                    null, "/{$this->opt($names)}\s*(\S.+)/isu")));

                if (count($addresses) === 1) {
                    $address = $addresses[0];
                }
            }

            $e->place()
                ->address($address);
        }

        if ($e->getCancelled()) {
            $date = $this->http->FindSingleNode("//text()[{$this->starts($this->t('We are pleased to confirm your reservation for'))}]/following::text()[normalize-space()][1]", null, true, "/^(.+\d{4}.*\d+:\d+.*)$/");

            if (!empty($date)) {
                $e->setStartDate($this->normalizeDate($date));
            }
        } else {
            $e->booked()
                ->guests($this->http->FindSingleNode("(//text()[{$this->contains($this->t('people)'))}])[1]", null,
                    true, "/\(\s*(\d+)\s*{$this->opt($this->t('people)'))}/"));

            $date = $this->http->FindSingleNode("//text()[{$this->starts($this->t('We are pleased to confirm your reservation for'))}]/following::text()[normalize-space()][1]", null, true, "/^(.+\d{4})$/");

            $time = $this->http->FindSingleNode("//text()[{$this->starts($this->t('We are pleased to confirm your reservation for'))}]/following::text()[normalize-space()][3]", null, true, "/^(\d+\:\d+\s*A?P?M?)/");

            if (!empty($date) && !empty($time)) {
                $e->setStartDate($this->normalizeDate($date . ', ' . $time));
            }
        }

        $e->setNoEndDate(true);
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
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

        return implode(" or ", array_map(function ($s) {
            return "starts-with(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }

    private function assignLang()
    {
        foreach ($this->detectLang as $lang => $words) {
            foreach ($words as $word) {
                if ($this->http->XPath->query("//text()[{$this->contains($word)}]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function normalizeDate($date)
    {
        // $this->logger->debug('$date = '.print_r( $date,true));
        $in = [
            // samedi 10 août 2024, 19:30
            // Tuesday 29 October 2024 at 8:15 PM
            "/^\s*[\w\-]+\s+(\d+\s*\w+\s*\d{4})(?:,|\s+at\s+)\s*(\d+\:\d+\s*A?P?M?)\s*$/u",
        ];
        $out = [
            "$1, $2",
        ];
        $date = preg_replace($in, $out, $date);
        // $this->logger->debug('$date = '.print_r( $date,true));

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#u", $date, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        return strtotime($date);
    }
}
