<?php

namespace AwardWallet\Engine\gyg\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class TicketInstructions extends \TAccountChecker
{
    public $mailFiles = "gyg/it-217264945.eml, gyg/it-218928641.eml, gyg/it-219510589.eml, gyg/it-290171122-da.eml, gyg/it-295528287-nl.eml, gyg/it-297173035-it.eml, gyg/it-658984328.eml, gyg/it-716337518-pt.eml";
    public $subjects = [
        '/Reserva(?:\s*[A-Z\d]+\s*|\s+)confirmada\s*\|\s*Instruções sobre o ingresso$/', // pt
        '/Booking\s*[A-Z\d]+\s*er bekræftet\s*\|\s*Billetinstruktioner$/', // da
        '/Prenotazione\s*[A-Z\d]+\s*\w+\s*\|\s*\D+$/', // it
        '/Prenotazione confermata\s*\|\s*Istruzioni per i biglietti/', // it
        '/Bestelling bevestigd\s*\|\s*Ticket-instructies/', // nl
        '/Booking\s*[A-Z\d]+\s*\w+\s*\|\s*\D+$/', // en
        '/[A-Z\d]+\s+Your booking was canceled\s*$/', // en
        '/You successfully canceled your booking - [A-Z\d]+\s*$/', // en
        '/, votre réservation est confirmée !$/u', // fr
        '/[A-Z\d]+ Votre réservation a été annulée\s*$/u', // fr
    ];

    public $lang = 'en';

