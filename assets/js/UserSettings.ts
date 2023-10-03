import {RegistrationHandler} from "./RegistrationHandler";

class UserSettings {

    private registerSuccessNotification: HTMLElement | null = document.querySelector('.register-success-notification');
    private registerErrorNotification: HTMLElement | null = document.querySelector('.register-error-notification');

    async startRegistration(): Promise<void> {
        const regHandler = new RegistrationHandler(this.notify.bind(this));
        await regHandler.start();
    }

    public notify(isSuccess: boolean, message: string): void {
        if (isSuccess && this.registerSuccessNotification) {
            this.registerSuccessNotification.innerHTML = message;
        } else if (!isSuccess && this.registerErrorNotification) {
            this.registerErrorNotification.innerHTML = message;
        }
    }

    public attachEventListeners(): void {
        const passkeysRegisterButton = document.querySelector<HTMLInputElement>('.passkeys-login__button--add');
        if (passkeysRegisterButton) {
            passkeysRegisterButton.addEventListener('click', () => {
                this.startRegistration().then(r => console.log(r));
            });
        }
    }

    public static init(): void {
        const userSettings = new UserSettings();
        userSettings.attachEventListeners();
    }
}

UserSettings.init();