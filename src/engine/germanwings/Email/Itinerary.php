<?php

namespace AwardWallet\Engine\germanwings\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Itinerary extends \TAccountChecker
{
    public $mailFiles = "germanwings/it-1.eml, germanwings/it-10004179.eml, germanwings/it-12232272.eml, germanwings/it-12356250.eml, germanwings/it-12520304.eml, germanwings/it-12897613.eml, germanwings/it-1608213.eml, germanwings/it-1610965.eml, germanwings/it-162528591.eml, germanwings/it-1824996.eml, germanwings/it-1855832.eml, germanwings/it-2092446.eml, germanwings/it-2578541.eml, germanwings/it-2596370.eml, germanwings/it-2938758.eml, germanwings/it-4215685.eml, germanwings/it-4216628.eml, germanwings/it-459111508.eml, germanwings/it-461765997.eml, germanwings/it-4898486.eml, germanwings/it-4960275.eml, germanwings/it-536385449.eml, germanwings/it-536387353.eml, germanwings/it-5667622.eml, germanwings/it-8563698.eml, germanwings/it-8567416.eml, germanwings/it-8870353.eml, germanwings/it-9915618.eml, germanwings/it-9945856.eml, germanwings/it-9945864.eml, germanwings/it-9965052.eml";
    public $pdfNamePattern = ".*pdf";

    private $reFrom = ['germanwings.com', 'eurowings.com'];

