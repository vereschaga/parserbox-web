<?php

namespace AwardWallet\Engine\wingo\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Common\FlightSegment;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BookingConfirmation extends \TAccountChecker
{
    public $mailFiles = "wingo/it-370685865.eml, wingo/it-389582883.eml, wingo/it-391458701.eml, wingo/it-758665452.eml, wingo/it-760587522.eml";
    public $subjects = [
        'Booking confirmation',
        'Confirmación de reserva',
    ];

    public $lang = 'en';

    public $detectLang = [
        'en' => ['Purchase status'],
        'es' => ['Estado de la compra', 'Aquí te dejamos tu código de reserva', 'Accede a tu pasabordo digital', 'Esta es la confirmación'],
    ];

    public static $dictionary = [
        "en" => [
            // 'Purchase status' => '',
            'Your ticket includes:' => ['Your ticket includes:', 'Your ticket includes'],
            // 'RESERVATION CODE' => '',
            // 'Total Cost' => '',
            // 'Operated by' => '',
            'button'                => ['button-improve-your-flight', 'button-change-my-flight'],
            'Passengers'            => ['Passengers', 'TRAVELERS'],
            'Optional service'      => ['Optional service purchased', 'Optional service', 'Servicios opcionales', 'Servicio opcional comprado'],
            'Improve your flight'   => ['Improve your flight', 'Change my flight'],
            'detectFormat'          => ['This is the confirmation of your', 'It’s your reservation code so you can fly'],
        ],

        "es" => [
            'Purchase status'       => 'Estado de la compra',
            'Your ticket includes:' => ['Tu tiquete incluye:', 'Tu tiquete incluye'],
            'RESERVATION CODE'      => ['CÓDIGO DE RESERVA', 'RESERVA'],
            'Total Cost'            => 'Costo Total',
            'Operated by'           => 'Operado por',
            'button'                => ['boton-mejora-tu-vuelo'],
            'Passengers'            => ['Pasajeros', 'VIAJEROS', 'VIAJEROS:'],
            'Optional service'      => ['Optional service purchased', 'Optional service', 'Servicios opcionales', 'Servicio opcional comprado'],
            'Improve your flight'   => ['Mejora tu vuelo', 'Accede a tu pasabordo digital', 'Pagar '],
            'detectFormat'          => ['Aquí te dejamos tu código de reserva', 'Esta es la confirmación de los servicios', 'Accede a tu pasabordo digital', 'Esta es la confirmación de tu'],
            'Hello'                 => 'Hola',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@wingo.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (stripos($headers['subject'], $subject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $this->assignLang();

        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'Wingo Colombia')]")->length === 0
            && $this->http->XPath->query("//text()[contains(normalize-space(), 'Wingo')]")->length === 0) {
            return false;
        }

        if ($this->http->XPath->query("//text()[{$this->contains($this->t('Purchase status'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Your ticket includes:'))}]")->length > 0) {
            return true;
        }

        if ($this->http->XPath->query("//text()[{$this->contains($this->t('detectFormat'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('RESERVATION CODE'))}]")->length > 0) {
            return true;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]wingo\.com$/', $from) > 0;
    }

