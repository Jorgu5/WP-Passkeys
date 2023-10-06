import {
    PublicKeyCredentialCreationOptionsJSON,
    RegistrationResponseJSON
} from "@simplewebauthn/typescript-types";
import { startRegistration } from "@simplewebauthn/browser";
import {NotifyFunctionType, VerificationResponse} from "./WebauthnTypes";

export class RegistrationHandler {
    private readonly notify: NotifyFunctionType;

    constructor(notifyFunction: NotifyFunctionType) {
        this.notify = notifyFunction;
    }

    async generateOptions(): Promise<PublicKeyCredentialCreationOptionsJSON> {
        try {
            const response: Response = await fetch('/wp-json/wp-passkeys/register/options');
            return await response.json();
        } catch (error: any) {
            console.error("Error fetching registration options:", error);
            throw error;
        }
    }

    async verify(attResp: RegistrationResponseJSON): Promise<VerificationResponse> {
        try {
            const response: Response = await fetch('/wp-json/wp-passkeys/register/verify', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(attResp),
            });
            return await response.json();
        } catch (error: any) {
            console.error("Error verifying registration:", error);
            throw error;
        }
    }

    async start(): Promise<VerificationResponse> {
        try {
            const startResp: PublicKeyCredentialCreationOptionsJSON = await this.generateOptions();
            const attResp: RegistrationResponseJSON = await startRegistration(startResp);
            let verificationJSON: VerificationResponse = {
                code: '',
                message: '',
            }
            try {
                verificationJSON = await this.verify(attResp);
                console.log(verificationJSON);
                if (verificationJSON.data?.redirectUrl) {
                    window.location.href = verificationJSON.data.redirectUrl;
                }
            } catch (error: any) {
                console.error("Error in registration verification:", error);
            }

            const notificationTarget = document.querySelector('#registerform') as HTMLElement;

            if(verificationJSON?.code === 'verified') {
                this.notify(verificationJSON?.message, true, notificationTarget);
            }
            
            if(verificationJSON?.code === 'credential-error') {
                this.notify(verificationJSON?.message, false, notificationTarget);
            }

            return verificationJSON;

        } catch (error: any) {
            throw error;
        }
    }
}