    public static $dictionary = [
        "pt" => [
            'junkPhrases'            => 'Esta atividade inclui serviço de busca. Informe ao fornecedor onde você gostaria de ser buscado antes do dia da sua atividade',
            'bookingReference'       => ['Código da reserva'],
            'locationHeader'         => ['No dia da atividade', 'Detalhes do serviço de busca'],
            'Where to go'            => ['Local do serviço de busca', 'Local de busca', 'Aonde ir'],
            'subnameFragment'        => ['(Idade', 'Agrupar até', '• Português', '• Espanhol', '• Inglês', '• albanês', '• Francês'],
            '_travellerName'         => 'Obrigado por seu pedido,',
            'travellerName_'         => ', seu pagamento foi aprovado',
            'Cancellation policy'    => 'Política de cancelamento',
            'Manage your booking'    => 'Gerenciar sua reserva',
            'Adult'                  => ['Adulto', 'Adultos', 'Estudante', 'Cidadão da UE'],
            'Child'                  => ['Jovens', 'Jovem', 'Bebê', 'Criança'],
            'at'                     => 'às',
            'hours'                  => ['horas', 'hora'],
            'minutes'                => 'minutos',
            'days'                   => 'dia',
            // "cancelled Reservation" => [""],
        ],
        "da" => [
            // 'junkPhrases' => '',
            'bookingReference'       => ['Bookingreference'],
            'locationHeader'         => ['Hvad du skal gøre på dagen'],
            'Where to go'            => 'Hvor du skal gå hen',
            'subnameFragment'        => ['(Alder', '• Spansk'],
            '_travellerName'         => 'Tak for din bestilling,',
            // 'travellerName_' => '',
            'Cancellation policy' => 'Afbestillingsregler',
            'Manage your booking' => 'Administrer din booking',
            'Adult'               => ['Voksne'],
            // 'Child' => '',
            'at'    => 'kl.',
            'hours' => 'timer',
            // 'minutes' => '',
            // 'days' => '',
            // "cancelled Reservation" => [""],
        ],
        "it" => [
            'junkPhrases'                   => "Il fornitore dell'attività ti contatterà per email o telefono il giorno prima dell'attività per fornirti le informazioni relative al servizio di prelievo.",
            'bookingReference'              => ['Codice di prenotazione'],
            'locationHeader'                => ["Il giorno dell'attività"],
            'Where to go'                   => ["Punto d'incontro", 'Luogo del prelievo', 'Punto di incontro'],
            'subnameFragment'               => ['anni)', '• Inglese', '• Italiano', '• Catalano'],
            '_travellerName'                => 'Grazie per la tua prenotazione,',
            'travellerName_'                => [', il pagamento è andato a buon fine', ', i tuoi biglietti sono in arrivo'],
            'Cancellation policy'           => 'Termini di cancellazione',
            'Manage your booking'           => 'Gestisci la tua prenotazione',
            'Adult'                         => ['Adulto', 'Adulti', 'Cittadini UE'],
            'Child'                         => ['Bambino', 'Neonato'],
            'at'                            => 'alle ore',
            'hours'                         => ['ore', 'ora'],
            'minutes'                       => 'minuti',
            // 'days' => '',
            // "cancelled Reservation" => [""],
        ],
        "nl" => [
            // 'junkPhrases' => '',
            'bookingReference'              => ['Reserveringsnummer'],
            'locationHeader'                => ['Op de dag van je activiteit'],
            'Where to go'                   => 'Trefpunt',
            'subnameFragment'               => ['(Leeftijd', 'Groep van max', '• Engels'],
            '_travellerName'                => 'Bedankt voor je bestelling,',
            'travellerName_'                => ', je betaling is gelukt',
            'Cancellation policy'           => 'Annuleringsbeleid',
            'Manage your booking'           => 'Beheer je boeking',
            'Adult'                         => 'Volwassenen',
            // 'Child' => '',
            'at'    => 'om',
            'hours' => 'uur',
            // 'minutes' => '',
            // 'days' => '',
            // "cancelled Reservation" => [""],
        ],
        "fr" => [
            // 'junkPhrases' => '',
            'bookingReference'              => ['Référence de réservation'],
            'locationHeader'                => ['Le jour J'],
            'Where to go'                   => 'Lieu de rendez-vous',
            'subnameFragment'               => ['ans) •', '• Anglais'],
            '_travellerName'                => 'Bedankt voor je bestelling,',
            'travellerName_'                => ', votre activité est réservée',
            'Cancellation policy'           => 'Conditions d’annulation',
            'Manage your booking'           => 'Gérez votre réservation',
            'Adult'                         => 'Adultes',
            // 'Child' => '',
            'at'    => 'à',
            'hours' => 'heures',
            // 'minutes' => '',
            // 'days' => '',
            "cancelled Reservation" => ["Votre réservation a bien été annulée", 'Réservation annulée', 'Votre réservation a été annulée'],
        ],
        "en" => [
            'junkPhrases' => [
                'This activity includes a pickup. Let the activity provider know where you’d like to be picked up before the date of your activity.',
                'This activity includes a pickup. Contact the activity provider when you have your pickup address, unless you’ve already heard from them.',
            ],
            'bookingReference'      => ['Booking reference'],
            'locationHeader'        => ['You need to add a pickup location', 'What to do on the day', 'You need to add a pickup point', 'You haven’t paid anything yet'],
            'Where to go'           => ['Where to go', 'Pickup location'],
            'subnameFragment'       => ['(Age', 'Group up to', '• English'],
            '_travellerName'        => 'Thanks for your order,',
            'travellerName_'        => ', your payment was successful',
            // 'Cancellation policy' => '',
            // 'Manage your booking' => '',
            'Adult'                 => ['Adult', 'Military', 'Youth', 'Student', 'EU Citizen'],
            // 'Child' => '',
            // 'at' => '',
            'hours'                 => ['hours', 'hour'],
            // 'minutes' => '',
            'days'                  => ['days', 'day'],
            "cancelled Reservation" => ["Your booking has been canceled", 'Canceled: activity discontinued', 'You successfully canceled your booking'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && (stripos($headers['from'], '@getyourguide.com') !== false || stripos($headers['from'], '.getyourguide.com') !== false)) {
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
        if ($this->http->XPath->query('//a[contains(@href,".getyourguide.com/") or contains(@href,"mail.getyourguide.com") or contains(@href,"&adj_deep_link=gyg")]')->length === 0
            && $this->http->XPath->query("//*[{$this->contains(['Get the GetYourGuide app', 'Access your activity with the GetYourGuide app', 'GetYourGuide Deutschland GmbH'])}]")->length === 0
        ) {
            return false;
        }

        return $this->assignLang();
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]getyourguide\.com$/', $from) > 0;
    }

    public function parseEvents(Email $email): void
    {
        $subnameRule = array_map(function ($item) {
            return '∆ ' . $item;
        }, (array) $this->t('Adult'));

        $xpathSubname = "({$this->contains($this->t('subnameFragment'))} or {$this->starts($subnameRule, 'translate(normalize-space(),"0123456789","∆∆∆∆∆∆∆∆∆∆")')})";
        $xpathAlert = '(ancestor::*[contains(translate(@style," ",""),"background:#e1f0ff") or contains(translate(@style," ",""),"background:#E1F0FF") or contains(@class,"alert--info")] or ' . $this->starts(['•', '-']) . ')';

        $patterns = [
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        ];

        $traveller = $this->http->FindSingleNode("//text()[{$this->starts($this->t('_travellerName'))}]", null, true, "/{$this->opt($this->t('_travellerName'))}\s*({$patterns['travellerName']})$/")
            ?? $this->http->FindSingleNode("//text()[{$this->contains($this->t('travellerName_'))}]", null, true, "/^({$patterns['travellerName']})\s*{$this->opt($this->t('travellerName_'))}$/")
        ;

        $junkStatuses = [];
        $nodes = $this->http->XPath->query("//text()[{$xpathSubname}][not({$xpathAlert})]/ancestor::tr[ descendant::text()[{$this->starts($this->t('bookingReference'))}] ][1]");

        foreach ($nodes as $i => $root) {
            $isJunk = $this->http->XPath->query("descendant::text()[{$this->starts($this->t('junkPhrases'))}]", $root)->length > 0;
            $junkStatuses[] = $isJunk;

            if ($isJunk) {
                $this->logger->debug("Event-{$i} is junk! Address is missing.");

                continue;
            }

            $e = $email->add()->event();
            $e->type()->event();

            if (!empty($this->http->FindSingleNode("(//*[" . $this->contains($this->t("cancelled Reservation")) . "])[1]"))) {
                $e->general()
                    ->cancelled()
                    ->status('Cancelled');
            }

            $priceText = $this->http->FindSingleNode("descendant::text()[{$xpathSubname}][not({$xpathAlert})]/following::text()[normalize-space()][1]", $root);

            if (preg_match("/^\s*(?<currency>[^\-\d)(]+?)\s*(?<total>\d[,.‘\'\d]*)\s*$/", $priceText, $m)
                || preg_match("/^\s*(?<total>\d[,.‘\'\d]*)\s*(?<currency>[^\-\d)(]+?)\s*$/", $priceText, $m)
            ) {
                // US$ 1,250.00    |    0,00 €
                $currency = $this->normalizeCurrency($m['currency']);
                $e->price()->currency($currency)->total(PriceHelper::parse($m['total'], $currency));
            }

            if (!empty($traveller)) {
                $e->general()
                    ->traveller($traveller);
            }

            $e->general()
                ->confirmation($this->http->FindSingleNode("descendant::text()[{$this->starts($this->t('bookingReference'))}][1]/following::text()[normalize-space()][1]", $root, true, "/^[A-Z\d]+$/"));

            $cancellation = implode(' ', $this->http->FindNodes("descendant::text()[{$this->eq($this->t('Cancellation policy'))}][1]/following::text()[normalize-space()][1]/ancestor::table[1]/descendant::text()[not({$this->contains($this->t('Cancellation policy'))} or {$this->contains($this->t('Manage your booking'))})]", $root));

            if (!empty($cancellation)) {
                $e->setCancellation($cancellation);
            }

            $e->setName($this->http->FindSingleNode("descendant::text()[{$xpathSubname}][not({$xpathAlert})]/preceding::text()[normalize-space()][1]", $root));

            $address = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('locationHeader'))}][1]/ancestor::table[1]/descendant::text()[{$this->eq($this->t('Where to go'))}]/following::text()[normalize-space()][1]", $root);

