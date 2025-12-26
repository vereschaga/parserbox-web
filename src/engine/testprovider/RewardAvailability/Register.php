<?php

namespace AwardWallet\Engine\testprovider\RewardAvailability;

use AwardWallet\Engine\testprovider\Success;

class Register extends Success
{
    private const SUCCESS_NOT_CONFIRMED = 'Congratulations! You account number is "%s". Go to email and confirm it';
    private const SUCCESS_CONFIRMED = '{"status":"success","login":"%s","message":"Registered success and email is confirmed"}';
    const MIN_PASSWORD_LENGTH = 12; // for Register

    public function registerAccount(array $fields)
    {
        $this->logger->notice(__METHOD__);
        $this->checkFields($fields);

        if ($fields['FirstName'] === 'Success') {
            $this->http->Log('Got Register number.');
            $this->ErrorMessage = json_encode(json_decode(
                sprintf(self::SUCCESS_CONFIRMED, $fields['FirstName'])
            ), JSON_PRETTY_PRINT);

            return true;
        }

        if ($fields['FirstName'] === 'Question') {
            $this->AskQuestion('link', '', 'Question');

            return false;
        }

        if ($fields['FirstName'] === 'Timeout') {
            throw new \CheckRetryNeededException();
        }

        return false;
    }

    public function ProcessStep($step)
    {
        if ($step === "Question") {
            // здесь логика работы с $this->Answers и ответ согласно отработки
            if (isset($this->Answers['link'])) {
                $this->http->Log('Got Register number.');
                $this->ErrorMessage = json_encode(json_decode(
                    sprintf(self::SUCCESS_CONFIRMED, $this->TransferFields['FirstName'])
                ), JSON_PRETTY_PRINT);

                return true;
            }
            $this->http->Log('Got Register number.');
            $this->ErrorMessage = sprintf(self::SUCCESS_NOT_CONFIRMED, $this->TransferFields['FirstName']);

            return true;
        }

        return false;
    }

    public function getRegisterFields()
    {
        return [
            "FirstName" => [
                "Type"     => "string",
                "Caption"  => "First Name",
                "Required" => true,
            ],
            "LastName" => [
                "Type"     => "string",
                "Caption"  => "Last Name",
                "Required" => true,
            ],
            "Address" => [
                "Type"     => "string",
                "Caption"  => "Address Line 1",
                "Required" => true,
            ],
            "City" => [
                "Type"     => "string",
                "Caption"  => "City",
                "Required" => true,
            ],
            "Email" => [
                "Type"     => "string",
                "Caption"  => "Email address",
                "Required" => true,
            ],
            "Password" => [
                "Type"     => "string",
                "Caption"  => "Password. recommend: contain eight or more characters, including one lower case letter, one upper case letter, and one number or character such $ ! # & @ ? % + _",
                "Required" => true,
            ],
        ];
    }

    protected function checkFields(&$fields)
    {
        if (!preg_match("#[A-Za-z]#", $fields['FirstName'])) {
            throw new \UserInputError('FirstName can include alphanumeric characters (no spaces).');
        }

        if (!preg_match("#[A-Za-z]#", $fields['Password']) || !preg_match("#\d#", $fields['Password']) || strpos($fields['Password'], ' ') !== false) {
            throw new \UserInputError('Passwords include at least one letter and one number (no spaces).');
        }
    }
}
