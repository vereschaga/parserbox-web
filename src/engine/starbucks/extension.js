var plugin = {

    hosts: {
        '/(.*[.])?starbucks[.](com[.](tw|sg|cn|hk)|co[.](uk|jp)|com|pe|pl|de|ch|mx|in|vn|ie|ca|es)/': true,
        'www.starbuckscard.in.th': true,
        'www.starbuckscardth.in.th': true,
    },

	getStartingUrl: function(params) {
		switch (params.account.login2) {
			case "UK":
				return "https://www.starbucks.co.uk/account/login";
            case "China":
                return "https://www.starbucks.com.cn/en/log-in";
            case "HongKong":
                return "https://www.starbucks.com.hk/en/customer/account/login/";
            case "India":
                return "https://www.starbucks.in/login";
            case "Ireland":
                return "https://www.starbucks.ie/account/login";
            case "Japan":
                return "https://www.starbucks.co.jp/mystarbucks/?mode=mb_001";
            case "Mexico":
                return "https://rewards.starbucks.mx/";
            case "Peru":
                return "https://www.starbucks.pe/rewards/rewards";
            case "Poland":
                return "https://card.starbucks.pl/j_spring_security_logout?language=en";
			case "Germany":
				return "https://www.starbucks.de/account/login";
            case "Spain":
                return "https://www.starbucks.es/account/login";
            case "Singapore":
                return "https://www.starbucks.com.sg/rewards/Login/?ReturnUrl=%2Frewards";
			case "Switzerland":
				return "https://www.starbucks.ch/en/account/login";
            case "Taiwan":
				return "https://myotgcard.starbucks.com.tw/StarbucksMemberWebsite/SignIn.html";
            case "Thailand":
				return "https://www.starbuckscardth.in.th/Profile/";
            case 'Canada':
                return "https://www.starbucks.ca/account/signin";
            case 'Vietnam':
                return "https://card.starbucks.vn/en/Account/Login";
			default:
                return "https://www.starbucks.com/account/signin";
				// return "https://app.starbucks.com/account/rewards";  // for parsing debug
		}
	},

    changeLocation: function(step, url) {
        browserAPI.log("changeLocation");
        provider.setNextStep(step, function () {
            document.location.href = url;
        });
    },

    loadLoginForm: function(params) {
        browserAPI.log("loadLoginForm");
		provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
	},

	start: function(params) {
        browserAPI.log("start");
        /*
        if (params.account.login2 == 'USA') { // for parsing debug
            plugin.parse(params);
            return;
        }
        */
        if (params.account.login2 == 'China') {
            let counter = 0;
            const start = setInterval(function () {
                browserAPI.log("waiting... " + counter);
                const logout = $('a:contains("Log out")');
                if (logout.length > 0) {
                    clearInterval(start);
                    provider.setNextStep('chinaLogin', function () {
                        logout.get(0).click();
                        setTimeout(function () {
                            plugin.chinaLogin(params);
                        }, 7000);
                    });
                }
                if ($('div.login-form:visible').find('input[name = "username"]').length > 0 || counter > 80) {
                    clearInterval(start);
                    setTimeout(function () {
                        plugin.login(params);
                    }, 3000);
                }
                counter++;
            }, 500);
            return;
        }
        if (params.account.login2 == 'HongKong') {
            plugin.HongKong.start(params);
            return;
        }
        if (params.account.login2 == 'Peru') {
            plugin.Peru.start(params);
            return;
        }
        if (params.account.login2 == 'Mexico' && $('a#LinkButton1:contains("Cerrar Sesión")').length > 0) {
            $('a#LinkButton1').get(0).click();
            return;
        }
        if (params.account.login2 == 'India' && $('a#signindata').length > 0) {
            $('a#signindata').get(0).click();
            plugin.changeLocation('start', plugin.getStartingUrl(params));
            return;
        }
        if (params.account.login2 == 'Japan' && $('a.js-logout:visible').length > 0) {
            $('a.js-logout:visible').get(0).click();
            provider.setNextStep('loadLoginForm');
            return;
        }
        if (params.account.login2 == 'Vietnam' && $('a[href *= signout]:visible').length > 0) {
            plugin.changeLocation('start', 'https://card.starbucks.vn/en/account/signout');
            return;
        }
        if (params.account.login2 == 'Thailand' && $('a:contains("Sign Out"):visible').length > 0) {
            plugin.changeLocation('loadLoginForm', 'https://www.starbuckscardth.in.th/Authorize/Signout');
            return;
        }
        if ($('a:contains("Logout")').length > 0) {
            $('a:contains("Logout")').get(0).click();
            return;
        }
        if (params.account.login2 == 'Poland') {
            plugin.login(params);
            return;
        }
        if (plugin.getStartingUrl(params) != document.location.href
            && $.inArray(params.account.login2, ['Thailand', 'China', 'Japan', 'USA']) === -1) {
            plugin.changeLocation('start', plugin.getStartingUrl(params));
			return;
		}
		plugin.login(params);
	},

    HongKong: {
        start: function (params) {
            browserAPI.log("start, region => HongKong");
            let counter = 0;
            let start = setInterval(function () {
                browserAPI.log("waiting... " + counter);
                let isLoggedIn = plugin.HongKong.isLoggedIn(params);

                if (isLoggedIn !== null) {
                    clearInterval(start);
                    if (isLoggedIn) {
                        if (plugin.HongKong.isSameAccount(params.account))
                            plugin.loginComplete(params);
                        else
                            plugin.HongKong.logout(params);
                    } else
                        plugin.HongKong.login(params);
                }

                else if (counter > 10) {
                    clearInterval(start);
                    provider.setError(util.errorMessages.unknownLoginState);
                    return;
                }

                counter++;
            }, 500);
        },

        isLoggedIn: function (params) {
            browserAPI.log("isLoggedIn");

            if ($('a[href *= "customer/account/login/referer"]').length) {
                browserAPI.log("not LoggedIn");
                return false;
            }

            if ($('a[href *= "customer/account/logout"]').length) {
                browserAPI.log("LoggedIn");
                return true;
            }

            return null;
        },

        isSameAccount: function (account) {
            browserAPI.log("isSameAccount");
            let stringWithEmail = $.cookie('remember_me_key') ?? '';
            if (stringWithEmail.length) {
                browserAPI.log("remember me key: " + stringWithEmail);
                return stringWithEmail.toLowerCase().includes(account.login.toLowerCase());
            }
            return false;
        },

        logout: function (params) {
            browserAPI.log("logout");
            provider.setNextStep('loadLoginForm', function () {
                $('a[href *= "customer/account/logout"]').get(0).click();
            });
        },

        loadLoginForm: function (params) {
            browserAPI.log("loadLoginForm");
            provider.setNextStep('start', function () {
                $('a[href *= "customer/account/login"]').get(0).click();
            });
        },

        login: function (params) {
            browserAPI.log("login");
            let form = $('#login-form');

            if (form.length === 0) {
                provider.setError(util.errorMessages.loginFormNotFound);
                return;
            }

            browserAPI.log("submitting saved credentials");
            if (/.+@.+\..+/.test(params.account.login)) {
                browserAPI.log("login is email");
                provider.eval(`
                    document.getElementById('mx-email').click();
                    let f = document.forms['login-form'];
                    f.email.value = '${params.account.login}';
                    f.emailPassword.value = '${params.account.password}';
                    document.getElementById('sb-sign-button').click();
                `);
            }
            else if (/^\+85[23]\d{9}$/.test(params.account.login)) {
                browserAPI.log("login is HongKong phone number");
                provider.eval(`
                    document.getElementById('mx-mobile').click();
                    let f = document.forms['login-form'];
                    f.areaCode1.value = '${params.account.login.substring(0, 4)}';
                    f.mobile1.value = '${params.account.login.substring(5)}';
                    f.mobilePassword.value = '${params.account.password}';
                    document.getElementById('sb-sign-button').click();
                `);
            }
            else {
                provider.setError(['Invalid login', util.errorCodes.invalidPassword]);
                return;
            }
            provider.setNextStep('checkLoginErrors', function () {
                setTimeout(function () {
                    plugin.HongKong.checkLoginErrors(params);
                }, 5000);
            });
        },

        checkLoginErrors: function (params) {
            browserAPI.log("checkLoginErrors, region => HongKong");

            let errors = $('div.mage-error');
            if (errors.length > 0 && util.filter(errors.text()) !== '') {
                provider.setError(util.filter(errors.text()));
                return;
            }

            if (document.location.href.includes('/customer/account/login/')) {
                return;
            }

            errors = $('.error.message');
            if (errors.length > 0 && util.filter(errors.text()) !== '') {
                provider.setError(util.filter(errors.text()));
                return;
            }

            plugin.HongKong.loginComplete(params);
        },

        loginComplete: function (params) {
            browserAPI.log('loginComplete');
            if (document.location.href.includes('/rewards/account/homepage/')) provider.complete();
            else provider.setNextStep('loginComplete', function () {
                browserAPI.log('redirecting to rewards page');
                provider.eval(`document.querySelector("a[href *= '/rewards/account/homepage/']").click()`);
            });
        }
    },

    Peru: {
        start: function (params) {
            browserAPI.log("start, region => Peru");
            let counter = 0;
            let start = setInterval(function () {
                browserAPI.log("waiting... " + counter);
                let isLoggedIn = plugin.Peru.isLoggedIn(params);

                if (isLoggedIn !== null) {
                    clearInterval(start);
                    if (isLoggedIn) {
                        if (plugin.Peru.isSameAccount(params.account))
                            plugin.loginComplete(params);
                        else
                            plugin.Peru.logout(params);
                    } else
                        plugin.Peru.login(params);
                }

                else if (counter > 10) {
                    clearInterval(start);
                    provider.setError(util.errorMessages.unknownLoginState);
                    return;
                }

                counter++;
            }, 500);
        },

        isLoggedIn: function (params) {
            browserAPI.log("isLoggedIn");

            if ($('form#form-login:visible').length) {
                browserAPI.log("not LoggedIn");
                return false;
            }

            if ($('a[data-href*="/rewards/auth/logout"]').length) {
                browserAPI.log("LoggedIn");
                return true;
            }

            return null;
        },

        isSameAccount: function (account) {
            browserAPI.log("isSameAccount");
            return false;
        },

        logout: function (params) {
            browserAPI.log("logout");
            provider.setNextStep('loadLoginForm', function () {
                $('a[data-href*="/rewards/auth/logout"]').get(0).click();
            });
        },

        loadLoginForm: function (params) {
            browserAPI.log("loadLoginForm");
            provider.setNextStep('start', function () {
                $('a[href *= "customer/account/login"]').get(0).click();
            });
        },

        login: function (params) {
            browserAPI.log("login");
            let form = $('form#form-login');
            if (form.length > 0) {
                browserAPI.log("submitting saved credentials");
                form.find('input[name="User.UserName"]').val(params.account.login);
                form.find('input[name="User.Password"]').val(params.account.password);
                // util.sendEvent(form.find('input[name="email"]').get(0), 'input');
                // util.sendEvent(form.find('input[name="password"]').get(0), 'input');
                provider.setNextStep('checkLoginErrors', function () {
                    form.find('a.send-submit.prevent-click').get(0).click();
                });
            }// if (form.length > 0)
            else
                provider.setError(util.errorMessages.loginFormNotFound);
        },

        checkLoginErrors: function (params) {
            browserAPI.log("checkLoginErrors, region => HongKong");

            let errors = $('.error-login.text-danger:visible');
            if (errors.length > 0 && util.filter(errors.text()) !== '') {
                provider.setError(util.filter(errors.text()));
                return;
            }

            plugin.Peru.loginComplete(params);
        },

        loginComplete: function (params) {
            browserAPI.log('loginComplete');
            provider.complete();
        }
    },

    chinaLogin: function(params){
        browserAPI.log('chinaLogin');
        plugin.changeLocation('start', plugin.getStartingUrl(params));
    },

	login: function(params){
        browserAPI.log("login");
        let form;
		switch (params.account.login2) {
            case "Japan":
				form = $('div.loginForm form');
				if (form.length === 0) {
                    provider.setError(['Login form not found [Code: J]', util.errorCodes.engineError]);
                    return;
				}// if (form.length === 0)
                browserAPI.log("submitting saved credentials");
                form.find('input[name = "username"]').val(params.account.login);
                form.find('input[name = "password"]').val(params.account.password);
                provider.setNextStep('checkLoginErrors', function () {
                    $('button.btnAction', form).trigger('click');
                    setTimeout(function () {
                        plugin.checkLoginErrors(params);
                    }, 10000)
                });
			break;
            case "USA":
            case "UK":
            case 'Canada':
            case "Germany":
            case "Ireland":
            case "Spain":
            case "Switzerland":
                form = $('form:has(input#username), form:has(input#edit-email)');
                if (form.length > 0) {
                    browserAPI.log("submitting saved credentials");
                    // without IE
                    let login = form.find('input#username, input#edit-email').eq(0);
                    let pass = form.find('input#password, input#edit-password').eq(0);
                    browserAPI.log("submitting saved credentials");
                    plugin.triggerInput(login[0], params.account.login);
                    plugin.triggerInput(pass[0], params.account.password);

                    // login.val(params.account.login);
                    // // refs #11326
                    // util.sendEvent(login.get(0), 'input');
                    // pass.val(params.account.password);
                    // // refs #11326
                    // util.sendEvent(pass.get(0), 'input');

                    provider.setNextStep('checkLoginErrors', function () {
                        form.find('button.sb-frap, button#edit-submit').get(0).click();
                        setTimeout(function () {
                            plugin.checkLoginErrors(params);
                        }, 10000)
                    });
                }// if (form.length > 0)
                else
                    provider.setError(['Login form not found [Code: ' + params.account.login2 + ']', util.errorCodes.engineError]);
                break;
            case "China":
                form = $('div.login-form');
                if (form.length > 0) {
                    browserAPI.log("submitting saved credentials");
                    // login = form.find('input[name = "username"]').val(params.account.login);
                    // pass = form.find('input[name = "password"]').val(params.account.password);
                    // // refs #11326
                    // util.sendEvent(login.get(0), 'input');
                    // util.sendEvent(pass.get(0), 'input');
                    // reactjs
                    provider.eval(
                        "function doEvent( obj, event ) {"
                        + "let event = new Event( event, {target: obj, bubbles: true} );"
                        + "return obj ? obj.dispatchEvent(event) : false;"
                        + "};"
                        + "let el = document.querySelector('[placeholder=\"Username or Email\"]'); el.value = \"" + params.account.login + "\"; doEvent(el, 'input' );"
                        + "el = document.querySelector('[placeholder=Password]'); el.value = \"" + params.account.password + "\"; doEvent(el, 'input' );"
                    );

                    provider.setNextStep('checkLoginErrors', function () {
                        if ($('label:contains("Captcha"):visible').length > 0 || $('div[class *= "spinning"][class *= "captcha"]').length > 0) {
                            provider.reCaptchaMessage();
                            let counter = 0;
                            const start = setInterval(function () {
                                browserAPI.log("waiting... " + counter);
                                const errors = $('div[class *= "active"][class *= "notification_message"]:visible div.content');
                                const logout = $('span:contains("Welcome to My Starbucks Rewards"):visible');
                                if (errors.length > 0 || logout.length > 0 || counter > 120) {
                                    clearInterval(start);
                                    plugin.checkLoginErrors(params);
                                }// if (errors.length > 0 || logout.length > 0 || counter > 60)
                                counter++;
                            }, 500);
                        }
                        else {
                            form.find('button:contains("Sign in"), button:contains("Login")').click();
                            setTimeout(function(){
                                plugin.checkLoginErrors(params);
                            }, 3000);
                        }
                    });
                }// if (form.length > 0)
                else
                    provider.setError(['Login form not found [Code: C]', util.errorCodes.engineError]);
                break;
            case "India":
                form = $('form[id = "loginForm"]');
                if (form.length > 0) {
                    browserAPI.log("submitting saved credentials");
                    // form.find('input[id = "username_input"]').val(params.account.login);
                    // form.find('input[id = "mat-input-1"]').val(params.account.password);

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
                        "triggerInput('#username_input', '" + params.account.login + "');\n" +
                        "triggerInput('#mat-input-1', '" + params.account.password + "');"
                    );

                    provider.setNextStep('checkLoginErrors', function () {
                        setTimeout(function() {
                            form.find('button:contains("Login")').get(0).click();

                            setTimeout(function() {
                                let btnYes = form.find('button[class = "btns btn-yes"]');

                                if (btnYes) {
                                    browserAPI.log("btn-yes");
                                    btnYes.get(0).click();
                                }

                                plugin.checkLoginErrors(params);
                            }, 7000);
                        }, 500);
                    });
                }// if (form.length > 0)
                else
                    provider.setError(['Login form not found [Code: I]', util.errorCodes.engineError]);
                break;
            case "Mexico":
                form = $('form[data-form-name="Login form"]');
                if (form.length > 0) {
                    browserAPI.log("submitting saved credentials");
                    form.find('input[name="email"]').val(params.account.login);
                    form.find('input[name="password"]').val(params.account.password);
                    util.sendEvent(form.find('input[name="email"]').get(0), 'input');
                    util.sendEvent(form.find('input[name="password"]').get(0), 'input');
                    provider.setNextStep('checkLoginErrors', function () {
                        form.find('button:contains("Iniciar Sesión")').get(0).click();
                    });
                }// if (form.length > 0)
                else
                    provider.setError(util.errorMessages.loginFormNotFound);
                break;
            case "Poland":
                form = $('form.loginForm');
                if (form.length > 0) {
                    browserAPI.log("submitting saved credentials");
                    form.find('input[name="j_username"]').val(params.account.login);
                    form.find('input[name="j_password"]').val(params.account.password);
                    // util.sendEvent(form.find('input[name="email"]').get(0), 'input');
                    // util.sendEvent(form.find('input[name="password"]').get(0), 'input');
                    provider.setNextStep('checkLoginErrors', function () {
                        form.find('input[type="submit"]').get(0).click();
                    });
                }// if (form.length > 0)
                else
                    provider.setError(util.errorMessages.loginFormNotFound);
                break;
            case "Singapore":
                form = $('#loginform');
                if (form.length > 0) {
                    browserAPI.log("submitting saved credentials");
                    form.find('input[type="Email"]').val(params.account.login);
                    form.find('input[type="Password"]').val(params.account.password);
                    const captcha = form.find('iframe[src *= "https://www.google.com/recaptcha/api2/anchor"]:visible');
                    if (captcha.length > 0) {
                        provider.reCaptchaMessage();
                        provider.setNextStep('checkLoginErrors', function () {
                            let counter = 0;
                            const login = setInterval(function () {
                                browserAPI.log("waiting... " + counter);
                                if (counter > 160) {
                                    clearInterval(login);
                                    provider.setError(util.errorMessages.captchaErrorMessage, true);
                                } else if ($('#loginmsgcontent:visible').length) {
                                    clearInterval(login);
                                    plugin.checkLoginErrors(params);
                                }
                                counter++;
                            }, 1000);
                        });
                    } else
                        form.find('button#btn-signin').get(0).click();
                }
                else
                    provider.setError(util.errorMessages.loginFormNotFound);
                break;
            case "Taiwan":
                form = $('form[action *= "SignIn.html"]');
                if (form.length > 0) {
                    browserAPI.log("submitting saved credentials");
                    form.find('input[name = "form.loginId"]').val(params.account.login);
                    form.find('input[name = "form.passw0rd"]').val(params.account.password);
                    provider.setNextStep('checkLoginErrors', function () {
                        setTimeout(function() {
                            const captcha = $('#imgVerify');

                            if (captcha.length > 0) {
                                provider.captchaMessageDesktop();
                            }

                            //browserAPI.log("waiting captcha -> " + captcha.attr('src'));
                            if (captcha.length > 0 && !provider.isMobile) {
                                browserAPI.log("waiting...");

                                const captchaDiv = document.createElement('div');
                                captchaDiv.id = 'captchaDiv';
                                document.body.appendChild(captchaDiv);

                                const canvas = document.createElement('CANVAS'),
                                    ctx = canvas.getContext('2d'),
                                    img = document.getElementById('imgVerify');

                                canvas.height = img.height;
                                canvas.width = img.width;
                                ctx.drawImage(img, 0, 0);
                                const dataURL = canvas.toDataURL('image/png');
                                //console.log("dataURL: " + dataURL);
                                browserAPI.send("awardwallet", "recognizeCaptcha", { captcha: dataURL, "extension": "jpg" }, function(response){
                                    browserAPI.log(JSON.stringify(response));
                                    if (response.success === true) {
                                        browserAPI.log("Success: " + response.success);
                                        form.find('input[name = "form.verifyCode"]').val(response.recognized);
                                        form.find('a[id = "loginBtn"]').get(0).click();
                                    }// if (response.success === true))
                                    if (response.success === false) {
                                        browserAPI.log("Success: " + response.success);
                                        provider.setError(util.errorMessages.captchaErrorMessage, true);
                                    }// if (response.success === false)
                                });
                            }// if (captcha.length > 0)
                            else {
                                browserAPI.log("captcha is not found");
                                provider.complete();
                            }
                        }, 1000)
                    });
                }// if (form.length > 0)
                else
                    provider.setError(['Login form not found [Code: T1]', util.errorCodes.engineError]);
            break;
            case "Thailand":
                form = $('form[action *= "/Authorize"]');
                if (form.length > 0) {
                    browserAPI.log("submitting saved credentials");
                    $('input[name = "Email"]').val(params.account.login);
                    $('input[name = "Password"]').val(params.account.password);
                    provider.setNextStep('checkLoginErrors', function () {
                        $('button[type = "submit"]').click();
                    });
                }
                else
                    provider.setError(['Login form not found [Code: T2]', util.errorCodes.engineError]);
            break;
            case "Vietnam":
                form = $('form[action *= "/en/Account/Login"]');
                if (form.length > 0) {
                    browserAPI.log("submitting saved credentials");
                    form.find('input[name = "Email_Address"]').val(params.account.login);
                    form.find('input[name = "Password"]').val(params.account.password);
                    provider.setNextStep('checkLoginErrors', function () {
                        form.find('input[id = "BtnSignIn"]').click();
                    });
                }
                else
                    provider.setError(['Login form not found [Code: V]', util.errorCodes.engineError]);
            break;
		}
	},

	checkLoginErrors: function(params) {
        browserAPI.log('checkLoginErrors');
        if (document.location.href.includes('www.starbucks.com.hk')) plugin.HongKong.checkLoginErrors(params);
        let errors = $('div.validation_summary.error');
        if (errors.length == 0)
			errors = $('div.validation_summary.warning');
        // India
        if (errors.length === 0) {
			errors = $('.mat-error:visible');
        }
        // Japan
        if (errors.length === 0) {
			errors = $('li.is-error:visible, div.alert-danger:visible > p');
        }
        // China
        if (errors.length == 0)
            errors = $('div[class *= "active"][class *= "notification_message"]:visible div.content');
        // Vietnam
        if (errors.length == 0)
			errors = $('span#CphMain_CvLoginValidator:visible');
        // Mexico
        if (errors.length == 0)
            errors = $('div[class^="message_error-"]:visible');
        // Poland
        if (errors.length == 0)
            errors = $('.errorList > li:visible');
        // Singapore
        if (errors.length == 0)
            errors = $('#loginmsgcontent:visible');
        // Thailand
        if (errors.length == 0)
            errors = $('span.req:visible');
        // UK
        if (errors.length == 0) {
			errors = $('div[class *= "alert"]:visible, li.input-validation-hint:visible');
            if (errors.length > 0 && util.filter(errors.text()) != '')
                provider.setError(util.filter(errors.text()));
            else
                plugin.loginComplete(params);
        }// if (errors.length == 0)
        else {
            if (errors.length > 0 && util.filter(errors.children('*').eq(0).text()) != '')
                provider.setError(util.filter(errors.children('*').eq(0).text()));
            else if (errors.length > 0 && util.filter(errors.text()) != '')
                provider.setError(util.filter(errors.text()));
            else
                plugin.loginComplete(params);
        }
	},

    loginComplete: function (params) {
        browserAPI.log('loginComplete');
		// refs #14382
        const form = $('form:has(input#username)');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            // without IE
            const login = form.find('input#username');
            const pass = form.find('input#password');
            login.val(params.account.login);
            // refs #11326
            util.sendEvent(login.get(0), 'input');
            pass.val(params.account.password);
            // refs #11326
            util.sendEvent(pass.get(0), 'input');

            provider.showFader('Please click the "Sign in" button to get logged in to your account');
            $('#awFader').remove();
            setTimeout(function () {
                provider.complete();
            }, 30000);
            return;
        }
        if (params.autologin) {
            provider.complete();
            return;
        }
        browserAPI.log('current URL: ' + document.location.href);
        setTimeout(() => plugin.parse(params), 1000);
        document.querySelector('a[href="/account/rewards"]').click();
	},

    triggerInput: function(input, enteredValue) {
        const lastValue = input.value;
        input.value = enteredValue;
        const event = new Event("input", { bubbles: true });
        const tracker = input._valueTracker;
        if (tracker) {
            tracker.setValue(lastValue);
        }
        input.dispatchEvent(event);
    },

    parse: function (params) {
        browserAPI.log('parse');
        let parsed = { SubAccounts: [] };
        let parsingInterval;
        let counter = 0;
        let parsing = () => {
            counter++;
            // Balance
            let balance = document.querySelector('div[data-e2e="starCount"]');
            if (balance) balance = parseInt(balance.textContent ?? null);
            if (balance !== null && !isNaN(balance)) {
                browserAPI.log('Balance: ' + (parsed.Balance = balance));
            }
            else browserAPI.log('Balance not found');

            // Stars until your next Reward
            let nextRewardPrice = document.querySelector('div[class^="goalMarker"] > div[class$="bg-neutralCool"] + div[class*="goalMarkerText"]');
            if (nextRewardPrice) nextRewardPrice = parseInt(nextRewardPrice.textContent ?? null);
            if (!isNaN(parsed.Balance) && !isNaN(nextRewardPrice)) {
                parsed.StarsNeeded = nextRewardPrice - balance;
            }
            else browserAPI.log('Stars until your next Reward not calculated');

            // Member Since
            let memberSince = document.querySelector('div[data-e2e="tenured-status"] h3, div[data-e2e="tenured-status"] p.px5.md-px0.text-center');
            if (memberSince) memberSince = util.findRegExp(memberSince.textContent ?? null, /member since (\d{4})/);
            if (memberSince !== null && memberSince.length)
                browserAPI.log('Member Since: ' + (parsed.Since = memberSince));
            else browserAPI.log('Member Since not found');

            let now = new Date();
            let expiringBalances = document.querySelectorAll('a#expiring-stars + hr + div h2:last-of-type + div div.grid--compactGutter.grid--valignMiddle');
            let zeroBalances = 0;
            for (let i = 0; i < expiringBalances.length; i++) {
                let row = expiringBalances[i];
                let points = row.querySelector('div:first-child');
                let expDate = row.querySelector('div:last-child');
                if (points) points = parseInt(points.textContent ?? null);
                if (expDate) expDate = expDate.textContent ?? null;
                browserAPI.log('points: ' + points + ', expiration: ' + expDate);
                if (points === null || expDate === null) continue;

                let expDateSplitted = expDate.split(' ');
                let expirationTimestampUTC;

                try {
                    let day = expDateSplitted[1];
                    let month = expDateSplitted[0];
                    let year = now.getFullYear();
                    let parsedDate = new Date(day + ' ' + month + ' ' + year);
                    if (parsedDate < now) {
                        year += 1;
                        parsedDate = new Date(day + ' ' + month + ' ' + year);
                    }
                    expirationTimestampUTC = Date.parse(parsedDate.toUTCString()) / 1000;
                } catch (e) {
                    browserAPI.log('error in expirations parsing: ' + e);
                }

                if (!isNaN(points) && points > 0 && !isNaN(expirationTimestampUTC)) {
                    // Stars Expiring Soon
                    browserAPI.log('Expiring Balance: ' + (parsed.ExpiringBalance = points));
                    // Stars expire on
                    browserAPI.log('Expiration Date: ' + (parsed.AccountExpirationDate = expirationTimestampUTC));
                    break;
                }
                else if (points == 0) {
                    zeroBalances++;
                    browserAPI.log(`Expiring Balance is ${points} on date ${expDate} (${expirationTimestampUTC})`);
                }
                else browserAPI.log('expirations not parsed correctly');
            }

            if (counter > 5 || (
                    parsed.Balance &&
                    parsed.Since &&
                    parsed.StarsNeeded && (
                        zeroBalances === expiringBalances.length || (
                            parsed.AccountExpirationDate &&
                            parsed.ExpiringBalance
                        )
                    )
                )
            ) {
                clearInterval(parsingInterval);
                params.data = parsed;
                provider.saveTemp(params.data);
                setTimeout(() => plugin.parseCards(params), 1000);
                document.querySelector('a[href="/account/cards"]').click();
            }
        };
        parsingInterval = setInterval(parsing, 1000);
    },

    parseCards: function (params) {
        browserAPI.log('parseCards');
        function parseCard(cardDescription) {
            let nickname = util.findRegExp(cardDescription, /Balance of card with nickname (.+) is/);
            let balance = util.findRegExp(cardDescription, /Balance of card with nickname .+ is (\$\d+[.,]\d+)/);
            if (nickname && balance) {
                let card = {
                    Code: 'starbucksCard' + params.account.login2 + plugin.md5(nickname),
                    DisplayName: nickname,
                    Balance: balance,
                };
                if (params.data.SubAccounts.find(subAcc => subAcc.Code === card.Code)) {
                    browserAPI.log('found card is duplicate');
                    return;
                }
                params.data.SubAccounts.push(card);
                browserAPI.log('card parsed: ' + JSON.stringify(card));
            }
            else browserAPI.log('found card not parsed successfully');
        }
        util.waitFor({
            selector: '#refresh-balance > span:first-child',
            success: element => { // Balance of card with nickname My Card (9365) is $0.00
                parseCard(element.text());
                setTimeout(() => {
                    Array.from(document.querySelectorAll('a[data-e2e="manageCardImageLink"] + div > span.hiddenVisually'))
                        .forEach(descriptionElement => parseCard(descriptionElement.textContent));
                    provider.saveTemp(params.data);
                    setTimeout(() => plugin.parseName(params), 1000);
                    document.querySelector('a[href="/account/personal"]').click();
                }, 2000);
            },
            fail: () => {
                browserAPI.log('hidden element with card description not found');
                provider.saveTemp(params.data);
                setTimeout(() => plugin.parseName(params), 1000);
                document.querySelector('a[href="/account/personal"]').click();
            },
            timeout: 5
        });
    },

    parseName: params => {
        // Name
        let name = document.querySelector('div.sb-contentColumn__inner > h2');
        if (name !== null && name.textContent.length)
            browserAPI.log('Name: ' + (params.data.Name = util.beautifulName(name.textContent)));
        else browserAPI.log('Name not found');
        provider.saveProperties(params.data);
        provider.complete();
    },

    md5: function(d){function M(d){for(var _,m="0123456789ABCDEF",f="",r=0;r<d.length;r++)_=d.charCodeAt(r),f+=m.charAt(_>>>4&15)+m.charAt(15&_);return f}function X(d){for(var _=Array(d.length>>2),m=0;m<_.length;m++)_[m]=0;for(m=0;m<8*d.length;m+=8)_[m>>5]|=(255&d.charCodeAt(m/8))<<m%32;return _}function V(d){for(var _="",m=0;m<32*d.length;m+=8)_+=String.fromCharCode(d[m>>5]>>>m%32&255);return _}function Y(d,_){d[_>>5]|=128<<_%32,d[14+(_+64>>>9<<4)]=_;for(var m=1732584193,f=-271733879,r=-1732584194,i=271733878,n=0;n<d.length;n+=16){var h=m,t=f,g=r,e=i;f=md5_ii(f=md5_ii(f=md5_ii(f=md5_ii(f=md5_hh(f=md5_hh(f=md5_hh(f=md5_hh(f=md5_gg(f=md5_gg(f=md5_gg(f=md5_gg(f=md5_ff(f=md5_ff(f=md5_ff(f=md5_ff(f,r=md5_ff(r,i=md5_ff(i,m=md5_ff(m,f,r,i,d[n+0],7,-680876936),f,r,d[n+1],12,-389564586),m,f,d[n+2],17,606105819),i,m,d[n+3],22,-1044525330),r=md5_ff(r,i=md5_ff(i,m=md5_ff(m,f,r,i,d[n+4],7,-176418897),f,r,d[n+5],12,1200080426),m,f,d[n+6],17,-1473231341),i,m,d[n+7],22,-45705983),r=md5_ff(r,i=md5_ff(i,m=md5_ff(m,f,r,i,d[n+8],7,1770035416),f,r,d[n+9],12,-1958414417),m,f,d[n+10],17,-42063),i,m,d[n+11],22,-1990404162),r=md5_ff(r,i=md5_ff(i,m=md5_ff(m,f,r,i,d[n+12],7,1804603682),f,r,d[n+13],12,-40341101),m,f,d[n+14],17,-1502002290),i,m,d[n+15],22,1236535329),r=md5_gg(r,i=md5_gg(i,m=md5_gg(m,f,r,i,d[n+1],5,-165796510),f,r,d[n+6],9,-1069501632),m,f,d[n+11],14,643717713),i,m,d[n+0],20,-373897302),r=md5_gg(r,i=md5_gg(i,m=md5_gg(m,f,r,i,d[n+5],5,-701558691),f,r,d[n+10],9,38016083),m,f,d[n+15],14,-660478335),i,m,d[n+4],20,-405537848),r=md5_gg(r,i=md5_gg(i,m=md5_gg(m,f,r,i,d[n+9],5,568446438),f,r,d[n+14],9,-1019803690),m,f,d[n+3],14,-187363961),i,m,d[n+8],20,1163531501),r=md5_gg(r,i=md5_gg(i,m=md5_gg(m,f,r,i,d[n+13],5,-1444681467),f,r,d[n+2],9,-51403784),m,f,d[n+7],14,1735328473),i,m,d[n+12],20,-1926607734),r=md5_hh(r,i=md5_hh(i,m=md5_hh(m,f,r,i,d[n+5],4,-378558),f,r,d[n+8],11,-2022574463),m,f,d[n+11],16,1839030562),i,m,d[n+14],23,-35309556),r=md5_hh(r,i=md5_hh(i,m=md5_hh(m,f,r,i,d[n+1],4,-1530992060),f,r,d[n+4],11,1272893353),m,f,d[n+7],16,-155497632),i,m,d[n+10],23,-1094730640),r=md5_hh(r,i=md5_hh(i,m=md5_hh(m,f,r,i,d[n+13],4,681279174),f,r,d[n+0],11,-358537222),m,f,d[n+3],16,-722521979),i,m,d[n+6],23,76029189),r=md5_hh(r,i=md5_hh(i,m=md5_hh(m,f,r,i,d[n+9],4,-640364487),f,r,d[n+12],11,-421815835),m,f,d[n+15],16,530742520),i,m,d[n+2],23,-995338651),r=md5_ii(r,i=md5_ii(i,m=md5_ii(m,f,r,i,d[n+0],6,-198630844),f,r,d[n+7],10,1126891415),m,f,d[n+14],15,-1416354905),i,m,d[n+5],21,-57434055),r=md5_ii(r,i=md5_ii(i,m=md5_ii(m,f,r,i,d[n+12],6,1700485571),f,r,d[n+3],10,-1894986606),m,f,d[n+10],15,-1051523),i,m,d[n+1],21,-2054922799),r=md5_ii(r,i=md5_ii(i,m=md5_ii(m,f,r,i,d[n+8],6,1873313359),f,r,d[n+15],10,-30611744),m,f,d[n+6],15,-1560198380),i,m,d[n+13],21,1309151649),r=md5_ii(r,i=md5_ii(i,m=md5_ii(m,f,r,i,d[n+4],6,-145523070),f,r,d[n+11],10,-1120210379),m,f,d[n+2],15,718787259),i,m,d[n+9],21,-343485551),m=safe_add(m,h),f=safe_add(f,t),r=safe_add(r,g),i=safe_add(i,e)}return Array(m,f,r,i)}function md5_cmn(d,_,m,f,r,i){return safe_add(bit_rol(safe_add(safe_add(_,d),safe_add(f,i)),r),m)}function md5_ff(d,_,m,f,r,i,n){return md5_cmn(_&m|~_&f,d,_,r,i,n)}function md5_gg(d,_,m,f,r,i,n){return md5_cmn(_&f|m&~f,d,_,r,i,n)}function md5_hh(d,_,m,f,r,i,n){return md5_cmn(_^m^f,d,_,r,i,n)}function md5_ii(d,_,m,f,r,i,n){return md5_cmn(m^(_|~f),d,_,r,i,n)}function safe_add(d,_){var m=(65535&d)+(65535&_);return(d>>16)+(_>>16)+(m>>16)<<16|65535&m}function bit_rol(d,_){return d<<_|d>>>32-_};var r = M(V(Y(X(d),8*d.length)));return r.toLowerCase()},
};