            if (empty($address) && $e->getCancelled()) {
            } else {
                $e->place()
                    ->address($address);
            }

            $phone = $this->http->FindSingleNode("descendant::text()[normalize-space()='Email' or normalize-space()='E-mail']/preceding::text()[normalize-space()][1]", $root, true, "/^[+(\d][-+. \d)(]{5,}[\d)]$/");
            $e->setPhone($phone, false, true);

            $dateStart = $timeStart = null;
            $dateStartVal = $this->http->FindSingleNode("descendant::text()[{$xpathSubname}][not({$xpathAlert})]/preceding::text()[normalize-space()][not({$xpathAlert})][2]", $root, true, '/^.*\d.*$/');

            if (preg_match("/^(?<date>.{4,}?\d{4})(?:\s+{$this->opt($this->t('at'))})?\s+(?<time>\d{1,2}(?:[.:：]\d{2})?(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?)$/u", $dateStartVal, $m)) {
                // 20. februar 2023 kl. 09.00
                $dateStart = $this->normalizeDate($m['date']);
                $timeStart = preg_replace('/(\d)[.](\d)/', '$1:$2', $m['time']);
            } elseif (preg_match("/^.{4,}\d{4}(?:\s*[•]|$)/", $dateStartVal) > 0) {
                // 20. februar 2023
                $dateStart = $this->normalizeDate(preg_replace('/^(.+?)\s+[•].*$/', '$1', $dateStartVal));
            }

