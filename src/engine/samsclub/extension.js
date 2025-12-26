var plugin = {
    cashbackLinkMobile: false,

    hosts: {
        'samsclub.com': true,
        'www.samsclub.com': true
    },

    getStartingUrl: function (params) {
        return 'https://www.samsclub.com/account/summary?xid=hdr_account_membership';
    },

    start: function (params) {
        browserAPI.log("start");
        let counter = 0;
        let start = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            let isLoggedIn = plugin.isLoggedIn();
            if (isLoggedIn !== null) {
                clearInterval(start);
                if (isLoggedIn) {
                    if (plugin.isSameAccount(params.account))
                        plugin.loginComplete(params);
                    else
                        plugin.logout(params);
                } else {
                    plugin.login(params);
                }

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
        if ($('#email:visible, .sign-in:visible').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if (util.filter($('.sc-summary-details-membership-number:visible').text()) !== '') {
            browserAPI.log("LoggedIn");
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        let number = util.findRegExp($('.sc-summary-details-membership-number:visible').text(), /([\d\s]{8,})/);
        browserAPI.log("number: " + number);
        return typeof (account.properties) != 'undefined'
            && typeof (account.properties.Account) != 'undefined'
            && account.properties.Account !== ''
            && number
            && number.indexOf(account.properties.Account) !== -1;
    },

    logout: function (params) {
        browserAPI.log('logout');
        provider.setNextStep('loadLoginForm', function () {
            document.location.href = 'https://www.samsclub.com/sams/logout.jsp?signOutSuccessUrl=/?xid=hdr_account_membership';
        });
    },

    loadLoginForm: function (params) {
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    login: function (params) {
        browserAPI.log('login');
        util.waitFor({
            selector: '.sc-login-form',
            success: function (form) {
                // $('#email', form).val(params.account.login);
                // util.sendEvent($('#email', form).get(0), 'input');
                // $('#password', form).val(params.account.password);
                // util.sendEvent($('#password', form).get(0), 'input');
                // reactjs
                provider.eval(
                    "function triggerInput(enteredName, enteredValue) {\n" +
                    "  const input = document.getElementById(enteredName);\n" +
                    "  const lastValue = input.value;\n" +
                    "  input.value = enteredValue;\n" +
                    "  const event = new Event(\"input\", { bubbles: true });\n" +
                    "  const tracker = input._valueTracker;\n" +
                    "  if (tracker) {\n" +
                    "    tracker.setValue(lastValue);\n" +
                    "  }\n" +
                    "  input.dispatchEvent(event);\n" +
                    "}\n" +
                    "triggerInput('email', '" + params.account.login + "');" +
                    "triggerInput('password', '" + params.account.password + "');"
                );
                provider.setNextStep('checkLoginErrors', function () {
                    let btn = form.find('.sc-btn.sc-btn-primary');
                    btn.get(0).click();

                    setTimeout(function () {
                        plugin.checkLoginErrors(params);
                    }, 3000);
                });
            },
            fail: function () {
                provider.setError(util.errorMessages.itineraryFormNotFound);
            },
            timeout: 5
        });
    },

    checkLoginErrors: function (params) {
        browserAPI.log('checkLoginErrors');
        let error = $('.sc-alert.sc-alert-error:visible, div.sc-input-box-error-block:visible, div.bst-alert-body > span:visible');
        if (error.length && util.filter(error.text()) !== '') {
            provider.setError(error.text());
            return;
        }

        plugin.loginComplete();
    },

    loginComplete: function () {
        browserAPI.log('loginComplete');
        provider.complete();
    }

};
