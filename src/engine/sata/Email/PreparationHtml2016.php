<?php

namespace AwardWallet\Engine\sata\Email;

class PreparationHtml2016 extends \TAccountChecker
{
    public $mailFiles = "sata/it-6228240.eml, sata/it-6234993.eml, sata/it-8819016.eml";

    public static $dict = [
        'en' => [],
        'pt' => [
            'Reservation code' => 'Reserva',
            'Ticket'           => 'Bilhete',
            'Arrival'          => 'Chegada',
            'Flight'           => 'Voo',
            'Name'             => 'Nome',
            'Departure'        => 'Partida',
        ],
    ];
    protected $lang = '';
    protected $subject = [
        'en' => [
            'booked additional services for your journey',
        ],
        'pt' => [
            'Prepare a sua viagem - Serviços Adicionais',
            'Ainda não reservou serviços adicionais para a sua viagem',
        ],
    ];
    protected $body = [
        'en' => [
            'ancilliary services that SATA offers to you',
            'For an even more pleasant and enjoyable trip, please check the additional services that we put at your disposal',
        ],
        'pt' => [
            'SATA coloca ao seu dispor',
            'Torne a sua viagem ainda mais agradável e cómoda adquirindo os serviços adicionais que colocamos ao seu dispor',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['subject']) && isset($headers['from'])
                && stripos($headers['from'], 'no-reply@sata') !== false && $this->detect($headers['subject'], $this->subject);
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return $this->detect($parser->getHTMLBody(), $this->body);
    }

    public function detectEmailFromProvider($from)
    {
        return (bool) preg_match("/[@\.]sata\./", $from);
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $its = [];

        if ($this->lang = $this->detect($parser->getHTMLBody(), $this->body)) {
            $its = $this->parseEmail();
        }

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => 'PreparationHtml2016' . ucfirst($this->lang),
        ];
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    protected function t($s)
    {
        if (isset($this->lang) && isset(self::$dict[$this->lang][$s])) {
            return self::$dict[$this->lang][$s];
        }

        return $s;
    }

    /**
     * Are case sensitive. Example:
     * <pre>
     * var $reSubject = [
     * 'en' => ['Reservation Modify'],
     * ];
     * </pre>.
     *
     * @param $haystack
     * @param $arrayNeedle
     *
     * @return int|string
     */
    protected function detect($haystack, $arrayNeedle)
    {
        foreach ($arrayNeedle as $lang => $needles) {
            if (is_array($needles)) {
                foreach ($needles as $needle) {
                    if (stripos($haystack, $needle) !== false) {
                        return $lang;
                    }
                }
            } elseif (stripos($haystack, $needles) !== false) {
                return $lang;
            }
        }
    }

    protected function parseEmail()
    {
        $result = ['Kind' => 'T'];
        $result['RecordLocator'] = orval($this->http->FindSingleNode("//text()[.='{$this->t('Reservation code')}']/ancestor::tr[1]/following-sibling::tr[1]", null, true, '/^[A-Z\d]{5,6}$/'), CONFNO_UNKNOWN);
        $result += $this->parseAdditionally();

        if ($this->http->XPath->query("//td[contains(., '{$this->t('Name')}') and not(.//td)]")->length > 0) {
            $result += $this->parseAdditionally2();
        }
        $result += $this->parseSegments();

        if ($this->http->XPath->query("//td[contains(., '{$this->t('Flight')}') and not(.//td)]/ancestor::tr[2]")->length > 0) {
            $result += $this->parseSegments2();
        }

        return [$result];
    }

