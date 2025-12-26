<?php

namespace AwardWallet\Engine\sevenrooms\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class YourReservation extends \TAccountCheckerExtended
{
    public $mailFiles = "sevenrooms/it-119008128.eml, sevenrooms/it-119878114.eml, sevenrooms/it-173892316.eml, sevenrooms/it-206291742.eml, sevenrooms/it-365621411.eml, sevenrooms/it-446545074.eml, sevenrooms/it-49509598.eml, sevenrooms/it-50115295.eml, sevenrooms/it-50532943.eml, sevenrooms/it-68914514.eml, sevenrooms/it-716438063.eml";
    public static $dictionary = [
        'pt' => [
            'Contact'                    => ['Contacto', 'Contato'],
            'btnText'                    => ['gerir reserva', 'gerenciar reserva', 'modificar reserva'],
            'btn2Text'                   => ['cancelar reserva'],
            'add to calendar'            => ['adicionar ao calendário'],
            //'cancelledPhrases'           => [''],
            //'statusPhrases'              => [''],
            //'statusVariants'             => [''],
            'Your reservation number is' => ['o seu número de reserva é', 'o seu número de reservas é'],
            'mailed by'                  => ['enviado por', 'alimentado pelo'],
            // 'imgLink' => '',
            //'startName' => [''],
            'Your Reservation at'   => ['Reservado para', 'A sua reserva no'],
            'for'                   => 'para',
            'on'                    => 'às',
            'guest'                 => ['pessoas'],
            'NameAfter_mailedby_RE' => 'Você está recebendo este email de (.+) através de Sevenrooms',
        ],
        'it' => [ // it-119008128.eml
            'Contact'                    => 'Contatto',
            'btnText'                    => ['CONFERMA PRENOTAZIONE', 'modifica prenotazione', 'gestisci prenotazione', 'COMPLETA PRENOTAZIONE', 'VISUALIZZA EVENTO'],
            'btn2Text'                   => ['o cancella la prenotazione', 'cancella la prenotazione', 'sito web'],
            //            'add to calendar' => [''],
            'cancelledPhrases'           => ['Questa prenotazione è stata cancellata', 'abbiamo provveduto ad annullare la prenotazione', 'abbiamo provveduto a cancellare la prenotazione'],
            'statusPhrases'              => ['La Sua prenotazione in'],
            'statusVariants'             => ['è confermata'],
            'Your reservation number is' => ['Il tuo numero di prenotazione è'],
            'mailed by'                  => 'spedito da',
            // 'imgLink' => '',
            // 'startName' => [''],
            'Your Reservation at'   => ['Conferma la tua prenotazione presso', 'La Sua prenotazione in', 'Grazie per la tua prenotazione a', 'la tua prenotazione a', 'La tua prenotazione presso'],
            'for'                   => 'per',
            'on'                    => 'il',
            'guest'                 => 'ospiti',
            'NameAfter_mailedby_RE' => 'Hai ricevuto questa email da (.+) tramite SevenRooms',
        ],
        'fr' => [ // it-119878114.eml
            'Contact'                    => 'Contact',
            'btnText'                    => ['ajouter au calendrier', 'CONFIRMER LA RÉSERVATION', 'Confirmer votre réservation', 'Modifier votre réservation', 'AJOUTER UNE CARTE DE CRÉDIT'],
            'btn2Text'                   => ['modifier la réservation', 'ou annuler la réservation', 'Annuler votre réservation', 'carte'],
            'add to calendar'            => ['ajouter au calendrier'],
            'cancelledPhrases'           => ['Cette réservation a été annulée',
                'Votre réservation a bien été annulée',
                'Votre réservation a été annulée',
                'Nous avons pris bonne note de votre annulation.',
            ],
            'statusPhrases'              => ['Cette réservation a été', 'Votre réservation a bien été'],
            'statusVariants'             => ['annulée'],
            'Your reservation number is' => ['Votre numéro de réservation est'],
            'mailed by'                  => ['envoyé par', 'alimenté par'],
            // 'imgLink' => '',
            //'startName' => [''],
            'Your Reservation at'   => ['Confirmez votre réservation à', 'Votre réservation à', 'Votre réservation chez', 'Votre réservation au'],
            'for'                   => 'pour',
            'on'                    => 'le',
            'guest'                 => ['person', 'personnes'],
            'NameAfter_mailedby_RE' => 'Vous recevez cet e-mail de (.+) via SevenRooms',
        ],
        'en' => [
            'Contact'          => ['Contact', 'Address'],
            'btnText'          => ['add to calendar', 'Click below to confirm your reservation', 'CONFIRM RESERVATION', 'll be there after tomorrow!', 'BOOK OR DECLINE THIS RESERVATION', 'map'],
            'btn2Text'         => ['or cancel reservation', 'manage your subscription preferences', 'Manage Your Subscription', 'modify reservation', 'map', 'cancel reservation', 'website'],
            'cancelledPhrases' => ['This reservation has been cancelled', 'This reservation has been canceled', 'cancel your reservation at',
                'Your reservation has been cancelled', 'has been cancelled as per your request',
                'We are sorry to hear that you won’t be able to make your reservation',
                'Your reservation has been canceled',
                'We are sorry to see you go.',
                'We\'re truly sorry that you won\'t be able to attend your reservation.',
                'We\'re sorry to hear of your cancellation',
                'We\'re sorry you had to cancel your reservation',
                'has been cancelled',
                'has now been cancelled', 'and we have cancelled your reservation today',
            ],
            // 'statusPhrases' => [''],
            'statusVariants'             => ['cancelled', 'canceled'],
            'Your reservation number is' => ['Your reservation number is', 'Your reservation code is'],
            'mailed by'                  => ['mailed by', 'powered by', 'experience by', 'You are receiving this email from'],
            // 'imgLink' => 'https://www.sevenrooms.com/.h/download/',
            'startName' => [
                'Agradecemos e confirmamos a sua reserva no', // pt
                'We look forward to having you at',
            ],
            'NameAfter_mailedby_RE' => 'You are receiving this email from (.+) through SevenRooms',
        ],
    ];
    private $detectFrom = ["@sevenrooms.com", '.sevenrooms.com'];
    private $detectSubject = [
        "pt" => ["A sua reserva no", "a sua reserva no"],
        "it" => ["Conferma la tua prenotazione presso",
            'La tua prenotazione presso',
            'La tua prenotazione a',
            'La Sua prenotazione deve essere completata',
        ],
        "fr" => ["Votre réservation à",
            'Annulation de réservation pour',
            'Mise à jour: votre réservation à',
            'Votre réservation au',
            'Votre réservation chez',
            'Annulation de votre réservation pour',
        ],
        "en" => ["Your Reservation at", "Confirm your reservation at", "Reservation Cancellation for",
            "Booking CONFIRMATION at",
        ],
    ];
    private $lang = "en";
    private $Subject;

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->Subject = $parser->getSubject();
        $this->assignLang();
        $this->parseHtml($email);
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return $this->containsText($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->containsText($headers["from"], $this->detectFrom) === false) {
            return false;
        }

