<?php

namespace AwardWallet\Engine\eiffel\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Event extends \TAccountChecker
{
	public $mailFiles = "eiffel/it-767577901.eml, eiffel/it-779760790.eml, eiffel/it-784584452.eml, eiffel/it-787222518.eml, eiffel/it-790710426.eml";
    public $subjects = [
        'Eiffel Tower ticket',
        'Eiffel Tower Ticket',
        'Billet Tour Eiffel',
        'Billete Torre Eiffel',
    ];

    public $ticketPdfNamePattern = "(?:tickets|Tickets|Billetes).*pdf";
    public $receiptPdfNamePattern = "(?:receipt|justificatif|Justificante).*pdf";

    public $lang = '';

    public static $dictionary = [
        'es' => [
            'Order' => 'Orden',
            'Purchase date' => 'Fecha de compra',
            'THIS IS A NON-SMOKING MONUMENT' => 'ESTÁ PROHIBIDO FUMAR EN EL MONUMENTO',
            'Ticket' => 'Entrada',
            'Type' => 'Tipo',
            'Conditions of use' => 'Condiciones de uso',
            'Allow 15-20 minutes' => 'Llegue unos 15-20 minutos',
            'PERSONAL TICKET' => 'ENTRADA NOMINATIVA',
            // 'adult' => '',
            // 'child' => '',
        ],
        'fr' => [
            'Order' => 'Achat',
            'Purchase date' => "Date d'achat",
            'THIS IS A NON-SMOKING MONUMENT' => 'LE MONUMENT EST NON FUMEUR',
            'Ticket' => 'Billet',
            // 'Type' => '',
            'Conditions of use' => 'Conditions d’utilisation',
            'Allow 15-20 minutes' => 'Prévoyez 15 à 20',
            'PERSONAL TICKET' => 'BILLET NOMINATIF',
            'adult' => 'ADULTE',
            'child' => ['ENFANT', 'JEUNE'],
        ],
        'en' => [
            // 'Order' => '',
            'Purchase date' => ['Purchase date', 'Date of purchase'],
            'THIS IS A NON-SMOKING MONUMENT' => ['THIS IS A NON-SMOKING MONUMENT', 'THIS IS A NON SMOKING MONUMENT', 'THIS IS A NON SMOCKING MONUMENT', 'THIS IS A NON-SMOCKING MONUMENT'],
            // 'Ticket' => '',
            // 'Type' => '',
            'Conditions of use' => ['Conditions of use'],
            // 'Allow 15-20 minutes' => '',
            'PERSONAL TICKET' => ['PERSONAL TICKET'],
            'adult' => 'ADULT',
            // 'child' => '',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@toureiffel.paris') !== false) {
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
        $pdfs = $parser->searchAttachmentByName($this->ticketPdfNamePattern);

        foreach ($pdfs as $pdf) {
            $ticketsText = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (strpos($ticketsText, 'Eiffel') === false) {
                continue;
            }

            if ($this->assignLang($ticketsText)) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]toureiffel\.paris$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $ticketsPdfs = $parser->searchAttachmentByName($this->ticketPdfNamePattern);
        $receiptPdfs = $parser->searchAttachmentByName($this->receiptPdfNamePattern);

        foreach ($ticketsPdfs as $ticket) {
            $ticketsText = \PDF::convertToText($parser->getAttachmentBody($ticket));

            if (empty($ticketsText)) {
                continue;
            }

            // remove watermarks
            $ticketsText = preg_replace([
                '/^(.{40}[ ]{2,})(?:Re|sa|le|pr|oh|ib|ite|d|In|te|rd|it|à|la|re|ve|nt|e|Pr|id|a)([ ]{2}|$)/mu',
                '/[ ]+$/m',
            ], [
                '$1 $2',
                '',
            ], $ticketsText);

            $this->assignLang($ticketsText);
            foreach ($receiptPdfs as $receipt){
                $receiptText = \PDF::convertToText($parser->getAttachmentBody($receipt));

                $this->Event($email, $ticketsText, $receiptText);
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function Event(Email $email, $ticketsText, $receiptText): void
    {
        $patterns = [
            'time' => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM  |  2:00 p. m.
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        ];

        $e = $email->add()->event();

        $ticketsText = preg_split("/\n+.*{$this->opt($this->tAll('THIS IS A NON-SMOKING MONUMENT'))}.*\n*/u", $ticketsText);

        if (preg_match("/({$this->opt($this->tAll('Order'))}\s*n°)\s*[:]+\s*(\d+)(?:[ ]{2}|[ ]*\n|$)/", $ticketsText[0], $m)) {
            $e->general()->confirmation($m[2], $m[1]);
        }

        if (preg_match("/\n[ ]*{$this->opt($this->tAll('Purchase date'))}\s*[:]+\s*(?<purDate>\d+[-.\/]\d+[-.\/]\d+)(?:[ ]*[[:alpha:]]+[ ]*|[ ]+)(?<purTime>{$patterns['time']})(?:[ ]{2}|[ ]*\n)/u", $ticketsText[0], $m)) {
            $e->general()->date(strtotime($m['purTime'], strtotime($this->normalizeDate($m['purDate']))));
        }

        $e->type()
            ->event();

        $e->place()
            ->address('5 Av. Anatole France, 75007 Paris, France')
            ->name($this->re("/{$this->opt($this->tAll('Ticket'))}\s*n°\d+\s*[[:alpha:]]+\s*\d+\n+\s*(.+)\n/u", $ticketsText[0]));

        if (preg_match("/^[ ]*(?<startDate>\d+[-.\/]\d+[-.\/]\d+)\s*-\s*(?<startTime>{$patterns['time']})/mu", $ticketsText[0], $m)){
            $e->booked()->start(strtotime($m['startTime'], strtotime($this->normalizeDate($m['startDate']))))->noEnd();
        }

        $adultsCount = 0;
        $kidsCount = 0;

        foreach ($ticketsText as $ticket){
            if (!empty($ticket)){
                $ticketText = preg_replace("/\n+.*{$this->opt($this->tAll('Allow 15-20 minutes'))}[\s\S]*$/", '',  $ticket);

                if (preg_match("/\n[ ]*({$patterns['travellerName']})\n+[ ]*{$this->opt($this->tAll('Type'))}\s*:/u", $ticketText, $m)) {
                    $e->general()->traveller($m[1], true);

                    $travellerType = $this->re("/\n[ ]*{$this->opt($this->tAll('Type'))}\s*[:]+\s*([^:\s].*?)(?:[ ]{2}|\n)/u", $ticketText);

                    if (preg_match("/\b{$this->opt($this->tAll('adult'))}\b/i", $travellerType)) {
                        $adultsCount++;
                    } elseif (preg_match("/\b{$this->opt($this->tAll('child'))}\b/i", $travellerType)) {
                        $kidsCount++;
                    }
                }
            }
        }

        if ($adultsCount > 0){
            $e->booked()
                ->guests($adultsCount);
        }

        if ($kidsCount > 0){
            $e->booked()
                ->kids($kidsCount);
        }

        $totalPrice = $this->re("/(?:^[ ]*|[ ]{2})Total TTC[: ]+(.*\d.*)$/m", $receiptText);

        if (preg_match("/^(?<amount>\d[,.’‘\'\d ]*?)[ ]*(?<currency>[^\-\d)(]+)$/", $totalPrice, $matches)) {
            // 174,90 €
            $e->price()
                ->total(PriceHelper::parse($matches['amount'], $matches['currency']))
                ->currency($matches['currency'])
            ;

            $baseFare = $this->re("/(?:^[ ]*|[ ]{2})Total HT[: ]+(.*\d.*)$/m", $receiptText);

            if ( preg_match('/^(?<amount>\d[,.’‘\'\d ]*?)[ ]*(?:' . preg_quote($matches['currency'], '/') . ')?$/u', $baseFare, $m) ) {
                $e->price()->cost(PriceHelper::parse($m['amount'], $matches['currency']));
            }
        }
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function assignLang(?string $text): bool
    {
        if ( empty($text) || !isset(self::$dictionary, $this->lang) ) {
            return false;
        }
        foreach (self::$dictionary as $lang => $phrases) {
            if ( !is_string($lang) || empty($phrases['PERSONAL TICKET']) || empty($phrases['Conditions of use']) ) {
                continue;
            }
            if ($this->strposArray($text, $phrases['PERSONAL TICKET']) !== false
                && $this->strposArray($text, $phrases['Conditions of use']) !== false
            ) {
                $this->lang = $lang;
                return true;
            }
        }
        return false;
    }

    private function strposArray(?string $text, $phrases, bool $reversed = false)
    {
        if (empty($text)) {
            return false;
        }

        foreach ((array) $phrases as $phrase) {
            if (!is_string($phrase)) {
                continue;
            }
            $result = $reversed ? strrpos($text, $phrase) : strpos($text, $phrase);

            if ($result !== false) {
                return $result;
            }
        }

        return false;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function tAll(string $s): array
    {
        $result = [];

        foreach (self::$dictionary as $phrases) {
            if ( !is_array($phrases) || empty($phrases[$s]) ) {
                $result[] = $s;

                continue;
            }

            $result = array_merge($result, (array) $phrases[$s]);
        }

        return array_filter(array_unique($result));
    }

    private function normalizeDate(string $str): string
    {
        $in = [
            "#^(\d{2})\/(\d{2})\/(\d{2})$#", //01/01/25
            "#^(\d{2})\/(\d{2})\/(\d{4})$#" //01/01/2025
        ];
        $out = [
            '$1.$2.20$3',
            '$1.$2.$3'
        ];
        $str = preg_replace($in, $out, $str);

        return $str;
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
                return str_replace(' ', '\s+', preg_quote($s, '/'));
            }, $field)) . ')';
    }
}
