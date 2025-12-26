<?php

namespace AwardWallet\ExtensionWorker;

class Message
{

    public const MESSAGE_RECAPTCHA = 'Message from AwardWallet: In order to log in into this account, you need to solve the CAPTCHA below and click the sign in button. Once logged in, sit back and relax, we will do the rest.';
    public const MESSAGE_IDENTIFY_COMPUTER = 'Message from AwardWallet: It seems that %DISPLAY_NAME% needs to identify this computer before you can update this account.';

    public static function identifyComputer(string $buttonName = 'Verify') : string
    {
        return str_replace('%BUTTON_NAME%', $buttonName, 'Please enter the received one-time code and click the "%BUTTON_NAME%" button to continue.');
    }

    public static function identifyComputerSelect(string $buttonName = 'Verify') : string
    {
        return str_replace('%BUTTON_NAME%', $buttonName, 'It seems that %DISPLAY_NAME% needs to identify this computer before you can update this account. Please choose the authentication method and click the "%BUTTON_NAME%" button to proceed.');
    }

    public static function captcha(string $buttonName = 'Sign In') : string
    {
        return str_replace('%BUTTON_NAME%', $buttonName, 'In order to log in into this account, you need to solve the CAPTCHA below and click the "%BUTTON_NAME%" button.');
    }

}