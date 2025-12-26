<?php

namespace AwardWallet\Engine\rzd\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            'no-reply@rzd-bonus.ru',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "#Завершение регистрации в Программе лояльности «РЖД Бонус»#i",
            "#Тариф \"Планируй заранее\"#i",
            "#Выписка по счету#i",
        ];
    }

    public function getParsedFields()
    {
        return [
            "Login",
            "Name",
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $html = $this->http->Response['body'];
        $text = text($html);

        $result["Login"] = orval(
            re("#в Личный кабинет[:\s]+([^\s]+)#i", $text),
            $parser->getCleanTo()
        );
        $result["Name"] = re("#Уважаем(?:ый|ая)\s+([^\n,:!.]+)#i", $text);

        return $result;
    }
}
