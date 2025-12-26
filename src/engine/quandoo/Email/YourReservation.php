<?php

namespace AwardWallet\Engine\quandoo\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class YourReservation extends \TAccountChecker
{
    public $mailFiles = "quandoo/it-135299415.eml, quandoo/it-135834140.eml, quandoo/it-136000280.eml, quandoo/it-136809688.eml, quandoo/it-136896658.eml, quandoo/it-81937646.eml, quandoo/it-82210095.eml";
    public $subjects = [
        '/Your reservation at .+ is\s*(?:coming up|confirmed)/',
        '/Votre réservation chez .+ est confirmée/',
        '/rezervasyonunuz onaylandı/',
    ];

    public $lang = 'en';
    public $subject;

    public $detectLang = [
        'en' => ['Your reservation'],
        'de' => ['Ihre Reservierung'],
        'it' => ['La tua prenotazione', 'Promemoria della tua prenotazione'],
        'fr' => ['Votre réservation'],
        'tr' => ['Rezervasyonunuz onaylandı'],
    ];

    public static $dictionary = [
        "en" => [
            'Your reservation is'        => ['Your reservation is', 'Your reservation has been'],
            'DATE'                       => 'DATE',
            'TIME'                       => 'TIME',
            'GUESTS'                     => 'GUESTS',
            'Reminder'                   => 'Your reservation is coming up',
            'noConfirmation'             => 'pending',
            'canceled'                   => ['cancelled'],
            'Good news, you’re going to' => ['Good news, you’re going to', 'Good news, it\'s almost time for you to dine at'],
            'is'                         => ['is', 'has'],
        ],
        "de" => [
            'Your reservation is' => ['Ihre Reservierung ist', 'Ihre Reservierung wurde', 'Ihre Reservierung'],
            'Have a question'     => 'Haben Sie noch Fragen',
            'GUEST NAME'          => 'NAME DES GASTES',
            'RESERVATION #'       => 'RESERVIERUNGS #',
            'DATE'                => 'DATUM',
            'TIME'                => 'UHRZEIT',
            'GUESTS'              => 'GÄSTE',
            'canceled'            => ['storniert', 'stornieren'],
            //'Reminder' => '',
            'noConfirmation'          => 'steht an',
            'is'                      => ['ist', 'wurde'],
            'Your reservation at'     => 'Ihre Reservierung bei',
            'Plan your journey'       => ['Planen Sie Ihre Anreise', 'Weitere Informationen'],
            'Book another restaurant' => 'Ihre Reservierung stornieren',
        ],
        "it" => [
            'Your reservation is' => ['La tua prenotazione è stata', 'La tua prenotazione è'],
            'Have a question'     => ['Hai domande', 'Contatta il ristorante'],
            'GUEST NAME'          => 'NOME OSPITE',
            'RESERVATION #'       => 'PRENOTAZIONE #',
            'DATE'                => ['DATA', 'DATE'],
            'TIME'                => 'ORA',
            'GUESTS'              => 'OSPITI',
            'canceled'            => ['annullata'],
            'Reminder'            => 'Promemoria della tua prenotazione',
            //'noConfirmation' => '',
            'Your reservation at'     => 'La tua prenotazione al',
            'is'                      => 'è',
            'Plan your journey'       => ['Pianifica il tuo viaggio', 'Altre informazioni'],
            'Book another restaurant' => 'Prenota un altro ristorante',
        ],
        "fr" => [
            'Your reservation is'     => ['Votre réservation est'],
            'Have a question'         => ['Des questions', 'Contacter le restaurant'],
            'GUEST NAME'              => "NOM DE L'INVITÉ",
            'RESERVATION #'           => 'RÉSERVATION #',
            'DATE'                    => 'DATE',
            'TIME'                    => 'HEURE',
            'GUESTS'                  => 'INVITÉS',
            'Your reservation at'     => 'Votre réservation chez',
            'is'                      => 'est',
            //'canceled' => '',
            //'Reminder' => '',
            //'noConfirmation' => '',
            //'Book another restaurant' => '',
        ],
        "tr" => [
            'Your reservation is' => ['Rezervasyonunuz'],
            'Have a question'     => 'Restoranla iletişime geç',
            'GUEST NAME'          => "KONUK ADI",
            'RESERVATION #'       => 'REZERVASYON NO.',
            'DATE'                => 'TARİH',
            'TIME'                => 'SAAT',
            'GUESTS'              => 'KONUK SAYISI',
            //'canceled' => '',
            //'Reminder' => '',
            //'noConfirmation' => '',
            //'Book another restaurant' => '',
            'Plan your journey'       => ['Yolculuğunuzu planlayın'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@mail.quandoo.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (preg_match($subject, $headers['subject'])) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'Quandoo')]")->length === 0) {
            return false;
        }

