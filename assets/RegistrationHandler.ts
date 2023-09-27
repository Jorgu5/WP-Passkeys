import {
    PublicKeyCredentialCreationOptionsJSON,
    RegistrationResponseJSON
} from "@simplewebauthn/typescript-types";
import { startRegistration } from "@simplewebauthn/browser";
import {NotifyFunctionType, VerificationResponse} from "./AuthenticatorInterface";

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

    async start(): Promise<void> {
        try {
            const startResp: PublicKeyCredentialCreationOptionsJSON = await this.generateOptions();
            const attResp: RegistrationResponseJSON = await startRegistration(startResp);
            let verificationJSON: VerificationResponse = {};

            try {
                verificationJSON = await this.verify(attResp);
                if (verificationJSON.redirectUrl) {
                    window.location.href = verificationJSON.redirectUrl;
                }
            } catch (error: any) {
                console.error("Error in registration verification:", error);
            }

            const message = verificationJSON?.status === 'Verified'
                ? 'Registration successful'
                : 'Registration failed';

            this.notify(!!verificationJSON?.status, message);
        } catch (error: any) {
            this.notify(false, `Error: ${error.message || error}`);
            throw error;
        }
    }
}
