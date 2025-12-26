<?php

namespace AwardWallet\Engine\lufthansa\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

// parsers with similar formats: lufthansa/CheckIn3, lufthansa/UpcomingTrip

class ChangeOfReservation2 extends \TAccountChecker
{
    public $mailFiles = "lufthansa/it-164794973.eml, lufthansa/it-294674074.eml, lufthansa/it-620972474.eml, lufthansa/it-621080774.eml, lufthansa/it-793739225.eml, lufthansa/it-817045965.eml, lufthansa/it-866188090.eml";

    public $providers = [
        'lufthansa' => [
            'from'     => '.lufthansa.com',
            'bodyText' => ['Your Lufthansa Team'],
            'bodyUrl'  => ['.lufthansa.com'],
        ],

        'swissair' => [
            'from'     => '.swiss.com',
            'bodyText' => ['Your SWISS Team'],
            'bodyUrl'  => ['.swiss.com'],
        ],

        'austrian' => [
            'from'     => '.austrian.com',
            'bodyText' => [],
            'bodyUrl'  => ['.austrian.com'],
        ],

        'brussels' => [
            'from'     => '.brusselsairlines.com',
            'bodyText' => [],
            'bodyUrl'  => ['.brusselsairlines.com'],
        ],
    ];

    private $detectFrom = "online@booking.lufthansa.com";
    private $detectSubject = [
        // en
        'Change of Reservation | Departure:', // Change of Reservation | Departure: 15 August 2022
        'Please confirm: changes to your itinerary |',
        'You have been rebooked | Your trip to ',
        // de
        'Bitte bestätigen Sie: Änderungen an Ihrem Reiseplan | Abflug:',
        'Buchungsänderung | Abflug:',
        'Bestätigung Ihres neuen Reiseplans | Flug',
        'Bitte bestätigen Sie: Änderungen an Ihrem Reiseplan | ',
        'Sie wurden umgebucht | Ihre Reise nach',
        // it
        'La preghiamo di confermare: modifiche al suo itinerario |',
        // ko
        '루프트한자 새로운 항공편 일정 확약 | ',
        // es
        'Cambio de reserva |',
        'Hemos cambiado su reserva | Su viaje con destino a',
        // fr
        'Merci de confirmer: changement dans votre réservation | ',
        'Changement dans votre réservation |',
        'Votre réservation a été modifiée | Votre voyage vers',
        // pt
        'Alterações ao seu itinerário |',
    ];
    private $detectBody = [
        'en' => [
            'Your itinerary has changed',
            'Your flight number changed',
            'Your flight has been cancelled',
            'We had to cancel your flights',
            'Confirmation of your new itinerary',
            'Please review the new itinerary',
            'You have been rebooked',
        ],
        'de' => [
            'Ihr Reiseplan hat sich geändert',
            'Bestätigung Ihres neuen Reiseplans',
            'Ihr Flug wurde annulliert',
            'Details zu Ihrem aktualisierten Ticket',
            'Eine Ihrer Flugnummern hat sich geändert',
            'Sie wurden umgebucht',
        ],
        'it' => [
            'l suo itinerario è cambiato',
            'Il suo nuovo itinerario',
        ],
        'ko' => [
            '새로운 항공편 일정 확약',
        ],
        'es' => [
            'Se han producido cambios en tu itinerario',
            'Hemos cambiado su reserva',
        ],
        'fr' => [
            'Votre voyage a été modifié',
            'Votre réservation a été modifiée',
        ],
        'pt' => [
            'O seu itinerário foi alterado',
            'Tivemos de fazer alguns ajustes ao nosso horário de voo',
        ],
    ];