    private $reBody = [
        'cs' => ['Číslo letu:', 'Vaše rezervace letu'],
        'sv' => ['Din bokning', 'Flygning:'],
        'de' => ['Flugnummer:', 'Gesamtflugpreis'],
        'es' => ['Importe total del vuelo'],
        'en' => ['Total flight fare', 'Eurowings - Customer Relations', 'Eurowings GmbH', 'many thanks for booking your flight with us'],
    ];
    private $reSubject = [
        'de' => ['Passenger Receipt - Buchungsbestätigung', 'Flugplanänderung - Buchung', 'Hinweise zu Ihrem Handgepäck'],
        'en' => ['Passenger Receipt - Confirmation of Booking'],
    ];
    private $confNumberBySubject;
    private $lang = 'en';
    private static $dict = [
        'cs' => [
            'Flight'                => 'Let:',
            'Departure'             => ['Odlet'],
            'Arrival'               => ['Přílet'],
            'Total'                 => ['Celková částka', 'Celková cena'],
            'Flight price'          => 'Cena letu',
            'Passenger'             => 'Cestující:',
            //'Firstname'             => '',
            //'Lastname'              => '',
            //'Frequent Flyer Number' => '',
            'Your booking'          => 'Vaše rezervace letu',
            'Date of booking'       => 'Den rezervace:',
            //'Date of Change'        => ['Datum změny:'],
            'Flight Number'         => 'Číslo letu:',
            //'CANCELED'              => '',
            'Operated By'           => ['Operated By'],
            'Seat(s)'               => 'Sedadlo(a):',
            //'Seat'                  => '',
        ],
        'sv' => [
            'Flight'                => 'Flygning:',
            'Departure'             => ['Avresa'],
            'Arrival'               => ['Ankomst'],
            'Total'                 => ['Totalt'],
            'Flight price'          => 'Pris för flygning',
            'Passenger'             => 'Passagerare:',
            //'Firstname'             => 'Vorname',
            //'Lastname'              => 'Nachname',
            //'Frequent Flyer Number' => 'Vielfliegernummer',
            'Your booking'          => 'Din bokning',
            'Date of booking'       => 'Bokningsdatum',
            //'Date of Change'        => ['Ändringsdatum'],
            'Flight Number'         => 'Flygnummer',
            //'CANCELED'              => 'ANNULLIERT',
            'Operated By'           => ['Operated by'],
            'Seat(s)'               => 'Sittplats(er)',
            //'Seat'                  => 'Sitz',
        ],
        'de' => [
            'Flight'                => 'Flug',
            'Departure'             => ['Start', 'Abflug'],
            'Arrival'               => ['Landung', 'Ankunft'],
            'Total'                 => ['Gesamtpreis', 'Gesamtbetrag'],
            'Flight price'          => 'Flugpreis',
            'Passenger'             => ['Passagier', 'Gast'],
            'Firstname'             => 'Vorname',
            'Lastname'              => 'Nachname',
            'Frequent Flyer Number' => 'Vielfliegernummer',
            'Your booking'          => 'Ihre Buchung',
            'Date of booking'       => 'Tag der Buchung',
            'Date of Change'        => ['Datum der Änderung:', 'Datum der Ãnderung:'],
            'Flight Number'         => 'Flugnummer',
            'CANCELED'              => 'ANNULLIERT',
            'Operated By'           => ['Durchgeführt von', 'DurchgefÃ¼hrt von', 'durchgeführt von', 'Operated by'],
            'Seat(s)'               => 'Sitz(e)',
            'Seat'                  => 'Sitz',
        ],
        'es' => [
            'Flight'       => 'Vuelo',
            'Departure'    => 'Salida',
            'Arrival'      => 'Llegada',
            'Total'        => 'Tarifa total',
            'Flight price' => 'Precio vuelo',
            'Passenger'    => 'Pasajero',
            //            'Firstname' => '',
            //            'Lastname' => '',
            'Frequent Flyer Number' => 'Número de Viajero Frecuente',
            'Your booking'          => 'Su reserva',
            'Date of booking'       => 'Fecha de reserva',
            //            'Date of Change' => '',
            'Flight Number' => 'No. de vuelo',
            //			'CANCELED' => '',
            //			'Operated By' => '',
            //			'Seat(s)' => '',
            //			'Seat' => '',
        ],
        'en' => [
            'Departure'    => ['Departure', 'Start'],
            'Arrival'      => ['Arrival', 'Landung'],
            'Firstname'    => ['Firstname', 'First Name'],
            'Lastname'     => ['Lastname', 'Last Name'],
            'Your booking' => ['Your booking', 'Receipt', 'Your Updated Booking', 'booking'],
            'CANCELED'     => ['CANCELED', 'CANCELLED'],
            'Operated By'  => ['Operated By', 'Operated by', 'operated by'],
            'Flight'       => ['Flight', 'Flght'],
            //			'Seat' => '',
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $body = $this->http->Response['body'];
        $this->assignLang($body);

        if ($this->http->XPath->query("//text()[{$this->contains($this->t('Departure'))}]")->length > 0) {
            $this->flight($email);
        } else {
            $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

            foreach ($pdfs as $pdf) {
                $text = \PDF::convertToText($parser->getAttachmentBody($pdf));
                $this->parseFlightPDF($email, $text);
            }
        }
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function parseFlightPDF(Email $email, string $text)
    {
        $f = $email->add()->flight();

        $confNumber = $this->re("/{$this->opt($this->t('Your booking code for check-in:'))}\s*([A-Z\d]{6})/", $text);

        if (empty($confNumber)) {
            $confNumber = $this->re("/{$this->opt($this->t('Date of booking'))}.+\n*\s*([A-Z\d]{6})\s+/u", $text);
        }

        $f->general()
            ->confirmation($confNumber);
        $f->general()
            ->date(strtotime($this->re("/{$this->opt($this->t('Date of booking'))}\s*\:?\s*(.+)\s+\(CEST\)/", $text)));

        if (preg_match_all("/\s*{$this->opt($this->t('Passenger'))}\s+\d+\s*\:?\s*(.+)\n/", $text, $m)
        || preg_match_all("/\d+\.\s*{$this->opt($this->t('Passenger'))}\s*\:?\s*(.+)\n/", $text, $m)) {
            $f->general()
                ->travellers(array_unique(array_filter(str_replace(['Herr ', 'MRS ', 'MR ', 'Fru ', 'Pan '], '', $m[1]))));
        }

        $price = $this->re("/{$this->opt($this->t('Total'))}\s*([\d\.\,]+\s*\D{1,3})\n/", $text);

        if (preg_match("/(?<total>[\d\.\,]+)\s*(?<currency>\D{1,3})/", $price, $m)) {
            $currency = $this->normalizeCurrency($m['currency']);

            $f->price()
                ->total(PriceHelper::parse($m['total'], $currency))
                ->currency($currency);

            $cost = $this->re("/^\s+{$this->opt($this->t('Flight price'))}\s*([\d\.\,]+)\s*\D{1,3}\n/mi", $text);

            if (!empty($cost)) {
                $f->price()
                    ->cost(PriceHelper::parse($cost, $currency));
            }

            $feeText = $this->re("/^\s+{$this->opt($this->t('Flight price'))}.+\n((?:.+\n*){1,5})\s*{$this->opt($this->t('Total'))}\s+\d/iu", $text);

            if (empty($feeText)) {
                $feeText = $this->re("/{$this->opt($this->t('Flight price'))}.+\n((?:.+\n*){1,3})\s*{$this->opt($this->t('Total'))}\s+\d/iu", $text);
            }
            $feeArray = array_filter(explode("\n", $feeText));

            foreach ($feeArray as $fee) {
                if (stripos($fee, ' x ') !== false) {
                    continue;
                }

                if (preg_match("/\s+(?<feeName>.+)[ ]{10,}(?<feeSum>[\d\.\,]+)/", $fee, $m)) {
                    $f->price()
                        ->fee($m['feeName'], PriceHelper::parse($m['feeSum'], $currency));
                }
            }
        }

        if (preg_match_all("/\n+^(\s+{$this->opt($this->t('Flight'))}\:?\s*.+{$this->opt($this->t('Flight Number'))}.+\n*(?:.+\n){1,5}\s+{$this->opt($this->t('Departure'))}.+\n\s*[\d\:]+.+)/mu", $text, $match)) {
            foreach ($match[1] as $seg) {
                $s = $f->addSegment();

                $s->airline()
                    ->name($this->re("/([A-Z][A-Z\d]|[A-Z\d][A-Z])/", $seg))
                    ->number($this->re("/(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(\d{1,4})\s+/", $seg));

                if (preg_match("/\((?<cabin>.+)\S\s*(?<bookingCode>[A-Z]{1,2})\s*\)/", $seg, $m)) {
                    $s->extra()
                        ->cabin($m['cabin'])
                        ->bookingCode($m['bookingCode']);
                }

                $operator = $this->re("/{$this->opt($this->t('Operated by'))}\s+(.+)/", $seg);

                if (!empty($operator)) {
                    $s->airline()
                        ->operator($operator);
                }

                $dateDep = $this->re("/{$this->opt($this->t('Flight'))}\:\s+([\d\.]+)\s*\|/", $seg);

                if (preg_match("/\s+{$this->opt($this->t('Departure'))}\s+{$this->opt($this->t('Arrival'))}\n+\s+(?<depTime>[\d\:]+)\s*(?<depName>.+)[ ]{5,}(?<arrTime>[\d\:]+)\s+(?<arrName>.+)/", $seg, $m)) {
                    $s->departure()
                        ->date(strtotime($dateDep . ' ' . $m['depTime']))
                        ->name($m['depName'])
                        ->noCode();

                    $s->arrival()
                        ->date(strtotime($dateDep . ' ' . $m['arrTime']))
                        ->name($m['arrName'])
                        ->noCode();
                }

                $seatsText = $this->re("/\s+{$this->opt($this->t('Seat(s)'))}\:\s*(.+)\n/", $text);
                $seatsText = preg_replace("/\s+\(\s*\d+\s*\)\s*/", "", $seatsText);
                $seats = explode(", ", $seatsText);

                if (count($seats) > 0) {
                    $s->extra()
                        ->seats($seats);
                }
            }
        }
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[contains(.,'Germanwings') or contains(.,'Eurowings')]")->length > 0) {
            $body = $parser->getHTMLBody();

            return $this->assignLang($body);
        }

        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (stripos($text, "Eurowings GmbH") !== false) {
                return $this->assignLang($text);
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        $this->confNumberBySubject = ($this->re('/[:]\s([-A-Z\d ]{5,})/', $headers['subject']));

        if (self::detectEmailFromProvider($headers['from']) !== true && stripos($headers['subject'], 'Germanwings') === false) {
            return false;
        }

        foreach ($this->reSubject as $phrases) {
            foreach ($phrases as $phrase) {
                if (stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->reFrom as $re) {
            if (stripos($from, $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function normalizeCurrency($string = '')
    {
        if (empty($string)) {
            return $string;
        }
        $currences = [
            'GBP' => ['£'],
            'EUR' => ['€'],
            'USD' => ['$', 'US$'],
        ];
        $string = trim($string);

        foreach ($currences as $currencyCode => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $currencyCode;
                }
            }
        }

        return $string;
    }

    private function flight(Email $email)
    {
        $f = $email->add()->flight();

        $confNumber = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Date of booking'))} or {$this->starts($this->t('Date of Change'))}]/preceding::text()[normalize-space(.)][1]", null, true, '/([-A-Z\d ]{5,})$/');
        $reservDate = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Date of booking'))} or {$this->starts($this->t('Date of Change'))}]/following::text()[normalize-space(.)][1]", null, true, "#(\d+\.\d+\.\d+(?:\s+\d+:\d+)?)#");

        if (!empty($confNumber)) {
            $f->general()->confirmation($confNumber);
        } else {
            $f->general()->confirmation($this->confNumberBySubject);
        }

        if (!empty($reservDate)) {
            $f->general()->date(strtotime($reservDate));
        }

        $passengerRows = $this->http->XPath->query('//tr[ not(.//tr) and ./td[' . $this->eq($this->t('Firstname')) . '] and ./td[' . $this->eq($this->t('Lastname')) . '] ]/following-sibling::tr');

        foreach ($passengerRows as $passengerRow) {
            $passengerText = '';

            if ($firstname = $this->http->FindSingleNode('./td[2][normalize-space(.)]', $passengerRow, true, '/^(\D+)$/')) {
                $passengerText .= $firstname;
            }

            if ($lastname = $this->http->FindSingleNode('./td[3][normalize-space(.)]', $passengerRow, true, '/^(\D+)$/')) {
                $passengerText .= ' ' . $lastname;
            }

            if ($passengerText) {
                $passengers[] = $passengerText;
            }
        }
        $seatsRows = $this->http->XPath->query('//text()[ ' . $this->eq($this->t('Seat')) . ' and ancestor::tr[1][' . $this->contains($this->t('Lastname')) . '] ]/ancestor-or-self::td[1]');
        $seatsTable = [];

        foreach ($seatsRows as $row) {
            $count = count($this->http->FindNodes('./preceding-sibling::td', $row));

            if (!empty($count)) {
                $seatsTable[] = $this->http->FindNodes('./ancestor::tr[1]/following-sibling::tr/td[' . ($count + 1) . ']', $row);
            }
        }

        if (empty($passengers)) {
            $checkStr = $this->http->FindSingleNode("(//text()[{$this->contains($this->t('Passenger'))}]/ancestor::*[1][not({$this->contains($this->t('Your booking'))})]/following::text()[normalize-space(.)!=''][1])[1]");

            if (!empty($checkStr) && strpos($checkStr, $this->t('Frequent Flyer Number')) !== false) {
                $passengers = $this->http->FindNodes("//text()[{$this->contains($this->t('Passenger'))}]/ancestor::tr[1][{$this->contains($this->t('Frequent Flyer Number'))}]/following-sibling::tr[string-length(normalize-space(.))>2]/td[normalize-space(.)!=''][1]", null, "#\d*.?\s*(.+)#");
                $accountNumbers = $this->http->FindNodes("//text()[{$this->contains($this->t('Passenger'))}]/ancestor::tr[1][{$this->contains($this->t('Frequent Flyer Number'))}]/following-sibling::tr[string-length(normalize-space(.))>2]/td[normalize-space(.)!=''][2]");
            } else {
                $passengers = $this->http->FindNodes("//text()[{$this->contains($this->t('Passenger'))} and (starts-with(translate(normalize-space(),'0123456789', '1111111111'), '1'))]/ancestor::*[1][contains(.,':') and not({$this->contains($this->t('Your booking'))})]/following::text()[normalize-space(.)][1]");

                if (empty($passengers)) {
                    $passengers = $this->http->FindNodes("//text()[{$this->contains($this->t('Passenger'))} and (contains(translate(normalize-space(),'0123456789', '1111111111'), '1'))]/ancestor::*[1][contains(.,':') and not({$this->contains($this->t('Your booking'))})]/following::text()[normalize-space(.)][1]");
                }

                if (!empty($passengers[0])) {
                    $passengers = array_values(array_unique($passengers));
                }
                $accountNumbers = $this->http->FindNodes("//text()[{$this->starts($this->t('Frequent Flyer Number'))}]", null, "#:\s*(.+)$#");
                $accountNumbers = array_values(array_filter($accountNumbers));

                if (!empty($accountNumber[0])) {
                    $accountNumbers = array_unique($accountNumbers);
                }
            }
        }

        if (!empty(array_filter($passengers))) {
            $f->general()->travellers(array_unique(array_filter(str_replace(['Herr ', 'MRS ', 'MR ', 'Fru ', 'Frau'], '', $passengers))));
        }

        if (!empty($accountNumbers)) {
            $f->program()->accounts($accountNumbers, false);
        }

        $tot = $this->getTotalCurrency($this->http->FindSingleNode("(//text()[{$this->eq($this->t('Total'))}]/ancestor::tr[1])[1]", null, true, "#{$this->opt($this->t('Total'))}\s*(.+)#"));

        if (!empty($tot['Total'])) {
            $f->price()
                ->total($tot['Total'])
                ->currency($tot['Currency']);
        }
        $tot = $this->getTotalCurrency($this->http->FindSingleNode("(//text()[{$this->eq($this->t('Flight price'))}]/ancestor::tr[1])[1]", null, true, "#{$this->opt($this->t('Flight price'))}\s*(.+)#"));

        if (!empty($tot['Total'])) {
            $f->price()
                ->cost($tot['Total'])
                ->currency($tot['Currency']);
        }

        $feeXpath = "//text()[{$this->eq($this->t('Total'))}]/ancestor::tr[1]/preceding-sibling::tr[normalize-space()][not({$this->contains($this->t('Total'))} or {$this->contains($this->t('Flight price'))})]";
        $feeNodes = $this->http->XPath->query($feeXpath);

        foreach ($feeNodes as $feeRoot) {
            $feeSumm = $this->http->FindSingleNode("./descendant::td[2]", $feeRoot, true, "/^([\d\.\,]+)/");
            $feeName = $this->http->FindSingleNode("./descendant::td[1]", $feeRoot);

            if (!empty($feeSumm) && !empty($feeName)) {
                $f->price()->fee($feeName, PriceHelper::parse($feeSumm, empty($f->getPrice()) ? null : $f->getPrice()->getCurrencyCode()));
            }
        }

        $xpath = "//text()[{$this->eq($this->t('Departure'))}]/ancestor::tr[contains(.,':')][1]";
        // $this->logger->debug('$xpath = '.print_r( $xpath,true));
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length === 0) {
            $this->logger->info("Segments not found by xpath: {$xpath}");

            return [];
        }

        foreach ($nodes as $key => $root) {
            $s = $f->addSegment();
            $seats = $this->http->FindSingleNode("./preceding::tr[position()<=3 and ({$this->contains($this->t('Seat(s)'))})][1]", $root, true, '/[\s:]+(.+)/');

            if (!empty($seats) && preg_match_all('/\b(\d{1,3}[A-Z])\b/i', $seats, $seatMatches)) {
                $s->extra()->seats($seatMatches[1]);
            }

            $operator = $this->http->FindSingleNode("./preceding::tr[position()<=3 and ({$this->contains($this->t('Operated By'))})][1]", $root, true, "#{$this->opt($this->t('Operated By'))}\s*(.+)#");

            if (empty($operator)) {
                $operator = $this->http->FindSingleNode("./preceding::text()[{$this->contains($this->t('Operated By'))}][1]", $root, true, "#{$this->opt($this->t('Operated By'))}\s*(.+)#");
            }

            if (!empty($operator)) {
                $s->airline()->operator($operator);
            }

            $re = '/' . $this->opt($this->t('Flight')) . '[:\s]+(?<date>\d{1,2}\.\d{1,2}\.\d{2,4})[|\s]+'
                . $this->opt($this->t('Flight Number')) . '[:\s]+(?:(?<airline>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<flightNumber>\d+)|'
                . $this->opt($this->t('CANCELED')) . ')\s*:?(?:\((?<cabin>.+?)\s*(?:[\s\\ ]*(?<class>[A-Z]{1,2}))\s*\)|$|'
                . $this->opt($this->t('Operated By')) . '\s+(?<operator>\w+))/u';

            $node = implode(' ', $this->http->FindNodes("preceding-sibling::tr[position()<=3 and ({$this->starts($this->t('Flight'))}) and ({$this->contains($this->t('Flight Number'))})][1]//text()[normalize-space()]", $root));

            if (empty($node)) {
                $node = implode(' ', $this->http->FindNodes("preceding::tr[position()<=3 and ({$this->starts($this->t('Flight'))}) and ({$this->contains($this->t('Flight Number'))})][1]//text()[normalize-space()]", $root));
            }

            if (empty($node)) {
                $node = implode(' ', $this->http->FindNodes("ancestor::tr[1]/preceding-sibling::tr[position()<=3 and ({$this->starts($this->t('Flight'))}) and ({$this->contains($this->t('Flight Number'))})][1]//text()[normalize-space()]", $root));
            }

            if (empty($node)) {
                $node = implode(' ', $this->http->FindNodes("./preceding::tr[{$this->starts($this->t('Flight'))} and {$this->contains($this->t('Flight Number'))}][1]//text()[normalize-space()]", $root));
            }

            if (preg_match($re, $node, $m)) {
                $date = strtotime($m['date']);

                if (!empty($m['airline']) && !empty($m['flightNumber'])) {
                    $s->airline()
                        ->name($m['airline'])
                        ->number($m['flightNumber']);
                } else {
                    if ($nodes->length == 1) {
                        $s->airline()
                            ->noNumber()
                            ->noName();
                        $f->general()
                            ->cancelled(true)
                            ->status('Cancelled');
                    } else {
                        $f->removeSegment($s);

                        continue;
                    }
                }

                if (!empty($m['cabin'])) {
                    $s->extra()->cabin(trim(str_replace("\\", '', $m['cabin'])));
                }

                if (!empty($m['class'])) {
                    $s->extra()->bookingCode($m['class']);
                }

                if (!empty($m['operator']) && empty($s->getOperatedBy())) {
                    $s->airline()->operator($m['operator']);
                }
            }

            if ($this->http->XPath->query("./descendant::tr[string-length(normalize-space(.))>2]", $root)->length > 0) {
                $node = $this->http->FindSingleNode("./descendant::tr[not({$this->contains($this->t('Seat(s)'))})][last()]/descendant::td[normalize-space(.)!=''][1]//text()[normalize-space(.)!='' and not({$this->eq($this->t('Departure'))})]", $root);

                if (preg_match("#(\d+:\d+)\s*(.+)#", $node, $m)) {
                    if (isset($date) && $date !== false) {
                        $s->departure()->date(strtotime($m[1], $date));
                    }
                    $s->departure()->name($m[2]);
                    $s->departure()->noCode();
                }

                $node = $this->http->FindSingleNode("./descendant::tr[{$this->contains($this->t('Seat(s)'))}]", $root);

                if (!empty($node) && preg_match_all('/\b(\d{1,3}[A-Z])\b/i', $node, $v)) {
                    $s->extra()->seats($v[1]);
                }

                $node = $this->http->FindSingleNode("./descendant::tr[not({$this->contains($this->t('Seat(s)'))})][last()]/descendant::td[normalize-space(.)!=''][2]//text()[normalize-space(.)!='' and not({$this->eq($this->t('Arrival'))})]", $root);

                if (preg_match("#(\d+:\d+)\s*(.+?)\s*:?(?:\((.*?)\s*(?:[\s\\ ]*([A-Z]{1,2}))\)|$)#", $node, $m)) {
                    if (isset($date) && $date !== false) {
                        $s->arrival()->date(strtotime($m[1], $date));
                    }
                    $s->arrival()->name($m[2]);
                    $s->arrival()->noCode();

                    if (isset($m[3]) && !empty($m[3])) {
                        $s->extra()->cabin(trim(str_replace("\\", '', $m[3])));
                    }

                    if (isset($m[4]) && !empty($m[4])) {
                        $s->extra()->bookingCode($m[4]);
                    }
                }
            } else {
                $node = $this->http->FindSingleNode("(./descendant::td[normalize-space(.)!=''][1]//text()[normalize-space(.)!='' and not({$this->eq($this->t('Departure'))})])[last()]", $root);

                if (!preg_match("/\d+/", $node)) {
                    $nodeTemp = implode("\n", $this->http->FindNodes("./descendant::td[normalize-space(.)!=''][1]//text()[normalize-space(.)]", $root));

                    if (preg_match("/{$this->opt($this->t('Departure'))}\s+(.+)/su", $nodeTemp, $m)) {
                        $node = $m[1];
                    }
                }

                if (preg_match("#(?<time>\d+:\d+)(?:\s*Uhr)?\s*(?<name>[\D\s]{3,})$#", $node, $m) || preg_match("#^(?<name>[\D\s]{3,}?)\s*(?<time>\d+:\d+)(?:\s*Uhr)?$#", $node, $m)) {
                    if (isset($date) && $date !== false) {
                        $s->departure()->date(strtotime($m['time'], $date));
                    }
                    $s->departure()->name($m['name']);
                    $s->departure()->noCode();
                }
                $node = $this->http->FindSingleNode("./descendant::td[normalize-space(.)!=''][2]//text()[normalize-space(.)!='' and not({$this->eq($this->t('Arrival'))})]", $root);

                if (empty($node)) {
                    $node = $this->http->FindSingleNode("./following::text()[{$this->starts($this->t('Arrival'))}][1]/following::text()[normalize-space()][1]", $root);
                }

                if (!preg_match("/\d+/", $node)) {
                    $nodeTemp = implode("\n", $this->http->FindNodes("./descendant::td[normalize-space(.)!=''][2]//text()[normalize-space(.)]", $root));

                    if (preg_match("/{$this->opt($this->t('Arrival'))}\s+(.+)/su", $nodeTemp, $m)) {
                        $node = $m[1];
                    }
                }

                if (preg_match("#(?<time>\d+:\d+)(?:\s*Uhr)?\s*(?<name>[\D\s]{3,})$#", $node, $m) || preg_match("#^(?<name>[\D\s]{3,})\s*(?<time>\d+:\d+)(?:\s*Uhr)?$#", $node, $m)) {
                    if (isset($date) && $date !== false) {
                        $s->arrival()->date(strtotime($m['time'], $date));
                    }
                    $s->arrival()->name($m['name']);
                    $s->arrival()->noCode();
                }
            }

            if (!empty($seatsTable) && count($seatsTable) == $nodes->length && !empty($seatsTable[$key])) {
                $seatsTable[$key] = array_map(function ($v) { if (!empty($v) && preg_match('#\b(\d{1,3}[A-Z])\b#', $v, $match)) { return $match[1]; } else {return false; }}, $seatsTable[$key]);
                $seatsTable[$key] = array_filter($seatsTable[$key]);

                if (!empty($seatsTable[$key])) {
                    $s->extra()->seats($seatsTable[$key]);
                }
            }
        }

        return $email;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang($body)
    {
        foreach ($this->reBody as $lang => $reBody) {
            foreach ($reBody as $re) {
                if (stripos($body, $re) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function getTotalCurrency($node)
    {
        $node = str_replace("€", "EUR", $node);
        $node = str_replace("e", "EUR", $node);
        $node = str_replace("$", "USD", $node);
        $node = str_replace("£", "GBP", $node);
        $node = str_replace("Złoty", "PLN", $node);
        $node = str_replace(["forint", "Forint"], "HUF", $node);
        $node = str_replace("DK", "DKK", $node);
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m) || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m) || preg_match("#(?<c>\-*?)(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)) {
            $m['t'] = preg_replace('/\s+/', '', $m['t']);            // 11 507.00	->	11507.00
            $m['t'] = preg_replace('/[,.](\d{3})/', '$1', $m['t']);    // 2,790		->	2790		or	4.100,00	->	4100,00
            $m['t'] = preg_replace('/,(\d{2})$/', '.$1', $m['t']);    // 18800,00		->	18800.00
            $tot = $m['t'];
            $cur = $m['c'];
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function eq($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
    }

    private function starts($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'starts-with(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function contains($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'contains(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) { return preg_quote($s, '/'); }, $field)) . ')';
    }
}