    protected function parseSegments()
    {
        $result = [];
        $xpath = "//text()[.='{$this->t('Arrival')}']/ancestor::tr[1]/following-sibling::tr[count(./td)=4]";
        //		$this->logger->info($xpath);
        $roots = $this->http->XPath->query($xpath);

        if ($roots->length === 0) {
            $this->logger->info("Segments1 not found by xpath: {$xpath}");

            return [];
        }

        foreach ($roots as $root) {
            $pattern = '/([A-Z\d]{2})\s*(\d+)\s+([A-Z]{3}) > ([A-Z]{3})/';

            if (preg_match($pattern, $this->http->FindSingleNode('./td[2]', $root), $matches)) {
                $i['AirlineName'] = $matches[1];
                $i['FlightNumber'] = $matches[2];
                $i['DepCode'] = $matches[3];
                $i['ArrCode'] = $matches[4];
            }

            $i['DepDate'] = strtotime($this->translateDate('/\d+ ([[:alpha:]]+) \d+ .+/', $this->http->FindSingleNode('./td[3]', $root), $this->lang), false);
            $i['ArrDate'] = strtotime($this->translateDate('/\d+ ([[:alpha:]]+) \d+ .+/', $this->http->FindSingleNode('./td[4]', $root), $this->lang), false);

            $result['TripSegments'][] = $i;
        }

        return $result;
    }

    protected function parseSegments2()
    {
        $result = [];
        $xpath = "//td[contains(., '{$this->t('Flight')}') and not(.//td)]/ancestor::tr[2]";
        //		$this->logger->info($xpath);
        $roots = $this->http->XPath->query($xpath);

        if ($roots->length === 0) {
            $this->logger->info("Segments2 not found by xpath: {$xpath}");

            return [];
        }

        foreach ($roots as $root) {
            /** @var \AwardWallet\ItineraryArrays\AirTripSegment $seg */
            $seg = [];

            if (preg_match('/([A-Z\d]{2})\s*(\d+)\s+([A-Z]{3})\s*>\s*([A-Z]{3})/iu', $this->http->FindSingleNode('td[1]', $root), $m)) {
                $seg['AirlineName'] = $m[1];
                $seg['FlightNumber'] = $m[2];
                $seg['DepCode'] = $m[3];
                $seg['ArrCode'] = $m[4];
            }

            if (preg_match("/{$this->t('Departure')}\s+(\d+ \w+ \d+ \d+:\d+)\s*{$this->t('Arrival')}\s+(\d+ \w+ \d+ \d+:\d+)/i", $this->http->FindSingleNode('td[2]', $root), $m)) {
                $seg['DepDate'] = $this->lang !== 'en' ? strtotime($this->translateDate('/\d+ (\w+) \d+ \d+:\d+/', $m[1], $this->lang)) : strtotime($m[1]);
                $seg['ArrDate'] = $this->lang !== 'en' ? strtotime($this->translateDate('/\d+ (\w+) \d+ \d+:\d+/', $m[2], $this->lang)) : strtotime($m[2]);
            }

            $result['TripSegments'][] = $seg;
        }

        return $result;
    }

    protected function parseAdditionally()
    {
        $result = [];

        foreach ($this->http->XPath->query("//text()[.='{$this->t('Ticket')}']/ancestor::tr[1]/following-sibling::tr[count(./td)=3]") as $root) {
            if ($this->http->FindSingleNode(".//text()[.='{$this->t('Arrival')}']", $root)) {
                return $result;
            }

            $result['Passengers'][] = $this->http->FindSingleNode('./td[2]', $root, false, '/[A-Z\s\\\\]+/');
            $result['TicketNumbers'][] = $this->http->FindSingleNode('./td[3]', $root, false, '/[\d-]+/');
        }

        return $result;
    }

    protected function parseAdditionally2()
    {
        $result = [];
        $result['Passengers'] = $this->http->FindNodes("//td[contains(., '{$this->t('Name')}') and not(.//td)]", null, "/{$this->t('Name')}\s+(.+)/");
        $result['TicketNumbers'] = $this->http->FindNodes("//td[contains(., '{$this->t('Ticket')}') and not(.//td)]", null, "/{$this->t('Ticket')}\s+(.+)/");

        return $result;
    }

    protected function translateDate($pattern, $string, $lang)
    {
        if (preg_match($pattern, $string, $matches)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($matches[1], $lang)) {
                return str_replace($matches[1], $en, $matches[0]);
            } else {
                return $matches[0];
            }
        } else {
            return $string;
        }
    }
}
