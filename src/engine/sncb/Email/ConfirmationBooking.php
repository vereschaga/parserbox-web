<?php

namespace AwardWallet\Engine\sncb\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Common\Train;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ConfirmationBooking extends \TAccountChecker
{
    public $mailFiles = "sncb/it-2026577.eml, sncb/it-2734404.eml, sncb/it-2738140.eml, sncb/it-3904723.eml, sncb/it-6041949.eml, sncb/it-61110591.eml, sncb/it-6278081.eml, sncb/it-6937246.eml, sncb/it-8684017.eml, sncb/it-8684025.eml, sncb/it-8774593.eml, sncb/it-8833046.eml, sncb/it-34113897.eml"; // +2 bcdtravel(html)[nl]

    public static $dict = [
        'en' => [
            'Your customer number:' => 'Your customer number:',
            'Passengers'            => 'Passengers',
            'From'                  => '/From\s*(.{3,}?)\s+to\s+(.{3,}?)\s*with\s*(.*?(?:T|t)rain number) (\d+)/',
            'Departure'             => '/(?:d|D)eparture at ([\d:]+) and arrival at ([\d:]+)/',
            'Coach'                 => 'Coach',
            'seat'                  => ['seat', 'seats'],
            //            'Grand Voyageur n° :' => '',
            //            'with fare' => ''
        ],
        'fr' => [
            'Your customer number:' => ['Votre numéro de client:', 'Eurostar Frequent Traveller'],
            'Passengers'            => 'Passagers',
            'Total'                 => 'Total',
            'Journey on'            => 'Voyage du',
            'Departure :'           => 'Départ :',
            'Return :'              => 'Retour :',
            'From'                  => '/De\s*(.{3,}?)\s+à\s+(.{3,}?)\s*avec (.+ n°) (\d+)/u',
            'Departure'             => '/Départ à ([\d:]+) et arrivée à ([\d:]+)/',
            'Coach'                 => 'Voiture',
            'seat'                  => ['places', 'place'],
            'Grand Voyageur n° :'   => ['Grand Voyageur n° :', 'Eurostar Frequent Traveller :'],
            'with fare'             => 'au tarif',
        ],
        'nl' => [
            'Your customer number:' => 'Uw klantnummer:',
            'Passengers'            => 'Reizigers',
            'Total'                 => 'Totaal',
            'Journey on'            => 'Reis van',
            'Departure :'           => ['Heenreis :', 'Vertrek :'],
            'Return :'              => 'Terugreis :',
            'From'                  => '/Van\s*(.{3,}?)\s+naar\s+(.{3,}?)\s*met (.+?) n° (\d+)/u',
            'Departure'             => '/Vertrek om ([\d:]+) en aankomst om ([\d:]+)/',
            'Coach'                 => 'Rijtuig',
            'seat'                  => ['zitplaats', 'zitplaat'],
            //            'Grand Voyageur n° :' => '',
            'with fare' => 'met tarief',
        ],
    ];
    public $lang = '';

    private $detectFrom = ['@b-rail.', '.b-rail.', 'NMBS/SNCB Europe', 'SNCB International'];

    private $detectSubject = [
        // en
        'Confirmation, booking reference:',
        'Confirmation, name:',
        'Confirmation of your Thalys booking',
        // fr
        'Confirmation de votre achat',
        'Itinéraire de voyage envoyé par United Airlines, Inc.',
        'Confirmation de votre réservation Thalys',
        // nl
        'Bevestiging, naam:',
        'Bevestiging, boekingscode:',
    ];

