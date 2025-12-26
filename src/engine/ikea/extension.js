var plugin = {

    hosts: {
        // almost every region
        '/.+\.accounts\.ikea\.com/': true,
        'www.ikea.com': true,
        // Singapore
        'family.ikea.com.sg': true,
    },

    getStartingUrl: function (params) {
        switch (params.account.login2) {
            case 'Singapore':
                return 'https://family.ikea.com.sg/membership/my-card';
            case 'Netherlands':
                return 'https://www.ikea.com/nl/nl/profile/';
            case 'Australia':
                return 'https://www.ikea.com/au/en/profile/';
            case 'UK':
                return 'https://www.ikea.com/gb/en/profile/';
            case 'Ireland':
                return 'https://www.ikea.com/ie/en/profile/';
            case 'Canada':
                return 'https://www.ikea.com/ca/en/profile/';
            case 'Switzerland':
                return 'https://www.ikea.com/ch/de/profile/';
            case 'Sweden':
                return 'https://www.ikea.com/se/sv/profile/';
            case 'USA':
            default:
                return 'https://www.ikea.com/us/en/profile/';
        }
    },
	
    start: function (params) {
        browserAPI.log("start");
        if (params.account.login2.length < 1) params.account.login2 = 'USA';
        browserAPI.log("region => " + params.account.login2);
        let counter = 0;
        let start = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            let isLoggedIn = plugin.isLoggedIn(params.account.login2);
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

    loadLoginForm: function (params) {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('start', function() {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    isLoggedIn: function (region) {
        browserAPI.log("isLoggedIn");
        if (region === 'Singapore') {
            if ($("button:contains('Log In'):visible").length > 0) {
                browserAPI.log("not LoggedIn");
                return false;
            }
            let num = $("#digitalcard div.text-container > p:nth-child(2)").text();
            if((typeof(num) != 'undefined')
                && (num != '')
                && num)
            {
                browserAPI.log("LoggedIn");
                return true;
            }
        }

        if ($('input#username:visible, input#password:visible').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }

        if ($('.member-card__number:visible').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }

        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        let number;
        if (account.login2 === 'Singapore')
            number = util.findRegExp($("#digitalcard div.text-container > p:nth-child(2)").text(), /\s*([\s\d]+)/i).replace(/\s+/g, '');
        else number = $('.member-card__number').text();
        browserAPI.log("number: " + number);
        return ((typeof(account.properties) != 'undefined')
                && (typeof(account.properties.Number) != 'undefined')
                && (account.properties.Number != '')
                && number
                && (number == account.properties.Number));
    },

    logout: function (params) {
        browserAPI.log("logout");
            provider.setNextStep('loadLoginForm', function () {
                $("a[href *= 'logout'], #wrapper a[href *= 'Logoff'], a:contains('Log out'), a:contains('Log Out'):visible").get(0).click();
            });
    },

    login: function (params) {
        browserAPI.log("login");
        if (params.account.login2 === 'Singapore') {
            let form = $("#login").find("form");
            if (form.length > 0) {
                browserAPI.log("submitting saved credentials");
                form.find("#membershipnuminput").val(params.account.login);
                // vue.js
                provider.eval(`
                    let email = document.querySelector('input[id="membershipnuminput"]');
                    email.dispatchEvent(new Event('input'));
                    email.dispatchEvent(new Event('change'));
                    email.dispatchEvent(new Event('keyup'));
                `);
                form.find('button#nextbutton').click();
                setTimeout(function () {
                    form.find('#password-input').val(params.account.password);
                    provider.eval(`
                        let pwd = document.querySelector('input[id="password-input"]');
                        pwd.dispatchEvent(new Event('input'));
                        pwd.dispatchEvent(new Event('change'));
                        pwd.dispatchEvent(new Event('keyup'));
                    `);
                    form.find('input[name = "rememberMe"]').prop('checked', true);
                    provider.setNextStep('checkLoginErrors', function () {
                        form.find('button#submitbutton').click();
                        setTimeout(function () {
                            plugin.checkLoginErrors(params);
                        }, 500);
                    });
                }, 1000);

            }
            else provider.setError(util.errorMessages.loginFormNotFound);
            return;
        }

        let login = document.getElementById('username');
        let pwd = document.getElementById('password');
        let btn = pwd?.form.querySelector('button[type="submit"]');
        if (!login || !pwd || !btn) {
            provider.setError(util.errorMessages.loginFormNotFound);
            return;
        }

        // reactjs
        provider.eval(`
            function triggerInput(selector, enteredValue) {
                let input = document.querySelector(selector);
                input.dispatchEvent(new Event('focus', { bubbles: true }));
                input.dispatchEvent(new Event('click', { bubbles: true }));
                input.dispatchEvent(new KeyboardEvent('keydown',{'key':'a'}));
                input.dispatchEvent(new KeyboardEvent('keypress',{'key':'a'}));
                let nativeInputValueSetter = Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, 'value').set;
                nativeInputValueSetter.call(input, enteredValue);
                input.dispatchEvent(new Event('input', { bubbles: true }));
                input.dispatchEvent(new Event('change', { bubbles: true }));
                input.dispatchEvent(new Event('blur', { bubbles: true }));
            }
            triggerInput('#username', '${params.account.login}');
            triggerInput('#password', '${params.account.password}');
        `);

        setTimeout(() => btn.click(), 200);
        provider.setNextStep('checkLoginErrors');
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        let errors = $('span.wrong:visible'); // Singapore
        if (errors.length < 1) errors = $('h1:contains("Oops! Something went wrong")');
        if (errors.length < 1) errors = $('.alert--error .alert__text:visible');

        if (errors.length > 0)
            provider.setError(errors.text());
        else
            plugin.loginComplete(params);
    },

    loginComplete: function (params) {
        browserAPI.log("loginComplete");
        provider.complete();
    }

};