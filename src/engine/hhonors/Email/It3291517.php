<?php

namespace AwardWallet\Engine\hhonors\Email;

// parsers with similar formats: It2287022

class It3291517 extends \TAccountCheckerExtended
{
    public $mailFiles = "hhonors/it-2890973.eml, hhonors/it-3291517.eml, hhonors/it-3291519.eml"; // +1 bcdtravel(html)[es]

    public $reFrom = '@res.hilton.com';
    public $reSubject = 'Confirmation #';
    public $reBody = 'Hilton';

    protected $langDetectors = [
        'es' => ['DE SUS FECHAS DE ESTADÍA:'],
        'en' => ['YOUR STAY DATES:'],
    ];

    protected $lang = '';

    protected static $dict = [
        'es' => [
            'CONFIRMATION:' => 'CONFIRMACIÓN:',
            'Tel:'          => ['Tel:', 'Tel.:'],
            //            'Fax:' => '',
            'YOUR STAY DATES:'  => 'DE SUS FECHAS DE ESTADÍA:',
            'Check In:'         => 'Horario de check-in:',
            'Check Out:'        => 'Horario de check-out:',
            'Welcome,'          => 'Bienvenido,',
            'Guests:'           => 'Huéspedes:',
            'Adult'             => 'Adulto',
            'Rooms:'            => 'Habitaciones:',
            'RATE INFORMATION:' => 'INFORMACIÓN DE LA TARIFA:',
            //            'night:' => '',
            'RATE RULES AND CANCELLATION POLICY:' => 'Reglas sobre tarifas y política de cancelación:',
            'ROOM INFORMATION:'                   => 'INFORMACIÓN DE LA HABITACIÓN:',
            'Rate:'                               => 'Tarifa:',
            'Taxes:'                              => 'Impuestos:',
            'Total for Stay:'                     => 'Total de su estadía:',
        ],
        'en' => [
            'Tel:' => ['Tel:', 'Tel.:'],
        ],
    ];

