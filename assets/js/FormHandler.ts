import {RegistrationHandler} from "./RegistrationHandler";
import {AuthenticationHandler} from "./AuthenticationHandler";
import {UserLoginResponse} from "./AuthenticatorInterface";
import {Utilities} from "./Utilities";

export class FormHandler {
    private form: HTMLInputElement | null = document.querySelector('#loginform');
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
        const formSwitcher = document.querySelector<HTMLInputElement>('.passkeys-login__button--switcher');
        const formBackSwitch = document.querySelector<HTMLInputElement>('.passkeys-backtodefault');
        const passkeysAuthButton = document.querySelector<HTMLInputElement>('.passkeys-login__button--auth');
        if (formSwitcher) {
            formSwitcher.addEventListener('click', () => {
                this.form?.classList.add('loginform--passkeys');
            });
            formBackSwitch?.addEventListener('click', () => {
                this.form?.classList.remove('loginform--passkeys');
            });
            passkeysAuthButton?.addEventListener('click', (e: Event) => this.handleFormSubmit(e));
        }
    }

    public isExistingUser(userResponse: UserLoginResponse | null): boolean {
        return !!userResponse?.isExistingUser;
    }

    async handleFormSubmit(event: Event): Promise<void> {
        event.preventDefault();
        try {
            const response = await Utilities.setUserLogin();
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