        foreach (self::$dictionary as $dict) {
            if (empty($dict['DATE']) || empty($dict['TIME']) || empty($dict['GUESTS'])) {
                return false;
            }

            if ($this->http->XPath->query("//*[*[normalize-space()][1][{$this->starts($dict['DATE'])}] and *[normalize-space()][2][{$this->starts($dict['TIME'])}] and *[normalize-space()][3][{$this->starts($dict['GUESTS'])}]]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]mail\.quandoo\.com$/', $from) > 0;
    }

    public function ParseEmail(Email $email)
    {
        $e = $email->add()->event();

        $e->type()
            ->event();

        $status = $this->http->FindNodes("//text()[{$this->starts($this->t('Your reservation is'))}]", null, "/{$this->opt($this->t('Your reservation is'))}\s*(\D+)/");

        if (count($status) > 0) {
            $status = array_filter($status)[0];
        }

        if (in_array($status, $this->t('canceled'))) {
            $e->general()
                ->cancelled()
                ->status($status);
        } elseif (empty($status) && $this->http->XPath->query("//text()[{$this->contains($this->t('Reminder'))}]")->length > 0) {
            $e->general()
                ->status('confirmed');
        } elseif ($status == $this->t('noConfirmation')) {
            $email->removeItinerary($e);
            $email->setIsJunk(true);

            return true;
        } elseif (!empty($status)) {
            $e->general()
                ->status($status);
        }

        $e->general()
            ->traveller($this->http->FindSingleNode("//text()[{$this->starts($this->t('GUEST NAME'))}]/following::text()[normalize-space()][1]"), true)
            ->confirmation($this->http->FindSingleNode("//text()[{$this->starts($this->t('RESERVATION #'))}]/following::text()[normalize-space()][1]"));

        $dateStart = $this->http->FindSingleNode("//text()[{$this->starts($this->t('DATE'))}]/following::text()[normalize-space()][1]");
        $timeStart = $this->http->FindSingleNode("//text()[{$this->starts($this->t('TIME'))}]/following::text()[normalize-space()][1]");

        $e->booked()
            ->guests($this->http->FindSingleNode("//text()[{$this->starts($this->t('GUESTS'))}]/following::text()[normalize-space()][1]"))
            ->start(strtotime($dateStart . ', ' . $timeStart))
            ->noEnd();

        if (preg_match("/{$this->opt($this->t('Your reservation at'))}\s*(?<eventName>.+)\s+{$this->opt($this->t('is'))}/", $this->subject, $m)
            || preg_match("/(.*:\s*)?(✅|❗|?)\s*(?<eventName>.+?)\s+rezervasyonunuz onaylandı/u", $this->subject, $m)
        ) {
            $eventName = $m['eventName'];
        }

        if (empty($eventName)) {
            $eventName = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Have a question'))}]/preceding::a[1]/preceding::table[1][count(.//tr) = 2]/descendant::tr[1]");
        }
        // if (empty($eventName)) {
        //     $eventName = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Have a question'))}]/preceding::text()[normalize-space()][2]");
        // }

        if (empty($eventName)) {
            $eventName = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Good news, you’re going to'))}]", null, true, "/{$this->opt($this->t('Good news, you’re going to'))}\s*(.+)(?:\!|\.)/");
        }

        $eventAddress = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Have a question'))}]/preceding::a[1]/preceding::table[1][count(.//tr) = 2]/descendant::tr[2]",
            null, true, "/.*\d+.*/");
        // $eventAddress = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Have a question'))}]/preceding::text()[normalize-space()][1][not({$this->contains($this->t('Book another restaurant'))})]");

        if (empty($eventAddress) && !empty($eventName)) {
            $eventAddress = $this->http->FindSingleNode("//text()[{$this->eq($eventName)}]/following::text()[normalize-space()][1][not({$this->contains($this->t('Book another restaurant'))})]");
        }

        $e->setName($eventName);

        if (!empty($eventAddress)) {
            $e->setAddress($eventAddress);
        }
        $phone = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Have a question'))}]/ancestor::*[{$this->starts($this->t('Have a question'))}][last()]//img[contains(@src, '/phone_icon')]/ancestor::tr[1]",
            null, true, "/^[\W\d]{5,}$/");

        if (strlen(preg_replace('/\D+/', '', $phone)) > 5) {
            $e->place()
                ->phone($phone);
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        $this->subject = $parser->getSubject();

        $this->ParseEmail($email);

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

    protected function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
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
            return str_replace(' ', '\s+', preg_quote($s));
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

    private function assignLang()
    {
        foreach ($this->detectLang as $lang => $words) {
            foreach ($words as $word) {
                if ($this->http->XPath->query("//*[{$this->contains($word)}]")->length > 0) {
                    $this->lang = substr($lang, 0, 2);

                    return true;
                }
            }
        }

        return false;
    }
}
