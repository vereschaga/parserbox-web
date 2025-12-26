<?php

namespace AwardWallet\Engine\wagonlit\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class BookingWithTraveldoo extends \TAccountChecker
{
    public $mailFiles = "wagonlit/it-12390688.eml, wagonlit/it-12582063.eml, wagonlit/it-12582065.eml, wagonlit/it-12582087.eml, wagonlit/it-29104393.eml, wagonlit/it-29104400.eml, wagonlit/it-29104414.eml, wagonlit/it-37664591.eml, wagonlit/it-37664611.eml, wagonlit/it-6335431.eml, wagonlit/it-6335434.eml, wagonlit/it-6128683.eml";

    public $reBody = [
        'en'  => ['Thank you for booking with', 'Request ID'],
        'en2' => ['has made a booking for you with', 'Request ID'],
        'pt'  => ['acabou de efectuar uma reserva para si em', 'Número do pedido'],
        'pt2' => ['Muito obrigado por ter reservado a sua viagem em', 'Número do pedido'],
        'fr'  => ["Nous vous remercions de vérifier le détail de votre réservation ci-dessous", 'de demande'],
    ];
    public $lang = '';

    public static $dict = [
        'en' => [
            //words previos segments
            'Flight' => ['Flight', 'flight', 'Luggage count', 'Connection time'],
            'Hotel'  => ['Hotel', 'hotel'],
            'Train'  => ['Train', 'train', 'station'],

            'excludeWords' => ['tickets delivery mode', 'Reservation number', 'Negotiated rate'],
            //something like trying to make regex in xpath
            //            'ABC' => "ABCDEFGHIJKLMNOPQRSTUVWXYZ",
            //            'abc' => "abcdefghijklmnopqrstuvwxyz"
        ],
        'pt' => [
            'Request ID' => 'Número do pedido',
            //            'Booked' => '',
            'Reservation number' => 'Número de reserva',
            '(Agency)'           => '(Agência)',
            //words previos segments
            'Flight' => ['Voo', 'Preço com restrições', 'Número de dossier Traveldoo'],
            //            'Hotel' => ['Hotel', 'hotel'],
            //            'Train' => ['Train', 'train', 'station'],
            'excludeWords'        => ['Modo de entrega dos bilhetes de avião', 'Número de reserva'],
            'people'              => 'pessoas',
            'cancellation policy' => 'Política de anulação do hotel',
            'Room rate per night' => 'Preço do quarto por noite',
            //            'operated by' => '',
        ],
        'fr' => [
            'Request ID'         => 'N° de demande',
            'Booked'             => 'Réservé',
            'Reservation number' => 'Numéro de réservation',
            '(Agency)'           => '(Agence)',
            //words previos segments
            'Flight' => ['Vol', 'Nb de bagages', 'Tarif avec restrictions', 'Temps de correspondance'],
            'Hotel'  => ['Hôtel'],
            'Train'  => ['Train', 'train'],
            'Rental' => ['Voiture'],

            'excludeWords'        => ['Mode de livraison des billets d'],
            'excludeRow'          => ['Temps de correspondance'],
            'Tel'                 => ['Tel', 'Tél'],
            'people'              => 'personne(s)',
            'cancellation policy' => 'Politique d\'annulation de l\'hotel',
            'Room rate per night' => 'Prix de la chambre par nuit',
            'operated by'         => 'exploité par',
        ],
    ];

    //all types of reservation and excludeRows (like 'Temps de correspondance')
    private $types = ['Flight', 'Hotel', 'Train', 'Rental'];

    private $patterns = [
        'time' => '\d{1,2}(?:[:：]\d{2})?(?:\s*[AaPp]\.?[Mm]\.?)?',
    ];

