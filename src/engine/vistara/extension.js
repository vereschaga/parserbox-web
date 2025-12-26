var plugin = {

    hosts : {
        'airvistara.com'     : true,
        'www.airvistara.com' : true
    },

    getStartingUrl : function (params) {
        return 'https://www.airvistara.com/trip/my-account';
    },

    openLoginForm: function (params) {
        var loginButton = $('#login-button');
        if (loginButton.length)
            loginButton.click();
        plugin.start(params)
    },

    start: function (params) {
        browserAPI.log("start");
        var counter = 0;
        var start = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            var isLoggedIn = plugin.isLoggedIn();
            if (isLoggedIn !== null) {
                clearInterval(start);
                if (isLoggedIn) {
                    if (plugin.isSameAccount(params.account))
                        provider.complete();
                    else
                        plugin.logout(params);
                }
                else
                    plugin.login(params);
            }// if (isLoggedIn !== null)
            if (isLoggedIn === null && counter > 10) {
                clearInterval(start);
                provider.setError(util.errorMessages.unknownLoginState);
                return;
            }// if (isLoggedIn === null && counter > 10)
            counter++;
        }, 1000);
    },

    isLoggedIn : function () {
        browserAPI.log('isLoggedIn');
        if ($('.sign-in.modal-content:visible').length) {
            browserAPI.log('isLoggedIn: false');
            return false;
        }
        if (util.trim($('#cardimageid .cardNumber').text()) !== '') {
            browserAPI.log('isLoggedIn: true');
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        // for debug only
        //browserAPI.log("account: " + JSON.stringify(account));
        //var number = $('.cardNumber:contains("'+ account.properties.CardNumber + '")').length;
        //browserAPI.log("number: " + number);
        return typeof account.properties !== 'undefined'
            && typeof account.properties.CardNumber !== 'undefined'
            && account.properties.CardNumber !== ''
            && $('.cardNumber:contains("' + account.properties.CardNumber + '")').length;
    },

    logout: function () {
        browserAPI.log('logout');
        provider.setNextStep('openLoginForm', function () {
            $('#flyer-info .userHdrName').click();
            $('#account-signout').get(0).click();
        });
    },

    login : function (params) {
        browserAPI.log('login');
        //$('#login-button').click();
        setTimeout(function() {
            const form = $('#sign-in-Club-Vistara, div.login-section form');
            if (form.length) {
                $('#sign-in-FFid, #flyerid').val(params.account.login);
                $('#sign-in-pin, #password').val(params.account.password);

                provider.setNextStep('checkLoginErrors', function () {
                    $('#login-btn-md').click();
                    setTimeout(function () {
                        plugin.checkLoginErrors(params);
                    }, 500);
                });
            } else {
                provider.setError(util.errorMessages.loginFormNotFound);
            }
        }, 1000);
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");

        if ($('h4:contains("CREATE STRONG PASSWORD")').length) {
            provider.setError(['As part of our Account Security enhancement, we request you to set up a new password in place of the 4 digit PIN.', util.errorCodes.providerError], true);
            return;
        }

        var errors = $('div#dynamicErrorLogin');
        if (errors.length > 0 && util.trim(errors.text()) !== '')
            provider.setError(errors.text());
        else
            provider.complete();
    },

    checkLogged : function (attempt, params) {
        if (attempt < 5) {
            setTimeout(function () {
                var $isUser = $('#diplayUserId');
                if ($isUser.length && '' != $isUser.text()) {
                    $('button.close', '#myModal').click();
                    var cookies = document.cookie.split(';');
                    var isSame = false;
                    for (var i in cookies) {
                        if (-1 !== cookies[i].indexOf('flyerid=') && cookies[i].substr(9) == params.account.properties.CardNumber) {
                            isSame = true;
                        }
                    }
                    isSame ? provider.complete() : provider.setError(util.errorMessages.unknownLoginState); // plugin.logout(); // loop redirect
                    return;
                }

                var $errors = $('.modal-body h4', '#modal-processed-message');
                var errorText = $errors.length ? $errors.text().trim() : null;
                if ('USER DOES NOT EXIST' == errorText
                    || 'VALIDATION ERROR' == errorText
                    || 'AUTHENTICATION FAILED' == errorText) {
                    provider.setError($errors.text());
                    return;
                }

                plugin.checkLogged(attempt, params);

            }, 500);

        } else {
            provider.setError(util.errorMessages.unknownLoginState);
        }
    }
};