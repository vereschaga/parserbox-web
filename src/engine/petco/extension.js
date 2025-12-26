var plugin = {

    hosts: {'www.petco.com': true, 'secure.petco.com': true},

    cashbackLink : '', // Dynamically filled by extension controller
    startFromCashback : function(params) {
        browserAPI.log('startFromCashback');
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    getStartingUrl: function (params) {
        return 'https://www.petco.com/shop/LogonForm?storeId=10151&catalogId=10051&langId=-1&myAcctMain=1';
    },

    loadLoginForm: function (params) {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
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
                        plugin.loginComplete(params);
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
        }, 500);
    },

    isLoggedIn: function () {
        browserAPI.log("isLoggedIn");
        if ($('a#signOutLinkButton:visible').length > 0 || $('strong:contains("Pals Rewards #"):visible').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        if ($('form#Logon').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        const number = util.findRegExp( $('strong:contains("Pals Rewards #")').text(), /\#\s*([\d]+)/i);
        browserAPI.log("number: " + number);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.Account) != 'undefined')
            && (account.properties.Account != '')
            && (number == account.properties.Account));
    },

    logout: function (params) {
        browserAPI.log("logout");
        provider.setNextStep("loadLoginForm", function () {
            $('a#signOutLinkButton').get(0).click();
        });
    },

    login: function (params) {
        browserAPI.log("login");
        var form = $('form#Logon');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            form.find('input[name = "logonId"]').val(params.account.login);
            form.find('input[name = "logonPassword"]').val(params.account.password);
            provider.setNextStep('checkLoginErrors', function () {
                setTimeout(function() {
                    var captcha = util.findRegExp( form.find('iframe[src *= "https://www.google.com/recaptcha/api2/anchor"]').attr('src'), /k=([^&]+)/i);
                    if (captcha && captcha.length > 0) {
                        browserAPI.log("waiting...");
                        provider.reCaptchaMessage();
                        var counter = 0;
                        var login = setInterval(function () {
                            browserAPI.log("waiting... " + counter);
                            var error = $('div.has-error > span:visible:contains("Please select the captcha.")');
                            if (counter > 40) {
                                clearInterval(login);
                                provider.setError(util.errorMessages.captchaErrorMessage, true);
                            }// if (counter > 80)
                            counter++;
                        }, 500);
                    }// if (captcha.length > 0)
                    else {
                        browserAPI.log("captcha is not found");
                        form.find('#WC_AccountDisplay_links_2')[0].click();
                    }
                }, 2000)
            });
        }
        else
            provider.setError(util.errorMessages.loginFormNotFound);
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        setTimeout(function () {
            var errors = $('#logonIdError:visible');
            if (errors.length > 0 && util.filter(errors.text()) != '')
                provider.setError(errors.text());
            else
                plugin.loginComplete(params);
        }, 2000);
    },

    loginComplete: function (params) {
        browserAPI.log("loginComplete");
        provider.complete();
    }

}