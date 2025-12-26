<?php

namespace AwardWallet\Engine\leohotels\Email;

use AwardWallet\Schema\Parser\Email\Email;

class ConfirmationReservationHotel extends \TAccountChecker
{
    public $mailFiles = "leohotels/it-43383679.eml, leohotels/it-53513843.eml, leohotels/it-53768189.eml, leohotels/it-54026167.eml"; // +3 bcdtravel(html)[de,fr,en]

    protected $lang = '';

    protected $langDetectors = [
        'de' => ['Ankunftsdatum'],
        'fr' => ['Informations concernant la réservation'],
        'es' => ['Información de la reserva'],
        'pt' => ['Informações sobre o reserva'],
        'it' => ['Informazioni sulla prenotazione'],
        'en' => ['Arrival Date:'],
    ];

    protected static $dict = [
        'de' => [
            'Reservation Number:' => 'Reservierungsnummer:',
            'Guest Name:'         => 'Gastname:',
            'Check-In:'           => 'Ankunftsdatum:',
            'Check-Out:'          => 'Abreisedatum:',
            'People:'             => 'Personen:',
            'Adults'              => 'Erwachsenen',
            'Room:'               => 'Zimmer:',
            'Meals:'              => 'Verpflegung:',
            'Description:'        => 'Beschreibung:',
            'Total Price:'        => 'Gesamtpreis Reservierung:',
            //            'points->detect' => '',
            //            'points->pattern' => '/^/i'
        ],
        'fr' => [
            'Reservation Number:' => 'Numéro de réservation:',
            'Guest Name:'         => 'Nom:',
            'Check-In:'           => 'Arrivée:',
            'Check-Out:'          => 'Départ:',
            'People:'             => 'Clients:',
            'Adults'              => 'Adulte',
            'Room:'               => 'Chambre:',
            'Meals:'              => 'Plan de repas:',
            'Description:'        => 'Description du tarif:',
            'Total Price:'        => 'Prix total de la réservation:',
            //            'points->detect' => '',
            //            'points->pattern' => '/^/i'
        ],
        'es' => [
            'Reservation Number:' => 'Número de reserva:',
            'Guest Name:'         => 'Nombre:',
            'Check-In:'           => 'Llegada:',
            'Check-Out:'          => 'Salida:',
            'People:'             => 'Huéspedes:',
            'Adults'              => 'Adult',
            'Room:'               => 'Habitación:',
            'Meals:'              => 'Plan de comidas:',
            'Description:'        => 'Descripción del precio:',
            'Total Price:'        => 'Precio total de la reserva:',
            'points->detect'      => 'en tu cuenta Leonardo Advantage',
            'points->pattern'     => '/^Se abonarán (.*?\d.*? puntos) en tu cuenta Leonardo Advantage/i',
        ],
        'pt' => [
            'Reservation Number:' => 'Número de reserva:',
            'Guest Name:'         => 'Nome:',
            'Check-In:'           => 'Data de chegada:',
            'Check-Out:'          => 'Data de partida:',
            'People:'             => 'Hóspedes:',
            'Adults'              => 'Adulto',
            'Room:'               => 'Quarto:',
            'Meals:'              => 'plano de refeição:',
            'Description:'        => 'Descrição da tarifa:',
            'Total Price:'        => 'Preço total da reserva:',
            'points->detect'      => 'will be credited on your Leonardo Advantage',
            'points->pattern'     => '/^(.*?\d.*? points) will be credited on your Leonardo Advantage/i',
        ],
        'it' => [
            'Reservation Number:' => 'Numero di prenotazione:',
            'Guest Name:'         => 'Nome:',
            'Check-In:'           => 'Arrivo:',
            'Check-Out:'          => 'Partenza:',
            'People:'             => 'Ospiti:',
            'Adults'              => 'Adulti',
            'Room:'               => 'Camera:',
            'Meals:'              => 'Trattamento:',
            'Description:'        => 'Descrizione della Tariffa:',
            'Total Price:'        => 'Prezzo totale della prenotazione:',
            'points->detect'      => 'sul tuo account Leonardo Advantage',
            'points->pattern'     => '/^Ti verranno accreditati (.*?\d.*? punti) sul tuo account Leonardo Advantage/i',
        ],
        'en' => [
            'Reservation Number:' => 'Reservation number:',
            'Guest Name:'         => 'Guest Name:',
            'Check-In:'           => 'Arrival Date:',
            'Check-Out:'          => 'Departure:',
            'People:'             => 'Guest(s):',
            'Adults'              => 'Adult',
            'Room:'               => 'Room type:',
            'Meals:'              => 'Meal plan:',
            'Description:'        => 'Rate description:',
            'Total Price:'        => 'Total reservation price:',
            'points->detect'      => 'will be credited on your Leonardo Advantage',
            'points->pattern'     => '/^(.*?\d.*? points) will be credited on your Leonardo Advantage/i',
        ],
    ];