        foreach ($this->detectSubject as $re) {
            foreach ($re as $subject) {
                if (stripos($headers["subject"], $subject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        // Detect Provider
        if ($this->http->XPath->query('//text()[contains(.,"www.sevenrooms.com") or contains(.,"@sevenrooms.com") or contains(.,"SevenRooms")]')->length === 0
            && $this->http->XPath->query('//a[contains(@href,"www.sevenrooms.com") or contains(@href,".sevenrooms.com/")]')->length === 0
        ) {
            return false;
        }

        // Detect Language and Format
        return $this->assignLang();
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function parseHtml(Email $email): void
    {
        $patterns = [
            'phone' => '[+(\d][-+. \d)(]{5,}[\d)]', // +377 (93) 15 48 52  |  (+351) 21 342 09 07  |  713.680.2992
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        ];

        // Travel Agency
        if (empty($this->http->FindSingleNode("//text()[{$this->eq($this->t('mailed by'))}]/preceding::text()[normalize-space()][1]", null, false, "/.*\b[A-Z\d]{5,}$/"))) {
            $email->obtainTravelAgency();
        }

        $e = $email->add()->event();

        $confirmationNumber = $this->http->FindSingleNode("//td[{$this->starts($this->t('Your reservation number is'))}]", null, true, "/{$this->opt($this->t('Your reservation number is'))}\s+(.+)/");

        if (!empty($confirmationNumber)) {
            $confirmationNumber = $this->re("/^([A-Z\d\-]+)$/", str_replace(' ', '', $confirmationNumber));
            $e->general()->confirmation($confirmationNumber);
        } elseif ($this->http->XPath->query("//td[{$this->starts($this->t('Your reservation number is'))}]")->length === 0) {
            $e->general()
                ->noConfirmation();
        }

        $e->type()->event();

        if ($this->http->XPath->query("//text()[{$this->contains($this->t('Click below to confirm your reservation'))}]")->length > 0) {
            $e->general()->status('not confirmed');
        }

        $status = $this->http->FindSingleNode("//text()[{$this->contains($this->t('statusPhrases'))}]", null, true, "/{$this->opt($this->t('statusPhrases'))}.+?({$this->opt($this->t('statusVariants'))})(?:\s*[,.:;!?]|$)/");

        if ($status) {
            $e->general()->status($status);
        }

        if ($this->http->XPath->query("//node()[{$this->contains($this->t('cancelledPhrases'))}]")->length > 0) {
            $e->general()
                ->status('cancelled')
                ->cancelled();
        }

        $traveller = $this->http->FindSingleNode("//*[contains(@class,'client-name')]", null, true, "/^{$patterns['travellerName']}$/u")
            ?? $this->re("/\s[|]\s*(?: Mr | Ms )?\s*({$patterns['travellerName']})\s+{$this->opt($this->t('on'))}\s/u", $this->Subject)
        ;

        $ruleChanged = "not(ancestor-or-self::*[{$this->contains('text-decoration:line-through', "translate(@style,' ','')")}])";
        $xpath = "//div[ contains(@class,'details') and (following::div[normalize-space()][1][{$this->contains($this->t('btnText'))} or {$this->contains($this->t('btn2Text'))}] or preceding::div[normalize-space()][1][contains(@class,'client-name')]) ]/descendant::text()[normalize-space()][{$ruleChanged}]";
        $mainText = implode("\n", $this->http->FindNodes($xpath));

        if (empty($mainText)) {
            $xpath = "//text()[{$this->contains($this->t('add to calendar'))} or contains(normalize-space(),'cancel reservation')]/ancestor::table[1]/descendant::text()[normalize-space()][{$ruleChanged}]";
//            $this->logger->debug('$xpath 2 = '.print_r( $xpath,true));
            $mainText = implode("\n", $this->http->FindNodes($xpath));
        }

        if (empty($mainText) && $traveller) {
            // it-68914514.eml
            $xpath = "//text()[{$this->eq($traveller)} or ancestor::*[contains(@class,'client-name')]]/ancestor::table[1]/descendant::text()[normalize-space()][{$ruleChanged}]";
//            $this->logger->debug('$xpath 3 = '.print_r( $xpath,true));
            $mainText = implode("\n", $this->http->FindNodes($xpath));
        }

        if (empty($mainText) && $traveller) {
            // Christie Martinez DDS
            $xpath = "//text()[{$this->eq(str_replace(' Dds', ' DDS', $traveller))}]/ancestor::table[1]/descendant::text()[normalize-space()][{$ruleChanged}]";
//            $this->logger->debug('$xpath 4 = '.print_r( $xpath,true));
            $mainText = implode("\n", $this->http->FindNodes($xpath));
        }

//        $this->logger->debug('$mainText = '.print_r( $mainText,true));

        $dateRE = '([-[:alpha:]]+\s*\,?\s*\d{1,2}\s+[[:alpha:]]+[\s,]+\d{4}|[-[:alpha:]]+\s*[,\s]\s*[[:alpha:]]+\s+\d{1,2}\s*,\s*\d{4})';

        if (empty($traveller)
            && preg_match("/^ *([A-Z][a-z]+(?:[ \-][A-Z][a-z]+){1,2})\n\s*" . $dateRE . "/u", $mainText, $m)
        ) {
            $traveller = $m[1];
        }

        if (preg_match('/\bEmail\b/i', $traveller)) {
            $traveller = null;
        }

        if ($traveller) {
            $e->general()->traveller($traveller);
        }

        $dateValue = $this->re('/' . $dateRE . '/u', $mainText);
        $date = strtotime($this->normalizeDate($dateValue));

        $time = '';
        $timeEnd = '';

        if (preg_match("/(?<start>\d{1,2}:\d{2}(?:\s*[ap]m)?)\s+-\s+(?<end>\d{1,2}:\d{2}(?:\s*[ap]m)?)(?:\s*\n\D+|\D*$)/is", $mainText, $m)
            || preg_match("/(?<start>\d{1,2}:\d{2}(?:\s*[ap]m)?)(?:\s*\n\D+|\D*$)/is", $mainText, $m)
        ) {
            $time = $m['start'];

            if (isset($m['end']) && !empty($m['end'])) {
                $timeEnd = $m['end'];
            }
        }
        /*$time = $this->re('/(\d{1,2}:\d{2}(?:\s*[ap]m)?)/i', $mainText);
        $timeEnd = $this->re('/\d{1,2}:\d{2}(?:\s*[ap]m)?\s+-\s+(\d{1,2}:\d{2}(?:\s*[ap]m)?)/i', $mainText);*/

        if (empty($time) && empty($timeEnd)
            && !preg_match("/\b\d{1,2} ?[:h\.] *\d{2}\b/", $mainText)
        ) {
            $e->booked()
                ->noStart()
                ->noEnd();
        }

        if (!empty($date) && !empty($time)) {
            $e->booked()->start(strtotime($time, $date));

            if (!empty($timeEnd)) {
                $e->booked()->end(strtotime($timeEnd, $date));

                if ($e->getEndDate() < $e->getStartDate()) {
                    $e->booked()->end(strtotime("+1 day", $e->getEndDate()));
                }
            } else {
                $e->booked()->noEnd();
            }
        }

        $guests = $this->re("/{$this->opt($this->t('for'))}?\s*(\d{1,3})\s+{$this->opt($this->t('guest'))}/i", $mainText);

        if (!empty($guests)) {
            $e->booked()->guests($guests);
        }

        $phone = $this->http->FindSingleNode("//div[{$this->eq($this->t('Contact'))}]/following-sibling::div[normalize-space()][2]", null, true, "/^{$patterns['phone']}$/");
        $e->place()->phone($phone, false, true);

        $totalPrice = $this->http->FindSingleNode("//tr[not(.//tr) and count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('total'))}]]/*[normalize-space()][2]");

        if (preg_match('/^(?<currency>[^\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*)$/', $totalPrice, $matches)) {
            // $9.79
            $currency = $this->normalizeCurrency($matches['currency']);
            $e->price()->currency($currency)->total(PriceHelper::parse($matches['amount'], $currency));

            $matches['currency'] = trim($matches['currency']);

            $baseFare = $this->http->FindSingleNode("//tr[not(.//tr) and count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('sub total'))}]]/*[normalize-space()][2]");

            if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.\'\d ]*?)$/', $baseFare, $m)) {
                $e->price()->cost(PriceHelper::parse($m['amount'], $currency));
            }

            $taxes = $this->http->FindSingleNode("//tr[not(.//tr) and count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('tax'))}]]/*[normalize-space()][2]");

            if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.\'\d ]*?)$/', $taxes, $m)) {
                $e->price()->tax(PriceHelper::parse($m['amount'], $currency));
            }
        }

        $name = $this->re("/{$this->opt($this->t('Your Reservation at'))}\s+(.{3,}?)(?:\s+{$this->opt($this->t('for'))}\s+{$patterns['travellerName']}|\s*\|.+)\s+{$this->opt($this->t('on'))}\s/iu", $this->Subject)
            ?? $this->re("/Reservation Cancell?ation for\s+(\D{3,}?)\s*\|/", $this->Subject)
            ?? $this->re("/{$this->opt($this->t('Your Reservation at'))}\s+(\D{3,}?)\s*\|/", $this->Subject)
            ?? $this->re("/Greetings from\s+(\D{3,}?)\s*We\s*have/", $this->Subject)
        ;

        if (empty($name)) {
            $str = implode("\n", $this->http->FindNodes("//text()[{$this->eq($this->t('mailed by'))}]/following::text()[normalize-space()][position() < 5]"));
            $name_temp = $this->re("/(?:^|\n){$this->t('NameAfter_mailedby_RE')}(?:\n|$)/u", $str);

            if (!empty($name_temp) && preg_match("/ " . preg_quote($name_temp) . "(?: |$)/", $this->Subject)) {
                $name = $name_temp;
            } else {
                $name_tempS = $this->re("/{$this->opt($this->t('Your Reservation at'))}\s+(?:the\s+)?(.{3,}?)\W$/iu", $this->Subject);

                if (stripos($name_temp, $name_tempS) !== false) {
                    $name = $name_tempS;
                }
            }
        }

        if (empty($name)) {
            $name_temp = $this->http->FindSingleNode("//text()[{$this->contains($this->t('startName'))}]", null, true, "/{$this->opt($this->t('startName'))}\s+(.{4,}?)(?:\s*[,.;:!?]|$)/");

            if ($this->http->XPath->query("//text()[{$this->contains($name_temp)}]")->length > 1) {
                $name = $name_temp;
            }
        }

        if (empty($name)) {
            $name_temp = $this->re("/{$this->opt($this->t('Your Reservation at'))}\s+(.{3,}?)(?:\s*\||[!]|$)/iu", $this->Subject);

            if ($this->http->XPath->query("//text()[{$this->contains($name_temp)}]")->length > 0) {
                $name = $name_temp;
            }
        }

        if (empty($name)) {
            $str = implode("\n", $this->http->FindNodes("//text()[{$this->eq($this->t('mailed by'))}]/following::text()[normalize-space()][position() < 5]"));
            $name = $this->re("/(?:^|\n){$this->t('NameAfter_mailedby_RE')}(?:\n|$)/u", $str);
        }

        if (!empty($name)) {
            $name = str_replace(['<', '>'], '', $name);
            $e->place()
                ->name($name);
        }

        $address = $this->http->FindSingleNode("(//*[not(.//tr) and {$this->eq($this->t('Contact'))}]/following-sibling::*[normalize-space()][1])[1]");

        if (!empty($address)) {
            $e->place()
                ->address($address);
        }

        $cancellation = $this->http->FindSingleNode("//div[ preceding::div[{$this->eq($this->t('Cancellation Policy'))}] and following::div[{$this->starts($this->t('Your reservation number is'))}] ]/descendant::text()[string-length(normalize-space())>2]");

        if (mb_strlen($cancellation) > 2000) {
            $ratePolicies = preg_replace('/(\d)\.(\d{2})/', '$1:$2', $cancellation); // 6.00pm  ->  6:00pm
            $ratePoliciesParts = preg_split('/[.]+\s*\b/', $ratePolicies);
            $ratePoliciesParts = array_filter($ratePoliciesParts, function ($item) {
                return stripos($item, 'cancel') !== false;
            });
            $cancellationText = implode('. ', $ratePoliciesParts);

            if (mb_strlen($cancellation) < 2000) {
                $e->general()->cancellation($cancellationText);
            }
        } elseif ($cancellation) {
            $e->general()->cancellation($cancellation);
        }
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang)) {
                continue;
            }

            if (!empty($phrases['Contact']) && $this->http->XPath->query("//text()[{$this->contains($phrases['Contact'])}]")->length > 0
                && !empty($phrases['btnText']) && $this->http->XPath->query("//text()[{$this->contains($phrases['btnText'])}]")->length > 0
                && !empty($phrases['btn2Text']) && $this->http->XPath->query("//text()[{$this->contains($phrases['btn2Text'])}]")->length > 0
                || !empty($phrases['cancelledPhrases']) && $this->http->XPath->query("//text()[{$this->contains($phrases['cancelledPhrases'])}]")->length > 0
//                && !empty($phrases['Your reservation number is']) && $this->http->XPath->query("//text()[{$this->contains($phrases['Your reservation number is'])}]")->length > 0
//                && !empty($phrases['mailed by']) && $this->http->XPath->query("//text()[{$this->contains($phrases['mailed by'])}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
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
        $field = (array)$field;
        if (count($field) === 0)
            return 'false()';
        return '(' . implode(' or ', array_map(function($s) use($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';
            return 'starts-with(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    /**
     * Dependencies `use AwardWallet\Engine\MonthTranslate` and `$this->lang`.
     *
     * @param string|null $text Unformatted string with date
     */
    private function normalizeDate(?string $text): ?string
    {
//        $this->logger->debug('$text = '.print_r( $text,true));
        if (preg_match('/^[-[:alpha:]]+\s*[,\s]\s*([[:alpha:]]+)\s+(\d{1,2})\s*,\s*(\d{4})$/u', $text, $m)) {
            // Friday, August 16, 2019
            $month = $m[1];
            $day = $m[2];
            $year = $m[3];
        } elseif (preg_match('/^[-[:alpha:]]+\s*\,?\s*(\d{1,2})\s+([[:alpha:]]+)[\s,]+(\d{4})$/u', $text, $m)) {
            // venerdì, 22 ottobre 2021
            $day = $m[1];
            $month = $m[2];
            $year = $m[3];
        }

        if (isset($day, $month, $year)) {
            if (preg_match('/^\s*(\d{1,2})\s*$/', $month, $m)) {
                return $m[1] . '/' . $day . ($year ? '/' . $year : '');
            }

            if (($monthNew = MonthTranslate::translate($month, $this->lang)) !== false) {
                $month = $monthNew;
            }

            return $day . ' ' . $month . ($year ? ' ' . $year : '');
        }

        return null;
    }

    private function normalizeCurrency(string $string): string
    {
        $string = trim($string);
        $currences = [
            'USD' => ['US$'],
        ];

        foreach ($currences as $currencyCode => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $currencyCode;
                }
            }
        }

        return $string;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function opt($field): string
    {
        $field = (array)$field;
        if (count($field) === 0)
            return '';
        return '(?:' . implode('|', array_map(function($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array)$field;
        if (count($field) === 0)
            return 'false()';
        return '(' . implode(' or ', array_map(function($s) use($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';
            return 'contains(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function containsText($text, $needle): bool
    {
        if (empty($text) || empty($needle)) {
            return false;
        }

        if (is_array($needle)) {
            foreach ($needle as $n) {
                if (stripos($text, $n) !== false) {
                    return true;
                }
            }
        } elseif (is_string($needle) && stripos($text, $needle) !== false) {
            return true;
        }

        return false;
    }
}