    public function __construct()
    {
        parent::__construct();

        $this->processors = [
            $this->reBody => function (&$itineraries) {
                $it = [];
                $it['Kind'] = 'R';

                // ConfirmationNumber
                $it['ConfirmationNumber'] = $this->http->FindSingleNode('//text()[' . $this->eq($this->t('CONFIRMATION:')) . ']/following::text()[normalize-space(.)][1]');

                // Hotel Name
                $it['HotelName'] = $this->http->FindSingleNode('//text()[' . $this->contains($this->t('Tel:')) . ']/preceding-sibling::text()[position()=last()]');

                $stayDates = $this->http->FindSingleNode('//text()[' . $this->eq($this->t('YOUR STAY DATES:')) . ']/following::text()[normalize-space(.)][1]');

                // CheckInDate
                $timeCheckIn = $this->getField($this->t('Check In:'));

                if ($timeCheckIn && preg_match('/^(.+?)\s*–\s*.+$/', $stayDates, $matches)) {
                    $it['CheckInDate'] = strtotime($matches[1] . ', ' . $timeCheckIn);
                }

                // CheckOutDate
                $timeCheckOut = $this->getField($this->t('Check Out:'));

                if ($timeCheckOut && preg_match('/^.+?\s*–\s*(.+)$/', $stayDates, $matches)) {
                    $it['CheckOutDate'] = strtotime($matches[1] . ', ' . $timeCheckOut);
                }

                // Address
                $it['Address'] = trim(implode(" ", $this->http->FindNodes('//text()[' . $this->contains($this->t('Tel:')) . ']/preceding-sibling::text()[position()<last()]')), ', ');

                // Phone
                $it['Phone'] = $this->http->FindSingleNode('//text()[' . $this->contains($this->t('Tel:')) . ']', null, true, '/' . $this->opt($this->t('Tel:')) . '\s*(.+)/');

                // Fax
                $fax = $this->http->FindSingleNode('//text()[' . $this->contains($this->t('Fax:')) . ']', null, true, '/' . $this->opt($this->t('Fax:')) . '\s*(.+)/');

                if ($fax) {
                    $it['Fax'] = $fax;
                }

                // GuestNames
                $guestNames = $this->http->FindNodes("//*[contains(text(),'Guest')][contains(text(),'name')]/parent::*/following-sibling::*");

                if (count($guestNames) > 0) {
                    $it['GuestNames'] = array_unique($guestNames);
                } else {
                    $guestName = $this->http->FindSingleNode('(//text()[' . $this->eq($this->t('Welcome,')) . '])[1]/following::text()[normalize-space(.)][1]', null, true, '/^([A-z][-.\'A-z ]*[.A-z])$/');

                    if ($guestName) {
                        $it['GuestNames'] = [$guestName];
                    }
                }

                // Guests
                $it['Guests'] = re('/(\d+)\s+' . $this->opt($this->t('Adult')) . '/', $this->getField($this->t('Guests:')));

                // Rooms
                $it['Rooms'] = $this->getField($this->t('Rooms:'));

                // RateType
                $it['RateType'] = $this->getField($this->t('RATE INFORMATION:'));

                // Rate
                $rate = $this->getField($this->t('night:'));

                if ($rate !== null) {
                    $it['Rate'] = $rate;
                }

                // CancellationPolicy
                $cancellationTexts = $this->http->FindNodes('//text()[' . $this->eq($this->t('RATE RULES AND CANCELLATION POLICY:')) . ']/ancestor::tr[1]/following-sibling::tr[normalize-space(.)][1]/descendant::text()[string-length(normalize-space(.))>3]');
                $cancellationText = implode(' ', $cancellationTexts);

                if (empty($cancellationText)) {
                    $cancellationText = implode(" ", $this->http->FindNodes("//text()[contains(., 'Cancellation')]"));
                }

                if (!empty($cancellationText)) {
                    $it['CancellationPolicy'] = $cancellationText;
                }

                // RoomType
                $it['RoomType'] = trim($this->getField($this->t('ROOM INFORMATION:')), ', ');

                // Cost
                $it['Cost'] = cost($this->getField($this->t('Rate:')));

                // Taxes
                $it['Taxes'] = cost($this->getField($this->t('Taxes:')));

                // Total
                $it['Total'] = cost($this->getField($this->t('Total for Stay:')));

                // Currency
                $it['Currency'] = currency($this->getField($this->t('Total for Stay:')));

                // SpentAwards
                $spentAwards = $this->http->FindSingleNode('(//text()[' . $this->contains($this->t('Total Number of Points per Stay:')) . ']/following::text()[normalize-space(.)][1])[1]');

                if ($spentAwards) {
                    $it['SpentAwards'] = $spentAwards;
                }

                $itineraries[] = $it;
            },
        ];
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->reFrom) !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (strpos($body, $this->reBody) === false) {
            return false;
        }

        return $this->assignLang();
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (!isset($headers['from'],$headers['subject'])) {
            return false;
        }

        return stripos($headers['from'], $this->reFrom) !== false
            && strpos($headers['subject'], $this->reSubject) !== false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (empty($body)) {
            $body = $parser->getPlainBody();
            $this->http->SetEmailBody($body);
        }

        if ($this->assignLang() === false) {
            return false;
        }

        $itineraries = [];

        foreach ($this->processors as $re => $processor) {
            if (stripos($body, $re)) {
                $processor($itineraries);

                break;
            }
        }
        $result = [
            'emailType'  => 'Reservations' . ucfirst($this->lang),
            'parsedData' => [
                'Itineraries' => $itineraries,
            ],
        ];

        return $result;
    }

    protected function t($phrase)
    {
        if (!isset(self::$dict[$this->lang]) || !isset(self::$dict[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dict[$this->lang][$phrase];
    }

    protected function assignLang(): bool
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

    protected function eq($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
    }

    protected function starts($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'starts-with(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    protected function contains($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'contains(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    protected function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) { return preg_quote($s, '/'); }, $field)) . ')';
    }

    private function getField($s)
    {
        $rule = $this->contains($s);

        return $this->http->FindSingleNode("//text()[{$rule}]/following::text()[normalize-space(.)][1]");
    }
}