    private $lang;
    private static $dictionary = [
        'en' => [
            'Your booking code:'                  => ['Your booking code:', 'Your booking code', 'Booking Code:', 'Booking code:'],
            'your booking code is not displayed.' => 'your booking code is not displayed.',
            'Status'                              => 'Status',
            'cancelledStatus'                     => 'cancelled',
            'operated by:'                        => ['operated by:', 'operado por:', 'uitgevoerd door:', 'Operated by', 'выполняется'], // operado por - not error
            'Your flight has been cancelled'      => ['Your flight has been cancelled', 'We had to cancel your flights'],
            'Class'                               => ['Class', 'Business', 'Economy'],
            'Class Title'                         => ['класс'],
            // 'Passenger(s) and ticket number(s)' => '',
            // 'Ticket number' => '',
            'Hi '                                 => ['Hi ', 'Dear '],
        ],
        'de' => [
            'Your booking code:' => ['Ihr Buchungscode:', 'Ihr Buchungscode', 'Buchungscode:'],
            //            'your booking code is not displayed.' => '',
            'Status'             => 'Status',
            'cancelledStatus'    => ['storniert', 'Abgesagt'],
            'operated by:'       => ['durchgeführt von:', 'Durchgeführt von'],
            'Class'              => ['Business', 'Economy'],
            // 'Class Title' => '',
            // 'Passenger(s) and ticket number(s)' => '',
            // 'Ticket number' => '',
            'Hi ' => 'Guten Tag ',
        ],
        'it' => [
            'Your booking code:' => ['Il suo codice di prenotazione:', 'Codice di prenotazione:'],
            //            'your booking code is not displayed.' => '',
            'Status'             => ['Status', 'Stato del volo'],
            //            'cancelledStatus'    => '',
            'operated by:'       => ['operato da', 'Effettuato da:'],
            'Class'              => ['Classe', 'Business', 'Economy'],
            'Class Title'        => ['Classe'],
            // 'Passenger(s) and ticket number(s)' => '',
            // 'Ticket number' => '',
            'Hi '                => 'Buongiorno ',
        ],
        'ko' => [
            'Your booking code:' => '예약 코드:',
            //            'your booking code is not displayed.' => '',
            'Status'             => '상태',
            //            'cancelledStatus'    => '',
            'operated by:'       => '운항사:',
            'Class'              => ['Business', 'Economy'],
            // 'Class Title' => '',
            // 'Passenger(s) and ticket number(s)' => '',
            // 'Ticket number' => '',
            //'Hi ' => '',
        ],
        'es' => [
            'Your booking code:' => ['Su código de reserva:', 'Código de reserva:', 'Tu código de reserva:'],
            //            'your booking code is not displayed.' => '',
            'Status'             => ['Estado', 'Status'],
            'cancelledStatus'    => 'Cancelado',
            'operated by:'       => ['operado por:', 'Operado por:'],
            'Class'              => ['Business', 'Economy'],
            'Class Title'        => 'Class',
            // 'Passenger(s) and ticket number(s)' => '',
            // 'Ticket number' => '',
            'Hi '                => 'Buenos días,',
        ],
        'fr' => [
            'Your booking code:'                  => ['Votre numéro de réservation', 'Votre code de réservation:', 'Code de réservation:'],
            'your booking code is not displayed.' => 'Conformément à votre demande, votre code de réservation ne s\'affiche pas.',
            'Status'                              => ['Statut', 'Status'],
            'cancelledStatus'                     => 'annulé',
            'operated by:'                        => ['opéré par', 'Opéré par :'],
            'Class'                               => ['Class', 'Business', 'Economy'],
            'Class Title'                         => 'Class',
            // 'Passenger(s) and ticket number(s)' => '',
            // 'Ticket number' => '',
            'Hi '                                 => ['Bonjour ', 'Cher/Chère '],
        ],
        'pt' => [
            'Your booking code:'                  => ['Your booking code', 'O seu código de reserva'],
            // 'your booking code is not displayed.' => '',
            'Status'                              => 'Estatuto',
            'cancelledStatus'                     => 'Cancelled',
            'operated by:'                        => 'Operado por',
            'Class'                               => ['Business', 'Economy', 'Economy Class/ Economy Classic'],
            // 'Class Title' => '',
            // 'Passenger(s) and ticket number(s)' => '',
            // 'Ticket number' => '',
            'Hi '                                 => 'Dear ',
        ],
    ];

    private $patterns = [
        'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        'eTicket' => '\d{3}(?: | ?- ?)?\d{5,}(?: | ?[-\/] ?)?\d{1,3}', // 175-2345005149-23  |  1752345005149/23
    ];

    private $travellers = [], $tickets = [];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        $detectedFrom = false;

        foreach ($this->providers as $fDetect) {
            if (!empty($fDetect['from']) && $this->containsText($headers['from'], $fDetect['from']) === true) {
                $detectedFrom = true;

                break;
            }
        }

