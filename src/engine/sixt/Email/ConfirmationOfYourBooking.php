<?php

namespace AwardWallet\Engine\sixt\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class ConfirmationOfYourBooking extends \TAccountChecker
{
    public $mailFiles = "sixt/it-39209135.eml, sixt/it-44237101.eml";

    public $reFrom = ["chauffeured-services@sixt.com", 'Sixt Chauffeured Services', 'ride@sixt.com'];
    public $reBody = [
        'de'  => ['Dank, dass Sie sich für Sixt', 'finden Sie Ihre Buchungsdetails'],
        'de2' => ['Dank, dass Sie sich für Sixt', 'Fahrer jetzt bewerten'],
        'en'  => ['Thank you for choosing Sixt', 'Here are the details of your'],
        'en2' => ['Thank you for choosing Sixt', 'find your Sixt booking details below'],
        'en3' => ['Your Sixt Team', 'You will receive an email'],
        'en4' => ['Your Sixt Team', 'we need to cancel your booking'],
        'en5' => ['Your Sixt Team', 'Due to a short-term lack of availability'],
        'en6' => ['Thank you for booking with SIXT', 'Please share your experience with us'],
        'fr'  => ['Votre équipe Sixt', 'Évaluez votre conducteur'],
    ];
    public $reSubject = [
        // de
        '#SIXT ride Buchungsbestätigung (\d{6,})#iu',
        '#Ihre SIXT ride Fahrtübersicht\s+(\d{6,})#iu',
        // en
        '#Confirmation of your booking (\d{6,})#',
        '#Confirmation of your booking request (\d{6,})#',
        '#SIXT ride booking (\d{6,}) confirmation#',
        // fr
        '#Votre réservation (\d{6,}) est réglée#u',
    ];
    public $lang = '';
    public static $dict = [
        'de' => [
            'PASSENGER:'    => 'FAHRGAST:',
            'PICK-UP TIME:' => 'ABHOLZEIT:',
            'PRICE:'        => 'PREIS:',
            'PICK-UP:'      => 'START:',
            'DROP-OFF:'     => 'ZIEL:',
            //            'DURATION:' => '',
            //            'DROP-OFF TIME:' => '',
            'RIDE TYPE:' => 'BUCHUNGSKLASSE:',
            'DISTANCE:'  => 'ENTFERNUNG:',
            //'cancellation' => "",
        ],
        'fr' => [
            'PASSENGER:'    => 'PASSAGER:',
            'PICK-UP TIME:' => 'HEURE DE PRISE EN CHARGE:',
            'PRICE:'        => 'PRIX:',
            'PICK-UP:'      => 'START:',
            'DROP-OFF:'     => 'DESTINATION:',
            //            'DURATION:' => '',
            //            'DROP-OFF TIME:' => '',
            'RIDE TYPE:' => 'CATEGORIA DE VÉHICULE:',
            'DISTANCE:'  => 'DISTANCE:',
            //            'cancellation' => "",
        ],
        'en' => [
            'PASSENGER:'    => 'PASSENGER:',
            'PICK-UP TIME:' => 'PICK-UP TIME:',
            'cancellation'  => ["we need to cancel your booking", "we have to cancel your ride booking"],
        ],
    ];
    private $subject;

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        $this->subject = $parser->getSubject();
        $a = explode('\\', __CLASS__);
        $class = end($a);
        $email->setType($class . ucfirst($this->lang));

        $this->parseEmail($email);

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//img[@alt='Sixt Chauffeured Services' or @alt='Sixt Ride' or contains(@src,'/mydriver/')] | //a[contains(@href,'.mydriver.com')]")->length > 0) {
            foreach ($this->reBody as $reBody) {
                if ($this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody[0]}')]")->length > 0
                    && $this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody[1]}')]")->length > 0
                ) {
                    return $this->assignLang();
                }
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && isset($headers["subject"])) {
            $flag = false;

            foreach ($this->reFrom as $reFrom) {
                if (stripos($headers['from'], $reFrom) !== false) {
                    $flag = true;
                }
            }

            if ($flag) {
                foreach ($this->reSubject as $reSubject) {
                    if (preg_match($reSubject, $headers["subject"])) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->reFrom as $reFrom) {
            if (stripos($from, $reFrom) !== false) {
                return true;
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
        $r = $email->add()->transfer();

        $confNo = null;

        foreach ($this->reSubject as $reSubject) {
            if (preg_match($reSubject, $this->subject, $m)) {
                $confNo = $m[1];

                break;
            }
        }

        if ($confNo === null && preg_match("#\b(\d{6,})\b#", $this->subject, $m)) {
            $confNo = $m[1];
        }
        $r->general()
            ->confirmation($confNo)
            ->traveller($this->nextTd($this->t('PASSENGER:')));

        $node = $this->nextTd($this->t('PRICE:'));

        if (!empty($node)) {
            $sum = $this->getTotalCurrency($node);
            $r->price()
                ->total($sum['Total'])
                ->currency($sum['Currency']);
        }

        $s = $r->addSegment();

        $s->departure()
            ->name($this->nextTd($this->t('PICK-UP:')))
            ->date(strtotime($this->nextTd($this->t('PICK-UP TIME:'))));

        if (!empty($node = $this->nextTd($this->t('DROP-OFF:')))) {
            $s->arrival()->name($node);
        }

        if (!empty($node = $this->nextTd($this->t('DURATION:')))) {
            $s->arrival()->date(strtotime('+ ' . $node, $s->getDepDate()));
        } elseif (!empty($node = $this->nextTd($this->t('DROP-OFF TIME:')))) {
            $s->arrival()->date(strtotime($node));
        } elseif ($this->http->XPath->query("//text()[{$this->contains($this->t('DROP-OFF TIME:'))} or {$this->contains($this->t('DURATION:'))}]")->length === 0) {
            $s->arrival()->noDate();
        }

        $s->extra()->type($this->nextTd($this->t('RIDE TYPE:')), true, true);

        if (!empty($node = $this->nextTd($this->t('DISTANCE:')))) {
            $s->extra()->miles($node);
        }

        if ($this->http->XPath->query("//text()[{$this->contains($this->t('cancellation'))}]")->length > 0) {
            $r->general()
                ->cancelled();
        }

        return true;
    }

    private function nextTd($field)
    {
        return $this->http->FindSingleNode("//text()[{$this->eq($field)}]/ancestor::td/following-sibling::td[normalize-space(.)!=''][1]");
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
        foreach (self::$dict as $lang => $words) {
            if (isset($words['PASSENGER:'], $words['PICK-UP TIME:'])) {
                if ($this->http->XPath->query("//*[{$this->contains($words['PASSENGER:'])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($words['PICK-UP TIME:'])}]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function getTotalCurrency($node)
    {
        $node = str_replace("€", "EUR", $node);
        $node = str_replace("$", "USD", $node);
        $node = str_replace("£", "GBP", $node);
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[.\d,\s]*\d*)#", $node, $m)
            || preg_match("#(?<t>\d[.\d,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m)
        ) {
            $cur = $m['c'];
            $tot = PriceHelper::cost($m['t']);
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }
}
