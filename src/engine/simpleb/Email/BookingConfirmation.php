<?php

namespace AwardWallet\Engine\simpleb\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Common\Hotel;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BookingConfirmation extends \TAccountChecker
{
    public $mailFiles = "simpleb/it-279953395.eml, simpleb/it-281386041.eml, simpleb/it-281585799.eml, simpleb/it-282618036.eml, simpleb/it-284070349.eml, simpleb/it-284276834.eml, simpleb/it-285201887.eml, simpleb/it-295385233.eml, simpleb/it-307852981.eml, simpleb/it-646808126.eml, simpleb/it-646942555.eml";

    public $lang = '';

    public $detectLang = [
        "it" => ["Informazioni sulla prenotazione"],
        "de" => ["Ihre Reservierung wurde korrekt annulliert"],
        "en" => ["Room", "Accommodation", "Your booking was correctly cancelled"],
    ];

    public $reSubject;

    public static $dictionary = [
        "en" => [
            'Confirmation number'   => ['Confirmation number', 'Booking #:', 'Reservation No:', 'Prenotazione #:'],
            'Guest data'            => ['Guest data', 'Guest Information', 'Reservation Information'],
            'Total amount :'        => ['Total amount :', 'Total Amount:'],
            'Lodging information'   => ['Lodging information', 'Hotel Information', 'Hotel information', 'Lodging Information', 'B&B Info'],
            'Cancellation Policies' => ['Cancellation Policies', 'Cancellation', 'Cancellation Policy'],
            'Persons:'              => ['Persons:', 'Guests:'],
            'Dear'                  => ['Dear', 'Grüezi', 'Hi '],
            'Arrival:'              => ['Arrival:', 'ARRIVAL', 'Arrivo:', 'Date of arrival:', 'Arrival'],
            'Departure:'            => ['Departure:', 'DEPARTURE', 'Partenza:', 'Date of departure:', 'Departure'],
            'Number of guests'      => ['Number of guests', 'NUMBERS OF GUESTS', 'Guests', 'Persons:'],
            'Check-in'              => ['Check-in', 'Check-In Starts at:', 'Check-In is at', 'Check-In:'],
            'Check-out'             => ['Check-Out:', 'Check-out', 'Check-out is at', 'Check out:'],
            'Name:'                 => ['Name:', 'Nominativo:'],
            'Room type:'            => ['Room type:', 'Accommodation type:', 'Apartment type:'],
            'Accommodation total'   => ['Accommodation total', 'Room total'],
            'Phone:'                => ['Phone:', 'Tel.'],

            // Cancelled format
            // 'Your booking was correctly cancelled' => '',
            // 'Booking N.:' => '',
            // 'date' => '',
            // 'at' => '',
        ],

        "it" => [
            'Modify / Cancel this Reservation' => 'Modifica / Cancella questa prenotazione',
            'Room type:'                       => 'Tipo Camera:',
            'Period'                           => 'Periodo Tar.',

            'Confirmation number'   => 'Prenotazione #:',
            //'Guest Name' => '',
            'Guest data' => 'Dati ospite',
            'Name:'      => 'Nominativo:',
            //'Province:' => '',
            //'Dear' => '',
            'Free cancellation until:' => 'Cancellazione gratuita fino al:',
            'Free cancellation'        => 'Cancellazione gratuita',
            'Booking Confirmation'     => 'Conferma prenotazione',
            //'We’re delighted to welcome you at' => '',
            'Lodging information' => ['Informazioni sulla struttura'],
            'Phone:'              => ['Tel.:', 'Telefono:'],
            //'Property:'           => '',
            //'Address:' => '',
            //'Fax:' => '',
            //'Contact details' => '',
            //'Stay' => '',
            'Arrival:'   => 'Arrivo:',
            'Departure:' => 'Partenza:',
            //'Check-in' => '',
            //'Check-out' => '',
            //'Number of guests' => '',
            'Adults:' => 'Adulti',
            //'Kids:' => '',
            'Persons:'                  => 'Ospiti:',
            'Rate plan:'                => 'Piano tariffario:',
            'Total amount :'            => 'Importo globale:',
            'Total additional services' => 'Totale servizi aggiuntivi:',
            'Total Rooms:'              => 'Totale Camere:',
            'Meal plan:'                => 'Trattamento:',
            'Price'                     => 'Importo',
            'Accommodation total'       => 'Totale Camera',
        ],
        "de" => [
            // 'Modify / Cancel this Reservation' => 'Modifica / Cancella questa prenotazione',
            // 'Room type:'                       => 'Tipo Camera:',
            // 'Period'                           => 'Periodo Tar.',

            // 'Confirmation number'   => 'Prenotazione #:',
            //'Guest Name' => '',
            // 'Guest data' => 'Dati ospite',
            // 'Name:'      => 'Nominativo:',
            //'Province:' => '',
            'Dear' => 'Geehrter',
            // 'Free cancellation until:' => 'Cancellazione gratuita fino al:',
            // 'Free cancellation'        => 'Cancellazione gratuita',
            // 'Booking Confirmation'     => 'Conferma prenotazione',
            //'We’re delighted to welcome you at' => '',
            // 'Lodging information' => ['Informazioni sulla struttura'],
            // 'Phone:'              => ['Tel.:', 'Telefono:'],
            //'Property:'           => '',
            //'Address:' => '',
            //'Fax:' => '',
            //'Contact details' => '',
            //'Stay' => '',
            // 'Arrival:'   => 'Arrivo:',
            // 'Departure:' => 'Partenza:',
            //'Check-in' => '',
            //'Check-out' => '',
            //'Number of guests' => '',
            // 'Adults:' => 'Adulti',
            //'Kids:' => '',
            // 'Persons:'                  => 'Ospiti:',
            // 'Rate plan:'                => 'Piano tariffario:',
            // 'Total amount :'            => 'Importo globale:',
            // 'Total additional services' => 'Totale servizi aggiuntivi:',
            // 'Total Rooms:'              => 'Totale Camere:',
            // 'Meal plan:'                => 'Trattamento:',
            // 'Price'                     => 'Importo',
            // 'Accommodation total'       => 'Totale Camera',
            // Cancelled format
            'Your booking was correctly cancelled' => 'Ihre Reservierung wurde korrekt annulliert',
            "Here's a summary:"                    => 'Hier eine Zusammenfassung:',
            'Booking N.:'                          => 'Reservierung Nummer:',
            'date'                                 => 'des',
            'at'                                   => 'beim',
            'Cancellation Policies'                => ['Stornierungsfrist'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $this->assignLang();

        if ($this->http->XPath->query("//text()[{$this->contains($this->t('Modify / Cancel this Reservation'))}]")->length > 0
            || $this->http->XPath->query("//text()[{$this->contains($this->t('The following reservation has been cancelled:'))}]")->length > 0
            || $this->http->XPath->query("//text()[{$this->contains($this->t('Cancellation Policies'))}]")->length > 0) {
            return

                ($this->http->XPath->query("//text()[{$this->contains($this->t('Room type:'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Meal plan:'))}]")->length > 0)

                || ($this->http->XPath->query("//text()[{$this->eq($this->t("Here's a summary:"))}]/following::text()[normalize-space()][1][{$this->starts($this->t('Booking N.:'))}]")->length > 0)

                || ($this->http->XPath->query("//text()[contains(normalize-space(.), 'Confirmation number')]/ancestor::tr[1]/following::tr[1][starts-with(normalize-space(), 'Stay')]")->length > 0);
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]simplebooking\.it$/', $from) > 0;
    }

    public function ParseHotel(Email $email)
    {
        $h = $email->add()->hotel();

        $confirmation = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Confirmation number'))}]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Confirmation number'))}\s*([A-Z\d]{5,})$/");

        if (empty($confirmation)) {
            $confirmation = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Confirmation number'))}]/following::text()[normalize-space()][1]", null, true, "/^\s*(\d+)$/");
        }

        if (empty($confirmation)) {
            $confirmation = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Confirmation number'))}]", null, true, "/^{$this->opt($this->t('Confirmation number'))}\:?\s*(\d+)$/");
        }

        if (empty(trim($confirmation))) {
            $confirmation = $this->re("/{$this->opt($this->t('Booking Confirmation'))}[\s\-]+(\d+)/ui", $this->reSubject);
        }

        if (!empty($confirmation)) {
            $h->general()
                ->confirmation($confirmation);
        }

        $traveller = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Guest Name'))}]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Guest Name'))}\s*(\D+)$/");

        if (empty($traveller)) {
            $traveller = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Guest data'))}]/following::text()[{$this->eq($this->t('Name:'))}][1]/following::text()[normalize-space()][1]", null, true, "/^\s*(\D+)$/");
        }

        if (empty($traveller)) {
            $traveller = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Province:'))}]/preceding::text()[string-length()>3][1]", null, true, "/^\s*(\D+)$/");
        }

        if (empty($traveller)) {
            $traveller = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Reservation under'))}]/following::text()[normalize-space()][1]");
        }

        if (empty($traveller)) {
            $traveller = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Dear'))}]", null, true, "/^{$this->opt($this->t('Dear'))}\s*(\D+)$/");
        }

        if (!preg_match('/^\s*Kunde\s*$/', $traveller)) {
            $h->general()
                ->traveller(trim(str_replace('Mr./Ms. ', '', $traveller), ','));
        }

        if ($this->http->XPath->query("//text()[{$this->contains($this->t('Your booking was correctly cancelled'))}]")->length > 0) {
            $h->general()
                ->cancelled()
                ->status('cancelled');

            $cancelledInfo = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Booking N.:'))}]");

            if (preg_match("/^{$this->opt($this->t('Booking N.:'))}\s*(?<confNumber>\d+)\s*{$this->opt($this->t('date'))}\s*(?<issue>\d+\s*\w+\s*\d{4}\s*\d+\:\d+)\:\d+\s*{$this->opt($this->t('at'))}\s*(?<hotelName>.+)$/", $cancelledInfo, $m)) {
                $h->general()
                    ->date(strtotime($m['issue']))
                    ->confirmation($m['confNumber']);

                $h->hotel()
                    ->name($m['hotelName']);
            }

            return $email;
        }

        $cancellation = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Modify / Cancel this Reservation'))}]/following::text()[normalize-space()][1][{$this->starts($this->t('Free cancellation'))}]");

        if (empty($cancellation)) {
            $cancellation = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Cancellation Policies'))}]/following::text()[normalize-space()][1]/ancestor::tr[1]", null, true, "/^(.+)\s*/");
            $cancellation = str_replace($this->t('Modify / Cancel this Reservation'), '', $cancellation);
        }

        if (!empty($cancellation) && strlen($cancellation) > 2000) {
            $h->general()
                ->cancellation('Please contact us for more information regarding our reservation cancellation policies.');
        }

        if (!empty($cancellation) && strlen($cancellation) < 2000) {
            $h->general()
                ->cancellation($cancellation);
        }

        $hotelName = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Welcome to the')]", null, true, "/{$this->opt($this->t('Welcome to the'))}\s*(.+)/");

        if (empty($hotelName)) {
            $hotelName = $this->re("/^\s*(.+)\s*\-\s*{$this->opt($this->t('Booking Confirmation'))}/", $this->reSubject);
        }

        if (empty($hotelName)) {
            $hotelName = $this->re("/^{$this->opt($this->t('Booking Confirmation'))}\s*(.+)\s*\-\s*\d+/", $this->reSubject);
        }

        if (empty($hotelName)) {
            $hotelName = $this->re("/^\s*(.+)\s*\-\s*{$this->opt($this->t('Cancel confirmation'))}/", $this->reSubject);

            if (!empty($hotelName)) {
                $h->general()
                    ->status('canceled')
                    ->cancelled();
            }
        }

        if (empty($hotelName)) {
            $hotelName = $this->http->FindSingleNode("//text()[{$this->eq($this->t('We’re delighted to welcome you at'))}]/following::text()[normalize-space()][1]");
        }

        if (!empty($hotelName)) {
            $h->hotel()
                ->name(str_replace("Fwd: ", "", $hotelName));
        }

        $timeIn = '';

        if ($this->http->XPath->query("//text()[{$this->eq($this->t('Lodging information'))}]")->length > 0) {
            $hotelInfo = implode("\n", $this->http->FindNodes("//text()[{$this->eq($this->t('Lodging information'))}]/following::text()[normalize-space()][1]/following::text()[normalize-space()][1]/ancestor::tr[1]/descendant::text()[normalize-space()]"));

            if (preg_match("#{$this->opt($h->getHotelName())}\n(?<address>(?:.+\n){1,2})(?:Check\-In\s*from\s*(?<timeIn>\d+a?p?m?)\s*onwards\.\n|\s*Travel Route\n)?{$this->opt($this->t('Phone:'))}\n*(?<phone>[+\d\s\)\(\-\.\*]+)\n#u", $hotelInfo, $m)
            || preg_match("#^(?<hotelName>.+)\n(?<address>(?:.+\n){1,2})(?:Check\-In\s*from\s*(?<timeIn>\d+a?p?m?)\s*onwards\.\n|\s*Travel Route\n)?{$this->opt($this->t('Phone:'))}\n*(?<phone>[+\d\s\)\(\-\.]+)\n#u", $hotelInfo, $m)
            || preg_match("#^(?<hotelName>.+)\n(?<address>(?:(.+\n){1,3}))(For.*\n*)?{$this->opt($this->t('Phone:'))}\n*(?<phone>[+\d\s\)\(\-\.\*\/]+)\n?#u", $hotelInfo, $m)
            ) {
                $h->hotel()
                    ->address(str_replace("\n", " ", $m['address']));

                if (strlen(str_replace('*', '', $m['phone'])) > 5) {
                    $h->hotel()
                        ->phone(str_replace('*', '', $m['phone']));
                }

                if (isset($m['timeIn']) && !empty($m['timeIn'])) {
                    $timeIn = $m['timeIn'];
                }

                //If the name of the hotel in subject email is not the same as the name of the hotel in Lodging information
                //it-284276834.eml
                if (isset($m['hotelName']) && !empty($m['hotelName'])) {
                    $h->hotel()
                        ->name($m['hotelName']);
                }
            }
        } elseif ($this->http->XPath->query("//text()[{$this->eq($this->t('Property:'))}]/following::text()[normalize-space()][1]")->length > 0) {
            $xpath = "//text()[{$this->eq($this->t('Property:'))}]/ancestor::tr[1]";
            $address = $this->http->FindSingleNode($xpath . "/following::tr[1]/descendant::text()[{$this->eq($this->t('Address:'))}]/following::text()[normalize-space()][1]");

            if (!empty($address)) {
                $h->hotel()
                    ->address($address);
            }

            $phone = $this->http->FindSingleNode($xpath . "/following::tr[2]/descendant::text()[{$this->eq($this->t('Phone:'))}]/following::text()[normalize-space()][1]");

            if (!empty($address)) {
                $h->hotel()
                    ->phone($phone);
            }

            $fax = $this->http->FindSingleNode($xpath . "/following::tr[2]/descendant::text()[{$this->eq($this->t('Fax:'))}]/following::text()[normalize-space()][1]");

            if (!empty($fax)) {
                $h->hotel()
                    ->fax($fax);
            }
        } elseif ($this->http->XPath->query("//text()[{$this->eq($this->t('Contact details'))}]/following::text()[{$this->eq($h->getHotelName())}]")->length > 0) {
            $hotelInfo = implode("\n", $this->http->FindNodes("//text()[{$this->eq($this->t('Contact details'))}]/following::text()[{$this->eq($h->getHotelName())}][1]/ancestor::td[1]/descendant::text()[normalize-space()]"));

            if (preg_match("/{$h->getHotelName()}\n(?<address>(?:.+\n*){1,2})\n*(?<phone>[+\d\s\)\(\-]+)?(?:\n|$)/u", $hotelInfo, $m)) {
                $h->hotel()
                    ->address(str_replace("\n", " ", $m['address']))
                    ->phone($m['phone']);
            }
        } elseif ($this->http->XPath->query("//text()[{$this->eq($this->t('Modify / Cancel this Reservation'))}]/following::text()[{$this->starts($h->getHotelName())}]")->length == 1) {
            $hotelInfo = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Modify / Cancel this Reservation'))}]/following::text()[{$this->starts($h->getHotelName())}]");

            if (preg_match("/{$h->getHotelName()}\s*\|\s*(?<address>.+)\|\s+Tel\.\s+(?<phone>[+\s\d\(\)]+)$/u", $hotelInfo, $m)
            || preg_match("/{$h->getHotelName()}\s*(?<address>.+)/", $hotelInfo, $m)) {
                $h->hotel()
                    ->address(str_replace("|", "", $m['address']));

                if (empty($m['phone'])) {
                    $m['phone'] = $this->http->FindSingleNode("//text()[{$this->starts($h->getHotelName())}]/following::a[contains(@href, '@')]/preceding::text()[string-length()>10][1]", null, true, "/^\s*(?:{$this->opt($this->t('Phone:'))}\s*)?([+\s\d\(\)]+)\s*\|?\s*$/");
                }

                $h->hotel()
                  ->phone($m['phone']);
            } else {
                $h->hotel()
                    ->noAddress();
            }
        } elseif ($this->http->XPath->query("//text()[{$this->eq($h->getHotelName())}]/following::text()[normalize-space()][1][{$this->eq($this->t('Address:'))}]")->length == 1) {
            $hotelInfo = implode("\n", $this->http->FindNodes("//text()[{$this->eq($h->getHotelName())}]/following::text()[normalize-space()][1][{$this->eq($this->t('Address:'))}]/ancestor::td[1]/descendant::text()[normalize-space()]"));

            if (preg_match("/{$h->getHotelName()}\n{$this->opt($this->t('Address:'))}\n(?<address>(?:.+\n){1,2}){$this->opt($this->t('Stars:'))}(?:.+\n){1,2}{$this->opt($this->t('Phone:'))}\n(?<phone>[+\d\s\)\(\-]+)/u", $hotelInfo, $m)) {
                $h->hotel()
                    ->address(str_replace("|", "", $m['address']))
                    ->phone($m['phone']);
            }
        } else {
            $h->hotel()
            ->noAddress();
        }

        //date checkIn|Out
        if ($this->http->XPath->query("//text()[{$this->eq($this->t('Stay'))}]")->length > 0) {
            $dateRange = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Stay'))}]/ancestor::tr[1]/descendant::td[normalize-space()][2]");

            if (preg_match("/^(.+\d{4})\s*{$this->opt($this->t('to'))}\s*(.+\d{4})$/", $dateRange, $m)) {
                $h->booked()
                    ->checkIn(strtotime($m[1]))
                    ->checkOut(strtotime($m[2]));
            }
        }

        if ($this->http->XPath->query("//text()[{$this->eq($this->t('Arrival:'))}]")->length > 0) {
            $checkIn = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Arrival:'))}]/following::text()[normalize-space()][1]", null, true, "/^(\d+\s*\w+\s*\d{4}.*)$/");

            if (!empty($checkIn)) {
                $h->booked()->checkIn($this->normalizeDate($checkIn));
            }

            $checkOut = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Departure:'))}]/following::text()[normalize-space()][1]", null, true, "/^(\d+\s*\w+\s*\d{4}.*)$/");

            if (!empty($checkOut)) {
                $h->booked()->checkOut($this->normalizeDate($checkOut));
            }
        } elseif ($this->http->XPath->query("//text()[{$this->starts($this->t('Arrival:'))}]")->length > 0) {
            $checkIn = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Arrival:'))}]", null, true, "/^{$this->opt($this->t('Arrival:'))}\s*(\d+\s*\w+\s*\d{4})$/");

            if (!empty($checkIn)) {
                $h->booked()->checkIn($this->normalizeDate($checkIn));
            }

            $checkOut = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Departure:'))}]", null, true, "/^{$this->opt($this->t('Departure:'))}\s*(\d+\s*\w+\s*\d{4})$/");

            if (!empty($checkOut)) {
                $h->booked()->checkOut($this->normalizeDate($checkOut));
            }
        } elseif ($this->http->XPath->query("//text()[{$this->starts($this->t('Arrival:'))}]")->length === 0
                  && $this->http->XPath->query("//text()[{$this->starts($this->t('Check-in'))}]")->length > 0) {
            $checkIn = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Check-in'))}]/following::text()[normalize-space()][1]", null, true, "/^(\d+\s*\w+\s*\d{4}.*)$/");

            if (!empty($checkIn)) {
                $h->booked()->checkIn($this->normalizeDate($checkIn));
            }

            $checkOut = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Check-out'))}]/following::text()[normalize-space()][1]", null, true, "/^(\d+\s*\w+\s*\d{4}.*)$/");

            if (!empty($checkOut)) {
                $h->booked()->checkOut($this->normalizeDate($checkOut));
            }
        }

        if (empty($timeIn)) {
            $timeIn = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Check-in'))}]", null, true, "/{$this->opt($this->t('Check-in'))}:?\s*{$this->opt($this->t('from'))}\s*(\d{1,2}[\.:]\d{2}(?:\s*[ap]m?)?)\b/i");
        }

        if (empty($timeIn)) {
            $timeIn = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Check-in'))}]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Check-in'))}:?\s*{$this->opt($this->t('from'))}\s*(\d{1,2}[\.:]\d{2}(?:\s*[ap]m?)?)\s*$/i");
        }

        if (empty($timeIn)) {
            $timeIn = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Check-in'))}]/following::text()[normalize-space()][1]", null, true, "/^\s*(\d{1,2}[\.:]\d{2}(?:\s*[ap]m?)?)\s*$/i");
        }

        if (empty($timeIn)) {
            $timeIn = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Arrival:'))}]/ancestor::td[1]", null, true, "/{$this->opt($this->t('check in from'))}\s*(\d{1,2}[\.:]\d{2}(?:\s*[ap]\.?m\.?)?)\s*$/i");
        }

        if (!empty($timeIn) && !empty($h->getCheckInDate())) {
            $h->booked()
                ->checkIn(strtotime(preg_replace("/^(\d+)\.(\d+)/", "$1:$2", $timeIn), $h->getCheckInDate()));
        }

        $timeOut = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Check-out'))}]", null, true, "/{$this->opt($this->t('Check-out'))}[:]?\s*{$this->opt($this->t('until'))}\s*(\d{1,2}[\.:]\d{2}(?:\s*[ap]m?)?)\b/i");

        if (empty($timeOut)) {
            $timeOut = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Check-out'))}]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Check-out'))}\s*{$this->opt($this->t('until'))}\s*(\d{1,2}[\.:]\d{2}(?:\s*[ap]m?)?)\s*$/i");
        }

        if (empty($timeOut)) {
            $timeOut = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Check-out'))}]/following::text()[normalize-space()][1]", null, true, "/^\s*(\d{1,2}[\.:]\d{2}(?:\s*[ap]m?)?)\s*$/i");
        }

        if (empty($timeOut)) {
            $timeOut = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Departure:'))}]/ancestor::td[1]", null, true, "/{$this->opt($this->t('check out before'))}\s*(\d{1,2}[\.:]\d{2}(?:\s*[ap]\.?m\.?)?)\s*$/i");
        }

        if (!empty($timeOut) && !empty($h->getCheckOutDate())) {
            $h->booked()
                ->checkOut(strtotime(preg_replace("/^(\d+)\.(\d+)/", "$1:$2", $timeOut), $h->getCheckOutDate()));
        }

        $guestsInfo = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Number of guests'))}]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Number of guests'))}\s*(.+)$/");

        if (preg_match("/^\s*{$this->opt($this->t('Adults:'))}\s*(?<adults>\d+)(?:\s*\-\s*{$this->opt($this->t('Kids:'))}\s*(?<kids>\d+))$/", $guestsInfo, $m)
        || preg_match("/^(?<adults>\d+)$/", $guestsInfo, $m)
        || preg_match("/^(?<adults>\d+)\s*{$this->opt($this->t('Persons'))}/", $guestsInfo, $m)) {
            $h->booked()
                ->guests($m['adults']);

            if (isset($m['kids']) && $m['kids'] !== null) {
                $h->booked()
                    ->kids($m['kids']);
            }
        }

        if (empty($guestsInfo)) {
            $guests = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Persons:'))}]/following::text()[normalize-space()][1]", null, true, "/^(\d+)$/");

            if (!empty($guests)) {
                $h->booked()
                    ->guests($guests);
            }
        }

        if (empty($guestsInfo)) {
            $guests = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Persons:'))}]", null, true, "/^{$this->opt($this->t('Persons:'))}\s*(\d+)$/");

            if (!empty($guests)) {
                $h->booked()
                    ->guests($guests);
            }
        }

        if (empty($guestsInfo)) {
            $guests = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Guest Number:'))}]/ancestor::tr[1]", null, true, "/^{$this->opt($this->t('Guest Number:'))}\s*(\d+)$/");

            if (!empty($guests)) {
                $h->booked()
                    ->guests($guests);
            }
        }

        $roomNote = $this->http->XPath->query("//text()[{$this->starts($this->t('Room type:'))}]");

        if ($roomNote->length > 0) {
            $h->booked()
                ->rooms($roomNote->length);

            foreach ($roomNote as $root) {
                $room = $h->addRoom();

                $roomType = $this->http->FindSingleNode("./following::text()[normalize-space()][1]", $root);
                $rateType = $this->http->FindSingleNode("./following::text()[{$this->starts($this->t('Rate plan:'))}][1]/following::text()[normalize-space()][1][not(contains(normalize-space(), 'Offer applied:'))]", $root);
                $rate = implode("; ", $this->http->FindNodes("./following::tr[{$this->starts($this->t('Period'))} and {$this->contains($this->t('Price'))}][1]/following-sibling::tr[not({$this->contains($this->t('Accommodation total'))})]/descendant::td[2]", $root));

                if (!empty($roomType) || !empty($rateType) || !empty($rate)) {
                    if (!empty($roomType)) {
                        $room->setType($roomType);
                    }

                    if (!empty($rateType)) {
                        $room->setRateType($rateType);
                    }

                    if (!empty($rate)) {
                        $room->setRate($rate);
                    }
                }
            }
        }

        $priceText = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Total amount :'))}]/ancestor::tr[1]");

        if (preg_match("/{$this->opt($this->t('Total amount :'))}\s*(?<total>[\d\.\,]+)\s*(?<currency>[A-Z]{3})\(*.*$/", $priceText, $m)) {
            $h->price()
                ->total(PriceHelper::parse($m['total'], $m['currency']))
                ->currency($m['currency']);

            $tax = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Total additional services'))}]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Total additional services'))}\s*([\d\.\,]+)/");

            if ($tax !== null) {
                $h->price()
                    ->tax(PriceHelper::parse($tax, $m['currency']));
            }

            $cost = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Total Rooms:'))}]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Total Rooms:'))}\s*([\d\.\,]+)/");

            if (!empty($cost)) {
                $h->price()
                    ->cost(PriceHelper::parse($cost, $m['currency']));
            }
        }

        $this->detectDeadLine($h);
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        $this->reSubject = $parser->getSubject();

        $this->ParseHotel($email);

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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) { return "normalize-space(.)=\"{$s}\""; }, $field)) . ')';
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function detectDeadLine(Hotel $h)
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (preg_match("/^{$this->opt($this->t('Free cancellation until:'))} (\d+\s*\w+\s*\d{4}\s*\d+\:\d+)\:\d+$/",
            $cancellationText, $m)
        ) {
            $h->booked()->deadline($this->normalizeDate($m[1]));
        }

        if (preg_match('/^(\d+\.\d+\.\d{4}\s*[\d\:]+)$/',
            $cancellationText, $m)
        ) {
            $h->booked()->deadline(strtotime($m[1]));
        }

        if (preg_match('/Cancellations and changes are free of charge until (\d+\s*days?) before arrival/',
            $cancellationText, $m)
            || preg_match('/Cancellation is free of charge up to (\d+\s*days?) before the arrival date/',
            $cancellationText, $m)
        ) {
            $h->booked()->deadlineRelative($m[1]);
        }

        if (preg_match('/no refund\, \d+[%] prepayment/', $cancellationText, $m)
        || preg_match('/If you modify your reservation, this deposit is not refundable/', $cancellationText, $m)
        || preg_match('/Non refundable price, /', $cancellationText, $m)
        ) {
            $h->booked()->nonRefundable();
        }
    }

    private function assignLang()
    {
        foreach ($this->detectLang as $key => $words) {
            foreach ($words as $word) {
                if ($this->http->XPath->query("//text()[{$this->contains($word)}]")->length > 0) {
                    $this->lang = $key;

                    return true;
                }
            }
        }

        return false;
    }

    private function normalizeDate($str)
    {
        $in = [
            "#^[\w\-]+\,\s*(\d+)\.?\s*(?:de\s+)?(\w+)(?:\s+de)?\s*(\d{4})$#u", //Miércoles, 19 de mayo de 2021
            "#^(\d+\s*\w+\s*\d{4})\s*\(\D+\s+([\d\:]+\s*a?p?m?)\)$#u", //28 Oct 2023 (check-in 2pm)
        ];
        $out = [
            "$1 $2 $3",
            "$1, $2",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }
}
