var plugin = {

    hosts: {
        'myrewards.stationcasinos.com': true
    },

    cashbackLink : '',

    startFromCashback : function(params) {
        browserAPI.log('startFromCashback');
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    getStartingUrl: function (params) {
        return 'https://myrewards.stationcasinos.com/accountmanagement/myrewards';
    },

    start: function (params) {
        browserAPI.log("start");
        let counter = 0;
        let start = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            let isLoggedIn = plugin.isLoggedIn(params);

            if (isLoggedIn !== null) {
                clearInterval(start);
                if (isLoggedIn) {
                    setTimeout(() => {
                        if (plugin.isSameAccount(params.account))
                            plugin.loginComplete(params);
                        else
                            plugin.logout(params);
                    }, 700);
                } else
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

    isLoggedIn: function (params) {
        browserAPI.log("isLoggedIn");

        if ($('img[data-testid="homebar_profile_logo"]').length) {
            browserAPI.log("LoggedIn");
            $('img[data-testid="homebar_profile_logo"]').get(0).click();
            return true;
        }

        let authorizationFrame = document.querySelector('iframe[title="sso"]');
        if (!authorizationFrame) {
            browserAPI.log("authorization frame not found");
            return null;
        }
        plugin.document = authorizationFrame.contentDocument;

        let progressbar = plugin.document.querySelector('span[role="progressbar"]');
        if (progressbar !== null && authorizationFrame.contentWindow.getComputedStyle(progressbar.parentElement).visibility !== 'hidden') {
            browserAPI.log("page in auth frame is loading");
            return null;
        }

        if (plugin.document.querySelector('input[data-testid="email-input"]') !== null) {
            browserAPI.log("not LoggedIn");
            return false;
        }

        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        let number = util.findRegExp($('label[data-testid="dataTestId_subTitle"]').text(), /Boarding Pass #(\d+)/i);
        browserAPI.log("number: " + number);
        return ((typeof (account.properties) != 'undefined')
                && (typeof (account.properties.AccountNumber) != 'undefined')
                && (account.properties.AccountNumber !== '')
                && number
                && (number === account.properties.AccountNumber));
    },

    logout: function (params) {
        browserAPI.log("logout");
        setTimeout(() => $('label:contains("Log Out")').get(0).click(), 1000);
        setTimeout(() => $('label:contains("Log Out")').get(0).click(), 2000);
        setTimeout(() => $('label:contains("Log Out")').get(0).click(), 3000);
        provider.setNextStep('start', function () {
            $('label:contains("Log Out")').get(0).click();
        });
    },

    login: function (params) {
        browserAPI.log("login");

        let input = plugin.document.querySelector('input[data-testid="email-input"]');
        if (input === null) {
            provider.setError(util.errorMessages.loginFormNotFound);
            return;
        }

        browserAPI.log("submitting email");
        input.dispatchEvent(new Event('focus', { bubbles: true }));
        input.dispatchEvent(new Event('click', { bubbles: true }));
        input.dispatchEvent(new KeyboardEvent('keydown',{'key':'a'}));
        input.dispatchEvent(new KeyboardEvent('keypress',{'key':'a'}));
        let nativeInputValueSetter = Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, 'value').set;
        nativeInputValueSetter.call(input, params.account.login);
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.dispatchEvent(new Event('change', { bubbles: true }));
        input.dispatchEvent(new Event('blur', { bubbles: true }));

        setTimeout(() => plugin.document.querySelector('button[data-testid="email-continue-btn"]').click(), 50);
        
        setTimeout(() => {
            let authorizationFrame = document.querySelector('iframe[title="sso"]');
            if (!authorizationFrame) {
                provider.setError(util.errorMessages.loginFormNotFound);
                return;
            }
            plugin.document = authorizationFrame.contentDocument;

            let error = plugin.document.querySelector('label[data-testid="enter-valid-email-content"]');
            if (error) plugin.checkLoginErrors(params);

            let input = plugin.document.querySelector('input[data-testid="password-input"]');
            if (input === null) {
                provider.setError(util.errorMessages.loginFormNotFound);
                return;
            }

            browserAPI.log("submitting password");
            input.dispatchEvent(new Event('focus', { bubbles: true }));
            input.dispatchEvent(new Event('click', { bubbles: true }));
            input.dispatchEvent(new KeyboardEvent('keydown',{'key':'a'}));
            input.dispatchEvent(new KeyboardEvent('keypress',{'key':'a'}));
            let nativeInputValueSetter = Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, 'value').set;
            nativeInputValueSetter.call(input, params.account.password);
            input.dispatchEvent(new Event('input', { bubbles: true }));
            input.dispatchEvent(new Event('change', { bubbles: true }));
            input.dispatchEvent(new Event('blur', { bubbles: true }));

            provider.setNextStep('checkLoginErrors', function () {
                setTimeout(() => plugin.document.querySelector('button[data-testid="password-continue-btn"]').click(), 50);
                setTimeout(() => plugin.checkLoginErrors(), 8000);
            });
        }, 2000);
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        let authorizationFrame = document.querySelector('iframe[title="sso"]');
        let errors = [];
        if (authorizationFrame) {
            errors = $(authorizationFrame.contentDocument.querySelector('label[data-testid="enter-valid-email-content"]'));
        }

        if (errors.length > 0 && util.filter(errors.text()) !== '') {
            provider.setError(errors.text());
            return;
        }

        plugin.loginComplete(params);
    },

    loginComplete: function (params) {
        browserAPI.log("loginComplete");

        provider.complete();
    },

};