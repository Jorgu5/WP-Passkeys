import {RegistrationHandler} from "./RegistrationHandler";
import {AuthenticationHandler} from "./AuthenticationHandler";
import {UserLoginResponse} from "./AuthenticatorInterface";
import {Utilities} from "./Utilities";

export class FormHandler {
    private loginForm: HTMLFormElement | null = document.querySelector('#loginform');
    private registerForm: HTMLFormElement | null = document.querySelector('#registerform');
    private registerSuccessNotification: HTMLElement | null = document.querySelector('.register-success-notification');
    private registerErrorNotification: HTMLElement | null = document.querySelector('.register-error-notification');
    private passkeysButton: HTMLButtonElement | null = document.querySelector('.passkeys-button');
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

    async handleFormSubmit(event: Event): Promise<void> {
        event.preventDefault();
        try {
            Utilities.setUserLogin().then(async () => {
                console.log('set user login');
                console.log(this.loginForm);
                if (this.loginForm) {
                    console.log('login');
                    const authInit = await this.authHandler.init(false);
                    await this.authHandler.start(authInit);
                } else if (this.registerForm) {
                    console.log('register');
                    await this.regHandler.start();
                }
            })
        } catch (error: any) {
            console.error("An error occurred during form submission:", error);
        }
    }

    initForm(): void {
        const submitButton: HTMLInputElement | null = document.querySelector('.submit');
        if (this.isAnyFormPresentAndButtonsExist(this.passkeysButton, submitButton)) {
            const wrapper = this.createWrapper();
            this.wrapButtons(wrapper, submitButton, this.passkeysButton);
            this.appendToForms(wrapper);
        }

        this.passkeysButton?.addEventListener('click', this.handleFormSubmit.bind(this));
    }

    private isAnyFormPresentAndButtonsExist(
        passkeysButton: HTMLButtonElement | null,
        submitButton: HTMLInputElement | null
    ): boolean {
        return Boolean((passkeysButton && this.loginForm) || (passkeysButton && this.registerForm) && submitButton);
    }

    private createWrapper(): HTMLDivElement {
        const wrapper = document.createElement('div');
        wrapper.classList.add('passkeys-login__wrapper');
        return wrapper;
    }

    private wrapButtons(
        wrapper: HTMLDivElement,
        submitButton: HTMLInputElement | null,
        passkeysButton: HTMLButtonElement | null
    ): void {
        if (submitButton) {
            wrapper.appendChild(submitButton);
            wrapper.appendChild(passkeysButton as HTMLInputElement);
        }
    }

    private appendToForms(wrapper: HTMLDivElement): void {
        if (this.loginForm) {
            this.loginForm.appendChild(wrapper);
        }
        if (this.registerForm) {
            this.registerForm.appendChild(wrapper);
        }
    }
}

const formHandler = new FormHandler();

formHandler.initForm();
