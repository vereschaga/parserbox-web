<?php

namespace AwardWallet\Engine\lufthansa\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class CancellationConfirmation extends \TAccountChecker
{
    public $mailFiles = "lufthansa/it-11173844.eml, lufthansa/it-620832228.eml, lufthansa/it-620972936.eml";
    public $reFrom = "online@booking-lufthansa.com";
    public $reSubject = [
        "de"=> "etix Stornobestaetigung",
        "ro"=> "Confirmarea anularii biletului electronic - etix",
        "pt"=> "Confirmação de cancelamento etix",
        "fr"=> "Confirmation d’annulation de votre etix",
        "en"=> "etix Cancellation Confirmation",
        "it"=> "Conferma annullamento etix",
    ];
    public $reBody = 'www.lufthansa.com';
    public $reBody2 = [
        "de"  => "Stornobestätigung",
        "de2" => "Bestätigung Ihrer Rückerstattung",
        "ro"  => "Confirmarea anularii",
        "pt"  => "Confirmação de cancelamento",
        "fr"  => "Confirmation d'annulation",
        "en"  => "Cancellation Confirmation",
        "en2" => "Cancellation acknowledgement",
        "en3" => "Confirmation of your refund",
        "it"  => "conferma di avvenuta cancellazione",
    ];

    public static $dictionary = [
        "de" => [
            //			"Lufthansa Buchungscode:" => "",
            //			"Passagierinformationen" => "",
            //			"Ihr Buchungscode nicht angezeigt" => "",
            'Refund total' => 'Rückerstattung gesamt',
            'Document:'    => 'Dokument:',
        ],
        "ro" => [
            "Lufthansa Buchungscode:" => "Cod de rezervare Lufthansa:",
            "Passagierinformationen"  => "Passenger Information",
            //			"Ihr Buchungscode nicht angezeigt" => "",
            //'Refund total' => '',
            //'Document:' => '',
        ],
        "pt" => [
            "Lufthansa Buchungscode:"          => "Cod de rezervare Lufthansa:",
            "Passagierinformationen"           => "Informação do passageiro",
            "Ihr Buchungscode nicht angezeigt" => "o seu código de reserva não será apresentado",
            //'Refund total' => '',
            //'Document:' => '',
        ],
        "fr" => [
            "Lufthansa Buchungscode:" => "Code de réservation Lufthansa:",
            "Passagierinformationen"  => "Informations passager",
            //			"Ihr Buchungscode nicht angezeigt" => "",
            //'Refund total' => '',
            //'Document:' => '',
        ],
        "en" => [
            "Lufthansa Buchungscode:" => "Lufthansa booking code:",
            "Passagierinformationen"  => "Passenger information",
            //			"Ihr Buchungscode nicht angezeigt" => "",
            //'Refund total'
            //'Document:' => '',
        ],
        "it" => [
            "Lufthansa Buchungscode:"          => "Codice di prenotazione Lufthansa:",
            "Passagierinformationen"           => "Informazioni sui passeggeri",
            "Ihr Buchungscode nicht angezeigt" => "suo codice di prenotazione non viene visualizzato",
            //'Refund total' => '',
            //'Document:' => '',
        ],
    ];

    public $lang = "en";

    public function parseHtml(Email $email)
    {
        $ruleTime = "contains(translate(normalize-space(.),'0123456789','dddddddddd--'),'d:dd')";

        if ($this->http->XPath->query("//text()[{$ruleTime}]")->length > 0
            && in_array($this->lang, ['en', 'it', 'fr', 'pt'])
        ) {
            return null; //go to parse by It5889106.php
        }

        $f = $email->add()->flight();

        $confirmation = $this->nextText($this->t("Lufthansa Buchungscode:"));

        if (empty($confirmation) && !empty($this->http->FindSingleNode("(//*[" . $this->contains($this->t("Ihr Buchungscode nicht angezeigt")) . "])[1]"))) {
            $f->general()
                ->noConfirmation();
        } else {
            $f->general()
                ->confirmation($confirmation);
        }

        $pax = array_filter($this->http->FindNodes("//text()[" . $this->eq($this->t("Passagierinformationen")) . "]/following::table[1]/descendant::text()[string-length(normalize-space(.))>1]", null,
            "#^([^\d]+/[^\d]+?)\s*(?:\(|$)#"));

        if (count(array_filter($pax)) === 0) {
            $pax = $this->http->FindNodes("//text()[starts-with(normalize-space(), 'Document:')]/preceding::text()[normalize-space()][1][contains(normalize-space(), ' MR')]");
        }

        if (count(array_filter($pax)) === 0) {
            $pax = $this->http->FindNodes("//text()[starts-with(normalize-space(), 'Document:')]/preceding::text()[normalize-space()][1][contains(normalize-space(), ' MR')]");
        }

        if (count(array_filter($pax)) === 0) {
            $pax = $this->http->FindNodes("//text()[{$this->contains($this->t('Document:'))}]", null, "/^(.+)\s+{$this->opt($this->t('Document:'))}/su");
        }

        if (count($pax) > 0) {
            $f->general()
                ->travellers(preg_replace("/^(.+)\s+(?:MRS|MR|MS)/", "$1", array_filter($pax)));
        }

        $ticket = array_unique($this->http->FindNodes("//text()[{$this->starts($this->t('Document:'))}]", null, "/{$this->opt($this->t('Document:'))}\s*([\d\-]+)/u"));

        if (count($ticket) === 0) {
            $ticket = array_unique($this->http->FindNodes("//text()[{$this->contains($this->t('Document:'))}]", null, "/{$this->opt($this->t('Document:'))}\s*([\d\-]+)/u"));
        }

        if (count($ticket) > 0) {
            $f->setTicketNumbers($ticket, false);
        }

        $f->general()
            ->cancelled()
            ->status('cancelled');

        $price = $this->http->FindSingleNode("//td[{$this->starts($this->t('Refund total'))}]/following::text()[normalize-space()][1]");

        if (preg_match("/^(?<currency>[A-Z]{3})\s*(?<total>[\d\.\,]+)$/", $price, $m)) {
            $f->price()
                ->total(PriceHelper::parse($m['total'], $m['currency']))
                ->currency($m['currency']);
        }
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (!isset($headers['from'],$headers['subject'])) {
            return false;
        }

        if (strpos($headers["from"], $this->reFrom) === false) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            if (stripos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (strpos($body, $this->reBody) === false) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if (strpos($body, $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getHeader('date'));

        foreach ($this->reBody2 as $lang=>$re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = substr($lang, 0, 2);

                break;
            }
        }

        $html = str_ireplace(['&zwnj;', '&8203;', '&#8203;', '​', '​'], '', html_entity_decode($this->http->Response['body'])); // Zero-width
        $this->http->SetBody($html);

        $this->parseHtml($email);

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

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function nextText($field, $root = null)
    {
        $rule = $this->eq($field);

        return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::text()[normalize-space(.)][1]", $root);
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) { return "normalize-space(.)=\"{$s}\""; }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) { return "contains(normalize-space(.), \"{$s}\")"; }, $field));
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
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
}
