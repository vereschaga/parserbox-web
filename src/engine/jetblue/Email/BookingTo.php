<?php

namespace AwardWallet\Engine\jetblue\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class BookingTo extends \TAccountChecker
{
    public $mailFiles = "jetblue/it-203702679.eml, jetblue/it-203715211.eml, jetblue/it-301912159.eml, jetblue/it-33016233.eml, jetblue/it-33304609.eml, jetblue/it-57196168.eml, jetblue/it-57295747.eml, jetblue/it-57296078.eml, jetblue/it-631488697.eml";

    private $langDetectors = [
        'en' => ['Your confirmation code is', 'Become a member to earn points every time you use JetBlue', 'Your JetBlue confirmation code is', 'Check out the details for your trip on', 'Have a question or concern?'],
        'es' => ['Tu código de confirmación de JetBlue es'],
        'fr' => ['Votre code de confirmation est', 'Votre code de confirmation JetBlue est'],
    ];

    private $lang = '';
    private $emailSubject;
    private $status;
    private $date;

    private static $dict = [
        'en' => [
            // subject
            'has been canceled' => ['has been canceled', 'has been cancelled'], // in subject only
            'confFromSubject'   => 'JetBlue +booking +confirmation +for +[A-Z\- ]+ - (?<conf>[A-Z\d]{5,7})',

            // body
            'Your confirmation code is' => ['Your confirmation code is', 'Your JetBlue confirmation code is'],
            //            'You can also check in on your phone' => '', // text for no confirmation

            //            'Terminal' => '',
            //            'Date' => '',
            //            'Departs' => '',
            //            'Arrives' => '',
            'Flight'                    => ['Flight', 'Flight1'],
            //            'Sold as' => '',

            //            'you may cancel it within' => '',// cancellation

            //            'Traveler Details' => '',
            //            'Frequent Flier:' => '',
            //            'Ticket number:' => '',
            //            'Seat:' => '',
            //            'Points' => '',
            //            'Taxes & fees' => '',
            //            'Total:' => '',
        ],
        'es' => [
            // subject
            //            'has been canceled' => '', // in subject only
            'confFromSubject' => 'JetBlue +booking +confirmation +for +[A-Z\- ]+ - (?<conf>[A-Z\d]{5,7})',

            // body
            'Your confirmation code is' => ['Tu código de confirmación de JetBlue es'],
            //            'You can also check in on your phone' => '', // text for no confirmation

            'Terminal' => 'Terminal',
            'Date'     => 'Fecha',
            'Departs'  => 'Salida',
            'Arrives'  => 'Llegada',
            'Flight'   => ['Vuelo'],
            //            'Sold as' => [''],

            //            'you may cancel it within' => '',// cancellation

            'Traveler Details' => 'Detalles del viajero',
            'Frequent Flier:'  => 'Viajero Frecuente:',
            'Ticket number:'   => 'Número de boleto:',
            'Seat:'            => 'Asiento:',
            'Points'           => 'Puntos',
            'Taxes & fees'     => 'Impuestos y cargos',
            'Total:'           => 'Total:',
        ],
        'fr' => [
            // subject
            //            'has been canceled' => '', // in subject only
            'confFromSubject' => 'Confirmation de reservation pour +[A-Z\- ]+ - (?<conf>[A-Z\d]{5,7})',

            // body
            'Your confirmation code is' => ['Votre code de confirmation JetBlue est', 'Votre code de confirmation est'],
            //            'You can also check in on your phone' => '', // text for no confirmation

            'Terminal' => 'Terminal',
            'Date'     => 'Date',
            'Departs'  => ['Départ', 'Départs'],
            'Arrives'  => ['Arrivée', 'Arrivées'],
            'Flight'   => ['Vol'],
            //            'Sold as' => [''],

            //            'you may cancel it within' => '',// cancellation

            'Traveler Details' => ['Détails du voyageur', 'Détails du passager'],
            'Frequent Flier:'  => 'Grand voyageur :',
            'Ticket number:'   => 'Numéro de billet :',
            'Seat:'            => 'Siège :',
            'Points'           => 'Points',
            'Taxes & fees'     => 'Impôts et taxes',
            'Total:'           => 'Total',
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return strpos($from, 'JetBlue Reservations') !== false
            || preg_match('/[.@]jetblue\.com/i', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return preg_match('/(?:'
            . 'Thanks for booking JetBlue to .+ - [A-z\d]{5,}\b'
            . '|JetBlue booking confirmation +for [[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]] - [A-z\d]{5,}\b'
            . '|Your itinerary has been canceled'
            . ')/iu', $headers['subject']) > 0;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if (
            $this->http->XPath->query('//a[contains(@href,"//email.jetblue.com")]')->length === 0
            && $this->http->XPath->query("//node()[contains(normalize-space(.),\"Thanks for choosing JetBlue\") or contains(normalize-space(.),\"Please refer to JetBlue's Contract\") or contains(.,\"www.jetblue.com\") or contains(.,\"@jetblue.com\")]")->length === 0
        ) {
            return false;
        }

        return $this->assignLang() || $this->assignLang($parser->getHTMLBody());
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang() && !$this->assignLang($this->http->Response['body'])) {
            $this->logger->notice("Can't determine a language!");

            return $email;
        }

        foreach ((array) $this->t('has been canceled') as $cancelText) {
            if (stripos($parser->getHeader('subject'), $cancelText) !== false) {
                $this->status = 'cancelled';

                break;
            }
        }

        $this->emailSubject = $parser->getSubject();
        $this->date = strtotime($parser->getDate());

        $this->parseEmail($email);
        $email->setType('BookingTo' . ucfirst($this->lang));

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

    private function parseEmail(Email $email)
    {
        $patterns = [
            'time'          => '\d{1,2}(?::| ?h ?)\d{2}(?:\s*[AaPp]\.?[Mm]\.?)?', // 4:19PM    |    2:00 p.m.
            'travellerName' => '[[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        ];

        $f = $email->add()->flight();

        $confirmationNumber = null;

        foreach ((array) $this->t('Your confirmation code is') as $fieldName) {
            $confirmationNumber = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Traveler Details'))}]/preceding::text()[{$this->eq($fieldName)}][1]/following::text()[normalize-space()][1]", null, true, '/^[A-Z\d]{5,}$/');

            if ($confirmationNumber) {
                $confirmationNumberTitle = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Traveler Details'))}]/preceding::text()[{$this->eq($fieldName)}][1]", null, true, '/^(.+?)(?:\s+is)?[\s:：]*$/u');

                break;
            }
        }

        if (empty($confirmationNumber) && preg_match("/" . $this->t('confFromSubject') . "$/", $this->emailSubject, $m) && !empty($m['conf'])) {
            $confirmationNumber = $m['conf'];
            $confirmationNumberTitle = null;
        }

        if (empty($confirmationNumber)) {
            $confirmationNumber = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Departs'))}]/preceding::text()[{$this->eq($fieldName)}][1]/following::text()[normalize-space()][1]", null, true, '/^\s*([A-Z\d]{5,7})\s*$/');
            $confirmationNumberTitle = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Departs'))}]/preceding::text()[{$this->eq($fieldName)}][1]", null, true, '/^(.+?)(?:\s+is)?[\s:：]*$/u');
        }

        if (!empty($confirmationNumber)) {
            $f->general()->confirmation($confirmationNumber, $confirmationNumberTitle);
        } elseif (0 < $this->http->XPath->query("//node()[" . $this->contains($this->t('You can also check in on your phone')) . "]")->length) {
            $f->general()
                ->noConfirmation();
        }

        //it-57295747, it-57296078, it-57196168
        $firstDepDate = '';
        $firstDepCode = '';
        $firstTerminal = '';
        $firstFlight = '';

        $ruleTime = "contains(translate(normalize-space(.),'0123456789','dddddddddd'),'d:dd')";

        $segments = $this->http->XPath->query("//tr[ *[{$this->eq($this->t('Date'))}] and following-sibling::tr[ *[{$this->eq($this->t('Departs'))}] ] ]");

        if (empty($segments->length)) {
            $xpath = "//img[contains(@src, 'Itinerary_arrow.jpg')]/following::text()[{$ruleTime}][1]/ancestor::table[1][count(.//text()[{$ruleTime}]) = 2]";
            $segments = $this->http->XPath->query($xpath);
        }

        foreach ($segments as $segment) {
            $xpathFragment0 = '(self::table or self::td)';
            $xpathFragment1 = "ancestor::*[$xpathFragment0]/preceding-sibling::*[$xpathFragment0]/descendant::tr[ *[normalize-space(.)][1][string-length(normalize-space(.))=3] and *[normalize-space(.)][last()][string-length(normalize-space(.))=3] ]";

            $depCode = $this->http->FindSingleNode($xpathFragment1 . '/*[normalize-space(.)][1]', $segment, true, '/^[A-Z]{3}$/');
            $arrCode = $this->http->FindSingleNode($xpathFragment1 . '/*[normalize-space(.)][last()]', $segment, true, '/^[A-Z]{3}$/');

            $flightInfo = [];

            if (empty($this->http->FindSingleNode("*[{$this->contains($this->t('Date'))}]", $segment))) {
                $tds = $this->http->FindNodes(".//text()[normalize-space()]", $segment);

                if (count($tds) > 3 && preg_match("/^\s*(?<date>\w+[^\w\n]+\w+[^\w\n]+\w+[^\w\n]*)\n(?<dTime>\d{1,2}:\d{2}[^\d\n]{0,5})\n(?<aTime>\d{1,2}:\d{2}[^\d\n]{0,5})\n(?<flight>\d{1,5})(?:\n|$)/", implode("\n", $tds), $m)) {
                    $flightInfo = [
                        'date'   => $m['date'],
                        'dTime'  => $m['dTime'],
                        'aTime'  => $m['aTime'],
                        'flight' => $m['flight'],
                    ];
                }
            }
            $date = 0;
            $dateText = $this->http->FindSingleNode("*[{$this->eq($this->t('Date'))}]/following-sibling::*[normalize-space(.)][last()]", $segment);

            if (empty($dateText) && isset($flightInfo['date'])) {
                $dateText = $flightInfo['date'];
            }

            if (preg_match('/^(?<wday>[^\d\W]{2,})\s*,\s*(?<date>.{3,})$/u', $dateText, $matches)
                || preg_match('/^(?<wday>[[:alpha:]]{2,})\s+(?<date>\d+\s+\w+)$/u', $dateText, $matches)
            ) {
                // Mon, Jun 17
                $dateNormal = $this->normalizeDate($matches['date']);
                $weekDayNumber = WeekTranslate::number1($matches['wday']);

                if ($weekDayNumber !== null && $dateNormal) {
                    $date = EmailDateHelper::parseDateUsingWeekDay($dateNormal . ((!empty($this->date)) ? ' ' . date('Y', $this->date) : ''), $weekDayNumber);
                }
            }

            $xpathFragment2 = $xpathFragment1 . '/following::tr[ *[normalize-space()][2] ][1]';
            $terminalDep = $this->http->FindSingleNode($xpathFragment2 . "/*[normalize-space(.)][1]/descendant::text()[{$this->contains($this->t('Terminal'))}]", $segment, true, "/{$this->opt($this->t('Terminal'))}[:\s]+([A-z\d ]+)$/"); //Terminal: West Lobby

            if (empty($terminalDep)) {
                $terminalDep = $this->http->FindSingleNode($xpathFragment2 . "/*[normalize-space(.)][1]/descendant::text()[{$this->contains($this->t('Terminal'))}]/ancestor::td[1]", $segment, true, "/{$this->opt($this->t('Terminal'))}[:\s]+([A-z\d ]+)$/");
            } //Terminal: West Lobby
            $flight = $this->http->FindSingleNode("following-sibling::tr/*[{$this->eq($this->t('Flight'))}]/following-sibling::*[normalize-space(.)][last()]", $segment, true, '/(\d+)$/');

            if (empty($flight) && isset($flightInfo['flight'])) {
                $flight = $flightInfo['flight'];
            }

            if ($date == $firstDepDate && $depCode == $firstDepCode && $firstFlight == $flight && isset($s)) {
                // the same flight
                $f->removeSegment($s);
            } elseif ($date == $firstDepDate && $depCode == $firstDepCode) {
                // the error segment
                $this->logger->debug('duplicate segment');
                //$s = $f->addSegment();
            }

            $s = $f->addSegment();

            $firstDepDate = $date;
            $firstDepCode = $depCode;
            $firstTerminal = $terminalDep;
            $firstFlight = $flight;

            $s->departure()->code($depCode);
            $s->arrival()->code($arrCode);

            $s->departure()->terminal($terminalDep, false, true);

            $terminalArr = $this->http->FindSingleNode($xpathFragment2 . "/*[normalize-space(.)][last()]/descendant::text()[{$this->contains($this->t('Terminal'))}]", $segment, true, "/{$this->opt($this->t('Terminal'))}[:\s]+([A-z\d ]+)$/");

            if (empty($terminalArr)) {
                $terminalArr = $this->http->FindSingleNode($xpathFragment2 . "/*[normalize-space()][last()]/descendant::text()[{$this->contains($this->t('Terminal'))}]/ancestor::td[1]", $segment, true, "/{$this->opt($this->t('Terminal'))}[:\s]+([A-z\d ]+)$/");
            }
            $s->arrival()->terminal($terminalArr, false, true);

            $timeDep = $this->normalizeTime($this->http->FindSingleNode("following-sibling::tr/*[{$this->eq($this->t('Departs'))}]/following-sibling::*[normalize-space(.)][last()]", $segment, true, "/^{$patterns['time']}$/"));

            if (empty($timeDep) && isset($flightInfo['dTime'])) {
                $timeDep = $flightInfo['dTime'];
            }

            if ($date && $timeDep) {
                $s->departure()->date(strtotime($timeDep, $date));
            }

            $timeArr = $this->normalizeTime($this->http->FindSingleNode("following-sibling::tr/*[{$this->eq($this->t('Arrives'))}]/following-sibling::*[normalize-space(.)][last()]", $segment, true, "/^{$patterns['time']}$/"));

            if (empty($timeArr) && isset($flightInfo['aTime'])) {
                $timeArr = $flightInfo['aTime'];
            }

            if ($date && $timeArr) {
                $s->arrival()->date(strtotime($timeArr, $date));
            }

            $flight = $this->http->FindSingleNode("following-sibling::tr/*[{$this->eq($this->t('Flight'))}]/following-sibling::*[normalize-space(.)][last()]", $segment, true, '/\d+$/');

            if (empty($flight) && isset($flightInfo['flight'])) {
                $flight = $flightInfo['flight'];
            }
            $airline = str_replace("Image removed by sender. ", "", $this->http->FindSingleNode("following-sibling::tr[ *[{$this->eq($this->t('Flight'))}] ]/following::img[1]/attribute::*[name()='alt' or name()='altx']", $segment));

            if (empty($airline)) {
                $airline = $this->http->FindSingleNode("./following::table[2]/descendant::text()[starts-with(normalize-space(), 'Operated by')]/following::text()[normalize-space()][1]", $segment);
            }

            $soldAs = $this->http->FindSingleNode("following-sibling::tr/*[{$this->eq($this->t('Flight'))}]/following-sibling::*[normalize-space(.)][last()]/following::text()[normalize-space()][1]/ancestor::tr[1]", $segment);

            if (preg_match("/{$this->opt($this->t('Sold as'))}\s+(?<al>[A-Z\d][A-Z]|[A-Z][A-Z\d])\s*(?<fn>\d{1,5})\s*$/", $soldAs, $m)) {
                $s->airline()
                    ->name($m['al'])
                    ->number($m['fn'])
                ;
                $s->airline()
                    ->carrierNumber($flight);

                if (!empty(trim($airline))) {
                    $s->airline()->carrierName($airline);
                }
            } else {
                $s->airline()
                    ->number($flight);

                if (!empty($airline)) {
                    $s->airline()->name($airline);
                } else {
                    $s->airline()->noName();
                }
            }

            $operator = $this->http->FindSingleNode("ancestor::table[1]/following::tr[normalize-space()][1][" . $this->starts($this->t("Operated by")) . "]", $segment, true, "#" . $this->opt($this->t("Operated by")) . "\s+(.+)#");

            if (!empty($operator)) {
                $s->airline()->operator($operator);
            }

            if (!empty($s->getDepCode()) && !empty($s->getArrCode())) {
                $seats = $this->http->FindNodes("//text()[{$this->contains($s->getDepCode() . ' - ' . $s->getArrCode() . ':')}]/ancestor::td[1]", null, "/{$this->opt($this->t('Seat:'))}\s*(\d{1,5}[A-Z])\b/");
                $seats = array_filter($seats);

                if (empty($seats) && !empty($flightInfo)) {
                    $seats = $this->http->FindNodes("//text()[{$this->contains($s->getDepCode() . ' - ' . $s->getArrCode() . ':')}]/ancestor::td[1]//a", null, "/^\s*(\d{1,3}[A-Z])\s*$/");
                    $seats = array_filter($seats);
                }

                if (count($seats)) {
                    $s->extra()->seats($seats);
                }
            }
        }

        if (!empty($this->status)) {
            $f->general()
                ->cancelled()
                ->status($this->status);
        }

        $cancellation = $this->http->FindSingleNode("//text()[{$this->contains($this->t('you may cancel it within'))}]/ancestor::*[self::td or self::th or self::tr][1]");

        if (!empty($cancellation)) {
            $f->general()->cancellation(str_ireplace(' Please click here for details on our change and cancel policies.', '', $cancellation), true, true);
        }

        $ffNumbers = [];
        $ticketNumbers = [];

        $travelerRows = $this->http->XPath->query("//tr[not(.//tr) and (({$this->contains($this->t('Frequent Flier:'))} and {$this->contains($this->t('Ticket number:'))}) or ({$this->contains($this->t('Frequent Flier:'))}))]");

        foreach ($travelerRows as $row) {
            $travellerName = $this->http->FindSingleNode('ancestor::table[ preceding-sibling::table[normalize-space(.)] ][1]/preceding-sibling::table[normalize-space(.)]/descendant::tr[not(.//tr) and normalize-space(.)]', $row, true, "/^{$patterns['travellerName']}$/");
            $f->addTraveller($travellerName, true);

            $ffNumber = $this->http->FindSingleNode("descendant::text()[{$this->contains($this->t('Frequent Flier:'))}]", $row, true, "/{$this->opt($this->t('Frequent Flier:'))}\s*([A-Z\d][A-Z\d\s]{8,}[A-Z\d])$/");

            if (empty($ffNumber)) {
                $ffNumber = $this->http->FindSingleNode("descendant::text()[{$this->contains($this->t('Frequent Flier:'))}]/following::text()[string-length(normalize-space(.))>2][1]", $row, true, '/^[ ]*([A-Z\d][A-Z\d\s]{8,}[A-Z\d])[ ]*$/');
            }

            if ($ffNumber) {
                $ffNumbers[] = $ffNumber;
            }

            $ticketNumber = $this->http->FindSingleNode("descendant::text()[{$this->contains($this->t('Ticket number:'))}]", $row, true, "/{$this->opt($this->t('Ticket number:'))}\s*(\d{3}[-\s]*\d{7,})$/");

            if (empty($ticketNumber)) {
                $ticketNumber = $this->http->FindSingleNode("descendant::text()[{$this->contains($this->t('Ticket number:'))}]/following::text()[string-length(normalize-space(.))>2][1]", $row, true, "/^\s*(\d{3}[-\s]*\d{7,})[ ]*$/");
            }

            if ($ticketNumber) {
                $ticketNumbers[] = $ticketNumber;
            }
        }

        if ($travelerRows->length === 0 && !empty($flightInfo)) {
            $s = $f->getSegments()[0] ?? null;

            if (!empty($s) && !empty($s->getDepCode()) && !empty($s->getArrCode())) {
                $travelerRows = $this->http->XPath->query("//text()[{$this->eq($s->getDepCode() . ' - ' . $s->getArrCode() . ':')}]/ancestor::tr[1][preceding-sibling::tr[1][not(normalize-space())]/*[contains(@style, 'height:1px;')]]/preceding-sibling::tr[2]");

                foreach ($travelerRows as $row) {
                    $travellerName = $this->http->FindSingleNode('ancestor::table[ preceding-sibling::table[normalize-space(.)] ][1]/preceding-sibling::table[normalize-space(.)]/descendant::tr[not(.//tr) and normalize-space(.)]', $row, true, "/^{$patterns['travellerName']}$/");
                    $f->addTraveller($travellerName, true);

                    $values = $this->http->FindNodes(".//text()[normalize-space()]", $row);

                    foreach ($values as $value) {
                        if (preg_match("/^\d{13}$/", $value)) {
                            $ticketNumbers[] = $value;
                        }

                        if (preg_match("/^\s*B6 *\d{5,}\s*$/", $value)) {
                            $ffNumbers[] = $value;
                        }
                    }
                }
            }
        }

        if (count($ffNumbers)) {
            $f->program()->accounts($ffNumbers, false);
        }

        if (count($ticketNumbers)) {
            $f->issued()->tickets($ticketNumbers, false);
        }

        $payment = $this->http->FindSingleNode("//td[{$this->eq($this->t('Total:'))}]/following-sibling::td[normalize-space(.)][last()]");

        if (preg_match('/^(?<currency>[^\d)(]+)\s*(?<amount>\d[,.\'\d]*)/', $payment, $matches)) {
            // $625.08
            if (trim($matches['currency']) === 'FFCURRENCY') {
                $matches['currency'] = null;
                $f->price()
                    ->spentAwards($matches['amount']);
            } else {
                $f->price()
                    ->currency($this->normalizeCurrency($matches['currency']))
                    ->total($this->normalizeAmount($matches['amount']));
            }

            $matches['currency'] = trim($matches['currency']);
            $taxesFees = $this->http->FindSingleNode("//td[{$this->eq($this->t('Taxes & fees'))}]/following-sibling::td[last()]");

            if (preg_match('/^' . preg_quote($matches['currency'], '/') . '\s*(?<amount>\d[,.\'\d]*)/', $taxesFees, $m)) {
                $f->price()->tax($this->normalizeAmount($m['amount']));
            }

            $cost = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'NONREF')]/ancestor::tr[1]/descendant::td[normalize-space()][2]");

            if (preg_match('/^' . preg_quote($matches['currency'], '/') . '\s*(?<amount>\d[,.\'\d]*)/', $cost, $m)) {
                $f->price()->cost($this->normalizeAmount($m['amount']));
            }

            $spent = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Total:'))}]/preceding::text()[normalize-space()][position() < 15][{$this->eq('Points')}]/ancestor::tr[1]/descendant::td[normalize-space()][2]");

            if (preg_match('/^\s*(?<amount>\d[,.\d]*)\s*$/', $spent, $m)) {
                $f->price()->spentAwards($m['amount'] . ' Points');
            }
        }
    }

    /**
     * Dependencies `use AwardWallet\Engine\MonthTranslate` and `$this->lang`.
     *
     * @return string|bool
     */
    private function normalizeDate(string $string)
    {
        if (preg_match('/^([^\d\W]{3,})\s*(\d{1,2})$/u', $string, $matches)) {
            // Jun 17
            $month = $matches[1];
            $day = $matches[2];
            $year = '';
        } elseif (preg_match('/^([^\d\W]{3,})\s*(\d{1,2})\s*(\d{4})$/u', $string, $matches)) {
            // Jun 17 2024
            $month = $matches[1];
            $day = $matches[2];
            $year = $matches[3];
        } elseif (preg_match('/^\s*(\d{1,2})\s*([[:alpha:]]{3,})\s*$/u', $string, $matches)) {
            // 26 août
            $day = $matches[1];
            $month = $matches[2];
            $year = '';
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

        return false;
    }

    /**
     * @param string $string Unformatted string with amount
     */
    private function normalizeAmount(string $s): string
    {
        $s = preg_replace('/\s+/', '', $s);             // 11 507.00  ->  11507.00
        $s = preg_replace('/[,.\'](\d{3})/', '$1', $s); // 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
        $s = preg_replace('/,(\d{1,2})$/', '.$1', $s);    // 18800,00   ->  18800.00

        return $s;
    }

    /**
     * @param string $string Unformatted string with currency
     */
    private function normalizeCurrency(string $string): string
    {
        $string = trim($string);
        $currences = [
            'GBP' => ['£'],
            'EUR' => ['€'],
            'USD' => ['$', 'US$'],
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

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'normalize-space(' . $node . ')="' . $s . '"'; }, $field)) . ')';
    }

    private function starts($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'starts-with(normalize-space(' . $node . '),"' . $s . '")'; }, $field)) . ')';
    }

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'contains(normalize-space(' . $node . '),"' . $s . '")'; }, $field)) . ')';
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

    private function normalizeTime($str): string
    {
        $in = [
            // 10h20
            "/^\s*(\d{1,2})\s*h\s*(\d{2})\D*$/",
        ];
        $out = [
            "$1:$2",
        ];
        $str = preg_replace($in, $out, $str);

        return $str;
    }
}