            if ($timeStart) {
                $e->booked()->start(strtotime($timeStart, $dateStart));
            } else {
                $e->booked()->start($dateStart);
            }

            $guestsText = $this->http->FindSingleNode("descendant::text()[{$xpathSubname}][not({$xpathAlert})]", $root);

            if (preg_match_all("/\b(\d{1,3})\s*{$this->opt($this->t('Adult'))}/iu", $guestsText, $m)) {
                $e->booked()->guests(array_sum($m[1]));
            }

            if (preg_match_all("/\b(\d{1,3})\s*{$this->opt($this->t('Child'))}/iu", $guestsText, $m)) {
                $e->booked()->kids(array_sum($m[1]));
            }

            if (preg_match("/\b(?<hours>\d[\d.,]*)\s*{$this->opt($this->t('hours'))}(?:\s*[•]|\D*$)/iu", $guestsText, $m)
                || preg_match("/\b(?<minutes>\d[\d.,]*)\s*{$this->opt($this->t('minutes'))}(?:\s*[•]|\D*$)/iu", $guestsText, $m)
                || preg_match("/\b(?<days>\d[\d.,]*)\s*{$this->opt($this->t('days'))}(?:\s*[•]|\D*$)/iu", $guestsText, $m)
            ) {
                if (stripos($m['hours'], '.') !== false) {
                    $m['minutes'] = $m['hours'] * 60;
                    unset($m['hours']);
                }

                if (!empty($m['hours']) && $timeStart) {
                    $e->booked()
                        ->end(strtotime($m['hours'] . ' hours', $e->getStartDate()));
                } elseif (!empty($m['minutes']) && $timeStart) {
                    $e->booked()
                        ->end(strtotime($m['minutes'] . ' minutes', $e->getStartDate()));
                } elseif (!empty($m['days'])) {
                    $e->booked()->end(strtotime($m['days'] . ' days', $e->getStartDate()));
                } else {
                    $e->setNoEndDate(true);
                }
            } elseif ($e->getGuestCount() !== null) {
                $e->setNoEndDate(true);
            }

            $notes = implode(' ', $this->http->FindNodes("descendant::text()[{$this->eq($this->t('locationHeader'))}]/ancestor::table[1]/descendant::text()[normalize-space()][not({$this->contains($this->t('locationHeader'))})]", $root));

            if (!empty($notes)) {
                $e->setNotes($notes);
            }
        }

        if (count(array_unique($junkStatuses)) === 1 && $junkStatuses[0] === true) {
            // it-218928641.eml
            $email->setIsJunk(true, 'Event(s) address is missing');
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();
        $this->parseEvents($email);

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

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'contains(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'normalize-space(' . $node . ')=' . $s;
        }, $field)) . ')';
    }

    private function starts($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'starts-with(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!empty($phrases['bookingReference']) && $this->http->XPath->query("//*[{$this->contains($phrases['bookingReference'])}]")->length > 0
                && (!empty($phrases['locationHeader']) && $this->http->XPath->query("//*[{$this->contains($phrases['locationHeader'])}]")->length > 0
                    || !empty($phrases['cancelled Reservation']) && $this->http->XPath->query("//*[{$this->contains($phrases['cancelled Reservation'])}]")->length > 0
                )
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
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

    private function normalizeDate($str)
    {
        $in = [
            "/^\s*([[:alpha:]]+)[.\s]*(\d{1,2})\s*,\s*(\d{4})\s*$/u", // January 7, 2023
            "/^\s*(\d{1,2})[.\s]*(?:de\s+)?([[:alpha:]]+)[.\s]*(?:de\s+)?(\d{4})\s*$/iu", // 6. November 2022
        ];
        $out = [
            "$2 $1 $3",
            "$1 $2 $3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function normalizeCurrency(string $string): string
    {
        $string = trim($string);
        $currencies = [
            'AED' => ['د.إ'],
            'GBP' => ['£'],
            'EUR' => ['€'],
            'PHP' => ['₱'],
            'ZAR' => ['R'],
            '$'   => ['$'],
            'INR' => ['Rs.'],
            'USD' => ['US$'],
            'AUD' => ['A$'],
            'CAD' => ['C$'],
        ];

        foreach ($currencies as $code => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $code;
                }
            }
        }

        return $string;
    }
}