        if ($detectedFrom !== true) {
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
        $detectedProv = false;

        foreach ($this->providers as $pDetect) {
            if (!empty($pDetect['bodyText']) && $this->http->XPath->query("//*[{$this->contains($pDetect['bodyText'])}]")->length > 0
                || !empty($pDetect['bodyUrl']) && $this->http->XPath->query("//a[{$this->contains($pDetect['bodyUrl'], '@href')}]")->length > 0
            ) {
                $detectedProv = true;

                break;
            }
        }

        if ($detectedProv !== true) {
            return false;
        }

        foreach ($this->detectBody as $detectBody) {
            if ($this->http->XPath->query("//*[{$this->contains($detectBody)}]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    public static function getEmailProviders()
    {
        return ['lufthansa', 'austrian', 'swissair', 'brussels'];
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        foreach ($this->detectBody as $lang => $detectBody) {
            if ($this->http->XPath->query("//*[{$this->contains($detectBody)}]")->length > 0) {
                $this->lang = $lang;

                break;
            }
        }

        // foreach (self::$dictionary as $lang => $dict) {
        //     if (!empty($dict['Your booking code:']) && $this->http->XPath->query("//*[{$this->contains($dict['Your booking code:'])}]")->length > 0) {
        //         $this->lang = $lang;
        //
        //         break;
        //     }
        //
        //     if (!empty($dict['your booking code is not displayed.']) && $this->http->XPath->query("//*[{$this->contains($dict['your booking code is not displayed.'])}]")->length > 0) {
        //         $this->lang = $lang;
        //
        //         break;
        //     }
        // }

        if (empty($this->lang)) {
            $this->logger->debug("can't determine a language");

            return $email;
        }

        if ($this->http->XPath->query("//text()[{$this->eq($this->t("Status"))}]/ancestor::tr[1][following::tr[not(.//tr)][normalize-space()][2]]")->length > 0) {
            $this->parseHtml($email);
        // } elseif ($this->http->XPath->query("//text()[{$this->starts($this->t('operated by:'))}]/following::text()[{$this->contains($this->t('Class'))}]/ancestor::table[1]")->length > 0) {
        } elseif ($this->http->XPath->query("//tr[count(*) > 1][*[position() > 1][normalize-space()][last()][{$this->starts($this->t('operated by:'))}]][following-sibling::*[normalize-space()][.//img[contains(@src, 'airplane-outbound')]]]/ancestor::table[1]")->length > 0) {
            $this->parseHtml2($email);
        }

        if ($this->http->XPath->query("//tr[count(*) > 1][*[position() > 1][normalize-space()][last()][{$this->starts($this->t('operated by:'))}]][following-sibling::*[normalize-space()][.//img[contains(@src, 'train.png')]]]/ancestor::table[1]")->length > 0) {
            $this->parseTrain($email);
        }

        foreach ($this->providers as $code => $pDetect) {
            if (!empty($pDetect['from']) && $this->containsText($parser->getCleanFrom(), $pDetect['from']) === true) {
                $email->setProviderCode($code);

                break;
            }

            if (!empty($pDetect['bodyText']) && $this->http->XPath->query("//*[{$this->contains($pDetect['bodyText'])}]")->length > 0
                || !empty($pDetect['bodyUrl']) && $this->http->XPath->query("//a[{$this->contains($pDetect['bodyText'], '@href')}]")->length > 0
            ) {
                $email->setProviderCode($code);

                break;
            }
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

    private function parseHtml(Email $email): void
    {
        $this->logger->debug(__FUNCTION__ . '()');

        $f = $email->add()->flight();
        $this->parseTravellers($f);

        // General
        if (!empty($this->http->FindSingleNode("(//*[{$this->contains($this->t('your booking code is not displayed.'))}])[1]"))) {
            $f->general()
                ->noConfirmation();
        } else {
            $f->general()
                ->confirmation($this->http->FindSingleNode("//text()[" . $this->eq($this->t('Your booking code:')) . "]/following::text()[normalize-space()][1]",
                    null, true, "/^\s*([A-Z\d]{5,7})\s*$/"));
        }

        if ($this->http->XPath->query("//text()[{$this->contains($this->t('Your flight has been cancelled'))}]")->length > 0) {
            $f->general()
                ->cancelled();
        }
        // Segments
        $xpath = "//text()[{$this->eq($this->t("Status"))}]/ancestor::tr[1][following::tr[not(.//tr)][normalize-space()][2]]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $s = $f->addSegment();
            $nextTr = 'following::tr[not(.//tr)][normalize-space()]';

            // Airline
            $airline = $this->http->FindSingleNode($nextTr . "[1]/*[normalize-space()][1]", $root);

            if (preg_match('/^\s*(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<number>\d{1,5})\s*$/', $airline, $m)) {
                $s->airline()
                    ->name($m['name'])
                    ->number($m['number']);
            }

            $operator = $this->http->FindSingleNode($nextTr . "[2]/*[normalize-space()][1]", $root, null, "/" . $this->opt($this->t("operated by:")) . "[\s:]*(.+?)(?: on behalf of .+| im Auftrag von | em nome de |\s*$)/ui");

            if (!empty($operator)) {
                $s->airline()
                    ->operator($operator);
            }

            $date = $this->http->FindSingleNode($nextTr . "[1]/*[normalize-space()][1]/following::text()[normalize-space()][1]", $root);

            // Departure
            $s->departure()
                ->code($this->http->FindSingleNode($nextTr . "[not({$this->contains($this->t('operated by:'))})][2]/*[normalize-space()][1]", $root))
            ;
            $name = implode("\n", $this->http->FindNodes($nextTr . "[4]/*[normalize-space()][1]/descendant::text()[normalize-space()]", $root));

            if (preg_match("/(.+?)\n([^\n]*Terminal.*)/s", $name, $m)) {
                $s->departure()
                    ->terminal(preg_replace("/\s*\bterminal\b\s*/i", '', trim($m[2])), true)
                ;
            }

            $time = $this->http->FindSingleNode($nextTr . "[not({$this->contains($this->t('operated by:'))})][not({$this->contains($this->t('Terminal'))})][3]/*[normalize-space()][1]", $root);

            if (stripos($time, ':') == false) {
                $time = $this->http->FindSingleNode($nextTr . "[not({$this->contains($this->t('operated by:'))})][not({$this->contains($this->t('Terminal'))})][4]/*[normalize-space()][1]", $root);
            }

            if (!empty($date) && !empty($time)) {
                $s->departure()
                    ->date($this->normalizeDate($date . ', ' . $time));
            }

            // Arrival
            $s->arrival()
                ->code($this->http->FindSingleNode($nextTr . "[not({$this->contains($this->t('operated by:'))})][2]/*[normalize-space()][2]", $root))
            ;
            $name = implode("\n", $this->http->FindNodes($nextTr . "[4]/*[normalize-space()][2]/descendant::text()[normalize-space()]", $root));

            if (preg_match("/(.+?)\n([^\n]*Terminal.*)/s", $name, $m)) {
                $s->arrival()
                    /*->name(preg_replace("/(?:\s+|&nbsp)/", ' ', trim($m[1])))*/
                    ->terminal(preg_replace("/\s*\bterminal\b\s*/i", '', trim($m[2])), true)
                ;
            } /*else {
                $s->arrival()
                    ->name(preg_replace("/(?:\s+|&nbsp)/", ' ', trim($name)))
                ;
            }*/

            $time = $this->http->FindSingleNode($nextTr . "[not({$this->contains($this->t('operated by:'))})][not({$this->contains($this->t('Terminal'))})][3]/*[normalize-space()][2]", $root);

            if (stripos($time, ':') == false) {
                $time = $this->http->FindSingleNode($nextTr . "[not({$this->contains($this->t('operated by:'))})][not({$this->contains($this->t('Terminal'))})][4]/*[normalize-space()][2]", $root);
            }

            if (!empty($date) && !empty($time)) {
                $overnight = null;

                if (preg_match("/(.+?)\s*([\-+] *\d)\s*$/", $time, $m)) {
                    $time = $m[1];
                    $overnight = $m[2] . ' days';
                }
                $s->arrival()
                    ->date($this->normalizeDate($date . ', ' . $time));

                if (!empty($overnight) && !empty($s->getArrDate())) {
                    $s->arrival()
                        ->date(strtotime($overnight, $s->getArrDate()));
                }
            }

            // Extra
            $s->extra()
                ->cabin($this->http->FindSingleNode("*[normalize-space()][2]", $root), true, true)
                ->status($this->http->FindSingleNode("*[normalize-space()][1]", $root, null, "/" . $this->opt($this->t("Status")) . "\s*(.+)/u"));

            if (preg_match("/^\s*{$this->opt($this->t('cancelledStatus'))}\s*$/iu", $s->getStatus())) {
                $s->extra()
                    ->cancelled();
            }
        }
    }

    private function parseHtml2(Email $email): void
    {
        $this->logger->debug(__FUNCTION__ . '()');

        $f = $email->add()->flight();
        $this->parseTravellers($f);

        // General
        if (!empty($this->http->FindSingleNode("(//*[{$this->contains($this->t('your booking code is not displayed.'))}])[1]"))) {
            $f->general()
                ->noConfirmation();
        } else {
            $f->general()
                ->confirmation($this->http->FindSingleNode("//text()[" . $this->eq($this->t('Your booking code:')) . "]/following::text()[normalize-space()][1]",
                    null, true, "/^\s*([A-Z\d]{5,7})\s*$/"));
        }

        if ($this->http->XPath->query("//text()[{$this->contains($this->t('Your flight has been cancelled'))}]")->length > 0) {
            $f->general()
                ->cancelled();
        }

        // Segments
        $xpath = "//tr[count(*) > 1][*[position() > 1][normalize-space()][last()][{$this->starts($this->t('operated by:'))}]][following-sibling::*[normalize-space()][.//img[contains(@src, 'airplane-outbound')]]]/ancestor::table[1]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $s = $f->addSegment();
            // Airline
            $airline = $this->http->FindSingleNode("./descendant::text()[normalize-space()][2]", $root);

            if (preg_match('/^\s*(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<number>\d{1,5})\s*$/', $airline, $m)) {
                $s->airline()
                    ->name($m['name'])
                    ->number($m['number']);
            }

            $operator = $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('operated by:'))}][1]", $root, null, "/" . $this->opt($this->t("operated by:")) . "[\s:]*(.+?)(?: on behalf of .+| im Auftrag von | em nome de |\s*$)/ui");

            if (!empty($operator)) {
                $s->airline()
                    ->operator($operator);
            }

            $date = $this->http->FindSingleNode("./descendant::text()[normalize-space()][1]", $root);

            // Departure
            $s->departure()
                ->code($this->http->FindSingleNode("./descendant::img[contains(@src, 'airplane')]/ancestor::tr[1]/descendant::td[normalize-space()][1]/descendant::text()[normalize-space()][1]", $root))
            ;
            $dName = trim(implode("\n", $this->http->FindNodes("./descendant::img[contains(@src, 'airplane')]/ancestor::tr[1]/descendant::td[normalize-space()][1]/descendant::text()[normalize-space()][position() > 1]", $root)));
            $aName = trim(implode("\n", $this->http->FindNodes("./descendant::img[contains(@src, 'airplane')]/ancestor::tr[1]/descendant::td[normalize-space()][2]/descendant::text()[normalize-space()][position() > 1]", $root)));

            if ((empty($dName) && !empty($aName)) || (empty($dName) && empty($aName))) {
                $dName = $this->http->FindSingleNode("./descendant::img[contains(@src, 'airplane')]/ancestor::tr[1]/following::tr[normalize-space()][1]/descendant::td[normalize-space()][1]", $root);
                $aName = $this->http->FindSingleNode("./descendant::img[contains(@src, 'airplane')]/ancestor::tr[1]/following::tr[normalize-space()][1]/descendant::td[normalize-space()][2]", $root);
            }

            $s->departure()
                ->name($dName);

            $time = $this->http->FindSingleNode("./descendant::img[contains(@src, 'airplane')]/ancestor::tr[1]/following-sibling::tr[contains(normalize-space(), ':')]/descendant::td[normalize-space()][1]", $root);

            if (!empty($date) && !empty($time)) {
                $s->departure()
                    ->date($this->normalizeDate($date . ', ' . $time));

                $depTerminal = $this->http->FindSingleNode("./descendant::img[contains(@src, 'airplane')]/ancestor::tr[1]/following-sibling::tr[contains(normalize-space(), ':')]/following::tr[1]/descendant::td[2]", $root);

                if (!empty($depTerminal)) {
                    $s->departure()
                        ->terminal(trim(preg_replace("/\s*\bterminal\b\s/iu", " ", $depTerminal)), true);
                }
            }

            // Arrival
            $s->arrival()
                ->code($this->http->FindSingleNode("./descendant::img[contains(@src, 'airplane')]/ancestor::tr[1]/descendant::td[normalize-space()][2]/descendant::text()[normalize-space()][1]", $root))
            ;
            $s->arrival()
                ->name($aName);

            $time = $this->http->FindSingleNode("./descendant::img[contains(@src, 'airplane')]/ancestor::tr[1]/following-sibling::tr[contains(normalize-space(), ':')]/descendant::td[normalize-space()][2]", $root);

            if (!empty($date) && !empty($time)) {
                $overnight = null;

                if (preg_match("/(.+?)\s*([\-+] *\d)\s*$/", $time, $m)) {
                    $time = $m[1];
                    $overnight = $m[2] . ' days';
                }
                $s->arrival()
                    ->date($this->normalizeDate($date . ', ' . $time));

                if (!empty($overnight) && !empty($s->getArrDate())) {
                    $s->arrival()
                        ->date(strtotime($overnight, $s->getArrDate()));
                }

                $arrTerminal = $this->http->FindSingleNode("./descendant::img[contains(@src, 'airplane')]/ancestor::tr[1]/following-sibling::tr[contains(normalize-space(), ':')]/following::tr[1]/descendant::td[4]", $root);

                if (!empty($arrTerminal)) {
                    $s->arrival()
                        ->terminal(trim(preg_replace("/\s*\bterminal\b\s/iu", " ", $arrTerminal)), true);
                }
            }

            // Extra
            $cabin = $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('operated by:'))}]/following::text()[normalize-space()][1]", $root);

