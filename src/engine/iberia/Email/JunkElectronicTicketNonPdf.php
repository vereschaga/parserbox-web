<?php

namespace AwardWallet\Engine\iberia\Email;

use AwardWallet\Schema\Parser\Email\Email;

class JunkElectronicTicketNonPdf extends \TAccountChecker
{
    public $mailFiles = "iberia/it-39952885.eml";

    public $lang = 'es';
    public static $dict = [
        'es' => [],
    ];

    private $detectFrom = ["ETServer@iberia.es"];
    private $detectSubject = [
        'es' => ['Iberia Billete Electrónico - Electronic Ticket -'],
    ];

    private $detectBody = [
        'es' => ['Adjuntamos el recibo pasajero del billete electrónico', 'Este mail y el documento adjunto se generan automáticamente'],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        if (self::detectEmailByBody($parser) === true && self::detectEmailByHeaders($parser->getHeaders()) === true) {
            $email->setIsJunk(true);
        }

        return $email;
    }

    public function detectEmailByHeaders(array $headers)
    {
//        if ($this->striposAll($headers['from'] ?? ', $this->detectFrom) !== true) {
//            return false;
//        }
        foreach ($this->detectSubject as $detectSubject) {
            if ($this->striposAll($headers['subject'] ?? '', $detectSubject) === true) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $foundBody = false;
        $body = substr(strip_tags($this->http->Response['body']), 0, 500);

        foreach ($this->detectBody as $detectBody) {
            if ($this->striposAll($body, $detectBody) === true) {
                $foundBody = true;
            } else {
                $foundBody = false;

                break;
            }
        }

        if ($foundBody == false) {
            return false;
        }
        $attaches = $parser->searchAttachmentByName('.*');

        if (count($attaches) == 0) {
            return true;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return $this->striposAll($from, $this->detectFrom);
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return 0;
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
}