    private $providerCode = '';
    private $region = '';

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->striposAll($headers['from'], $this->detectFrom) !== true) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers['subject'], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        // Detecting Provider
        if ($this->assignProvider($parser->getHeaders()) === false) {
            return false;
        }

        // Detecting Language
        return $this->assignLang();
    }

    public function detectEmailFromProvider($from)
    {
        return $this->striposAll($from, $this->detectFrom) !== false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        // Detecting Provider
        $this->assignProvider($parser->getHeaders());
        $email->setProviderCode($this->providerCode);

        // Detecting Language
        if ($this->assignLang() !== true) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        $t = $email->add()->train();

        // General
        $confsAll = [];
        $confsText = $this->htmlToText($this->http->FindHTMLByXpath('//text()[' . $this->eq($this->t('Passengers')) . ']/preceding::text()[contains(.,"(DNR):")][1]/ancestor::td[1]'));

        if (preg_match("/\(DNR\):[ ]*([A-Z\d][A-Z\d, ]{3,}[A-Z\d])\b/", $confsText, $m)) {
            $confs = preg_split("/\s*[,]+\s*/", $m[1]);
        } else {
            $confs = [];
        }

        if (!empty($confs)) {
            $confsAll[trim($this->http->FindSingleNode('//text()[' . $this->eq($this->t('Passengers')) . ']/preceding::text()[contains(.,"(DNR):")][1]'), ':')] = $confs;
        }

        $confsText = $this->htmlToText($this->http->FindHTMLByXpath('//text()[' . $this->eq($this->t('Passengers')) . ']/preceding::text()[contains(.,"(PNR):")][1]/ancestor::td[1]'));

        if (preg_match("/\(PNR\):[ ]*([A-Z\d][A-Z\d, ]{3,}[A-Z\d])\b/", $confsText, $m)) {
            $confs = preg_split("/\s*[,]+\s*/", $m[1]);
        } else {
            $confs = [];
        }

        if (!empty($confs)) {
            $confsAll[trim($this->http->FindSingleNode('//text()[' . $this->eq($this->t('Passengers')) . ']/preceding::text()[contains(.,"(PNR):")][1]'), ':')] = $confs;
        }

        $t->general()
            ->travellers(array_filter($this->http->FindNodes("//text()[contains(.,'{$this->t('Passengers')}')]/ancestor::tr[2]/following-sibling::tr//td[contains(.,'•') and not(.//td)]/following-sibling::td/descendant::text()[normalize-space(.)][1]", null, '/^([[:alpha:]\s]+)$/i')), true)
        ;

        $accounts = array_filter($this->http->FindNodes("//text()[" . $this->starts($this->t('Grand Voyageur n° :')) . "]", null, '/:\s*(\d{5,})\b$/'));

        if (empty($accounts)) {
            $accounts[] = $this->http->FindSingleNode("//text()[" . $this->contains($this->t('Your customer number:')) . "]/following::strong[1]", null, false, '/\d+/');
            $accounts = array_filter($accounts);
        }

        if (stripos($parser->getSubject(), ' Thalys ') !== false
                || $this->http->XPath->query("//text()[" . $this->contains(['Merci d\'avoir choisi Thalys pour l\'achat de vos billets']) . "]")->length > 0) {
            $email->setProviderCode('thalys');
            $email->ota()->code('sncb');

            if (!empty($confsAll)) {
                foreach ($confsAll as $name => $confs) {
                    foreach ($confs as $conf) {
                        $email->ota()
                            ->confirmation($conf, $name);
                    }
                }
                $t->general()->noConfirmation();
            }
            // Program
            if (!empty($accounts)) {
                $email->ota()->accounts($accounts, false);
            }

            $tAccount = array_filter($this->http->FindNodes("//text()[" . $this->starts($this->t('Thalys n° :')) . "]", null, '/:\s*(\d{5,})\b$/'));

            if (!empty($tAccount)) {
                $t->program()->accounts($tAccount, false);
            }
        } else {
            foreach ($confsAll as $name => $confs) {
                foreach ($confs as $conf) {
                    $t->general()
                        ->confirmation($conf, $name);
                }
            }

            // Program
            foreach ($accounts as $account) {
                $pax = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Passengers'))}]/following::table[string-length()>2][1]/descendant::text()[{$this->contains($account)}][1]/preceding::text()[normalize-space()][1]");

                if (!empty($pax)) {
                    $t->program()->account($account, false, $pax);
                } else {
                    $t->program()->account($account, false);
                }
            }
            /*if (!empty($accounts)) {
                $t->program()->accounts($accounts, false);
            }*/
        }

        // Price
        if ($totalCharge = $this->http->FindSingleNode("//text()[normalize-space(.)='{$this->t('Total')}']/ancestor::td[1]/following-sibling::td", null, true, "#(?:.*-->)?(.+)#")) { // change to the total amount to paid --> € 87,00
            $t->price()
                ->total((float) preg_replace('/[^\d.]+/', '', str_replace(',', '.', $totalCharge)))
                ->currency(preg_replace(['/[\d.,\s]+/', '/€/', '/^\$$/'], ['', 'EUR', 'USD'], $totalCharge))
            ;
        }

        $type = '2015';

        if (!$this->parseSegments2015($t)) {
            $type = '2014';
            $this->parseSegments2014($t);
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang) . 'Segment' . $type);

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

    public static function getEmailProviders()
    {
        return ['thalys', 'thetrainline', 'sncb'];
    }

    protected function t($s)
    {
        if (isset($this->lang) && isset(self::$dict[$this->lang][$s])) {
            return self::$dict[$this->lang][$s];
        }

        return $s;
    }

    private function striposAll($text, $needle): bool
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

    private function assignProvider($headers): bool
    {
        if (stripos($headers['subject'], 'your Thalys booking') !== false
            || $this->http->XPath->query('//a[contains(@href,".thalysthecard.com/") or contains(@href,"www.thalysthecard.com")]')->length > 0
            || $this->http->XPath->query('//*[contains(normalize-space(),"Thank you for buying your Thalys")]')->length > 0
        ) {
            $this->providerCode = 'thalys';

            return true;
        }

        if ($this->http->XPath->query('//img[contains(@src,"logos/TheTrainLine.jpg")]')->length > 0) {
            $this->providerCode = 'thetrainline';

            return true;
        }

        if (stripos($headers['from'], 'nmbs_sncb_noreply@b-rail.be') !== false
            || $this->http->XPath->query('//a[contains(@href,".b-europe.com/") or contains(@href,"www.b-europe.com")]')->length > 0
            || $this->http->XPath->query('//title[contains(normalize-space(),"NMBS Internationaal")]')->length > 0
            || $this->http->XPath->query('//*[contains(normalize-space(),"Thank you for choosing to purchase your tickets with SNCB International") or contains(normalize-space(),"The SNCB International team")]')->length > 0
            || $this->http->XPath->query('//img[contains(@src,"prototype/logo_sncb.jpg")]')->length > 0
        ) {
            $this->providerCode = 'sncb';

            return true;
        }

        return false;
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dict, $this->lang)) {
            return false;
        }

        foreach (self::$dict as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['Your customer number:']) || empty($phrases['Passengers'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[{$this->contains($phrases['Your customer number:'])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->contains($phrases['Passengers'])}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function assignRegion(array $stationNames): void
    {
        // added region for google, to help find correct address of stations
        foreach ($stationNames as $sName) {
            if (preg_match("/(?:\bLuxembourg\b)/i", $sName)) {
                $this->region = 'Europe';

                return;
            }
        }
    }

    private function normalizeDate($date)
    {
//        $this->logger->debug('$date = '.print_r( $date,true));
        $in = [
            // Mon 13 Oct 2014, 11:45; ven. 28 août 2015, 11:45; jeu. 02 nov. 2017, 09:35
            '#^\s*[^\d\s]+\s+(\d{1,2})\s+(\w+)[.]?\s+(\d{4}),\s+(\d{1,2}:\d{2})\s*$#ui',
            // Mon 13 Oct 2014  ||  ven. 28 août 2015
            '#^\s*[^\d\s]+\s+(\d{1,2})\s+(\w+)\s+(\d{4})\s*$#ui',
        ];
        $out = [
            "$1 $2 $3, $4",
            "$1 $2 $3",
        ];

        $str = preg_replace($in, $out, $date);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function parseSegments2015(Train $t): bool
    {
        $this->logger->debug(__METHOD__);
        $dates = $this->http->XPath->query('//text()[' . $this->contains($this->t('Departure :')) . ' or contains(.,"' . $this->t('Return :') . '")]/ancestor::tr[1]');

        if ($dates->length == 0) {
            return false;
        }

        foreach ($dates as $dateRoot) {
            $date = null;
            $nodes = $this->http->XPath->query('following-sibling::tr[.//img[contains(@src,"arivalIcon.gif") or (@height="10" and @width="8")  or (contains(@alt,"Image removed by sender"))]]', $dateRoot);

            if ($nodes->length == 0) {
                return false;
            }

            if (preg_match('/:\s*([.\w\s]+)/u', $dateRoot->nodeValue, $matches)) {
                $date = trim($matches[1]);
            }

            foreach ($nodes as $root) {
                if (preg_match('/(\d+:\d{2})\s*([-\w\s\/().]{3,}?)\s*(\d+:\d{2})\s*([-\w\s\/().]{3,}?)\s+(\d+)/u', $root->nodeValue, $matches)) {
                    $s = $t->addSegment();

                    $this->assignRegion([$matches[2], $matches[4]]);

                    // Departure
                    $s->departure()
                        ->name($this->region ? implode(', ', [$matches[2], $this->region]) : $matches[2])
                        ->date($this->normalizeDate($date . ', ' . $matches[1]));

                    // Arrival
                    $s->arrival()
                        ->name($this->region ? implode(', ', [$matches[4], $this->region]) : $matches[4])
                        ->date($this->normalizeDate($date . ', ' . $matches[3]));

                    $s->extra()
                        ->number($matches[5]);
                } else {
                    foreach ($t->getSegments() as $seg) {
                        $t->removeSegment($seg);

                        return false;
                    }
                }
            }
        }

        return true;
    }

    private function parseSegments2014(Train $t): bool
    {
        $this->logger->debug(__METHOD__);
        $i = -1;
        $nodes = $this->http->XPath->query("//*[contains(text(),'" . $this->t('Journey on') . "')]/ancestor::table[1]//tr/td[normalize-space()]");

        foreach ($nodes as $root) {
            // first parent element
            if (preg_match('/' . $this->t('Journey on') . '\s*(\b[,.\d\w\s]{4,}\b)/u', $root->nodeValue, $matches)) {
                $i++;
                $s = $t->addSegment();
                $date = $matches[1];

                continue;
            }

            $this->logger->debug($root->nodeValue);
            $this->logger->debug('----------------------------');
            $this->logger->debug($this->t('From'));
            $this->logger->debug('----------------------------');

            if (preg_match($this->t('From'), $root->nodeValue, $matches)) {
                $this->assignRegion([$matches[1], $matches[2]]);
                $s->departure()
                    ->name($this->region ? implode(', ', [$matches[1], $this->region]) : $matches[1]);
                $s->arrival()
                    ->name($this->region ? implode(', ', [$matches[2], $this->region]) : $matches[2]);

                if (!empty($matches[4])) {
                    $s->extra()
                        ->number($matches[4]);
                }
            }

            if (preg_match($this->t('Departure'), $root->nodeValue, $matches)) {
                $s->departure()
                    ->date($this->normalizeDate($date . ', ' . $matches[1], false));
                $s->arrival()
                    ->date($this->normalizeDate($date . ', ' . $matches[2], false));
            }

            if (preg_match('/' . $this->t('Coach') . ' (?<coach>\d+), (?<seatName>' . $this->preg_implode($this->t('seat')) . '\s*)?(?<seats>[\d ,A-Z]+)/', $root->nodeValue, $matches)) {
                $s->extra()
                    ->car($matches['coach']);
                $seats = array_filter(array_map('trim', explode(',', trim($matches['seats'], ', '))));

                if (!empty($seats)) {
                    $s->extra()->seats($seats);
                }
            }
        }

        return true;
    }

    private function contains($field, $text = 'normalize-space(.)')
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) use ($text) { return "contains({$text}, \"{$s}\")"; }, $field));
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'starts-with(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v); }, $field)) . ')';
    }

    private function htmlToText(?string $s, bool $brConvert = true): string
    {
        if (!is_string($s) || $s === '') {
            return '';
        }
        $s = str_replace("\r", '', $s);
        $s = preg_replace('/<!--.*?-->/s', '', $s); // comments

        if ($brConvert) {
            $s = preg_replace('/\s+/', ' ', $s);
            $s = preg_replace('/<[Bb][Rr]\b.*?\/?>/', "\n", $s); // only <br> tags
        }
        $s = preg_replace('/<[A-z][A-z\d:]*\b.*?\/?>/', '', $s); // opening tags
        $s = preg_replace('/<\/[A-z][A-z\d:]*\b[ ]*>/', '', $s); // closing tags
        $s = html_entity_decode($s);
        $s = str_replace(chr(194) . chr(160), ' ', $s); // NBSP to SPACE

        return trim($s);
    }
}