            if (preg_match("/^\s*[A-Z]{1,2}\s*$/", $cabin)) {
                $s->extra()
                    ->bookingCode($cabin);
            } else {
                $cabin = preg_replace(["/^\s*(.+)\s+{$this->opt($this->t('Class'))}\s*$/", "/^\s*{$this->opt($this->t('Class'))}\s+(.+)\s*$/"], '$1', $cabin);
                $s->extra()
                    ->cabin($cabin);
            }
            $s->extra()
                ->duration($this->http->FindSingleNode("./descendant::img[contains(@src, 'airplane')]/ancestor::tr[1]/following-sibling::tr[contains(normalize-space(), ':')]/preceding::tr[1]/descendant::text()[normalize-space()][1]", $root, true, "/^\s*(\d+\s*(?:h|m).*)/"), null, true)
                ->status($this->http->FindSingleNode("./descendant::img[contains(@src, 'airplane')]/ancestor::tr[1]/following-sibling::tr[contains(normalize-space(), ':')]/preceding::tr[1]/descendant::text()[normalize-space()][last()]", $root));

            if (preg_match("/^\s*{$this->opt($this->t('cancelledStatus'))}\s*$/iu", $s->getStatus())) {
                $s->extra()
                    ->cancelled();
            }
        }
    }

    private function parseTrain(Email $email): void
    {
        $this->logger->debug(__FUNCTION__ . '()');

        $t = $email->add()->train();
        $this->parseTravellers($t);

        if (!empty($this->http->FindSingleNode("(//*[{$this->contains($this->t('your booking code is not displayed.'))}])[1]"))) {
            $t->general()
                ->noConfirmation();
        } else {
            $t->general()
                ->confirmation($this->http->FindSingleNode("//text()[" . $this->eq($this->t('Your booking code:')) . "]/following::text()[normalize-space()][1]",
                    null, true, "/^\s*([A-Z\d]{5,7})\s*$/"));
        }

        $xpath = "//tr[count(*) > 1][*[position() > 1][normalize-space()][last()][{$this->starts($this->t('operated by:'))}]][following-sibling::*[normalize-space()][.//img[contains(@src, 'train')]]]/ancestor::table[1]";
        $this->logger->debug($xpath);
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $s = $t->addSegment();
            // Airline
            $trainInfo = $this->http->FindSingleNode("./descendant::text()[normalize-space()][2]", $root);

            if (preg_match('/^\s*(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<number>\d{1,5})\s*$/', $trainInfo, $m)) {
                $s->setNumber($m['number']);
                $s->setServiceName($m['name']);
            }

            $operator = $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('operated by:'))}][1]", $root, true, "/{$this->opt($this->t('operated by:'))}\s*(.+)/");
            $this->logger->error($operator);

            if (!empty($operator)) {
                $s->setServiceName($operator);
            }

            $date = $this->http->FindSingleNode("./descendant::text()[normalize-space()][1]", $root);

            $s->departure()
                ->code($this->http->FindSingleNode("./descendant::img[contains(@src, 'train')]/ancestor::tr[1]/descendant::td[normalize-space()][1]/descendant::text()[normalize-space()][1]", $root));

            $dName = $this->http->FindSingleNode("./descendant::img[contains(@src, 'train')]/ancestor::tr[1]/following::tr[normalize-space()][1]/descendant::td[normalize-space()][1]", $root);
            $aName = $this->http->FindSingleNode("./descendant::img[contains(@src, 'train')]/ancestor::tr[1]/following::tr[normalize-space()][1]/descendant::td[normalize-space()][2]", $root);

            $s->departure()
                ->name($dName);

            $time = $this->http->FindSingleNode("./descendant::img[contains(@src, 'train')]/ancestor::tr[1]/following-sibling::tr[contains(normalize-space(), ':')]/descendant::td[normalize-space()][1]", $root);

            if (!empty($date) && !empty($time)) {
                $s->departure()
                    ->date($this->normalizeDate($date . ', ' . $time));

                $depTerminal = $this->http->FindSingleNode("./descendant::img[contains(@src, 'train')]/ancestor::tr[1]/following-sibling::tr[contains(normalize-space(), ':')]/following::tr[1]/descendant::td[2]", $root);

                if (!empty($depTerminal)) {
                    $s->setDepName($s->getDepName() . ', ' . $depTerminal);
                }
            }

            // Arrival
            $s->arrival()
                ->code($this->http->FindSingleNode("./descendant::img[contains(@src, 'train')]/ancestor::tr[1]/descendant::td[normalize-space()][2]/descendant::text()[normalize-space()][1]", $root))
            ;
            $s->arrival()
                ->name($aName);

            $time = $this->http->FindSingleNode("./descendant::img[contains(@src, 'train')]/ancestor::tr[1]/following-sibling::tr[contains(normalize-space(), ':')]/descendant::td[normalize-space()][2]", $root);

            if (!empty($date) && !empty($time)) {
                $overnight = null;

                if (preg_match("/(.+?)\s*([\-+] *\d)\s*$/", $time, $m)) {
                    $time = $m[1];
                    $overnight = $m[2] . ' days';
                }
                $s->arrival()
                    ->date($this->normalizeDate($date . ', ' . $time));

                if (!empty($overnight) && !empty($s->getArrDate())) {
                    $s->arrival()
                        ->date(strtotime($overnight, $s->getArrDate()));
                }

                $arrTerminal = $this->http->FindSingleNode("./descendant::img[contains(@src, 'train')]/ancestor::tr[1]/following-sibling::tr[contains(normalize-space(), ':')]/following::tr[1]/descendant::td[4]", $root);

                if (!empty($arrTerminal)) {
                    $s->setArrName($s->getArrName() . ', ' . $arrTerminal);
                }
            }

            // Extra
            $cabin = $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('operated by:'))}]/following::text()[normalize-space()][1]", $root);

            if (preg_match("/^\s*[A-Z]{1,2}\s*$/", $cabin)) {
                $s->extra()
                    ->bookingCode($cabin);
            } else {
                $cabin = preg_replace(["/^\s*(.+)\s+{$this->opt($this->t('Class'))}\s*$/", "/^\s*{$this->opt($this->t('Class'))}\s+(.+)\s*$/"], '$1', $cabin);
                $s->extra()
                    ->cabin($cabin);
            }
            $s->extra()
                ->duration($this->http->FindSingleNode("./descendant::img[contains(@src, 'train')]/ancestor::tr[1]/following-sibling::tr[contains(normalize-space(), ':')]/preceding::tr[1]/descendant::text()[normalize-space()][1]", $root, true, "/^\s*(\d+\s*(?:h|m).*)/"), null, true)
                ->status(str_replace($this->t(', new time'), '', $this->http->FindSingleNode("./descendant::img[contains(@src, 'train')]/ancestor::tr[1]/following-sibling::tr[contains(normalize-space(), ':')]/preceding::tr[1]/descendant::text()[normalize-space()][last()]", $root)));

            if (preg_match("/^\s*{$this->opt($this->t('cancelledStatus'))}\s*$/iu", $s->getStatus())) {
                $s->extra()
                    ->cancelled();
            }
        }
    }

    private function parseTravellers($obj): void
    {
        /** @var \AwardWallet\Schema\Parser\Common\Train $obj */

        $ticketNodes = $this->http->XPath->query("//*[{$this->eq($this->t('Passenger(s) and ticket number(s)'), "translate(.,':','')")}]/following::text()[{$this->starts($this->t('Ticket number'))}]");

        foreach ($ticketNodes as $tktNode) {
            // it-866188090.eml
            $passengerName = $this->normalizeTraveller($this->http->FindSingleNode("ancestor-or-self::node()[ preceding-sibling::node()[normalize-space() and not(self::comment())] ][1]/preceding-sibling::node()[normalize-space() and not(self::comment())][1]", $tktNode, true, "/^{$this->patterns['travellerName']}$/u"));

            if ($passengerName && !in_array($passengerName, $this->travellers)) {
                $obj->general()->traveller($passengerName, true);
                $this->travellers[] = $passengerName;
            }

            $ticket = $this->http->FindSingleNode(".", $tktNode, true, "/^{$this->opt($this->t('Ticket number'))}[:\s]+({$this->patterns['eTicket']})$/");

            if ($ticket && !in_array($ticket, $this->tickets)) {
                $obj->addTicketNumber($ticket, false, $passengerName);
                $this->tickets[] = $ticket;
            }
        }

        if (count($this->travellers) > 0) {
            return;
        }

        $traveller = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Hi '))}]", null, true, "/{$this->opt($this->t('Hi '))}\s*({$this->patterns['travellerName']})\s*[,;!]/u");

        $traveller = $this->normalizeTraveller($traveller);

        if ($traveller && !in_array($traveller, $this->travellers)) {
            $obj->general()->traveller($traveller);
            $this->travellers[] = $traveller;
        }
    }

    private function t($s)
    {
        if (!isset($this->lang, self::$dictionary[$this->lang], self::$dictionary[$this->lang][$s])) {
            return $s;
        }

        return self::$dictionary[$this->lang][$s];
    }

    // additional methods

    private function normalizeDate($date)
    {
        // $this->logger->debug('$date = '.print_r($date,true));
        $in = [
            // 08. 02. 2024, 20:20
            "/^\s*(\d{1,2}) ?[.] ?(\d{1,2}) ?[.] ?(\d{4})\s*,\s*(\d{1,2}:\d{2}(?: *[ap]m)?)\s*$/ui",
            // sexta-feira 27 outubro 2023, 21:25
            "/^\s*[[:alpha:]\-]+\s+(\d{1,2})\s+([[:alpha:]]+)\s+(\d{4})\s*,\s*(\d{1,2}:\d{2}(?: *[ap]m)?)\s*$/ui",
        ];
        $out = [
            "$1.$2.$3, $4",
            "$1 $2 $3, $4",
        ];
        $date = preg_replace($in, $out, $date);
        // $this->logger->debug('$date = '.print_r($date,true));

        if (preg_match("/\d+\s+([[:alpha:]]+)\s+\d{4}/u", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        return strtotime($date);
    }

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

    private function eq($field, string $node = ''): string
    {
        $field = (array)$field;
        if (count($field) === 0)
            return 'false()';
        return '(' . implode(' or ', array_map(function($s) use($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';
            return 'normalize-space(' . $node . ')=' . $s;
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

    private function normalizeTraveller(?string $s): ?string
    {
        if (empty($s)) {
            return null;
        }

        $namePrefixes = '(?:MASTER|MSTR|MISS|MRS|MR|MS|DR|Herr|Frau|Señor|Señora|Signor)';

        return preg_replace([
            "/^(?:{$namePrefixes}[.\s]+)+(.{2,})$/is",
            "/^\s*passenger\s*$/i"
        ], [
            '$1',
            '',
        ], $s);
    }
}