    private $pax;
    private $date;
    private $reservDate = null;
    private $recLocs;
    private $code = null;
    private static $bodies = [
        'airfrance' => [
            '@airfrance.fr',
            'booking for you with Air France',
        ],
        'fcmtravel' => [
            'FCM Support Team',
        ],
        'amextravel' => [
            'TRAVELSTORE AMERICAN EXPRESS',
            'travelstore.pt',
        ],
        'bcd' => [
            '@bcd.',
            '@bcdtravel.',
        ],
        'hoggrob' => [ // it-6128683.eml
            '@nl.hrgworldwide.com',
            'please call Customer Support HRG NL',
        ],
        // below provider always last!
        'wagonlit' => [
            'contactcwt.com',
            '//a[contains(@href,".traveldoo.com/")]',
        ],
    ];
    private $headers = [
        'airfrance' => [
            'from' => ['@airfrance.fr'],
            'subj' => [
                'Booking Confirmation for',
                'Confirmation de la réservation pour',
            ],
        ],
        'fcmtravel' => [
            'from' => ['@fr.fcm.travel'],
            'subj' => [
                'Booking confirmation for',
            ],
        ],
        'amextravel' => [
            'from' => ['@travelstore.pt'],
            'subj' => [
                'Confirmação da reserva para',
            ],
        ],
        'bcd' => [
            'from' => ['@bcd.fr', '@bcdtravel.fr'],
            'subj' => [
                'Confirmation de la réservation pour',
            ],
        ],
        'hoggrob' => [
            'from' => ['@nl.hrgworldwide.com'],
            'subj' => [
                'Booking Confirmation for',
            ],
        ],
        // below provider always last!
        'wagonlit' => [
            'from' => ['@contactcwt.com'],
            'subj' => [
                'Booking confirmation for',
            ],
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        foreach ($this->headers as $arr) {
            foreach ($arr['from'] as $f) {
                if (stripos($from, $f) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers['from']) || empty($headers['subject'])) {
            return false;
        }

        foreach ($this->headers as $code => $arr) {
            $byFrom = $bySubj = false;

            foreach ($arr['from'] as $f) {
                if (stripos($headers['from'], $f) !== false) {
                    $byFrom = true;
                }
            }

            foreach ($arr['subj'] as $subj) {
                if (stripos($headers['subject'], $subj) !== false) {
                    $bySubj = true;
                }
            }

            if ($byFrom && $bySubj) {
                $this->code = $code;

                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getDate());
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }
        $this->parseEmail($email);
        $email->setType('BookingWithTraveldoo' . ucfirst($this->lang));
        $tot = $this->getTotalCurrency($this->http->FindSingleNode("(//text()[{$this->eq($this->t('Total'))}])[1]/following::text()[normalize-space(.)!=''][1]"));

        if ($tot['Total'] !== null) {
            $email->price()
                ->total($tot['Total'])
                ->currency($tot['Currency']);
        }

        $providerCode = $this->getProvider($parser);

        if ($providerCode && $providerCode !== 'wagonlit') {
            $email->setProviderCode($providerCode);
        }

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $detectProvider = false;

        foreach (self::$bodies as $criteria) {
            foreach ($criteria as $search) {
                if (strpos($search, '//') === 0 && $this->http->XPath->query($search)->length > 0
                    || strpos($parser->getHTMLBody(), $search) !== false
                ) {
                    $detectProvider = true;
                }
            }
        }

        return $detectProvider && $this->assignLang();
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        $types = 4; // flights, trains, hotels, rentals
        $cnt = $types * count(self::$dict);

        return $cnt;
    }

    public static function getEmailProviders()
    {
        return array_keys(self::$bodies);
    }

    private function getProvider(\PlancakeEmailParser $parser): ?string
    {
        $this->detectEmailByHeaders(['from' => $parser->getCleanFrom(), 'subject' => $parser->getSubject()]);

        if ($this->code) {
            return $this->code;
        }

        foreach (self::$bodies as $code => $criteria) {
            foreach ($criteria as $search) {
                if (strpos($search, '//') === 0 && $this->http->XPath->query($search)->length > 0
                    || strpos($parser->getHTMLBody(), $search) !== false
                ) {
                    return $code;
                }
            }
        }

        return null;
    }

    private function parseFlights(Email $email, string $xpath): void
    {
        $f = $email->add()->flight();

        $confirmationNumbers = [];

        $f->general()->travellers($this->pax);

        if ($this->reservDate !== null) {
            $f->general()->date($this->reservDate);
        }

//        $this->logger->debug($xpath);
        $roots = $this->http->XPath->query($xpath);

        foreach ($roots as $root) {
            $s = $f->addSegment();

            $node = implode("\n", $this->http->FindNodes("./descendant::tr[1]//text()[normalize-space(.)!='']", $root));

            if (preg_match("/(\w+\.?\s+\d+\s+\w+)\.?\s+-\s+(\d+:\d+)\s+-\s+(.+)\s+(\w+\.?\s+\d+\s+\w+)\.?\s+-\s+(\d+:\d+)\s+-\s+(.+)/u", $node, $m)) {
                $date = $this->normalizeDate($m[1]);
                $s->departure()->date(strtotime($m[2], $date));

                if (preg_match("#(.+?)\((.*?{$this->opt($this->t('terminal'))}.*?)\),?\s*(.*)#i", $m[3], $v)) {
                    $s->departure()
                        ->name($v[1] . $v[3])
                        ->terminal(trim(preg_replace("#{$this->opt($this->t('terminal'))}#i", ' ', $v[2])));
                } else {
                    $s->departure()->name($m[3]);
                }
                $s->departure()->noCode();
                $date = $this->normalizeDate($m[4]);
                $s->arrival()->date(strtotime($m[5], $date));

                if (preg_match("#(.+?)\((.*?{$this->opt($this->t('terminal'))}.*?)\),?\s*(.*)#i", $m[6], $v)) {
                    $s->arrival()
                        ->name($v[1] . $v[3])
                        ->terminal(trim(preg_replace("#{$this->opt($this->t('terminal'))}#i", ' ', $v[2])));
                } else {
                    $s->arrival()->name($m[6]);
                }
                $s->arrival()->noCode();
            }
            $node = $this->http->FindSingleNode("./descendant::tr[2]", $root);

            if (preg_match("/(.+)\s+-\s+([A-Z][A-Z\d]|[A-Z\d][A-Z])\s+-\s+(\d+)/", $node, $m)) {
                if (!empty($this->recLocs[$m[1]])) {
                    $recLocs = $this->recLocs[$m[1]];

                    foreach ((array) $recLocs as $rl) {
                        if (preg_match('/^[A-Z\d]{5,7}$/', $rl) && array_search($rl, $confirmationNumbers) === false) {
                            $confirmationNumbers[] = $rl;
                        }
                    }
                }
                $s->airline()
                    ->name($m[2])
                    ->number($m[3]);
            }

            if (preg_match("/{$this->opt($this->t('operated by'))}\s+(.+)/", $node, $m)) {
                $s->airline()->operator($m[1]);
            }
            $node = $this->http->FindSingleNode("descendant::tr[position()=3 or position()=4][not(.//img)]/descendant::text()[normalize-space()][1]", $root);

            if (preg_match("/^(.{3,}?)\s*(?:\(([A-Z]{1,2})\)|$)/", $node, $m)) {
                // Economy(B)
                $s->extra()->cabin($m[1]);

                if (!empty($m[2])) {
                    $s->extra()->bookingCode($m[2]);
                }
            }
        }

        $node = $this->http->FindSingleNode("./following-sibling::table[1][normalize-space(.)!=''][1]", $root);

        if ($node === null) {
            $node = $this->http->FindSingleNode("./following::table[1][.//img[contains(@src,'/conforme.png')]][normalize-space(.)!=''][1]",
                $root);
        }
        $tot = $this->getTotalCurrency($node);

        if ($tot['Total'] !== null) {
            $f->price()
                ->total($tot['Total'])
                ->currency($tot['Currency']);
        }

        foreach ($confirmationNumbers as $confNumber) {
            $f->general()->confirmation($confNumber);
        }
    }

    private function parseTrains(Email $email, string $xpath): void
    {
        $t = $email->add()->train();

        $confirmationNumbers = [];

        $t->general()->travellers($this->pax);

        if ($this->reservDate !== null) {
            $t->general()->date($this->reservDate);
        }

        $roots = $this->http->XPath->query($xpath);
//        $this->logger->debug($xpath);
        foreach ($roots as $root) {
            $s = $t->addSegment();

            if (count($this->recLocs) === 1) {
                $recLocs = array_values($this->recLocs)[0];

                foreach ((array) $recLocs as $rl) {
                    if (array_search($rl, $confirmationNumbers) === false) {
                        $confirmationNumbers[] = $rl;
                    }
                }
            }

            $node = implode("\n", $this->http->FindNodes("./descendant::tr[1]//text()[normalize-space(.)!='']", $root));

            if (preg_match("/(\w+\.?\s+\d+\s+\w+)\.?\s+-\s+(\d+:\d+)\s+-\s+(.+)\s+(\w+\.?\s+\d+\s+\w+)\.?\s+-\s+(\d+:\d+)\s+-\s+(.+)/u", $node, $m)) {
                $date = $this->normalizeDate($m[1]);
                $s->departure()
                    ->date(strtotime($m[2], $date))
                    ->name($m[3]);
                $date = $this->normalizeDate($m[4]);
                $s->arrival()
                    ->date(strtotime($m[5], $date))
                    ->name($m[6]);
            }

            if (preg_match("/^(.+?)\s+(\d+)$/", $this->http->FindSingleNode('descendant::tr[2]', $root), $m)) {
                // Thalys - Thalys 9309
                $s->extra()
                    ->service($m[1])
                    ->number($m[2]);
            }
            $seats = array_values(array_filter(array_map(function ($s) {
                $seat = $this->re("#({$this->opt($this->t('Seat'))}[\s:]+\d+)#", $s);
                $coach = $this->re("#({$this->opt($this->t('Coach'))}[\s:]+\d+)#", $s);

                return trim($coach . ' ' . $seat);
            }, $this->http->FindNodes("./descendant::tr[contains(.,'Seat') and contains(.,'Coach')]", $root))));
            $s->extra()->seats($seats);
            $s->extra()->cabin($this->http->FindSingleNode('descendant::tr[normalize-space()][last()]/descendant::text()[normalize-space()][1]', $root));
        }

        $node = $this->http->FindSingleNode("./following-sibling::table[1][normalize-space(.)!=''][1]", $root);

        if ($node === null) {
            $node = $this->http->FindSingleNode("./following::table[1][.//img[contains(@src,'/conforme.png')]][normalize-space(.)!=''][1]",
                $root);
        }
        $tot = $this->getTotalCurrency($node);

        if ($tot['Total'] !== null) {
            $t->price()
                ->total($tot['Total'])
                ->currency($tot['Currency']);
        }

        if (count($this->recLocs) !== 1) {
            $t->general()->noConfirmation();

            return;
        }

        foreach ($confirmationNumbers as $confNumber) {
            $t->general()->confirmation($confNumber);
        }
    }

    private function parseHotels(Email $email, string $xpath): void
    {
        $roots = $this->http->XPath->query($xpath);
//        $this->logger->debug($xpath);
        foreach ($roots as $root) {
            $h = $email->add()->hotel();
            $h->general()->travellers($this->pax);

            if ($this->reservDate !== null) {
                $h->general()->date($this->reservDate);
            }
            $node = implode("\n\n\n",
                $this->http->FindNodes("./descendant::tr[1]/descendant::text()[normalize-space(.)!='']", $root));

            if (preg_match("#^\s*(.+?)[ ]*\n\n\n(.+?)[ ]*\s+\-\s+(.+?)[ ]*\n\n\n\-\n\n\n(.+?)[ ]*\n\n\n#s", $node, $m)) {
                $h->booked()
                    ->checkIn($this->normalizeDate($m[1]))
                    ->checkOut($this->normalizeDate($m[2]));
                $h->hotel()
                    ->name(preg_replace('/\s+/', ' ', $m[3]))
                    ->address($m[4]);
            }
            $h->hotel()
                ->phone($this->re("#\b{$this->opt($this->t('Tel'))}\b[\s:\.]+([+(\d][-. \d)(]{5,}[\d)])#i", $node))
                ->fax($this->re("#\b{$this->opt($this->t('Fax'))}\b[\s:\.]+([+(\d][-. \d)(]{5,}[\d)])#i", $node));
            $h->booked()->guests($this->re("#\n\n\n(\d+)\s+{$this->opt($this->t('people'))}\n\n\n#i", $node));
            $room = $h->addRoom();
            $room->setDescription($this->http->FindSingleNode("descendant::tr[2]/descendant::text()[not({$this->contains($this->t('cancellation policy'))})][normalize-space()][1]", $root));
            $cancellation = $this->http->FindSingleNode("descendant::text()[{$this->contains($this->t('cancellation policy'))}]", $root);
            $h->general()->cancellation($cancellation);

            if ($cancellation && !empty($h->getCheckInDate())) {
                $patterns['date'] = '\d{1,2}\/\d{1,2}\/\d{4}';

                if (preg_match("/Cancell?ation (?i)without penalties is possible before (?<date>{$patterns['date']}) (?<time>{$this->patterns['time']})/", $cancellation, $m) // en
                    || preg_match("/Annulation (?i)sans pénalités est possible avant le (?<date>{$patterns['date']}) (?<time>{$this->patterns['time']})/u", $cancellation, $m) // fr
                ) {
                    if (preg_match('/^(\d{1,2})\s*\/\s*(\d{1,2})\s*\/\s*(\d{4})$/', $m['date'], $matches) && $matches[1] > 12) {
                        // 20/05/2019
                        $m['date'] = $matches[2] . '/' . $matches[1] . '/' . $matches[3];
                    }
                    $h->booked()->deadline2($m['date'] . ' ' . $m['time']);
                } elseif (preg_match("/\b(?<prior>\d{1,3})-D PRIOR TO (?<hour>\d{1,2}(?:[-:]+\d{1,2})?)H LOCAL TIME/i", $cancellation, $m) // en
                ) {
                    $m['hour'] = str_replace('-', ':', $m['hour']);
                    $h->booked()->deadlineRelative($m['prior'] . ' days', $m['hour']);
                }
            }

            $room->setRate(trim(preg_replace('/\s+/', ' ', $this->re("#({$this->opt($this->t('Room rate per night'))}\s+.+)#i", $node))));
            $node = $this->http->FindSingleNode("./following-sibling::table[1][normalize-space(.)!=''][1]", $root);

            if ($node === null) {
                $node = $this->http->FindSingleNode("./following::table[1][.//img[contains(@src,'/conforme.png')]][normalize-space(.)!=''][1]",
                    $root);
            }
            $tot = $this->getTotalCurrency($node);

            if ($tot['Total'] !== null) {
                $h->price()
                    ->total($tot['Total'])
                    ->currency($tot['Currency']);
            }

            if (!empty($h->getHotelName()) && !empty($this->recLocs[$h->getHotelName()])) {
                $recLocs = $this->recLocs[$h->getHotelName()];

                foreach ((array) $recLocs as $rl) {
                    $h->general()->confirmation($rl);
                }
            }
        }
    }

    private function parseRentals(Email $email, string $xpath): void
    {
        $roots = $this->http->XPath->query($xpath);
//        $this->logger->debug($xpath);
        foreach ($roots as $root) {
            $car = $email->add()->rental();
            $car->general()->travellers($this->pax);

            if ($this->reservDate !== null) {
                $car->general()->date($this->reservDate);
            }
            $node = implode("\n\n\n",
                $this->http->FindNodes("./descendant::tr[1]/descendant::text()[normalize-space(.)!='']", $root));

            if (preg_match("/^\s*(.+?)[ ]*\n\n\n[ ]*(.+?)\s+-\n\n\n[ ]*(.+?)\s+-\s+\d/s", $node, $m)) {
                $car->pickup()->date($this->normalizeDate($m[1]));
                $car->dropoff()->date($this->normalizeDate($m[2]));
                $company = preg_replace('/\s+/', ' ', $m[3]);

                if (($code = $this->normalizeProvider($company))) {
                    $car->program()->code($code);
                } else {
                    $car->extra()->company($company);
                }
            }
            $car->pickup()->location($this->http->FindSingleNode('descendant::tr[1]/following-sibling::tr[1]', $root));
            $car->dropoff()->location($this->http->FindSingleNode('descendant::tr[1]/following-sibling::tr[2]', $root));
            $node = $this->http->FindSingleNode("./descendant::tr[4]/descendant::text()[normalize-space()!=''][1]", $root);

            if (preg_match("#(.+?)(?:,\s+(.+))?$#", $node, $m)) {
                $car->car()->model($m[1]);

                if (!empty($m[2])) {
                    $car->car()->type($m[2]);
                }
            }
            $node = $this->http->FindSingleNode("./following-sibling::table[1][normalize-space(.)!=''][1]", $root);

            if ($node === null) {
                $node = $this->http->FindSingleNode("./following::table[1][.//img[contains(@src,'/conforme.png')]][normalize-space(.)!=''][1]",
                    $root);
            }

            $tot = $this->getTotalCurrency($node);

            if ($tot['Total'] !== null) {
                $car->price()
                    ->total($tot['Total'])
                    ->currency($tot['Currency']);
            }

            if (isset($company) && !empty($this->recLocs[$company])) {
                $recLocs = $this->recLocs[$company];

                foreach ((array) $recLocs as $rl) {
                    $rl = str_replace('(Hertz-Direct-Link)', '', $rl);
                    $car->general()->confirmation($rl);
                }
            }
        }
    }

    /**
     * @param string|null $string Provider keyword
     *
     * @return string|null Provider code
     */
    private function normalizeProvider(?string $string): ?string
    {
        $string = trim($string);
        $providers = [
            'national' => ['NATIONAL CAR RENTAL'],
        ];

        foreach ($providers as $code => $keywords) {
            foreach ($keywords as $keyword) {
                if (strcasecmp($string, $keyword) === 0) {
                    return $code;
                }
            }
        }

        return null;
    }

    private function parseEmail(Email $email): void
    {
        $nodes = $this->http->XPath->query("//text()[{$this->eq($this->t('Reservation number'))}]/ancestor::div[1][not(.//img)]/following-sibling::*[normalize-space(.)!='']");

        if (($nodes->length % 2 !== 0) || $nodes->length === 0) {
            $nodes = $this->http->XPath->query("//text()[{$this->eq($this->t('Reservation number'))}]/ancestor::p[1]/following-sibling::*[normalize-space(.)!='']");

            if ($nodes->length == 0) {
                $this->logger->debug("Can't determine record Locators (1)");

                return;
            }

            foreach ($nodes as $rootRL) {
                $subj = $this->http->FindNodes("./descendant::text()[normalize-space()!='']", $rootRL);

                if (count($subj) !== 2) {
                    $this->logger->debug("Can't determine record Locators (2)");

                    return;
                }
                $agency = trim(preg_replace("#\s+#", ' ', $subj[0]));
                $this->recLocs[$agency] = array_map("trim", explode(",",
                    $this->re("#(.+?)\s*(?:{$this->opt($this->t('(Agency)'))}|\(\s*{$agency}|$)#", $subj[1])));
            }
        } else {
            $cnt = (int) $nodes->length / 2;

            for ($i = 1; $i <= $cnt; $i++) {
                $agency = trim(preg_replace("#\s+#", ' ', $nodes->item($i * 2 - 2)->nodeValue));
                $this->recLocs[$agency] = array_map("trim", explode(",",
                    $this->re("#(.+?)\s*(?:{$this->opt($this->t('(Agency)'))}|$)#", $nodes->item($i * 2 - 1)->nodeValue)));
            }
        }

        $this->pax = $this->http->FindNodes("//img[contains(@src, '/profil-display-avatar.png')]/ancestor::td[1]/following-sibling::td[1]/descendant::text()[normalize-space(.)][1]");

        if (empty($this->pax)) {
            $this->pax = [
                $this->http->FindSingleNode("(//text()[{$this->starts($this->t('Dear'))}])[1]", null, true,
                    "#{$this->opt($this->t('Dear'))}\s+(.+?)\s*(?:,|$)#"),
            ];
        }
        $tripNumber = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Request ID'))}]/following::text()[normalize-space(.)!=''][1]");
        $email->ota()->confirmation($tripNumber);
        $dateBooked = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Booked'))}]/ancestor::*[contains(.,'/')][1]", null, true, '/\d+\/\d+\/\d+/');

        if ($dateBooked) {
            $this->reservDate = $this->normalizeDate($dateBooked);
        }

        $xpathContent = 'count(descendant::tr[normalize-space()])>1 and contains(.,"-") and contains(translate(.,"0123456789","dddddddddd"),"dd")';
        $xpath = "//img[contains(@src,\"arrow\")]/ancestor::table[1][{$xpathContent}]";
        //segment: table starts with /\w{3} \d{2} \w{3}/
//        $word = str_repeat("w", strlen($this->t('abc')));
//        $digit = str_repeat("d", 10);
//        $xpath = "//table[starts-with(translate(translate(translate(normalize-space(.), '{$this->t('ABC')}', '{$this->t('abc')}'),'{$this->t('abc')}','{$word}'),'0123456789','{$digit}'),'www dd www') and not(./descendant::table)]";
        if ($this->http->XPath->query($xpath)->length === 0) {
            //if can't make in dictionary correct mapping for regex (not for english emails)
            $xpath = "//table[not(.//table) and {$xpathContent} and not({$this->contains($this->t('excludeWords'))})]";

            if ($this->http->XPath->query($xpath)->length === 0) {
                $this->logger->debug("Can't find reservations!");

                return;
            }
        }

        // check that all types can parse and format is right
        $arr = [];

        foreach ($this->types as $type) {
            $arr = array_merge($arr, (array) $this->t($type));
        }

        $xpathFrag = "[./preceding-sibling::table[normalize-space(.)][1][not({$this->contains($arr)})]]";
        $xpathFragCheck = "[./preceding-sibling::table[normalize-space(.)][1][not({$this->contains($this->t('excludeRow'))})]]";
        $rootCheck = $this->http->XPath->query($rootCheckText = $xpath . $xpathFrag . $xpathFragCheck);
        $this->logger->debug("[rootCheck]: " . $rootCheckText);

        if ($rootCheck->length > 0) {
            $this->logger->debug('Wrong format!');

            return;
        }

        //start parsing
        $xpathFrag = "[./preceding::table[normalize-space(.)][1][{$this->contains($this->t('Flight'))}]]";
        $roots = $this->http->XPath->query($xpath . $xpathFrag . '');

        if ($roots->length > 0) {
            $this->parseFlights($email, $xpath . $xpathFrag);
        }

        $xpathFrag = "[./preceding::table[normalize-space(.)][1][{$this->contains($this->t('Hotel'))}]]";
        $roots = $this->http->XPath->query($xpath . $xpathFrag);

        if ($roots->length > 0) {
            $this->parseHotels($email, $xpath . $xpathFrag);
        }

        $xpathFrag = "[./preceding::table[normalize-space(.)][1][{$this->contains($this->t('Rental'))}]]";
        $roots = $this->http->XPath->query($xpath . $xpathFrag);

        if ($roots->length > 0) {
            $this->parseRentals($email, $xpath . $xpathFrag);
        }

        $xpathFrag = "[./preceding::table[normalize-space(.)][1][{$this->contains($this->t('Train'))}]]";
        $roots = $this->http->XPath->query($xpath . $xpathFrag);

        if ($roots->length > 0) {
            $this->parseTrains($email, $xpath . $xpathFrag);
        }
    }

    private function normalizeDate($date)
    {
        $year = date('Y', $this->date);
        $in = [
            //Mon 30 Jan
            '#^(\w+)\.?\s+(\d+)\s+(\w+)\.?$#u',
            //23/03/2018
            '#^(\d+)\/(\d+)\/(\d+)$#',
            //mar. 28 mai - 8:00
            '#^(\w+)\.?\s+(\d+)\s+(\w+)[.]?\s+\-\s+(\d+:\d+)$#u',
        ];
        $out = [
            '$2 $3 ' . $year,
            '$3-$2-$1',
            '$2 $3 ' . $year . ', $4',
        ];
        $outWeek = [
            '$1',
            '',
            '$1',
        ];

        if (!empty($week = preg_replace($in, $outWeek, $date))) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($week, $this->lang));
            $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));
            $str = EmailDateHelper::parseDateUsingWeekDay($str, $weeknum);
        } else {
            $str = strtotime($this->dateStringToEnglish(preg_replace($in, $out, $date)));
        }

        return $str;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang(): bool
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if ($this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody[0]}')]")->length > 0
                    && $this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody[1]}')]")->length > 0
                ) {
                    $this->lang = substr($lang, 0, 2);

                    return true;
                }
            }
        }

        return false;
    }

    private function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function getTotalCurrency(?string $node): array
    {
        $node = str_replace("€", "EUR", $node);
        $node = str_replace("$", "USD", $node);
        $node = str_replace("£", "GBP", $node);
        $tot = null;
        $cur = null;

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]{2,}\d*)#", $node,
                $m) || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node,
                $m) || preg_match("#(?<c>\-*?)(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)
        ) {
            $m['t'] = preg_replace('/\s+/', '', $m['t']);            // 11 507.00	->	11507.00
            $m['t'] = preg_replace('/[,.](\d{3})/', '$1', $m['t']);    // 2,790		->	2790		or	4.100,00	->	4100,00
            $m['t'] = preg_replace('/^(.+),$/', '$1', $m['t']);    // 18800,		->	18800
            $m['t'] = preg_replace('/,(\d{1,2})$/', '.$1', $m['t']);    // 18800,00		->	18800.00
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

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }
}
