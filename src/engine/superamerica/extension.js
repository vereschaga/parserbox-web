var plugin = {

    hosts: {'superamerica.com': true, 'www.superamerica.com': true, 'superamerica.encryptedrequest.com': true},

    getStartingUrl: function (params) {
        return 'https://superamerica.encryptedrequest.com/LoyaltyLogin';
    },

    start: function (params) {
        browserAPI.log("start");
        if (plugin.isLoggedIn()) {
            if (plugin.isSameAccount(params.account))
                provider.complete();
            else
                plugin.logout();
        }
        else
            plugin.loginViaCaptcha(params);
            // plugin.login(params);
    },

    isLoggedIn: function () {
        browserAPI.log("isLoggedIn");
        if ($('#ContentPlaceHolder1_MainContent_LoginControl_memberNumberTextInput').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('#recaptcha_challenge_image').length > 0) {
            browserAPI.log("not LoggedIn, captcha");
            return false;
        }
        if ($("a[href *= Logout]").length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        browserAPI.log("Can't determine login state");
        provider.setError(["Can't determine login state", util.errorCodes.providerError]);
        throw "Can't determine login state";
    },

    isSameAccount: function (account) {
        // for debug only
        //browserAPI.log("account: " + JSON.stringify(account));
        var cardNumber = $('#kickBackHeaderLoggedInAsSpan').text();
        browserAPI.log("name: " + cardNumber);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.CardNumber) != 'undefined')
            && (account.properties.CardNumber != '')
            && (cardNumber == account.properties.CardNumber));
    },

    logout: function () {
        browserAPI.log("logout");
        document.location.href = 'https://superamerica.encryptedrequest.com/LoyaltyLogout';
    },

    loginViaCaptcha: function (params) {
        browserAPI.log("loginViaCaptcha");
        setTimeout(function() {
            var captcha = $('#recaptcha_challenge_image');
            var form = $('#form1');
            if (captcha.length > 0) {
                provider.captchaMessageDesktop();
                browserAPI.log("waiting...");

                browserAPI.send("awardwallet", "recognizeCaptcha", {
                   captcha: captcha.attr('src'),
                   "extension": "jpg"
                }, function (response) {
                    browserAPI.log(JSON.stringify(response));
                    if (response.success === true) {
                        browserAPI.log("Success: " + response.success);
                        form.find('input[name = "recaptcha_response_field"]').val(response.recognized);
                        provider.setNextStep('login', function () {
                            form.find('input[name = "ctl00$ctl00$ContentPlaceHolder1$MainContent$LoginControl$LoginRecaptchaControl$submitButton"]').click();
                        });
                    }// if (response.success === true))
                    if (response.success === false) {
                        browserAPI.log("Success: " + response.success);
                        provider.setError(util.errorMessages.captchaErrorMessage, true);
                    }// if (response.success === false)
                });
            }
            else {
                browserAPI.log('captcha not found');
                plugin.login(params);
            }
        }, 3000);
    },

    login: function (params) {
        browserAPI.log("login");
        var memberNumber = $('#ContentPlaceHolder1_MainContent_LoginControl_memberNumberTextInput');
        if (memberNumber.length > 0) {
            browserAPI.log("submitting saved credentials");
            memberNumber.val(params.account.login);
            $('#ContentPlaceHolder1_MainContent_LoginControl_pinTextInput').val(params.account.password);
            provider.setNextStep('checkLoginErrors');
            setTimeout(function() {
                $('#ContentPlaceHolder1_MainContent_LoginControl_submitButton').trigger('click');
                $('#ContentPlaceHolder1_MainContent_LoginControl_secondPasswordTextInput').val(params.account.login2);
                // waiting popup with lastname
                setTimeout(function() {
                    browserAPI.log("submitting saved Last Name");
                    $('#ContentPlaceHolder1_MainContent_LoginControl_loginWithSecondPasswordButton').get(0).click();
                    setTimeout(function () {
                        plugin.checkLoginErrors()
                    }, 5000);
                }, 1000)

            }, 1000);
        }
        else {
            browserAPI.log('Login form not found');
            provider.setError(['Login form not found', util.errorCodes.providerError]);
            throw 'Login form not found';
        }
    },

    checkLoginErrors: function () {
        var error = $('#ContentPlaceHolder1_MainContent_LoginControl_messageSpan');
        if (error.length > 0)
            provider.setError(error.text());
        else
            provider.complete();
    }
}