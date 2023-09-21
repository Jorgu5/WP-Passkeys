import {
    AuthenticationResponseJSON,
    AuthenticatorAssertionResponseJSON,
    PublicKeyCredentialRequestOptionsJSON
} from "@simplewebauthn/typescript-types";

export type NotifyFunctionType = (success: boolean, message: string) => void;

export type VerificationResponse = {
    status?: string,
    statusText?: string,
    redirectUrl?: string
}

export type UserLoginResponse = {
    isExistingUser?: boolean
}

export interface AuthenticatorInterface {
    generateOptions(): Promise<PublicKeyCredentialRequestOptionsJSON>;
    start(): Promise<void>;
    verify(attResp: AuthenticationResponseJSON): Promise<VerificationResponse>
}