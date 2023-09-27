import {RegistrationHandler} from "./RegistrationHandler";
import {AuthenticationHandler} from "./AuthenticationHandler";
import {UserLoginResponse} from "./AuthenticatorInterface";

export class FormHandler {
    private form: HTMLInputElement | null = document.querySelector('#loginform');
    private passwordInput: HTMLInputElement | null = document.querySelector('.user-pass-wrap');
    private rememberAuthCredLink: HTMLAnchorElement | null = document.querySelector('.forgetmenot');
    private lostPasswordLink: HTMLAnchorElement | null = document.querySelector('#nav');
    private usernameInput: HTMLInputElement | null = document.querySelector('#user_login');
    private registerSuccessNotification: HTMLElement | null = document.querySelector('.register-success-notification');
    private registerErrorNotification: HTMLElement | null = document.querySelector('.register-error-notification');
    private $authHandler: AuthenticationHandler;
    private $regHandler: RegistrationHandler

    constructor() {
        this.$authHandler = new AuthenticationHandler(this.notify.bind(this));
        this.$regHandler = new RegistrationHandler(this.notify.bind(this));
    }

    public notify(isSuccess: boolean, message: string): void {
        if (isSuccess && this.registerSuccessNotification) {
            this.registerSuccessNotification.innerHTML = message;
        } else if (!isSuccess && this.registerErrorNotification) {
            this.registerErrorNotification.innerHTML = message;
        }
    }

    public attachEventListeners(): void {
        const registerButton = document.querySelector<HTMLInputElement>('#wp-submit');
        if (registerButton) {
            registerButton.addEventListener('click', (e: Event) => this.handleFormSubmit(e));
        }
    }

    private async setUserLogin(): Promise<UserLoginResponse | null> {
        const userLogin: HTMLInputElement | null = document.querySelector('#user_login');
        let userResponse: { isExistingUser?: boolean } = {};
        if (userLogin?.value) {
            try {
                const response: Response = await fetch(`/wp-json/wp-passkeys/register/user?name=${userLogin.value}`, {
                    method: 'POST',
                });
                if (response.ok) {
                    userResponse = await response.json();
                } else {
                    console.error(`Server returned ${response.status}: ${response.statusText}`);
                }

            } catch (error) {
                console.error('There was a problem with the fetch operation:', error);
            }

            return userResponse;
        }

        return null;
    }

    public isExistingUser(userResponse: UserLoginResponse | null): boolean {
        return !!userResponse?.isExistingUser;
    }

    public initForm():void {
        if(this.form) {
            const uselessElementsForWebAuthn: (HTMLInputElement | HTMLAnchorElement | null)[] = [
                this.passwordInput, this.rememberAuthCredLink, this.lostPasswordLink
            ];
            uselessElementsForWebAuthn.forEach((element) => {
                if(element) {
                    element.style.display = 'none';
                }
            });

            const usernameLabel: HTMLLabelElement | null = this.form.querySelector('label[for="user_login"]');

            if(usernameLabel) {
                // usernameLabel.innerHTML = 'Username or Email Address';
            }

            this.usernameInput && (this.usernameInput.autocomplete = 'username webauthn');
        }
    }

    async handleFormSubmit(event: Event): Promise<void> {
        event.preventDefault();
        try {
            const response = await this.setUserLogin();
            if (this.isExistingUser(response)) {
                await this.$authHandler.start();
            } else {
                await this.$regHandler.start();
            }
        } catch (error: any) {
            console.error("An error occurred during form submission:", error);
        }
    }
}

const formHandler = new FormHandler();
formHandler.initForm();
formHandler.attachEventListeners();
