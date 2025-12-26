<?php

/**
 * Created by PhpStorm.
 * User: roman.
 */

namespace AwardWallet\Engine\sata\Email;

use AwardWallet\Engine\MonthTranslate;

class AirTicket extends \TAccountChecker
{
    public $mailFiles = "sata/it-10161315.eml, sata/it-6284941.eml, sata/it-6284993.eml, sata/it-8134679.eml";

    public static $dict = [
        'en' => [],
        'pt' => [
            'Name'        => 'Nome',
            'Ticket'      => 'Bilhete',
            'Aircraft'    => 'Avião',
            'Reservation' => 'Reserva',
            'Flight'      => 'Voo',
            'Date'        => 'Data',
            'From'        => 'Origem',
            'To'          => 'Destino',
            'Departure'   => 'Partida',
            'Arrival'     => 'Chegada',
            'Flight Time' => 'Duração',
        ],
    ];

    protected $lang = '';

    protected $subject = [
        'Web check-in já disponível para o voo',
        'Web check-in for flight',
        'Informações úteis para o voo',
    ];

    protected $body = [
        'en' => ['From now on we invite you to do your web check-in', 'We remind you that there are airport arrival'],
        'pt' => ['A partir de agora, já pode efetuar o seu web check-in', 'Gostaríamos de o informar que já pode efetuar o seu Web/Mobile check-in', 'Relembramos que deverá efectuar'],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['subject']) && isset($headers['from'])
                && stripos($headers['from'], '@sata') !== false && $this->detect($headers['subject'], $this->subject);
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return stripos($parser->getHTMLBody(), 'sata') !== false && $this->detect($parser->getHTMLBody(), $this->body);
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'sata') !== false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $its = [];

        if ($this->lang = $this->detect($parser->getHTMLBody(), $this->body)) {
            $its = $this->parseEmail();
        }

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => 'AirTicket' . ucfirst($this->lang),
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
     * @param string $haystack
     * @param array $arrayNeedle
     *
     * @return string
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

    private function parseEmail()
    {
        /** @var \AwardWallet\ItineraryArrays\AirTrip $it */
        $it = ['Kind' => 'T'];

        $it['Passengers'] = $this->http->FindNodes("//text()[normalize-space(.)='" . $this->t("Name") . "']/following::text()[normalize-space(.)][1]");
        $it['TicketNumbers'] = $this->http->FindNodes("//text()[normalize-space(.)='" . $this->t("Ticket") . "']/following::text()[normalize-space(.)][1]");

        $xpath = "//td[contains(., '{$this->t('Aircraft')}') and not(descendant::td)]/ancestor::table[contains(., '{$this->t('Flight')}') and contains(., '{$this->t('Date')}')][1]";
        $roots = $this->http->XPath->query($xpath);

        if ($roots->length === 0) {
            $this->logger->info('Segments not found by: ' . $xpath);

            return [];
        }
        $recLoc = [];

        foreach ($roots as $root) {
            /** @var \AwardWallet\ItineraryArrays\AirTripSegment $seg */
            $seg = [];

            $recLoc[] = $this->getNode($this->t('Reservation'), $root);

            $date = $this->getNode($this->t('Date'), $root);

            if ($this->lang !== 'en') {
                $date = $this->normalizeData($date);
            }

            $flight = $this->getNode($this->t('Flight'), $root, true);

            if (preg_match('/([A-Z]\d|\d[A-Z]|[A-Z]{2})\s*(\d+)/', $flight, $m)) {
                $seg['AirlineName'] = $m[1];
                $seg['FlightNumber'] = $m[2];
            }

            $seg['DepCode'] = $this->getNode($this->t('From'), $root);

            $seg['ArrCode'] = $this->getNode($this->t('To'), $root);

            $seg['Aircraft'] = $this->getNode($this->t('Aircraft'), $root);

            if (!empty($date)) {
                $seg['DepDate'] = strtotime($date . ', ' . $this->getNode($this->t('Departure'), $root), false);
                $seg['ArrDate'] = strtotime($date . ', ' . $this->getNode($this->t('Arrival'), $root), false);
            }

            $seg['Duration'] = $this->getNode($this->t('Flight Time'), $root);

            $it['TripSegments'][] = $seg;
        }

        $it['RecordLocator'] = array_unique($recLoc)[0];

        return [$it];
    }

    private function normalizeData($str)
    {
        $regExps = [
            '/(\d{1,2})\s+(\w+)\s+(\d{2})/',
        ];

        foreach ($regExps as $regExp) {
            if (preg_match($regExp, $str, $m) && ($en = MonthTranslate::translate($m[2], $this->lang))) {
                return $m[1] . ' ' . $en . ' 20' . $m[3];
            }
        }

        return $str;
    }

    private function getXpath($str, $node = '.')
    {
        $res = '';

        if (is_array($str)) {
            $contains = array_map(function ($str) use ($node) {
                return "contains(" . $node . ", '" . $str . "')";
            }, $str);
            $res = implode(' or ', $contains);
        } elseif (is_string($str)) {
            $res = "contains(" . $node . ", '" . $str . "')";
        }

        return $res;
    }

    private function getNode($str, \DOMNode $root, $first = false)
    {
        if ($first === false) {
            return $this->http->FindSingleNode("descendant::td[contains(., '" . $str . "')]/following-sibling::td[not(descendant::td) and contains(@style, 'color')][1]", $root);
        } else {
            return $this->http->FindSingleNode("(descendant::td[contains(., '" . $str . "')]/following-sibling::td[not(descendant::td) and contains(@style, 'color')][1])[1]", $root);
        }
    }
}
