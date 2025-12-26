<?php

namespace AwardWallet\Engine\chinaeastern\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class AirTicketConfirmation extends \TAccountChecker
{
    public $mailFiles = "chinaeastern/it-9094222.eml, chinaeastern/it-772971330-zh.eml, chinaeastern/it-779473882-de.eml, chinaeastern/it-773443606-fr.eml"; // +1 bcdtravel(html)[en]

    public $reFrom = ['flychinaeastern', '@ceair.com'];
    public $reBody = [
        'Call China Eastern Airlines', 'please call China Eastern Airlines',
        '請致電中國東方航空台灣呼叫中心客服專線',
        'China Eastern Airlines al numerol',
        'von China Eastern Airlines', // de
        'Contactez le service clients de China Eastern Airlines', // fr
    ];
    public $reSubject = [
        'Air ticket issue confirmation',
        '中國東方航空電子機票行程單', '机票出票成功确认',
        'Conferma emissione biglietto aereo',
    ];
    private $lang = '';
    private $langDetectors = [
        'en' => ['ARR City'],
        'zh' => ['抵達城市', '到达城市'],
        'it' => ['Città di arrivo'],
        'de' => ['Abflugort'],
        'fr' => ['Ville de départ'],
    ];
    private static $dict = [
        'en' => [],
        'zh' => [
            'The order No. is' => ['訂單號碼是', '订单号'],
            'Flight No.'       => ['航班號碼', '航班号'],
            'Total Amount'     => ['總金額', '总金额'],
            'Ticket fares'     => ['票價', '票价'],
            'Tax & Fees'       => ['稅金與費用', '税费'],
            'Passenger type'   => ['乘客類型', '乘客类型'],
            'Passenger name'   => ['乘客姓名', '乘机人'],
            'ID No.'           => ['證件號碼', '证件号'],
        ],
        'it' => [
            'The order No. is' => 'Il n. di ordine è',
            'Flight No.'       => 'N. volo',
            'Total Amount'     => 'Importo totale',
            'Ticket fares'     => 'Tariffe del biglietto',
            'Tax & Fees'       => 'Tasse e spese',
            'Passenger type'   => 'Tipo passeggero',
            'Passenger name'   => 'Nome di passeggeri',
            // 'ID No.' => '',
        ],
        'de' => [
            'The order No. is' => 'Ihr bestelltes Ticket',
            'Flight No.'       => 'Flugnummer',
            'Total Amount'     => 'Gesamt Betrag',
            'Ticket fares'     => 'Ticketpreis',
            'Tax & Fees'       => 'Steuern und Gebühren',
            'Passenger type'   => 'Passagiertyp',
            'Passenger name'   => 'Passagiere',
            'ID No.'           => 'Zertifikatsnr.',
        ],
        'fr' => [
            'The order No. is' => 'Numéro de réservation',
            'Flight No.'       => 'N° de vol',
            'Total Amount'     => 'Montant total',
            'Ticket fares'     => 'Prix des billets',
            'Tax & Fees'       => 'Taxes et frais',
            'Passenger type'   => 'Type de passager',
            'Passenger name'   => 'Nom du passager',
            'ID No.'           => "N° d'identité",
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        foreach ($this->reFrom as $reFrom) {
            if (stripos($from, $reFrom) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (!array_key_exists('from', $headers) || $this->detectEmailFromProvider(rtrim($headers['from'], '> ')) !== true) {
            return false;
        }

        if (isset($headers["subject"])) {
            foreach ($this->reSubject as $reSubject) {
                if (stripos($headers["subject"], $reSubject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//*[{$this->contains($this->reBody)} or {$this->contains(['@ceair.com', 'sg.ceair.com', 'us.ceair.com', 'au.ceair.com', 'ca.ceair.com', 'de.ceair.com', 'fr.ceair.com'])}]")->length === 0
            && $this->http->XPath->query("//a[{$this->contains(['//sg.ceair.com', '//us.ceair.com', '//au.ceair.com', '//ca.ceair.com', '//de.ceair.com', '//fr.ceair.com'], '@href')}]")->length === 0
        ) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug('can\'t determine a language');

            return $email;
        }
        $this->parseEmail($email);
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

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

    private function t($phrase)
    {
        if (!isset(self::$dict[$this->lang]) || !isset(self::$dict[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dict[$this->lang][$phrase];
    }

    private function parseEmail(Email $email): void
    {
        $patterns = [
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
            'travellerName2' => '[[:upper:]]+(?: [[:upper:]]+)*[ ]*\/[ ]*(?:[[:upper:]]+ )*[[:upper:]]+', // KOH / KIM LENG MR
            'eTicket' => '\d{3}(?: | ?- ?)?\d{5,}(?: | ?[-\/] ?)?\d{1,3}', // 175-2345005149-23  |  1752345005149/23
        ];

        $r = $email->add()->flight();
        $tripNumber = $this->http->FindSingleNode("//text()[{$this->contains($this->t('The order No. is'))}]/following::text()[normalize-space(.)!=''][1]",
            null, true, '/^([A-Z\d]{5,})$/');
//        if ($tripNumber)
        $email->ota()->confirmation($tripNumber);
        $r->general()->noConfirmation();

        $segments = $this->http->XPath->query("//text()[{$this->eq($this->t('Flight No.'))}]/ancestor::tr[1]/following-sibling::tr[ ./td[7] ]");

        foreach ($segments as $segment) {
            $s = $r->addSegment();

            $flight = $this->http->FindSingleNode('./td[2]', $segment);

            if (preg_match('/^([A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(\d+)/', $flight, $matches)) {
                $s->airline()
                    ->name($matches[1])
                    ->number($matches[2]);
            }

            $s->departure()
                ->name($this->http->FindSingleNode('./td[3]', $segment))
                ->date(strtotime($this->http->FindSingleNode('./td[5]', $segment)))
                ->noCode();

            $s->arrival()
                ->name($this->http->FindSingleNode('./td[4]', $segment))
                ->date(strtotime($this->http->FindSingleNode('./td[6]', $segment)))
                ->noCode();

            $class = $this->http->FindSingleNode('./td[7]', $segment);
            // Economy Class T
            if (preg_match('/(.+)\s+([A-Z]{1,2})$/', $class, $matches)) {
                $s->extra()
                    ->cabin($matches[1])
                    ->bookingCode($matches[2]);
            } elseif ($class) {
                $s->extra()
                    ->cabin($class);
            }
        }

        $totalPrice = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Total Amount'), "translate(.,':：','')")}]/following::text()[normalize-space()][1]", null, true, '/^.*\d.*$/');

        if (preg_match('/^(?<amount>\d[,.‘\'\d ]*?)[ ]*(?<currency>[^\-\d)(]+?)$/u', $totalPrice, $matches)) {
            // 811.70 SGD
            $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
            $r->price()
                ->total(PriceHelper::parse($matches['amount'], $currencyCode))
                ->currency($matches['currency']);

            $baseFare = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Ticket fares'), "translate(.,':：','')")}]/ancestor::div[1][count(descendant::text()[normalize-space()])>1]", null, true, "/^{$this->opt($this->t('Ticket fares'))}[\s:：]+(.*\d.*)$/");
            
            if (preg_match('/^(?<amount>\d[,.‘\'\d ]*?)[ ]*(?:' . preg_quote($matches['currency'], '/') . ')?$/u', $baseFare, $m)) {
                $r->price()->cost(PriceHelper::parse($m['amount'], $currencyCode));
            }

            $tax = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Tax & Fees'), "translate(.,':：','')")}]/ancestor::div[1][count(descendant::text()[normalize-space()])>1]", null, true, "/^{$this->opt($this->t('Tax & Fees'))}[\s:：]+(.*\d.*)$/");

            if (preg_match('/^(?<amount>\d[,.‘\'\d ]*?)[ ]*(?:' . preg_quote($matches['currency'], '/') . ')?$/u', $tax, $m)) {
                $r->price()->tax(PriceHelper::parse($m['amount'], $currencyCode));
            } else {
                $feesRows = $this->http->XPath->query("//div[{$this->eq($this->t('Tax & Fees'), "translate(.,':：','')")}]/following-sibling::div[normalize-space()]");

                foreach ($feesRows as $feesRow) {
                    if (preg_match("/^\s*(?<name>[^:：]+?)[\s:：]+(?<charge>.*\d.*?)\s*$/u", $feesRow->nodeValue, $m)) {
                        // YQ：232.20SGD
                        if (preg_match('/^(?<amount>\d[,.‘\'\d ]*?)[ ]*(?:' . preg_quote($matches['currency'], '/') . ')?$/u', $m['charge'], $m2)) {
                            $r->price()->fee($m['name'], PriceHelper::parse($m2['amount'], $currencyCode));
                        }
                    }
                }
            }
        }

        $passengers = $ticketNumbers = $accountNumbers = [];
        $xpathPaxHRow = "tr[ *[3][{$this->eq($this->t('Passenger name'))}] and descendant::text()[{$this->eq($this->t('Passenger type'))}] ]";
        $passengerRows = $this->http->XPath->query("//{$xpathPaxHRow}/following-sibling::tr[*[4] and normalize-space()]");
        $accountPreCell = $this->http->XPath->query("//{$xpathPaxHRow}/*[{$this->eq($this->t('ID No.'))}]/preceding-sibling::*")->length;

        foreach ($passengerRows as $row) {
            $passenger = $this->http->FindSingleNode('*[3]', $row, true, "/^(?:{$patterns['travellerName']}|{$patterns['travellerName2']})$/u");

            if ($passenger && !in_array($passenger, $passengers)) {
                $r->general()->traveller($passenger, true);
                $passengers[] = $passenger;
            }

            $ticket = $this->http->FindSingleNode('*[1]', $row, true, "/^{$patterns['eTicket']}$/");

            if ($ticket && !in_array($ticket, $ticketNumbers)) {
                $r->issued()->ticket($ticket, false, $passenger);
                $ticketNumbers[] = $ticket;
            }

            if ($accountPreCell < 1) {
                continue;
            }

            $account = $this->http->FindSingleNode("*[{$accountPreCell}+1]", $row, true, '/^[- A-Z\d]{5,}$/');
            $accountTitle = $this->http->FindSingleNode("preceding-sibling::tr[ descendant::text()[{$this->eq($this->t('Passenger type'))}] ][1]/*[{$accountPreCell}+1]", $row);

            if ($account && !in_array($account, $accountNumbers)) {
                $r->program()->account($account, false, $passenger, $accountTitle);
                $accountNumbers[] = $account;
            }
        }
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array)$field;
        if (count($field) === 0)
            return 'false()';
        return '(' . implode(' or ', array_map(function($s) use($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';
            return 'normalize-space(' . $node . ')=' . $s;
        }, $field)) . ')';
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array)$field;
        if (count($field) === 0)
            return 'false()';
        return '(' . implode(' or ', array_map(function($s) use($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';
            return 'contains(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array)$field;
        if (count($field) === 0)
            return '';
        return '(?:' . implode('|', array_map(function($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }

    private function assignLang(): bool
    {
        foreach ($this->langDetectors as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if ($this->http->XPath->query("//*[{$this->contains($phrase)}]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }
}