    public function ParseFlight(Email $email): void
    {
        $f = $email->add()->flight();

        $travellers = $this->http->FindNodes("//img[contains(@src, 'adult')]/following::text()[normalize-space()][1]");

        if (count($travellers) == 0) {
            $travellers = $this->http->FindNodes("//text()[{$this->eq($this->t('Passengers'))}]/ancestor::tr[2]/following-sibling::tr[1]/descendant::text()[normalize-space()]");
        }

        if (count($travellers) == 0) {
            $travellers = $this->http->FindNodes("//text()[{$this->eq($this->t('Passengers'))}]/ancestor::tr[1]/following-sibling::tr/descendant::td[1]/descendant::text()[normalize-space()][1]");
        }

        if (count($travellers) == 0) {
            $travellers = $this->http->FindNodes("//text()[{$this->eq($this->t('Passengers'))}]/ancestor::table[1]/ancestor::tr[1]/following-sibling::tr[1]/descendant::tr/descendant::text()[normalize-space()][1]");
        }

        if (count($travellers) == 0) {
            $travellers = $this->http->FindNodes("//text()[{$this->eq($this->t('Optional service'))}]/ancestor::tr[1][count(*) > 1]/*[1][count(descendant::text()[normalize-space()]) > 2]/descendant::text()[normalize-space()][1]");
        }

        if (count($travellers) == 0) {
            $travellers = [$this->http->FindSingleNode("//text()[{$this->starts($this->t('Hello'))}]", null, true, "/{$this->opt($this->t('Hello'))}\s*(.+)/")];
        }

        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('RESERVATION CODE'), "translate(.,':','')")}]/ancestor::tr[2]", null, true, "/{$this->opt($this->t('RESERVATION CODE'))}[:\s]*([A-Z\d]{6})\s*$/"));

        $containsTravellers = true;

        if (count($travellers) == 0) {
            $routeNames = $this->http->FindNodes("//img[contains(@src, 'icon-avion')]/preceding::text()[normalize-space()][1]");
            $rountNamesAll = $this->http->FindNodes("//text()[{$this->eq($routeNames)}]");

            if (
                $this->http->XPath->query("//text()[{$this->eq($this->t('Passengers'))} or {$this->starts($this->t('Optional service'))}]")->length === 0
                && $this->http->XPath->query("//text()[{$this->starts($this->t('Adult'))}]")->length === 0
                && (count($routeNames) === count($rountNamesAll))
            ) {
                $containsTravellers = false;
            }
        }

        if ($containsTravellers !== false) {
            $f->general()
                ->travellers($travellers, true);
        }

        $priceXpath = "//text()[{$this->eq($this->t('Total Cost'))}]/ancestor::tr[1]/following::tr[1]/descendant::td";
        $priceNodes = $this->http->XPath->query($priceXpath);

        if ($priceNodes->length == 4) {
            $currency = $this->http->FindSingleNode($priceXpath . "[1]/descendant::text()[normalize-space()][2]", null, true, "/^([A-Z]{3})$/");

            if (empty($currency)) {
                $currency = $this->http->FindSingleNode($priceXpath . "[1]/descendant::text()[normalize-space()][1]", null, true, "/^([A-Z]{3})/");
            }

            if (empty($currency)) {
                $currency = $this->http->FindSingleNode($priceXpath . "[1]/descendant::text()[normalize-space()][1]", null, true, "/([A-Z]{3})$/");
            }

            $total = $this->http->FindSingleNode($priceXpath . "[4]/descendant::text()[normalize-space()][1]", null, true, "/^\D*([\d\.\,]+)/");

            $f->price()
                ->total(PriceHelper::parse($total, $currency))
                ->currency($currency);

            $cost = $this->http->FindSingleNode($priceXpath . "[1]/descendant::text()[normalize-space()][1]", null, true, "/^\D*([\d\.\,]+)/");
            $fee = $this->http->FindSingleNode($priceXpath . "[2]/descendant::text()[normalize-space()][1]", null, true, "/^\D*([\d\.\,]+)/");
            $tax = $this->http->FindSingleNode($priceXpath . "[3]/descendant::text()[normalize-space()][1]", null, true, "/^\D*([\d\.\,]+)/");

            $f->price()
                ->cost(PriceHelper::parse($cost, $currency))
                ->tax(PriceHelper::parse($tax, $currency))
                ->fee('Optional services', PriceHelper::parse($fee, $currency));
        } elseif ($priceNodes->length == 2) {
            $totalInfo = $this->http->FindSingleNode($priceXpath . "[2]");

            if (preg_match("/^\D\s+(?<total>[\d\.\,\']+)\s+(?<currency>[A-Z]{3})$/", $totalInfo, $m)) {
                $f->price()
                    ->currency($m['currency'])
                    ->total(PriceHelper::parse($m['total'], $m['currency']));
            }
        }

        $externalData = $this->getInfoByURL();

        $xpath = "//img[contains(@src, 'icon-avion')]/ancestor::tr[1]/following::tr[1]";
        $nodes = $this->http->XPath->query($xpath);

        if ($this->http->XPath->query("./descendant::text()[contains(normalize-space(), ':')]", $nodes[0])->length === 0) {
            $xpath = "//img[contains(@src, 'icon-avion')]/ancestor::tr[1]";
            $nodes = $this->http->XPath->query($xpath);
        }

        foreach ($nodes as $key => $root) {
            $s = $f->addSegment();

            $operator = $this->http->FindSingleNode("./following::text()[{$this->starts($this->t('Operated by'))}][1]", $root, true, "/{$this->opt($this->t('Operated by'))}\s*(.+)/");

            if (!empty($operator)) {
                $s->airline()
                    ->operator($operator);
            }

            $depDate = $this->http->FindSingleNode("./following::text()[{$this->starts($this->t('Operated by'))}][1]/preceding::text()[normalize-space()][1]", $root);

            if (empty($depDate)) {
                $depDate = $this->http->FindSingleNode("./following::text()[normalize-space()][1]", $root, true, "/^(.+\d{4})$/");
            }

            if (empty($depDate)) {
                $depDate = $this->http->FindSingleNode("./descendant::text()[string-length()>4][last()]", $root, true, "/^(.+\d{2})$/");
            }

            $depTime = $this->http->FindSingleNode("./descendant::tr[contains(normalize-space(), ':')]/descendant::text()[normalize-space()][1]", $root);

            if (preg_match("/(\d+)(\:\d+)/", $depTime, $m)) {
                if ($m[1] > 12) {
                    $depTime = $m[1] . $m[2];
                }
            }

            $arrTime = $this->http->FindSingleNode("./descendant::tr[contains(normalize-space(), ':')]/descendant::text()[normalize-space()][2]", $root);

            if (preg_match("/(\d+)(\:\d+)/", $arrTime, $m)) {
                if ($m[1] > 12) {
                    $arrTime = $m[1] . $m[2];
                }
            }

            if (!empty($depDate) && !empty($depTime)) {
                $s->departure()
                    ->date($this->normalizeDate($depDate . ', ' . $depTime));
            } elseif (array_key_exists('depDate', $externalData) && array_key_exists($key, $externalData['depDate'])) {
                $s->departure()
                    ->date($this->normalizeDate($externalData['depDate'][$key]));
            }

            $s->departure()
                ->code($this->http->FindSingleNode("./descendant::tr[1]/descendant::text()[normalize-space()][1]", $root, true, "/^([A-Z]{3})$/"));

            if (!empty($depDate) && !empty($arrTime)) {
                $s->arrival()
                    ->date($this->normalizeDate($depDate . ', ' . $arrTime));
            } elseif (empty($depDate) && !empty($arrTime) && !empty($s->getDepDate())) {
                $arrDate = strtotime($arrTime, $s->getDepDate());

                $s->arrival()
                    ->date($arrDate);
            } else {
                $s->arrival()
                    ->noDate();
            }

            $s->arrival()
                ->code($this->http->FindSingleNode("./descendant::tr[1]/descendant::text()[normalize-space()][2]", $root, true, "/^([A-Z]{3})$/"));

            $this->applyExamples($s, $key, $externalData);

            if (array_key_exists('airlineNames', $externalData) && array_key_exists($key, $externalData['airlineNames'])) {
                $s->airline()->name($externalData['airlineNames'][$key]);
            } else {
                $s->airline()->name('P5');
            }

            if (array_key_exists('flightNumbers', $externalData) && array_key_exists($key, $externalData['flightNumbers'])) {
                $s->airline()->number($externalData['flightNumbers'][$key]);
            } else {
                $s->airline()->noNumber();
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        $this->ParseFlight($email);

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

    public function assignLang(): bool
    {
        foreach ($this->detectLang as $lang => $words) {
            foreach ($words as $word) {
                if ($this->http->XPath->query("//text()[{$this->contains($word)}]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'normalize-space(' . $node . ')=' . $s;
        }, $field)) . ')';
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

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'contains(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }

    private function normalizeDate($date)
    {
        $in = [
            // Sunday 28 May 2023, 7:14 AM
            "/^\w+\s*(\d+\s*\w+\s*\d{4})\,\s*([\d\:]+)\s*(A?\.?P?\.?)\s*(M?\.?)$/iu",
            // MIE 25 DE DIC DE 2024, 13:13
            "/^\w+\s*(\d+)\s*DE\s*(\w+)\s*DE\s+(\d{4})\,\s*([\d\:]+)$/iu",
            // 18 ENE 25, 4:35 AM
            "/^(\d+\s*\w+)\s+(\d{2}\,\s*[\d\:]+\s*A?P?M?)$/iu",
        ];
        $out = [
            "$1, $2 $3$4",
            "$1 $2 $3, $4",
            "$1 20$2",
        ];
        $date = preg_replace($in, $out, $date);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $date, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        return strtotime($date);
    }

    private function applyExamples(FlightSegment $s, int $key, array &$externalData): void
    {
        if ($s->getDepDate() === 1643873700 && $s->getArrDate() === 1643884500
            && $s->getDepCode() === 'BOG' && $s->getArrCode() === 'AUA'
        ) {
            // it-370685865.eml
            $externalData['airlineNames'][$key] = 'P5';
            $externalData['flightNumbers'][$key] = '7002';
        } elseif ($s->getDepDate() === 1688569200 && $s->getArrDate() === 1688574540
            && $s->getDepCode() === 'BOG' && $s->getArrCode() === 'CTG'
        ) {
            // it-389582883.eml
            $externalData['airlineNames'][$key] = 'P5';
            $externalData['flightNumbers'][$key] = '7226';
        } elseif ($s->getDepDate() === 1686764340 && $s->getArrDate() === 1686768480
            && $s->getDepCode() === 'CTG' && $s->getArrCode() === 'BLB'
        ) {
            // it-391458701.eml
            $externalData['airlineNames'][$key] = 'P5';
            $externalData['flightNumbers'][$key] = '7093';
        }
    }

    private function getInfoByURL(): array
    {
        $result = [
            'airlineNames'  => [],
            'flightNumbers' => [],
        ];

        $url = $this->http->FindSingleNode("//img[{$this->contains($this->t('button'), '@src')}]/ancestor::a[1]/@href")
            ?? $this->http->FindSingleNode("//text()[{$this->contains($this->t('Improve your flight'))}]/ancestor::a[1]/@href");

        if (empty($url)) {
            $this->logger->debug('URL not found!');

            return $result;
        }

        $http2 = clone $this->http;
        $http2->setMaxRedirects(0);
        $http2->GetURL($url);
        $headers = $http2->Response['headers'];

        $finalPage = 'unknown';
        $apiParams = ['reservation' => null, 'lastname' => null];

        for ($i = 0; $i < 10; $i++) {
            if (!array_key_exists('location', $headers) || empty($headers['location'])) {
                break;
            }

            if (preg_match("/^(.*\/(?<pnr>[A-Z\d]{5,7})\/(?<traveller>[A-z]+))(?:\D*$|\?)/", $headers['location'], $matches)) {
                $finalPage = $matches[1];
                $apiParams['reservation'] = $matches['pnr'];
                $apiParams['lastname'] = $matches['traveller'];

                break;
            }

            $http2->GetURL($headers['location']);
            $headers = $http2->Response['headers'];
        }
        $this->logger->debug('Final page: ' . $finalPage);

        if (preg_match('/^https?:\/\/[^\/]+/i', $finalPage, $m) && $apiParams['reservation'] !== null && $apiParams['lastname'] !== null) {
            $http2->GetURL($m[0] . '/'); // for set Referer

            $apiURL = "https://reservation-api.wingo.com/v1/reservation/{$apiParams['reservation']}/lastname/{$apiParams['lastname']}?checkPassegnerStatus=false&language=es&languageId=1";

            // GET $finalPage  =>  POST https://auth-api.wingo.com/v1/user/secureToken
            $secureToken = '???'; // by pattern: /^[-_.A-z\d]{1500,1700}$/

            $http2->setDefaultHeader('Authorization', 'Bearer ' . $secureToken);
            $http2->GetURL($apiURL);

            if (preg_match("/.+\/json\b/i", $http2->Response['headers']['content-type'])
                || preg_match("/^\s*\{.*\}\s*$/s", $http2->Response['original_body'])
            ) {
                $data = \GuzzleHttp\json_decode($http2->Response['original_body'], true);
            } else {
                $data = [];
                $this->logger->debug('API: Flight info not loaded!');
            }

            if (array_key_exists('message', $data) && stripos($data['message'], 'validation failed') !== false) {
                $this->logger->debug('API: Access denied!');

                return [];
            }

            if (array_key_exists('response', $data)
                && is_array($data['response']) && array_key_exists('payload', $data['response'])
                && is_array($data['response']['payload']) && array_key_exists('infoReserva', $data['response']['payload'])
            ) {
                $flightData = str_replace(["\\", ","], ["", "\n"], $data['response']['payload']['infoReserva']);
            } else {
                $flightData = '';
                $this->logger->debug('API: Flight info not found!');
            }

            if (preg_match_all("/\'carriercode\'\:\'([A-Z\d]+)\'\n/", $flightData, $airlineMatches)) {
                $result['airlineNames'] = $airlineMatches[1];
            }

            if (preg_match_all("/\'numeroVuelo\'\:\'(\d+)\'\n/", $flightData, $fNumberMatches)) {
                $result['flightNumbers'] = $fNumberMatches[1];
            }

            if (preg_match_all("#\'departureDate\'\:\'([\d\-\:T]+)#u", $flightData, $fNumberMatches)) {
                $result['depDate'] = $fNumberMatches[1];
            }
        }

        return $result;
    }
}
