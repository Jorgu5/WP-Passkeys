const loginForm: HTMLInputElement | null = document.querySelector('#loginform');
const passwordInput: HTMLInputElement | null = document.querySelector('.user-pass-wrap');
const rememberAuthCredLink: HTMLAnchorElement | null = document.querySelector('.forgetmenot');
const lostPasswordLink: HTMLAnchorElement | null = document.querySelector('#nav');
const usernameInput: HTMLInputElement | null = document.querySelector('#user_login');

// TODO: Check if user has been authorized previously and if so, change the button name to "login" or "register"
// TODO: Allow change authentication behaviour for users with email and password.
// TODO: Add support for usernameless login

if(loginForm) {
    const uselessElementsForWebAuthn: (HTMLInputElement | HTMLAnchorElement | null)[] = [
        passwordInput, rememberAuthCredLink, lostPasswordLink
    ];
    uselessElementsForWebAuthn.forEach((element) => {
        if(element) {
            element.style.display = 'none';
        }
    });

    usernameInput && (usernameInput.autocomplete = 'username webauthn');
}