    // Standard Methods

    public function detectEmailFromProvider($from)
    {
        return strpos($from, 'Leonardo Hotel Negev') !== false
            || stripos($from, '@leonardo-hotels.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) === false) {
            return false;
        }

        return stripos($headers['subject'], 'Your booking confirmation') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $condition1 = $this->http->XPath->query('//node()[contains(normalize-space(.),"Leonardo Hotel Negev") or contains(.,"@leonardo-hotels.com")]')->length === 0;
        $condition2 = $this->http->XPath->query('//a[contains(@href,"//www.leonardo-hotels.com") or contains(@href,"@leonardo-hotels.com")]')->length === 0;

        if ($condition1 && $condition2) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if ($this->assignLang() === false) {
            return $email;
        }

        $this->parseEmail($email);
        $email->setType('ConfirmationReservationHotel' . ucfirst($this->lang));

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

    protected function normalizeDate($date)
    {
        $in = [
            '/(\d{1,2})\/(\d{1,2})\/(\d{2,4})/',
        ];
        $out = [
            '$1.$2.$3',
        ];

        return strtotime(preg_replace($in, $out, $date));
    }

    protected function normalizePrice($string = '')
    {
        if (empty($string)) {
            return $string;
        }
        $string = preg_replace('/\s+/', '', $string);			// 11 507.00	->	11507.00
        $string = preg_replace('/[,.](\d{3})/', '$1', $string);	// 2,790		->	2790		or	4.100,00	->	4100,00
        $string = preg_replace('/,(\d{2})$/', '.$1', $string);	// 18800,00		->	18800.00

        return $string;
    }

    protected function t($phrase)
    {
        if (!isset(self::$dict[$this->lang]) || !isset(self::$dict[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dict[$this->lang][$phrase];
    }

    protected function assignLang()
    {
        foreach ($this->langDetectors as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if ($this->http->XPath->query('//node()[contains(normalize-space(.),"' . $phrase . '")]')->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function parseEmail(Email $email)
    {
        $r = $email->add()->hotel();

        $r->general()
            ->confirmation($this->http->FindSingleNode('//text()[normalize-space(.)="' . $this->t('Reservation Number:') . '"]/following::text()[normalize-space(.)][1]',
                null, true, '/^([-\d]{5,})$/'));

        $headers = $this->http->XPath->query('//tr[count(./*)=2 and not(.//tr) and ./*[position()=1 and ./descendant::img[contains(@src,"thumb")]]]');

        if ($headers->length > 0) {
            $header = $headers->item(0);
            $hotelName = $this->http->FindSingleNode('./*[1]/descendant::img[contains(@src,"thumb")]/@alt', $header);

            if (!$hotelName) {
                $hotelName = $this->http->FindSingleNode('./*[2]/descendant::*[(name()="h4" or name()="div") and normalize-space(.)!="" and not(.//h4 or .//div)][1]',
                    $header);
            }
            $address = $this->http->FindSingleNode('./*[2]/descendant::*[(name()="h4" or name()="div") and normalize-space(.)!="" and not(.//h4 or .//div)][2]',
                $header);
            $phone = str_replace("–", '-',
                $this->http->FindSingleNode('./*[2]/descendant::*[(name()="h4" or name()="div") and normalize-space(.)!="" and not(.//h4 or .//div)][3]',
                    $header));
            $r->hotel()
                ->name($hotelName)
                ->address($address)
                ->phone($phone);
        }

        if ($guest = $this->http->FindSingleNode('//td[normalize-space(.)="' . $this->t('Guest Name:') . '" and not(.//td)]/following-sibling::td[normalize-space(.)][1]')) {
            $r->general()->traveller($guest);
        }

        if ($checkInDate = $this->http->FindSingleNode('//td[normalize-space(.)="' . $this->t('Check-In:') . '" and not(.//td)]/following-sibling::td[normalize-space(.)][1]')) {
            $r->booked()->checkIn($this->normalizeDate($checkInDate));
        }

        if ($checkOutDate = $this->http->FindSingleNode('//td[normalize-space(.)="' . $this->t('Check-Out:') . '" and not(.//td)]/following-sibling::td[normalize-space(.)][1]')) {
            $r->booked()->checkOut($this->normalizeDate($checkOutDate));
        }

        $guests = $this->http->FindSingleNode('//td[normalize-space(.)="' . $this->t('People:') . '" and not(.//td)]/following-sibling::td[normalize-space(.)][1]');

        if (preg_match('/(\d{1,3})\s+' . $this->t('Adults') . '/i', $guests, $matches)) {
            $r->booked()->guests($matches[1]);
        }

        $room = $r->addRoom();
        $roomType = $this->http->FindSingleNode('//td[normalize-space(.)="' . $this->t('Room:') . '" and not(.//td)]/following-sibling::td[normalize-space(.)][1]');

        if ($roomType) {
            $room->setType($roomType);
        }

        $roomDesc = $this->http->FindSingleNode('//td[normalize-space(.)="' . $this->t('Meals:') . '" and not(.//td)]/following-sibling::td[normalize-space(.)][1]');

        if ($roomDesc) {
            $room->setDescription($roomDesc);
        }

        $cancelPolicy = $this->http->FindSingleNode('//td[normalize-space(.)="' . $this->t('Description:') . '" and not(.//td)]/following-sibling::td[normalize-space(.)][1]');

        if ($cancelPolicy) {
            $r->general()->cancellation(trim($cancelPolicy, ', '));
        }

        $earned = $this->http->FindSingleNode("//text()[contains(normalize-space(),'{$this->t('points->detect')}')]",
            null, true, $this->t('points->pattern'));

        if (!empty($earned)) {
            $r->program()->earnedAwards($earned);
        }

        $payment = $this->http->FindSingleNode('//td[normalize-space(.)="' . $this->t('Total Price:') . '" and not(.//td)]/following-sibling::td[normalize-space(.)][1]');

        if (preg_match('/([^,.\d]+)([,.\d\s]+)$/', $payment, $matches)) {
            $r->price()
                ->currency(str_replace(['€'], ['EUR'], trim($matches[1])))
                ->total((float) $this->normalizePrice($matches[2]));
        }
        $this->detectDeadLine($r);

        return;
    }

    /**
     * Here we describe various variations in the definition of dates deadLine.
     */
    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h)
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (preg_match("/Cancellable until (?<time>.+?) on the day of arrival\. Beyond that time/i", $cancellationText, $m) // en
            || preg_match("/Cancelable hasta las (?<time>.+?) (?:del día de llegada|1 día antes de la llegada)\. Transcurrido este tiempo/i", $cancellationText, $m) // es
            || preg_match("/Cancelável até as (?<time>.+?) horas do dia da chegada\. Após este prazo/i", $cancellationText, $m) // pt
        ) {
            // Cancellable until 6 p.m. on the day of arrival. Beyond that time
            $h->booked()
                ->deadlineRelative('1 day', str_replace('.', '', $m['time']));
        } elseif (preg_match("/Cancellable until (?<time>.+?), (?<prior>\d+) days before arrival. Beyond that time/i",
            $cancellationText, $m)
        ) {
            // Cancellable until 6:00 pm, 30 days before arrival. Beyond that time
            $h->booked()
                ->deadlineRelative($m['prior'] . ' days', str_replace('.', '', $m['time']));
        }

        $h->booked()
            ->parseNonRefundable("#No change and no cancellation allowed. The full deposit is not refundable#");
    }
}
