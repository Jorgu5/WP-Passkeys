import {RegistrationHandler} from "./RegistrationHandler";
import {AuthenticationHandler} from "./AuthenticationHandler";
import {UserLoginResponse} from "./AuthenticatorInterface";
import {AuthenticationResponseJSON} from "@simplewebauthn/typescript-types";

export class FormHandler {
    private form: HTMLInputElement | null = document.querySelector('#loginform');
    private passwordInput: HTMLInputElement | null = document.querySelector('.user-pass-wrap');
    private rememberAuthCredLink: HTMLAnchorElement | null = document.querySelector('.forgetmenot');
    private lostPasswordLink: HTMLAnchorElement | null = document.querySelector('#nav');
    private registerSuccessNotification: HTMLElement | null = document.querySelector('.register-success-notification');
    private registerErrorNotification: HTMLElement | null = document.querySelector('.register-error-notification');
    private authHandler: AuthenticationHandler;
    private regHandler: RegistrationHandler;

    constructor() {
        this.authHandler = new AuthenticationHandler(this.notify.bind(this));
        this.regHandler = new RegistrationHandler(this.notify.bind(this));
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

    async handleFormSubmit(event: Event): Promise<void> {
        event.preventDefault();
        try {
            const response = await this.setUserLogin();
            if (!this.isExistingUser(response)) {
                await this.regHandler.start();
            }
            const authInit = await this.authHandler.init(false);
            await this.authHandler.start(authInit);
        } catch (error: any) {
            console.error("An error occurred during form submission:", error);
        }
    }
}

const formHandler = new FormHandler();

formHandler.attachEventListeners();
