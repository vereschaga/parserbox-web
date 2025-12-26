var plugin = {

    hosts: {
        'mileageplan.rewardsnetwork.com': true,
        'aa.rewardsnetwork.com': true,
        'login.aa.com': true,
        'www.aadvantagedining.com': true,
        'skymiles.rewardsnetwork.com': true,
        'priorityclub.rewardsnetwork.com': true,
        'www.rapidrewardsdining.com': true,
        'www.rewardzonedining.com': true,
        'mpdining.rewardsnetwork.com': true,
        'usairways.rewardsnetwork.com': true,
        'www.hiltonhonorsdining.com': true,
        'www.united.com': true,
        'dining.mileageplus.com': true,
        'www.clubodining.com': true,
        'dining.fuelrewards.com': true,
        'neighborhoodnoshrewards.com': true,
        'www.orbitzrewardsdining.com': true,
        'www.freespiritdining.com': true,
        'truebluedining.com': true,
        'skymilesdining.com': true,
        'eataroundtown.marriott.com': true,
        'auth.marriott.com': true,
        'ihgrewardsdineandearn.rewardsnetwork.com': true,
    },

    newSite: function () {
        return [
            'https://truebluedining.com/',
            'https://skymilesdining.com/',
            'https://skymiles.rewardsnetwork.com/',
            'https://aa.rewardsnetwork.com/',
            'https://www.aadvantagedining.com/',
            'https://www.rapidrewardsdining.com/',
            'https://www.freespiritdining.com/',
            'https://mileageplan.rewardsnetwork.com/',
            'https://www.hhonorsdining.com/',
            'https://www.hiltonhonorsdining.com/',
            'https://ihgrewardsclubdining.rewardsnetwork.com/',
            'https://ihgrewardsdineandearn.rewardsnetwork.com/',
            'https://neighborhoodnoshrewards.com/',
        ];
    },

    getStartingUrl: function (params) {
        if (params.account.login2 === 'https://www.hhonorsdining.com/') {
            params.account.login2 = 'https://www.hiltonhonorsdining.com/';
        }
        if (params.account.login2 === 'https://mpdining.rewardsnetwork.com/') {
            params.account.login2 = 'https://dining.mileageplus.com/'
        }

        if (params.account.login2 === 'https://priorityclub.rewardsnetwork.com/') {
            params.account.login2 = 'https://ihgrewardsclubdining.rewardsnetwork.com/';
        }
        if (params.account.login2 === 'https://www.idine.com/') {
            params.account.login2 = 'https://neighborhoodnoshrewards.com/';
        }

        if (plugin.newSite().indexOf(params.account.login2) !== -1) {
            if (params.account.login2 === 'https://skymiles.rewardsnetwork.com/')
                params.account.login2 = 'https://skymilesdining.com/';
            if (params.account.login2 === 'https://aa.rewardsnetwork.com/')
                params.account.login2 = 'https://www.aadvantagedining.com/';

            return params.account.login2 + 'login';
        }

        if (params.account.login2 === 'https://eataroundtown.marriott.com/')
            return params.account.login2 + 'Login';
        if (params.account.login2 === 'https://dining.mileageplus.com/')
            return params.account.login2 + 'Login';
        if (params.account.login2 === 'https://www.aadvantagedining.com/')
            return params.account.login2 + 'Login';

        var url = params.account.login2.replace('http:', 'https:') + 'myaccount/rewards.htm';
        browserAPI.log("login2 => " + params.account.login2);
        browserAPI.log("url => " + url);

        return url;
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
        }, 500);
    },

    isLoggedIn: function (params) {
        browserAPI.log("isLoggedIn");
        // MileagePlus
        if ($('input[name = "ctl00$ContentInfo$btnLogin"]').length > 0){
            browserAPI.log("not LoggedIn");
            return false;
        }
        // JetBlue / Delta
        if ($('form[name = "Login Form"]').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        // AA
        if ($('form#loginFormId:visible').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('form[name = "Sign In"]').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        // JetBlue / Delta / Marriott / Southwest
        if ($("a[href *= 'SignOut']").length > 0 && $("a[href*=SignOut]").attr('href') === '/SignOut') {
            browserAPI.log("LoggedIn");
            return true;
        }
        if ($("h1:contains('Account center'):visible").length) {
            browserAPI.log("LoggedIn");
            return true;
        }
        // Other regions
        if ($('a:contains("Logout")').text() || $("a[href*=logout]").attr('href') === '/logout.htm') {
            browserAPI.log("LoggedIn");
            return true;
        }
        // Marriott
        if ($('form[action = "/login"]:visible').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        // United
        if ($('div[class *= "LoginForm_loginForm"]:visible').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        // deprecated?
        if (
            params.account.login2 !== 'https://mpdining.rewardsnetwork.com/'
            && params.account.login2 !== 'https://dining.mileageplus.com/'
            && $('#lbcid').length
            && $('#loginform').length === 0
            && $.inArray(params.account.login2, plugin.newSite()) === -1
        ) {
            browserAPI.log("go to login page");
            window.location.href = params.account.login2.replace('http:', 'https:')+ '/login.htm';
        }
        if ($('#loginform').length > 0 || $('div.loginButtonClosed').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        return null;
    },

    isSameAccount: function (account) {
        var number;
        if ($.inArray(account.login2, plugin.newSite()) !== -1) {
            number = util.filter($('div.partner-program-number > span').text());

            if (account.login2 == 'https://www.aadvantagedining.com/') {
                var email = $('user-menu div.email').text();
                browserAPI.log("email: " + email);
                return email == account.login;
            }
        }
        else
            number = util.findRegExp($('#asExtra').html(), /#:<\/b>\s*([\d\w]+)/i);
        browserAPI.log("number: " + number);

        if (!number) {
            let name = util.filter($('div.userNameDisplay:visible').text());
            browserAPI.log("name: " + name);
            return ((typeof(account.properties) != 'undefined')
                    && (typeof(account.properties.Name) != 'undefined')
                    && (account.properties.Name !== '')
                    && name
                    && (name.toLowerCase() === account.properties.Name.toLowerCase()));
        }

        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.Number) != 'undefined')
            && (account.properties.Number !== '')
            && number
            && (number === account.properties.Number));
    },

    logout: function(params){
        browserAPI.log("logout");
        provider.setNextStep('LoadLoginForm', function () {//todo
            if ($.inArray(params.account.login2, plugin.newSite()) !== -1)
                document.location.href = params.account.login2 + 'SignOut';
            else if (
                params.account.login2 === 'https://mpdining.rewardsnetwork.com/'
                || params.account.login2 === 'https://dining.mileageplus.com/'
            ) {
                document.location.href = params.account.login2 + 'SignOut';
            }
            else
                document.location.href = params.account.login2.replace('http:', 'https:') + '/logout.htm';
        });
    },

    LoadLoginForm: function (params) {
        browserAPI.log("LoadLoginForm");
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    login: function(params){
        browserAPI.log("login");
        var form = $('#loginform');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            form.find('input[name = "loginId"]').val(params.account.login);
            form.find('input[name = "password"]').val(params.account.password);
            provider.setNextStep('checkLoginErrors', function () {
                setTimeout(function() {
                    var captcha = form.find('div.recaptcha:visible, div.g-recaptcha:visible');
                    if (captcha.length > 0) {
                        if (!provider.isMobile) {
                            provider.reCaptchaMessage();
                            browserAPI.log("waiting...");
                            setTimeout(function() {
                                provider.setError(util.errorMessages.captchaErrorMessage, true);
                            }, 120000);
                        }else{
                            provider.command('show', function(){
                                provider.reCaptchaMessage();
                                browserAPI.log(">>> mobile");
                                browserAPI.log("waiting...");
                                var loginBtn = form.find('input[value = "Sign in"]');
                                loginBtn.bind('click.captcha', function(event){
                                    event.preventDefault();
                                    loginBtn.unbind('click');
                                    provider.command('hide', function(){
                                        browserAPI.log("captcha entered by user");
                                        provider.setNextStep('checkLoginErrors', function(){
                                            form.submit();
                                        });
                                    });
                                });
                            });
                        }
                        //form.find('#btn_submit').click();
                    }// if (captcha.length > 0)
                    else {
                        browserAPI.log("captcha is not found");
                        form.submit();
                    }
                }, 2000);
            });
        }
        // mileageplan.rewardsnetwork.com
        // ihgrewardsdineandearn.rewardsnetwork.com
        else if ((form = $('form[name = "Sign In"]')) && form.length > 0) {
            browserAPI.log("ihgrewardsdineandearn.rewardsnetwork.com");
            browserAPI.log("submitting saved credentials");
            //form.find('input[name = "email"]').val(params.account.login);
            //form.find('input[name = "password"]').val(params.account.password);

            // reactjs
            provider.eval(
                "function triggerInput(selector, enteredValue) {\n" +
                "      let input = document.querySelector(selector);\n" +
                "      input.dispatchEvent(new Event('focus'));\n" +
                "      input.dispatchEvent(new KeyboardEvent('keypress',{'key':'a'}));\n" +
                "      let nativeInputValueSetter = Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, 'value').set;\n" +
                "      nativeInputValueSetter.call(input, enteredValue);\n" +
                "      let inputEvent = new Event(\"input\", { bubbles: true });\n" +
                "      input.dispatchEvent(inputEvent);\n" +
                "}\n" +
                "triggerInput('input[name = \"email\"]', '" + params.account.login + "');\n" +
                "triggerInput('input[name = \"password\"]', '" + params.account.password + "');"
            );

            provider.setNextStep('checkLoginErrors', function () {
                setTimeout(function() {
                    var captcha = form.find('div.g-recaptcha:visible');
                    if (captcha.length > 0) {
                        if (!provider.isMobile) {
                            provider.reCaptchaMessage();
                            browserAPI.log("waiting...");
                            setTimeout(function () {
                                provider.setError(util.errorMessages.captchaErrorMessage, true);
                            }, 120000);
                        } else {
                            provider.command('show', function(){
                                provider.reCaptchaMessage();
                                browserAPI.log(">>> mobile");
                                browserAPI.log("waiting...");
                                var loginBtn = form.find('button.submit-sign-in');
                                loginBtn.bind('button[type="submit"]', function(event){
                                    event.preventDefault();
                                    loginBtn.unbind('click');
                                    provider.command('hide', function(){
                                        browserAPI.log("captcha entered by user");
                                        provider.setNextStep('checkLoginErrors', function(){
                                            form.submit();
                                        });
                                    });
                                });
                            });
                        }
                        //form.find('#btn_submit').click();
                    }// if (captcha.length > 0)
                    else {
                        browserAPI.log("captcha is not found");
                        form.find('button[type="submit"]').get(0).click();
                    }

                    setTimeout(function () {
                        plugin.checkLoginErrors(params);
                    }, 5000);
                }, 2000);
            });
        }
        // JetBlue / Delta / Southwest
        else if ((form = $('form[name = "Login Form"]')) && form.length > 0) {
            browserAPI.log("JetBlue / Delta");
            browserAPI.log("submitting saved credentials");
            form.find('input[name = "email"]').val(params.account.login);
            form.find('input[name = "password"]').val(params.account.password);
            provider.setNextStep('checkLoginErrors', function () {
                setTimeout(function() {
                    var captcha = form.find('div.g-recaptcha:visible');
                    if (captcha.length > 0) {
                        if (!provider.isMobile) {
                            provider.reCaptchaMessage();
                            browserAPI.log("waiting...");
                            setTimeout(function () {
                                provider.setError(util.errorMessages.captchaErrorMessage, true);
                            }, 120000);
                        } else {
                            provider.command('show', function(){
                                provider.reCaptchaMessage();
                                browserAPI.log(">>> mobile");
                                browserAPI.log("waiting...");
                                var loginBtn = form.find('button.submit-sign-in');
                                loginBtn.bind('click.captcha', function(event){
                                    event.preventDefault();
                                    loginBtn.unbind('click');
                                    provider.command('hide', function(){
                                        browserAPI.log("captcha entered by user");
                                        provider.setNextStep('checkLoginErrors', function(){
                                            form.submit();
                                        });
                                    });
                                });
                            });
                        }
                        //form.find('#btn_submit').click();
                    }// if (captcha.length > 0)
                    else {
                        browserAPI.log("captcha is not found");
                        form.submit();
                    }
                }, 2000);
            });
        }
        // Marriott
        else if ((form = $('form[action = "/login"]:visible')) && form.length > 0) {
            browserAPI.log("Marriott");
            browserAPI.log("submitting saved credentials");
            form.find('input[name = "username"]').val(params.account.login);
            form.find('input[name = "password"]').val(params.account.password);
            provider.setNextStep('checkLoginErrors', function () {
                form.find('button[name = "submitButton"]').click();
                setTimeout(function() {
                    plugin.checkLoginErrors(params);
                }, 10000);
            });
        }
        else if ((form = $('form#loginFormId:visible')) && form.length > 0) {
            browserAPI.log("AA");
            browserAPI.log("submitting saved credentials");
            // form.find('input[name = "username"]').val(params.account.login);
            // form.find('input[name = "password"]').val(params.account.password);

            // angularjs 10
            provider.eval(
                "function triggerInput(enteredName, enteredValue) {\n" +
                "      const input = document.querySelector(enteredName);\n" +
                "      var createEvent = function(name) {\n" +
                "            var event = document.createEvent('Event');\n" +
                "            event.initEvent(name, true, true);\n" +
                "            return event;\n" +
                "      }\n" +
                "      input.dispatchEvent(createEvent('focus'));\n" +
                "      input.value = enteredValue;\n" +
                "      input.dispatchEvent(createEvent('change'));\n" +
                "      input.dispatchEvent(createEvent('input'));\n" +
                "      input.dispatchEvent(createEvent('blur'));\n" +
                "}\n" +
                "triggerInput('input[name = \"username\"]', '" + params.account.login + "');\n" +
                "triggerInput('input[name = \"password\"]', '" + params.account.password + "');"
            );

            provider.setNextStep('checkLoginErrors', function () {
                form.find('button#button_login').click();
                setTimeout(function() {
                    plugin.checkLoginErrors(params);
                }, 10000);
            });
        }
        else {// MileagePlus
            form = $('div[class *= "LoginForm_loginForm"]');
            browserAPI.log("MileagePlus");
            if (form.length > 0) {
                browserAPI.log("submitting saved credentials");
                /*
                $('input[name = "ctl00$ContentInfo$txtUserName"]').remove();
                $('input[name = "ctl00$ContentInfo$txtPassword"]').remove();
                */
                form.find('input[name = "username"]').val(params.account.login);
                util.sendEvent(form.find('input[name = "username"]').get(0), 'input');
                setTimeout(function() {
                    form.find('input[name = "password"]').val(params.account.password);
                    util.sendEvent(form.find('input[name = "password"]').get(0), 'input');
                }, 500);

                /*
                var login = document.createElement( 'input' );
                login.type = 'text';
                login.name = 'ctl00$ContentInfo$txtUserName';
                login.id = 'ctl00_ContentInfo_txtUserName';
                login.setAttribute('class', 'tbwEligible tbw');
                login.setAttribute('size', '20');
                login.value = params.account.login;
                document.getElementById( 'onePassSignInLt' ).appendChild( login );

                var pwd = document.createElement( 'input' );
                pwd.type = 'password';
                pwd.name = 'ctl00$ContentInfo$txtPassword';
                pwd.id = 'ctl00_ContentInfo_txtPassword';
                pwd.setAttribute('class', 'tbwEligible tbw');
                pwd.setAttribute('size', '20');
                pwd.value = params.account.password;
                document.getElementById( 'onePassSignInRt' ).appendChild( pwd );
                */

                provider.setNextStep('checkLoginErrors', function () {
                    // form.find('input[name = "ctl00$ContentInfo$btnLogin"]').get(0).click();
                    setTimeout(function() {
                        form.find('button[id = "btnSubmit"]').get(0).click();
                    }, 1000);
                    setTimeout(function() {
                        plugin.checkLoginErrors(params);
                    }, 10000);
                });
            }
            else
                provider.setError(util.errorMessages.loginFormNotFound);
        }
    },

    checkLoginErrors: function () {
        browserAPI.log("checkLoginErrors");
        var errors = $("#loginErrorMsg");
        // JetBlue / Delta / Southwest
        if (errors.length == 0) {
            errors = $("dd.error:visible:visible");
        }
        // AA
        if (errors.length == 0) {
            errors = $('app-login-error span:eq(0):visible');
        }
        if (errors.length == 0 && util.filter($("div.locked-out-message:visible").text()) !== '')
            errors = $("div.locked-out-message:visible");
        // United
        if (errors.length == 0)
            errors = $("#ctl00_ContentInfo_uNameErrorMsg:visible");
        // Marriott
        if (errors.length == 0)
            errors = $("div#error-message:visible");
        // United
        if (errors.length === 0) {
            errors = $('span[class *= "LoginForm_error"]:visible:eq(0)');
        }
        if (errors.length > 0) {
            provider.setError(errors.text());
            return;
        }

        provider.complete();
    }

};
