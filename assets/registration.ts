// Import dependencies
import { startRegistration } from '@simplewebauthn/browser';
import {RegistrationResponseJSON} from "@simplewebauthn/typescript-types";

// Fetch the start registration request
async function fetchStartRegistration() {
    const response: Response = await fetch('/wp-json/wp-passkeys/register/start');
    return response.json();
}

// Fetch the authentication response
async function fetchAuthentication(attResp: RegistrationResponseJSON) {
    try {
        console.log("Fetching authentication...");
        console.log(attResp);
        const response: Response = await fetch('/wp-json/wp-passkeys/register/authenticate', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(attResp),
        });
        const jsonData = await response.json();
        console.log("Received authentication data: ", jsonData);
        return jsonData;
    } catch (error: any) {
        console.error("Error fetching authentication: ", error);
        throw error;
    }
}

// Main function to handle registration
async function handleRegisterClick(event: Event) {
    event.preventDefault();
    const registerSuccessNotification: HTMLElement | null = document.querySelector('.register-success-notification');
    const registerErrorNotification : HTMLElement | null = document.querySelector('.register-error-notification');

    registerSuccessNotification && (registerSuccessNotification.innerHTML = '');
    registerErrorNotification && (registerErrorNotification.innerHTML = '');

    try {
        const startResp = await fetchStartRegistration();
        const attResp: RegistrationResponseJSON = await startRegistration(startResp);

        const verificationJSON: { verified?: boolean } = await fetchAuthentication(attResp);

        if (verificationJSON?.verified) {
            registerSuccessNotification && (registerSuccessNotification.innerHTML = 'Success!');
        } else {
            registerErrorNotification && (registerErrorNotification.innerHTML = `Oh no, something went wrong! Response: <pre>${JSON.stringify(verificationJSON)}</pre>`);
        }
    } catch (error: any) {
        if (registerErrorNotification) {
            registerErrorNotification.innerText = error.name === 'InvalidStateError' ?
                'Error: Authenticator was probably already registered by user' :
                `${error}`;
        }
        throw error;
    }
}

// Attach the event listener
const registerButton = document.querySelector<HTMLInputElement>('#wp-submit');

if (registerButton) {
    registerButton.addEventListener('click', handleRegisterClick);
}
