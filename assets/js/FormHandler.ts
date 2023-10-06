import {RegistrationHandler} from "./RegistrationHandler";
import {AuthenticationHandler} from "./authentication/AuthenticationHandler";
import {Utilities} from "./Utilities";
import {userData} from "./WebauthnTypes";

export class FormHandler {
    private loginForm: HTMLFormElement | null = document.querySelector('#loginform');
    private registerForm: HTMLFormElement | null = document.querySelector('#registerform');
    private passkeysButton: HTMLButtonElement | null = document.querySelector('.passkeys-button');

    private setNotification(message: string, isSuccess: boolean, target: HTMLElement): void {
        const notifyWrapper = document.querySelector('#login') as HTMLElement;
        const notification = document.createElement('p');
        notification.classList.add('message');
        if(isSuccess) {
            notification.classList.add('success');
        } else {
            notification.classList.add('error');
        }

        notification.innerText = message;

        notifyWrapper.insertBefore(notification, target);
    }

    async handleLogin(event: Event): Promise<void> {
        event.preventDefault();
        try {
            if (this.loginForm) {
                const authHandler = new AuthenticationHandler(this.setNotification.bind(this))
                const authInit = await authHandler.init(false);
                await authHandler.start(authInit);
            }
        } catch (error: any) {
            console.error("An error occurred during form submission:", error);
        }
    }

    async handleRegistration(): Promise<void> {
        const userDataInputs = this.registerForm?.querySelectorAll('input') as NodeListOf<HTMLInputElement>;
        if(this.validatedRegisterFields(userDataInputs)) {
            try {
                Utilities.setUserData(this.getAllRegisterFormInputValues(userDataInputs)).then(async () => {
                    const regHandler = new RegistrationHandler(this.setNotification.bind(this));
                    await regHandler.start();
                });
            } catch (error: any) {
                console.error("An error occurred during form submission:", error);
            }
        }
    }

    initForm(): void {
        const submitButton: HTMLInputElement | null = document.querySelector('.submit');
        if (this.isAnyFormPresentAndButtonsExist(this.passkeysButton, submitButton)) {
            const wrapper = this.createWrapper();
            this.wrapButtons(wrapper, submitButton, this.passkeysButton);
            this.appendToForms(wrapper);
        }

            this.passkeysButton?.addEventListener('click', this.handleLogin.bind(this));
            this.passkeysButton?.addEventListener('click', this.handleRegistration.bind(this));
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

    private getAllRegisterFormInputValues(userDataInputs: NodeListOf<HTMLInputElement>): userData {
        const userData: userData = {};

        userDataInputs.forEach((input) => {
            const userKey = input.name as keyof userData;
            if (userKey) {
                userData[userKey] = input.value;
            }
        });

        return userData;
    }

    private validatedRegisterFields(userDataInputs: NodeListOf<HTMLInputElement>): boolean {
        const requiredFields = Array.from(userDataInputs).filter((field) => field.required);
        const emptyFields = requiredFields.filter((field) => !field.value);
        emptyFields.forEach((field) => {
            field.classList.add('error');
        });

        if (emptyFields.length) {
            this.setNotification('Please fill in all required fields.', false, this.registerForm as HTMLElement);
            return false;
        }

        return true;
    }
}

const formHandler = new FormHandler();

formHandler.initForm();
