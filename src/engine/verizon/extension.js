var plugin = {

    hosts : {
        'verizonwireless.com'          : true,
        'www.verizonwireless.com'      : true,
        'login.verizonwireless.com'    : true,
        'nbillpay.verizonwireless.com' : true
    },

    getStartingUrl : function (params) {
        return 'https://login.verizonwireless.com/amserver/UI/Login?userNameOnly=false&amp;mode=i&amp;realm=vzw';
    },

    cashbackLink: '', // Dynamically filled by extension controller
    startFromCashback : function (params) {
        browserAPI.log('startFromCashback');
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    loadLoginForm: function (params) {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    start : function (params) {
        browserAPI.log("start");
        var counter = 0;
        var start = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            var isLoggedIn = plugin.isLoggedIn(params.account);
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
        }, 1000);
    },

    isLoggedIn : function (params) {
        browserAPI.log('isLoggedIn');
        if ($('a[href*="SignOut"]').length) {
            browserAPI.log('isLoggedIn: true');
            return true;
        }
        if ($('#vgn_signIn').length) {
            browserAPI.log('isLoggedIn: false');
            return false;
        }

        var form = $('#login-form');
        if (form.length) {
            browserAPI.log('isLoggedIn: false');
            return false;
        }

        return null;
    },

    isSameAccount : function (account) {
        browserAPI.log('isSameAccount');
        return ('undefined' != typeof account.properties
            && 'undefined' != typeof account.properties.AccountNumber
            && '' != account.properties.AccountNumber
            && $('div:contains("' + account.properties.AccountNumber + '")').length);
    },

    logout : function (params) {
        browserAPI.log('logout');
        provider.setNextStep('startFromCashback', function () {
            var signout = $('a[href*="SignOut"]');
            if (signout.length)
                signout.get(0).click();
            else
                document.location.href = 'https://nbillpay.verizonwireless.com/myv/nda/amLogoutRedirect.jsp';
        });
    },

    login: function (params) {
        browserAPI.log('login');
        var form = $('#login-form');
        if (form.length) {
            browserAPI.log("submitting saved credentials");
            form.find('#IDToken1').val(params.account.login);
            form.find('#IDToken2').val(params.account.password);
            provider.setNextStep('checkLoginErrors', function () {
                form.find('button[type="submit"]').get(0).click();
                setTimeout(function() {
                    plugin.checkLoginErrors(params);
                }, 7000)
            });
        } else
            provider.setError(util.errorMessages.loginFormNotFound);
    },

    checkLoginErrors: function (params) {
        browserAPI.log('checkLoginErrors');
        var error = $('div:contains("you entered does not match")');
        if (error.length)
            provider.setError('The information you entered does not match the information we have on file.');
        else {
            if (provider.isMobile)
                plugin.findSecurityQuestion(params);
            else
                plugin.loginComplete(params);
        }
    },

    findSecurityQuestion: function (params) {
        browserAPI.log("findSecurityQuestion");
        // for debug only
        //if ((typeof(params) != 'undefined'))
        //    browserAPI.log("answers: " + JSON.stringify(params));
        var form = $('form[id = "challengequestion"]');
        if (form.length > 0) {
            var question = form.find('label[for = "IDToken1"]:visible');
            if (question.length > 0) {
                browserAPI.log("question: " + question.text());
                if ((typeof(params.account.answers) !== 'undefined')
                    && (typeof(params.account.answers[question.text()]) !== 'undefined')) {
                    var answer = params.account.answers[question.text()];
                    browserAPI.log("answer: " + answer);
                    form.find('#IDToken1').val(answer);
                    // form.find('button[type="submit"]').get(0).click();
                }
                provider.setNextStep('loginComplete', function () {
                    setTimeout(function() {
                        var error = $('div:contains("you entered does not match")');
                        if (error.length)
                            provider.setError('The information you entered does not match the information we have on file.');
                        else
                            plugin.loginComplete(params);
                    }, 7000)
                });
            }
            else {
                browserAPI.log("Security Questions are not found");
                plugin.loginComplete(params);
            }
        }
        else {
            browserAPI.log("Security Question form not found");
            plugin.loginComplete(params);
        }
    },

    loginComplete: function (params) {
        browserAPI.log("loginComplete");
        provider.complete();
    }

};
