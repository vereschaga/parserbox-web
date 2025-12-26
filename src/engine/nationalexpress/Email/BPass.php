<?php

namespace AwardWallet\Engine\nationalexpress\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class BPass extends \TAccountChecker
{
    public $mailFiles = "nationalexpress/it-16598798.eml, nationalexpress/it-18720188.eml, nationalexpress/it-18831439.eml, nationalexpress/it-7318847.eml, nationalexpress/it-8561344.eml, nationalexpress/it-9075659.eml, nationalexpress/it-9210966.eml, nationalexpress/it-9382981.eml";

    public $detectLang = [
        'en' => ['Departure', 'Arrive'],
        'es' => ['Salida', 'Llegada'],
        'de' => ['Abreise', 'Ankunft'],
        'fr' => ['Départ', 'Arrivée'],
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
            'Your ticket number' => ['Your ticket number', 'Ticket number'],
            'Customer name'      => ['Customer name', 'Lead passenger'],
            'journeyRef'         => ['Journey Ref Outbound', 'Journey Ref Return'],
        ],
        'es' => [
            'Date of travel'     => 'Fecha de viaje',
            'Departure'          => 'Salida',
            'Arrive'             => 'Llegada',
            'Service'            => 'Servicio',
            'Your ticket number' => ['Su número de billete', 'Número de billete'],
            'Customer name'      => ['Nombre del cliente', 'Pasajero principal'],
            'journeyRef'         => ['Ref Viaje Ida', 'Ref Viaje Vuelta'],
        ],
        'de' => [
            'Date of travel'     => 'Reisedatum',
            'Departure'          => 'Abreise',
            'Arrive'             => 'Ankunft',
            'Service'            => 'Angebot',
            'Your ticket number' => 'Ticketnummer',
            'Customer name'      => 'Hauptreisender',
            'Total'              => 'Gesamtsumme',
            'journeyRef'         => ['Nummer der Hinreise', 'Nummer der Rückkehr'], //Rückkehr?? no example, guess
        ],
        'fr' => [
            'Date of travel'     => 'Date de voyage',
            'Departure'          => 'Départ',
            'Arrive'             => 'Arrivée',
            'Service'            => ['National Express Bus', 'Eurolines Bus'],
            'Your ticket number' => 'Numéro de billet',
            'Customer name'      => 'Nom du voyageur',
            'journeyRef'         => ['Référence d\'aller', 'Référence de retour'], //de retour?? no example, guess
        ],
    ];

    private $detects = [
        'Thank you for choosing National Express, we hope you enjoy your journey',
        'Thanks, your booking is confirmed',
        'Gracias por elegir National Express', //es
        'Vielen Dank, dass Sie sich für eine Reise mit National Express', //de
        'choisi de voyager avec National Express', //fr
    ];
    private $date;

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getDate());

        if (!$this->assignLang()) {
            $this->logger->debug('can\'t determine a language');

            return $email;
        }

        $a = explode('\\', __CLASS__);
        $class = end($a);
        $email->setType($class . ucfirst($this->lang));

        if ($this->parseEmail($email)) {
            return $email;
        }

        return null;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return (isset($headers['from']) && stripos($headers['from'], '@nationalexpress.com') !== false)
            || (isset($headers['subject']) && stripos($headers['subject'],
                    'National Express confirmation email') !== false);
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@nationalexpress.com') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (stripos($body, 'National Express') === false) {
            return false;
        }

        foreach ($this->detects as $detect) {
            if (stripos($body, $detect) !== false) {
                return $this->assignLang();
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

    private function parseEmail(Email $email)
    {
        $b = $email->add()->bus();
        $url = $this->http->FindSingleNode("//img[contains(@src, 'coach.nationalexpress.com/nxbooking/qrcode') or contains(@src, 'book.nationalexpress.com/nxrest/qrcode')]/@src");

        $pax = $this->getPassengers();
        $b->general()
            ->traveller($pax);

        if (is_array($this->t('journeyRef'))) {
            foreach ($this->t('journeyRef') as $value) {
                $confNo = $this->http->FindSingleNode("//text()[{$this->starts($value)}]/following::text()[normalize-space(.)!=''][1]");

                if (!empty($confNo)) {
                    $b->general()
                        ->confirmation($confNo, $value);
                }
            }
        }

        if (!empty($total = $this->getTotal())) {
            $b->price()
                ->total($total['TotalCharge'])
                ->currency($total['Currency']);
        }

        $b->setTicketNumbers([$this->getTicketNumbers()], false);

        $xpath = "//tr[( ({$this->contains($this->t('Date of travel'))}) and ({$this->contains($this->t('Departure'))}) and ({$this->contains($this->t('Arrive'))}) ) and not(descendant::tr)]/ancestor::table[1]";
        $roots = $this->http->XPath->query($xpath);

        if ($roots->length === 0) {
            $this->logger->info('Segments not found by xpath: ' . $xpath);

            return false;
        }

        foreach ($roots as $root) {
            $s = $b->addSegment();

            $date = $this->getNode($root);
            $depTime = $this->getNode($root, 2);
            $arrTime = $this->getNode($root, 3);
            $depName = $this->getNode($root, 4, null, 'descendant::*[name() = "strong" or name() = "b"][1]');
            $arrName = $this->getNode($root, 5, null, 'descendant::*[name() = "strong" or name() = "b"][1]');
            $s->departure()
                ->date($this->normalizeDate($date . ', ' . $depTime))
                ->name($depName);
            $s->arrival()
                ->date($this->normalizeDate($date . ', ' . $arrTime))
                ->name($arrName);

            $flight = $this->http->FindSingleNode("(ancestor::tr/preceding-sibling::tr[{$this->contains($this->t('Service'))}]/descendant::node()[{$this->contains($this->t('Service'))}]/following-sibling::*[name() = 'strong' or name() = 'b'][1])[1]",
                $root);

            if (empty($flight)) {
                $flight = $this->http->FindSingleNode("(ancestor::tr/preceding-sibling::tr[{$this->contains($this->t('Service'))}]/descendant::node()[{$this->contains($this->t('Service'))}]/descendant::*[name() = 'strong' or name() = 'b'][1])[1]",
                    $root);
            }
            $s->setNumber($flight);

            if ($seats = $this->http->FindSingleNode("ancestor::tr[1]/preceding-sibling::tr[1]/descendant::td[({$this->contains($this->t('Seats'))}) and not(.//td)]/b",
                $root)
            ) {
                $s->extra()->seats(array_map("trim", explode(',', $seats)));
            }

            //BoardingPass only for flights
//            if (!empty($url)){
//                $bp = $email->add()->bpass();
//                $bp->setTraveller($pax)
//                    ->setFlightNumber($s->getNumber())
//                    ->setDepDate($s->getDepDate())
//                    ->setUrl($url);
//            }
        }

        return true;
    }

    private function getNode2($str, $re = null)
    {
        return $this->http->FindSingleNode("//td[({$this->contains($str)}) and not(descendant::td)]/following-sibling::td[1]",
            null, true, $re);
    }

    private function normalizeDate($str)
    {
        $year = date("Y", $this->date);
        $strOrig = $str;
        $in = [
            "/^\w+\s+(\d{1,2}\s+\w+\s+\d{4})\,\s+\d+:\d+\s*\((\d{1,2}:\d{2}\s*[ap]m)\)$/iu",
            // Sat 08 Jul 2017, 09:15 (9:15 AM)
            "/^\w+\s+(\w+)\s*(\d{1,2})\s*\,?\s+\d+:\d+\s*\((\d{1,2}:\d{2}\s*[ap]m)\)$/iu",
            // Sat 08 Jul, 09:15 (9:15 AM)
        ];
        $out = [
            '$1 $2',
            '$2 $1 ' . $year . ' $3',
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#^\w+,?\s+\w+\s+\w+\,?\s+\d+:\d+\s*\(\d{1,2}:\d{2}\s*[ap]m\)$#iu",
                $strOrig) && !(preg_match("#^\w+\s+\w+\s+\d{4}\,?\s+\d+:\d+\s*\(\d{1,2}:\d{2}\s*[ap]m\)$#iu", $strOrig))
        ) {
            $inWeek = [
                "#^(\w+),?\s+\w+\.?\s+\w+\,?\s+\d+:\d+\s*\(\d{1,2}:\d{2}\s*[ap]m\)$#iu",
            ];
            $outWeek = [
                '$1',
            ];
            $weeknum = WeekTranslate::number1(WeekTranslate::translate(preg_replace($inWeek, $outWeek, $strOrig),
                'en'));

            $str = date("Y-m-d H:i", EmailDateHelper::parseDateUsingWeekDay($str, $weeknum));
        }

        return strtotime($str);
    }

    private function getNode(\DOMNode $root, $td = 1, $re = null, $child = null)
    {
        if (null === $child) {
            return $this->http->FindSingleNode('descendant::tr[2]/descendant::td[' . $td . ']', $root, true, $re);
        } else {
            return $this->http->FindSingleNode('descendant::tr[2]/descendant::td[' . $td . ']/' . $child, $root, true,
                $re);
        }
    }

    private function getTotal(): array
    {
        $res = [];
        $total = $this->getNode2($this->t('Total'));

        if (preg_match('/(\D+)\s*([\d\.]+)/', $total, $m)) {
            $res['Currency'] = str_replace(['£'], ['GBP'], $m[1]);
            $res['TotalCharge'] = $m[2];
        }

        return $res;
    }

    private function getTicketNumbers()
    {
        return $this->getNode2($this->t('Your ticket number'), '/([A-Z\d]{5,9})\s*/');
    }

    private function getPassengers()
    {
        return trim($this->getNode2($this->t('Customer name'), '/^([\D\s]+)/u'));
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang()
    {
        if (isset($this->detectLang)) {
            foreach ($this->detectLang as $lang => $reBody) {
                if ($this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody[0]}')]")->length > 0
                    && $this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody[1]}')]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field));
    }
}
