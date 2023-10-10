import Registration from "../registration/Registration";
import {VerificationResponse} from "../WebauthnTypes";

export default class UserSettings {

    private readonly registerSuccessNotification: HTMLElement | null = document.querySelector('.register-success-notification');
    private readonly registerErrorNotification: HTMLElement | null = document.querySelector('.register-error-notification');
    private readonly passkeysRegisterButton = document.querySelector<HTMLInputElement>('.passkeys-login__button--add');
    private readonly passkeysRemoveButton = document.querySelector<HTMLInputElement>('.passkeys-login__button--remove');
    private readonly passkeyRow = document.querySelector('#pk_credential_id');


    async startRegistration(): Promise<VerificationResponse> {
        const regHandler = new Registration();
        return await regHandler.start();
    }

    async removePasskey(): Promise<void> {
        try {
            // @ts-ignore
            const response: Response = await fetch('/wp-json/wp-passkeys/creds/user/remove', {
                method: 'DELETE',
                headers: {
                    'X-WP-Nonce': passkeys.nonce,
                }
            });
            return await response.json();
        } catch (error: any) {
            console.error("User credentials has not been removed:", error);
            throw error;
        }
    }

    public notify(isSuccess: boolean, message: string): void {
        if (isSuccess && this.registerSuccessNotification) {
            this.registerSuccessNotification.innerHTML = message;
        } else if (!isSuccess && this.registerErrorNotification) {
            this.registerErrorNotification.innerHTML = message;
        }
    }

    public attachEventListeners(): void {
        if (this.passkeysRegisterButton) {
            this.passkeysRegisterButton.addEventListener('click', () => {
                this.startRegistration().then((r) => {
                    this.notify(true, 'Passkey has been registered. You can now login without password!');
                    if (this.passkeyRow) {
                        this.passkeyRow.innerHTML = <string>r.pk_credential_id;
                    }
                })
            });
        }

        if (this.passkeysRemoveButton) {
            this.passkeysRemoveButton.addEventListener('click', () => {
                this.removePasskey().then(
                    () => {
                        if (this.passkeyRow) {
                            this.passkeyRow.innerHTML = '';
                        }
                        this.notify(true, 'Passkey has been removed.');
                    }
                );
            });
        }
    }

    public static init(): void {
        const userSettings = new UserSettings();
        userSettings.attachEventListeners();
    }
}

UserSettings.init();